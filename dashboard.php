<?php
$pageTitle = 'Dashboard';
require_once 'config.php';
requireLogin();

$settings = getSettings();
$userId = getUserId();
$userRole = getUserRole();

// Get dashboard statistics
$stats = [];

// Total projects
if (hasPermission('projects', 'view')) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects WHERE status = 'active'");
    $stats['active_projects'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects");
    $stats['total_projects'] = $stmt->fetch()['count'];
}

// Plot statistics
if (hasPermission('plots', 'view')) {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as booked,
        SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold,
        COALESCE(SUM(CASE WHEN status = 'available' THEN price ELSE 0 END), 0) as available_value
        FROM plots");
    $plotStats = $stmt->fetch();
    $stats = array_merge($stats, $plotStats);
}

// Sales statistics
if (hasPermission('sales', 'view')) {
    // This month sales
    $stmt = $pdo->query("SELECT 
        COUNT(*) as count, 
        COALESCE(SUM(sale_price), 0) as total,
        COALESCE(SUM(deposit_amount), 0) as deposits
        FROM sales 
        WHERE MONTH(sale_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(sale_date) = YEAR(CURRENT_DATE())
        AND status != 'cancelled'");
    $monthlySales = $stmt->fetch();
    $stats['monthly_sales'] = $monthlySales['count'];
    $stats['monthly_revenue'] = $monthlySales['total'];
    $stats['monthly_deposits'] = $monthlySales['deposits'];
    
    // Total sales
    $stmt = $pdo->query("SELECT 
        COUNT(*) as count, 
        COALESCE(SUM(sale_price), 0) as total,
        COALESCE(SUM(balance), 0) as balance
        FROM sales WHERE status != 'cancelled'");
    $totalSales = $stmt->fetch();
    $stats['total_sales'] = $totalSales['count'];
    $stats['total_revenue'] = $totalSales['total'];
    $stats['outstanding_balance'] = $totalSales['balance'];
    
    // Today's sales
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sales WHERE DATE(sale_date) = CURDATE()");
    $stats['today_sales'] = $stmt->fetch()['count'];
}

// Lead statistics
if (hasPermission('leads', 'view')) {
    $query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_leads,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted
        FROM leads";
    
    if ($userRole === 'sales_agent') {
        $query .= " WHERE assigned_to = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->query($query);
    }
    
    $leadStats = $stmt->fetch();
    $stats['total_leads'] = $leadStats['total'];
    $stats['new_leads'] = $leadStats['new_leads'];
    $stats['converted_leads'] = $leadStats['converted'];
    $stats['conversion_rate'] = $leadStats['total'] > 0 ? ($leadStats['converted'] / $leadStats['total']) * 100 : 0;
}

// Client count
if (hasPermission('clients', 'view')) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM clients");
    $stats['total_clients'] = $stmt->fetch()['count'];
}

// Payments this month
if (hasPermission('payments', 'view')) {
    $stmt = $pdo->query("SELECT 
        COALESCE(SUM(amount), 0) as total,
        COUNT(*) as count
        FROM payments 
        WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
    $payments = $stmt->fetch();
    $stats['monthly_payments'] = $payments['total'];
    $stats['monthly_payment_count'] = $payments['count'];
}

// Recent activities
$recentSales = [];
if (hasPermission('sales', 'view')) {
    $stmt = $pdo->query("SELECT s.*, c.full_name as client_name, p.plot_number, pr.project_name, u.full_name as agent_name
                         FROM sales s
                         JOIN clients c ON s.client_id = c.id
                         JOIN plots p ON s.plot_id = p.id
                         JOIN projects pr ON p.project_id = pr.id
                         JOIN users u ON s.agent_id = u.id
                         WHERE s.status != 'cancelled'
                         ORDER BY s.created_at DESC LIMIT 5");
    $recentSales = $stmt->fetchAll();
}

// Top performing agents (if admin/manager)
$topAgents = [];
if (in_array($userRole, ['admin', 'manager']) && hasPermission('sales', 'view')) {
    $stmt = $pdo->query("SELECT 
        u.full_name,
        COUNT(s.id) as sales_count,
        COALESCE(SUM(s.sale_price), 0) as total_revenue
        FROM users u
        LEFT JOIN sales s ON u.id = s.agent_id 
            AND MONTH(s.sale_date) = MONTH(CURRENT_DATE())
            AND YEAR(s.sale_date) = YEAR(CURRENT_DATE())
            AND s.status != 'cancelled'
        WHERE u.role = 'sales_agent' AND u.status = 'active'
        GROUP BY u.id, u.full_name
        ORDER BY total_revenue DESC
        LIMIT 5");
    $topAgents = $stmt->fetchAll();
}

// Sales trend (last 7 days)
$salesTrend = [];
if (hasPermission('sales', 'view')) {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayName = date('D', strtotime("-$i days"));
        
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as count,
            COALESCE(SUM(sale_price), 0) as revenue
            FROM sales 
            WHERE DATE(sale_date) = ? AND status != 'cancelled'");
        $stmt->execute([$date]);
        $data = $stmt->fetch();
        
        $salesTrend[] = [
            'day' => $dayName,
            'count' => $data['count'],
            'revenue' => $data['revenue']
        ];
    }
}

// Upcoming site visits
$upcomingSiteVisits = [];
if (hasPermission('site_visits', 'view')) {
    $stmt = $pdo->query("SELECT sv.*, pr.project_name 
                         FROM site_visits sv
                         JOIN projects pr ON sv.project_id = pr.id
                         WHERE sv.visit_date >= NOW() AND sv.status = 'scheduled'
                         ORDER BY sv.visit_date ASC
                         LIMIT 5");
    $upcomingSiteVisits = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <!-- Welcome Section with Time-based Greeting -->
    <div class="mb-6">
        <?php
        $hour = date('G');
        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
        ?>
        <h1 class="text-3xl md:text-4xl font-bold text-gray-800">
            <?php echo $greeting; ?>, <?php echo sanitize(explode(' ', getUserName())[0]); ?>
        </h1>
        <p class="text-gray-600 mt-1">Here's what's happening with your business today.</p>
    </div>
    
    <!-- Quick Stats Cards - Row 1 -->
    <!-- Quick Stats Cards - Row 1 -->
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-4 mb-6">
        <!-- Today's Sales -->
        <?php if (isset($stats['today_sales'])): ?>
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-1">
                <div class="bg-white bg-opacity-30 p-2 rounded-lg">
                    <i class="fas fa-calendar-day text-xl"></i>
                </div>
                <span class="text-xs bg-white bg-opacity-20 px-2 py-0.5 rounded-full">Today</span>
            </div>
            <p class="text-2xl font-bold mb-0.5"><?php echo $stats['today_sales']; ?></p>
            <p class="text-xs opacity-90">Sales Today</p>
        </div>
        <?php endif; ?>
        
        <!-- Monthly Sales -->
        <?php if (isset($stats['monthly_sales'])): ?>
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-1">
                <div class="bg-white bg-opacity-30 p-2 rounded-lg">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <span class="text-xs bg-white bg-opacity-20 px-2 py-0.5 rounded-full">Month</span>
            </div>
            <p class="text-2xl font-bold mb-0.5"><?php echo $stats['monthly_sales']; ?></p>
            <p class="text-xs opacity-90 truncate"><?php echo formatMoney($stats['monthly_revenue']); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Monthly Payments -->
        <?php if (isset($stats['monthly_payments'])): ?>
        <div class="bg-gradient-to-br from-teal-500 to-teal-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-1">
                <div class="bg-white bg-opacity-30 p-2 rounded-lg">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
                <span class="text-xs bg-white bg-opacity-20 px-2 py-0.5 rounded-full">Payments</span>
            </div>
            <p class="text-2xl font-bold mb-0.5"><?php echo $stats['monthly_payment_count']; ?></p>
            <p class="text-xs opacity-90 truncate"><?php echo formatMoney($stats['monthly_payments']); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Available Plots -->
        <?php if (isset($stats['available'])): ?>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-1">
                <div class="bg-white bg-opacity-30 p-2 rounded-lg">
                    <i class="fas fa-map text-xl"></i>
                </div>
                <span class="text-xs bg-white bg-opacity-20 px-2 py-0.5 rounded-full">Plots</span>
            </div>
            <p class="text-2xl font-bold mb-0.5"><?php echo $stats['available']; ?></p>
            <p class="text-xs opacity-90">Available</p>
        </div>
        <?php endif; ?>
        
        <!-- New Leads -->
        <?php if (isset($stats['new_leads'])): ?>
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-1">
                <div class="bg-white bg-opacity-30 p-2 rounded-lg">
                    <i class="fas fa-user-plus text-xl"></i>
                </div>
                <span class="text-xs bg-white bg-opacity-20 px-2 py-0.5 rounded-full">Fresh</span>
            </div>
            <p class="text-2xl font-bold mb-0.5"><?php echo $stats['new_leads']; ?></p>
            <p class="text-xs opacity-90">New Leads</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Key Metrics Cards - Row 2 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        <!-- Total Revenue -->
        <?php if (isset($stats['total_revenue'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500 hover:shadow-xl transition">
            <div class="flex items-center mb-2">
                <div class="bg-green-100 p-2 rounded-lg mr-2">
                    <i class="fas fa-dollar-sign text-green-600 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-gray-600 text-xs">Total Revenue</p>
                    <p class="text-lg font-bold text-gray-800 truncate"><?php echo formatMoney($stats['total_revenue']); ?></p>
                </div>
            </div>
            <div class="text-xs">
                <span class="text-green-600 font-semibold"><?php echo $stats['total_sales']; ?> sales</span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Outstanding Balance -->
        <?php if (isset($stats['outstanding_balance'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-orange-500 hover:shadow-xl transition">
            <div class="flex items-center mb-2">
                <div class="bg-orange-100 p-2 rounded-lg mr-2">
                    <i class="fas fa-exclamation-circle text-orange-600 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-gray-600 text-xs">Outstanding</p>
                    <p class="text-lg font-bold text-gray-800 truncate"><?php echo formatMoney($stats['outstanding_balance']); ?></p>
                </div>
            </div>
            <div class="text-xs">
                <span class="text-orange-600 font-semibold">Pending</span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Total Clients -->
        <?php if (isset($stats['total_clients'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500 hover:shadow-xl transition">
            <div class="flex items-center mb-2">
                <div class="bg-blue-100 p-2 rounded-lg mr-2">
                    <i class="fas fa-users text-blue-600 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-gray-600 text-xs">Total Clients</p>
                    <p class="text-lg font-bold text-gray-800"><?php echo $stats['total_clients']; ?></p>
                </div>
            </div>
            <div class="text-xs">
                <span class="text-blue-600 font-semibold">Active</span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Conversion Rate -->
        <?php if (isset($stats['conversion_rate'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-purple-500 hover:shadow-xl transition">
            <div class="flex items-center mb-2">
                <div class="bg-purple-100 p-2 rounded-lg mr-2">
                    <i class="fas fa-percentage text-purple-600 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-gray-600 text-xs">Conversion</p>
                    <p class="text-lg font-bold text-gray-800"><?php echo number_format($stats['conversion_rate'], 1); ?>%</p>
                </div>
            </div>
            <div class="text-xs">
                <span class="text-purple-600 font-semibold"><?php echo $stats['converted_leads']; ?>/<?php echo $stats['total_leads']; ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Total Projects -->
        <?php if (isset($stats['total_projects'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-indigo-500 hover:shadow-xl transition">
            <div class="flex items-center mb-2">
                <div class="bg-indigo-100 p-2 rounded-lg mr-2">
                    <i class="fas fa-building text-indigo-600 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-gray-600 text-xs">Projects</p>
                    <p class="text-lg font-bold text-gray-800"><?php echo $stats['total_projects']; ?></p>
                </div>
            </div>
            <div class="text-xs">
                <span class="text-indigo-600 font-semibold"><?php echo $stats['active_projects']; ?> active</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Charts and Data Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Sales Trend Chart -->
        <?php if (!empty($salesTrend)): ?>
        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">7-Day Sales Trend</h2>
                <select class="text-sm border border-gray-300 rounded-lg px-3 py-1">
                    <option>Last 7 Days</option>
                    <option>Last 30 Days</option>
                    <option>This Month</option>
                </select>
            </div>
            <canvas id="salesTrendChart" class="w-full" style="max-height: 300px;"></canvas>
        </div>
        <?php endif; ?>
        
        <!-- Plot Status Distribution -->
        <?php if (isset($stats['total'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Plot Distribution</h2>
            <canvas id="plotChart" style="max-height: 300px;"></canvas>
            <div class="mt-4 space-y-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                        <span class="text-sm text-gray-600">Available</span>
                    </div>
                    <span class="text-sm font-semibold"><?php echo $stats['available']; ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                        <span class="text-sm text-gray-600">Booked</span>
                    </div>
                    <span class="text-sm font-semibold"><?php echo $stats['booked']; ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                        <span class="text-sm text-gray-600">Sold</span>
                    </div>
                    <span class="text-sm font-semibold"><?php echo $stats['sold']; ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Activity and Top Performers -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Recent Sales -->
        <?php if (!empty($recentSales)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">Recent Sales</h2>
                <a href="/sales.php" class="text-sm text-primary hover:underline">View All →</a>
            </div>
            <div class="space-y-3 overflow-y-auto" style="max-height: 350px;">
                <?php foreach ($recentSales as $sale): ?>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="flex items-center flex-1 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold mr-3 flex-shrink-0">
                            <?php echo strtoupper(substr($sale['client_name'], 0, 1)); ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate"><?php echo sanitize($sale['client_name']); ?></p>
                            <p class="text-xs text-gray-600 truncate">
                                <?php echo sanitize($sale['project_name'] . ' - Plot ' . $sale['plot_number']); ?>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo formatDate($sale['sale_date'], 'M d'); ?></p>
                        </div>
                    </div>
                    <div class="text-right ml-4">
                        <p class="font-bold text-sm text-primary whitespace-nowrap"><?php echo formatMoney($sale['sale_price']); ?></p>
                        <span class="text-xs px-2 py-1 rounded-full <?php echo $sale['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                            <?php echo ucfirst($sale['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Top Performing Agents -->
        <?php if (!empty($topAgents)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">Top Performers</h2>
                <span class="text-sm text-gray-600">This Month</span>
            </div>
            <div class="space-y-3">
                <?php foreach ($topAgents as $index => $agent): ?>
                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-200">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white mr-3 
                            <?php echo $index === 0 ? 'bg-yellow-500' : ($index === 1 ? 'bg-gray-400' : ($index === 2 ? 'bg-orange-600' : 'bg-gray-300')); ?>">
                            <?php echo $index + 1; ?>
                        </div>
                        <div>
                            <p class="font-semibold text-sm"><?php echo sanitize($agent['full_name']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo $agent['sales_count']; ?> sales</p>
                        </div>
                    </div>
                    <p class="font-bold text-primary"><?php echo formatMoney($agent['total_revenue']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Upcoming Site Visits -->
    <?php if (!empty($upcomingSiteVisits)): ?>
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-800">Upcoming Site Visits</h2>
            <a href="/site-visits.php" class="text-sm text-primary hover:underline">View All →</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($upcomingSiteVisits as $visit): ?>
            <div class="border border-gray-200 rounded-lg p-4 hover:border-primary hover:shadow-md transition">
                <div class="flex items-start justify-between mb-2">
                    <h3 class="font-semibold text-sm"><?php echo sanitize($visit['title']); ?></h3>
                    <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                        <?php echo ucfirst($visit['status']); ?>
                    </span>
                </div>
                <p class="text-xs text-gray-600 mb-2"><?php echo sanitize($visit['project_name']); ?></p>
                <div class="flex items-center text-xs text-gray-500">
                    <i class="fas fa-calendar mr-1"></i>
                    <?php echo formatDate($visit['visit_date'], 'M d, Y h:i A'); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <?php if (hasPermission('leads', 'create')): ?>
            <a href="/leads.php?action=create" class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-gray-200 hover:border-primary hover:bg-primary hover:bg-opacity-5 transition group">
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mb-2 group-hover:bg-primary group-hover:bg-opacity-20 transition">
                    <i class="fas fa-user-plus text-xl text-blue-600 group-hover:text-primary"></i>
                </div>
                <span class="text-xs font-semibold text-center">Add Lead</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('clients', 'create')): ?>
            <a href="/clients.php?action=create" class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-gray-200 hover:border-secondary hover:bg-secondary hover:bg-opacity-5 transition group">
                <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center mb-2 group-hover:bg-secondary group-hover:bg-opacity-20 transition">
                    <i class="fas fa-users text-xl text-orange-600 group-hover:text-secondary"></i>
                </div>
                <span class="text-xs font-semibold text-center">Add Client</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('sales', 'create')): ?>
            <a href="/sales.php?action=create" class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-gray-200 hover:border-green-500 hover:bg-green-50 transition group">
                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mb-2 group-hover:bg-green-200 transition">
                    <i class="fas fa-handshake text-xl text-green-600"></i>
                </div>
                <span class="text-xs font-semibold text-center">New Sale</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('projects', 'view')): ?>
            <a href="/projects.php" class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-gray-200 hover:border-purple-500 hover:bg-purple-50 transition group">
                <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center mb-2 group-hover:bg-purple-200 transition">
                    <i class="fas fa-building text-xl text-purple-600"></i>
                </div>
                <span class="text-xs font-semibold text-center">Projects</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('payments', 'create')): ?>
            <a href="/payments.php?action=create" class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-gray-200 hover:border-yellow-500 hover:bg-yellow-50 transition group">
                <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center mb-2 group-hover:bg-yellow-200 transition">
                    <i class="fas fa-money-bill-wave text-xl text-yellow-600"></i>
                </div>
                <span class="text-xs font-semibold text-center">Add Payment</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('reports', 'view')): ?>
            <a href="/reports.php" class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-gray-200 hover:border-indigo-500 hover:bg-indigo-50 transition group">
                <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center mb-2 group-hover:bg-indigo-200 transition">
                    <i class="fas fa-chart-bar text-xl text-indigo-600"></i>
                </div>
                <span class="text-xs font-semibold text-center">Reports</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Sales Trend Chart
<?php if (!empty($salesTrend)): ?>
const trendCtx = document.getElementById('salesTrendChart');
if (trendCtx) {
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($salesTrend, 'day')); ?>,
            datasets: [{
                label: 'Sales Count',
                data: <?php echo json_encode(array_column($salesTrend, 'count')); ?>,
                borderColor: '<?php echo $settings['primary_color']; ?>',
                backgroundColor: '<?php echo $settings['primary_color']; ?>20',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 5,
                pointBackgroundColor: '<?php echo $settings['primary_color']; ?>',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 7
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
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '<?php echo $settings['primary_color']; ?>',
                    borderWidth: 1,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'Sales: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: '#6B7280'
                    },
                    grid: {
                        color: '#E5E7EB'
                    }
                },
                x: {
                    ticks: {
                        color: '#6B7280'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Plot Distribution Chart
<?php if (isset($stats['total'])): ?>
const plotCtx = document.getElementById('plotChart');
if (plotCtx) {
    new Chart(plotCtx, {
        type: 'doughnut',
        data: {
            labels: ['Available', 'Booked', 'Sold'],
            datasets: [{
                data: [
                    <?php echo $stats['available']; ?>,
                    <?php echo $stats['booked']; ?>,
                    <?php echo $stats['sold']; ?>
                ],
                backgroundColor: [
                    '#10B981',
                    '#F59E0B',
                    '#EF4444'
                ],
                borderWidth: 0,
                hoverOffset: 4
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
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderWidth: 0,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
}
<?php endif; ?>

// Add smooth animations
document.querySelectorAll('.transform').forEach(el => {
    el.style.transition = 'all 0.3s ease';
});

// Auto-refresh stats every 5 minutes
setTimeout(() => {
    location.reload();
}, 300000);
</script>

<?php include 'includes/footer.php'; ?>