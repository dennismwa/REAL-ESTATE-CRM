<?php
// analytics-dashboard.php
$pageTitle = 'Analytics Dashboard';
require_once 'config.php';
requirePermission('reports', 'view');

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$compareMode = $_GET['compare'] ?? 'previous_period';

class AnalyticsEngine {
    private $pdo;
    private $startDate;
    private $endDate;
    
    public function __construct($pdo, $startDate, $endDate) {
        $this->pdo = $pdo;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }
    
    // Sales Forecasting using simple linear regression
    public function getSalesForcast($months = 3) {
        $stmt = $this->pdo->query("
            SELECT 
                DATE_FORMAT(sale_date, '%Y-%m') as month,
                COUNT(*) as sales_count,
                SUM(sale_price) as revenue
            FROM sales
            WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month
        ");
        
        $historicalData = $stmt->fetchAll();
        
        if (count($historicalData) < 3) {
            return ['forecast' => [], 'accuracy' => 0];
        }
        
        // Simple linear regression
        $n = count($historicalData);
        $x = range(1, $n);
        $y = array_column($historicalData, 'revenue');
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        // Generate forecast
        $forecast = [];
        for ($i = 1; $i <= $months; $i++) {
            $forecastValue = $slope * ($n + $i) + $intercept;
            $forecastMonth = date('Y-m', strtotime("+$i month"));
            $forecast[] = [
                'month' => $forecastMonth,
                'predicted_revenue' => max(0, $forecastValue),
                'confidence' => 0.75 - (0.05 * $i) // Decreasing confidence
            ];
        }
        
        return ['forecast' => $forecast, 'trend' => $slope > 0 ? 'up' : 'down'];
    }
    
    // Lead Conversion Funnel
    public function getConversionFunnel() {
        $funnel = [];
        
        // Total Leads
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM leads WHERE created_at BETWEEN ? AND ?");
        $stmt->execute([$this->startDate, $this->endDate . ' 23:59:59']);
        $funnel['leads'] = $stmt->fetch()['count'];
        
        // Contacted Leads
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM leads WHERE status IN ('contacted', 'qualified', 'negotiation', 'converted') AND created_at BETWEEN ? AND ?");
        $stmt->execute([$this->startDate, $this->endDate . ' 23:59:59']);
        $funnel['contacted'] = $stmt->fetch()['count'];
        
        // Qualified Leads
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM leads WHERE status IN ('qualified', 'negotiation', 'converted') AND created_at BETWEEN ? AND ?");
        $stmt->execute([$this->startDate, $this->endDate . ' 23:59:59']);
        $funnel['qualified'] = $stmt->fetch()['count'];
        
        // Negotiations
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM leads WHERE status IN ('negotiation', 'converted') AND created_at BETWEEN ? AND ?");
        $stmt->execute([$this->startDate, $this->endDate . ' 23:59:59']);
        $funnel['negotiation'] = $stmt->fetch()['count'];
        
        // Converted
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM leads WHERE status = 'converted' AND created_at BETWEEN ? AND ?");
        $stmt->execute([$this->startDate, $this->endDate . ' 23:59:59']);
        $funnel['converted'] = $stmt->fetch()['count'];
        
        // Calculate conversion rates
        $funnel['rates'] = [
            'contact_rate' => $funnel['leads'] > 0 ? ($funnel['contacted'] / $funnel['leads']) * 100 : 0,
            'qualification_rate' => $funnel['contacted'] > 0 ? ($funnel['qualified'] / $funnel['contacted']) * 100 : 0,
            'negotiation_rate' => $funnel['qualified'] > 0 ? ($funnel['negotiation'] / $funnel['qualified']) * 100 : 0,
            'conversion_rate' => $funnel['negotiation'] > 0 ? ($funnel['converted'] / $funnel['negotiation']) * 100 : 0,
            'overall_conversion' => $funnel['leads'] > 0 ? ($funnel['converted'] / $funnel['leads']) * 100 : 0
        ];
        
        return $funnel;
    }
    
    // Agent Performance Scoring
    public function getAgentPerformanceScores() {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                COUNT(DISTINCT s.id) as total_sales,
                COALESCE(SUM(s.sale_price), 0) as total_revenue,
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT lc.id) as converted_leads,
                AVG(DATEDIFF(s.sale_date, l.created_at)) as avg_conversion_days,
                (
                    SELECT COUNT(*) FROM site_visit_attendees sva 
                    JOIN site_visits sv ON sva.site_visit_id = sv.id
                    WHERE sva.user_id = u.id 
                    AND sv.visit_date BETWEEN ? AND ?
                ) as site_visits
            FROM users u
            LEFT JOIN sales s ON u.id = s.agent_id 
                AND s.sale_date BETWEEN ? AND ?
            LEFT JOIN leads l ON u.id = l.assigned_to 
                AND l.created_at BETWEEN ? AND ?
            LEFT JOIN leads lc ON u.id = lc.assigned_to 
                AND lc.status = 'converted'
                AND lc.created_at BETWEEN ? AND ?
            WHERE u.role = 'sales_agent' AND u.status = 'active'
            GROUP BY u.id, u.full_name
        ");
        
        $stmt->execute([
            $this->startDate, $this->endDate,
            $this->startDate, $this->endDate,
            $this->startDate, $this->endDate,
            $this->startDate, $this->endDate
        ]);
        
        $agents = $stmt->fetchAll();
        
        // Calculate performance scores
        foreach ($agents as &$agent) {
            $score = 0;
            
            // Revenue contribution (40%)
            $maxRevenue = max(array_column($agents, 'total_revenue'));
            if ($maxRevenue > 0) {
                $score += ($agent['total_revenue'] / $maxRevenue) * 40;
            }
            
            // Sales count (20%)
            $maxSales = max(array_column($agents, 'total_sales'));
            if ($maxSales > 0) {
                $score += ($agent['total_sales'] / $maxSales) * 20;
            }
            
            // Conversion rate (20%)
            $conversionRate = $agent['total_leads'] > 0 ? 
                ($agent['converted_leads'] / $agent['total_leads']) : 0;
            $score += $conversionRate * 20;
            
            // Activity level (10%)
            $maxVisits = max(array_column($agents, 'site_visits'));
            if ($maxVisits > 0) {
                $score += ($agent['site_visits'] / $maxVisits) * 10;
            }
            
            // Speed bonus (10%) - faster conversion gets higher score
            $avgDays = $agent['avg_conversion_days'] ?: 30;
            $speedScore = max(0, 10 - ($avgDays / 3)); // Lose 1 point per 3 days
            $score += $speedScore;
            
            $agent['performance_score'] = round($score, 1);
            $agent['grade'] = $this->getGrade($score);
        }
        
        // Sort by score
        usort($agents, function($a, $b) {
            return $b['performance_score'] <=> $a['performance_score'];
        });
        
        return $agents;
    }
    
    private function getGrade($score) {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }
    
    // ROI Analysis
    public function getProjectROI() {
        $stmt = $this->pdo->prepare("
            SELECT 
                pr.id,
                pr.project_name,
                pr.location,
                COUNT(p.id) as total_plots,
                COUNT(CASE WHEN p.status = 'sold' THEN 1 END) as sold_plots,
                COALESCE(SUM(CASE WHEN p.status = 'sold' THEN s.sale_price END), 0) as revenue,
                COALESCE(
                    (SELECT SUM(amount) FROM project_expenses WHERE project_id = pr.id),
                    pr.total_plots * 100000
                ) as total_cost,
                COUNT(DISTINCT sv.id) as site_visits,
                COUNT(DISTINCT l.id) as leads_generated
            FROM projects pr
            LEFT JOIN plots p ON pr.id = p.project_id
            LEFT JOIN sales s ON p.id = s.plot_id
            LEFT JOIN site_visits sv ON pr.id = sv.project_id
            LEFT JOIN leads l ON l.notes LIKE CONCAT('%', pr.project_name, '%')
            GROUP BY pr.id
        ");
        $stmt->execute();
        
        $projects = $stmt->fetchAll();
        
        foreach ($projects as &$project) {
            $project['roi'] = $project['total_cost'] > 0 ? 
                (($project['revenue'] - $project['total_cost']) / $project['total_cost']) * 100 : 0;
            $project['occupancy_rate'] = $project['total_plots'] > 0 ?
                ($project['sold_plots'] / $project['total_plots']) * 100 : 0;
            $project['avg_plot_value'] = $project['sold_plots'] > 0 ?
                $project['revenue'] / $project['sold_plots'] : 0;
        }
        
        return $projects;
    }
    
    // Heat Map Data
    public function getLocationHeatmap() {
        $stmt = $this->pdo->query("
            SELECT 
                pr.location,
                pr.office_latitude as lat,
                pr.office_longitude as lng,
                COUNT(DISTINCT s.id) as sales_count,
                SUM(s.sale_price) as total_revenue,
                AVG(DATEDIFF(s.sale_date, l.created_at)) as avg_conversion_time
            FROM projects pr
            JOIN plots p ON pr.id = p.project_id
            LEFT JOIN sales s ON p.id = s.plot_id
            LEFT JOIN clients c ON s.client_id = c.id
            LEFT JOIN leads l ON c.lead_id = l.id
            GROUP BY pr.location, pr.office_latitude, pr.office_longitude
            HAVING sales_count > 0
        ");
        
        return $stmt->fetchAll();
    }
}

$analytics = new AnalyticsEngine($pdo, $startDate, $endDate);
$forecast = $analytics->getSalesForcast();
$funnel = $analytics->getConversionFunnel();
$agentScores = $analytics->getAgentPerformanceScores();
$projectROI = $analytics->getProjectROI();
$heatmapData = $analytics->getLocationHeatmap();

// Get comparison data if needed
$comparison = null;
if ($compareMode === 'previous_period') {
    $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
    $compareStart = date('Y-m-d', strtotime($startDate . " -$daysDiff days"));
    $compareEnd = date('Y-m-d', strtotime($endDate . " -$daysDiff days"));
    
    $compareAnalytics = new AnalyticsEngine($pdo, $compareStart, $compareEnd);
    $comparison = [
        'funnel' => $compareAnalytics->getConversionFunnel()
    ];
}

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <!-- Header with Date Filter -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Analytics Dashboard</h1>
            <p class="text-gray-600 mt-1">Advanced insights and predictions</p>
        </div>
        
        <div class="flex gap-2 mt-4 md:mt-0">
            <input type="date" id="startDate" value="<?php echo $startDate; ?>" class="px-3 py-2 border rounded-lg">
            <input type="date" id="endDate" value="<?php echo $endDate; ?>" class="px-3 py-2 border rounded-lg">
            <button onclick="updateDateRange()" class="px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90">
                Apply
            </button>
        </div>
    </div>
    
    <!-- Sales Forecast -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Revenue Forecast</h2>
            <span class="text-sm text-gray-600">
                Trend: <span class="<?php echo $forecast['trend'] === 'up' ? 'text-green-600' : 'text-red-600'; ?>">
                    <i class="fas fa-arrow-<?php echo $forecast['trend']; ?>"></i> <?php echo ucfirst($forecast['trend']); ?>
                </span>
            </span>
        </div>
        
        <canvas id="forecastChart" height="100"></canvas>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <?php foreach ($forecast['forecast'] as $f): ?>
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600"><?php echo date('F Y', strtotime($f['month'] . '-01')); ?></p>
                <p class="text-xl font-bold text-primary"><?php echo formatMoney($f['predicted_revenue']); ?></p>
                <p class="text-xs text-gray-500">Confidence: <?php echo ($f['confidence'] * 100); ?>%</p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Conversion Funnel -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Conversion Funnel</h2>
        
        <div class="relative">
            <div class="flex justify-between items-center">
                <?php 
                $funnelStages = [
                    'leads' => ['label' => 'Leads', 'color' => 'blue'],
                    'contacted' => ['label' => 'Contacted', 'color' => 'indigo'],
                    'qualified' => ['label' => 'Qualified', 'color' => 'purple'],
                    'negotiation' => ['label' => 'Negotiation', 'color' => 'pink'],
                    'converted' => ['label' => 'Converted', 'color' => 'green']
                ];
                
                foreach ($funnelStages as $key => $stage): 
                    $width = $funnel['leads'] > 0 ? ($funnel[$key] / $funnel['leads']) * 100 : 0;
                ?>
                <div class="flex-1 text-center">
                    <div class="relative mb-2">
                        <div class="h-20 bg-<?php echo $stage['color']; ?>-100 mx-2" 
                             style="width: <?php echo $width; ?>%; margin: 0 auto; clip-path: polygon(0 0, 100% 0, 90% 100%, 10% 100%);">
                        </div>
                        <span class="absolute inset-0 flex items-center justify-center font-bold text-lg">
                            <?php echo $funnel[$key]; ?>
                        </span>
                    </div>
                    <p class="text-sm font-semibold"><?php echo $stage['label']; ?></p>
                    <?php if ($key !== 'leads' && isset($funnel['rates'])): ?>
                    <p class="text-xs text-gray-600">
                        <?php echo number_format($funnel['rates'][array_keys($funnel['rates'])[array_search($key, array_keys($funnel)) - 1]], 1); ?>%
                    </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mt-6 p-4 bg-primary bg-opacity-10 rounded-lg">
            <p class="text-center">
                <span class="text-2xl font-bold text-primary"><?php echo number_format($funnel['rates']['overall_conversion'], 1); ?>%</span>
                <span class="text-gray-600 ml-2">Overall Conversion Rate</span>
            </p>
        </div>
    </div>
    
    <!-- Agent Performance Leaderboard -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Agent Performance Leaderboard</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Rank</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Agent</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Score</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Sales</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Revenue</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Conv. Rate</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Grade</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach (array_slice($agentScores, 0, 10) as $index => $agent): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <?php if ($index < 3): ?>
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full 
                                <?php echo $index === 0 ? 'bg-yellow-400' : ($index === 1 ? 'bg-gray-400' : 'bg-orange-400'); ?> text-white font-bold">
                                <?php echo $index + 1; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-600 font-semibold"><?php echo $index + 1; ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-semibold"><?php echo sanitize($agent['full_name']); ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-primary h-2 rounded-full" style="width: <?php echo $agent['performance_score']; ?>%"></div>
                                </div>
                                <span class="text-sm font-semibold"><?php echo $agent['performance_score']; ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm"><?php echo $agent['total_sales']; ?></td>
                        <td class="px-4 py-3 text-sm font-semibold"><?php echo formatMoney($agent['total_revenue']); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php 
                            $convRate = $agent['total_leads'] > 0 ? 
                                ($agent['converted_leads'] / $agent['total_leads']) * 100 : 0;
                            echo number_format($convRate, 1) . '%';
                            ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs font-bold rounded-full
                                <?php 
                                $gradeColors = [
                                    'A+' => 'bg-green-100 text-green-800',
                                    'A' => 'bg-green-100 text-green-800',
                                    'B' => 'bg-blue-100 text-blue-800',
                                    'C' => 'bg-yellow-100 text-yellow-800',
                                    'D' => 'bg-orange-100 text-orange-800',
                                    'F' => 'bg-red-100 text-red-800'
                                ];
                                echo $gradeColors[$agent['grade']] ?? 'bg-gray-100 text-gray-800';
                                ?>">
                                <?php echo $agent['grade']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Project ROI Analysis -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Project ROI Analysis</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($projectROI as $project): ?>
            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition">
                <h3 class="font-bold mb-2"><?php echo sanitize($project['project_name']); ?></h3>
                <p class="text-xs text-gray-600 mb-3"><?php echo sanitize($project['location']); ?></p>
                
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">ROI</span>
                        <span class="font-bold <?php echo $project['roi'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo number_format($project['roi'], 1); ?>%
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Occupancy</span>
                        <span class="font-semibold"><?php echo number_format($project['occupancy_rate'], 0); ?>%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Revenue</span>
                        <span class="font-semibold text-primary"><?php echo formatMoney($project['revenue']); ?></span>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-3">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-primary h-2 rounded-full" style="width: <?php echo min(100, $project['occupancy_rate']); ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo $project['sold_plots']; ?> / <?php echo $project['total_plots']; ?> plots sold
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Location Heat Map -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold mb-4">Sales Heat Map</h2>
        <div id="heatmap" class="h-96 rounded-lg"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>
// Forecast Chart
const forecastData = <?php echo json_encode($forecast['forecast']); ?>;
new Chart(document.getElementById('forecastChart'), {
    type: 'line',
    data: {
        labels: forecastData.map(d => d.month),
        datasets: [{
            label: 'Predicted Revenue',
            data: forecastData.map(d => d.predicted_revenue),
            borderColor: '<?php echo $settings['primary_color']; ?>',
            backgroundColor: '<?php echo $settings['primary_color']; ?>20',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'KES ' + context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'KES ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Heat Map
const heatmapData = <?php echo json_encode($heatmapData); ?>;
const map = L.map('heatmap').setView([-1.2921, 36.8219], 10);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

if (heatmapData.length > 0) {
    const heat = L.heatLayer(
        heatmapData.map(d => [d.lat || -1.2921, d.lng || 36.8219, d.total_revenue / 1000000]),
        {radius: 25}
    ).addTo(map);
}

function updateDateRange() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    window.location.href = `?start_date=${startDate}&end_date=${endDate}`;
}
</script>

<?php include 'includes/footer.php'; ?>