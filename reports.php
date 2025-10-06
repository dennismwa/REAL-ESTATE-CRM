<?php
$pageTitle = 'Reports';
require_once 'config.php';
requirePermission('reports', 'view');

// Date range filter
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Sales Report
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(sale_price), 0) as total_revenue,
        COALESCE(SUM(deposit_amount), 0) as total_deposits,
        COALESCE(SUM(balance), 0) as total_balance
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
    AND status != 'cancelled'
");
$stmt->execute([$startDate, $endDate]);
$salesReport = $stmt->fetch();

// Sales by Agent
$stmt = $pdo->prepare("
    SELECT 
        u.full_name,
        COUNT(s.id) as sales_count,
        COALESCE(SUM(s.sale_price), 0) as total_revenue
    FROM users u
    LEFT JOIN sales s ON u.id = s.agent_id AND s.sale_date BETWEEN ? AND ? AND s.status != 'cancelled'
    WHERE u.role = 'sales_agent' AND u.status = 'active'
    GROUP BY u.id, u.full_name
    ORDER BY total_revenue DESC
");
$stmt->execute([$startDate, $endDate]);
$salesByAgent = $stmt->fetchAll();

// Plot Status Summary
$stmt = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count,
        COALESCE(SUM(price), 0) as total_value
    FROM plots
    GROUP BY status
");
$plotStatus = $stmt->fetchAll();

// Payments Report
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_payments,
        COALESCE(SUM(amount), 0) as total_amount,
        payment_method,
        COUNT(*) as method_count
    FROM payments
    WHERE payment_date BETWEEN ? AND ?
    GROUP BY payment_method
");
$stmt->execute([$startDate, $endDate]);
$paymentsByMethod = $stmt->fetchAll();

// Total payments
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totalPayments = $stmt->fetch()['total'];

// Lead Conversion Rate
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_leads,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_leads
    FROM leads
    WHERE created_at BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$leadStats = $stmt->fetch();
$conversionRate = $leadStats['total_leads'] > 0 ? ($leadStats['converted_leads'] / $leadStats['total_leads']) * 100 : 0;

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Reports & Analytics</h1>
        <p class="text-gray-600 mt-1">Business insights and performance metrics</p>
    </div>
    
    <!-- Date Filter -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                <input type="date" name="start_date" value="<?php echo $startDate; ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                <input type="date" name="end_date" value="<?php echo $endDate; ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            
            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-filter mr-2"></i>Filter
            </button>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Sales Count</p>
                    <p class="text-2xl font-bold text-primary mt-1"><?php echo $salesReport['total_sales']; ?></p>
                </div>
                <i class="fas fa-handshake text-3xl text-primary opacity-20"></i>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Revenue</p>
                    <p class="text-lg font-bold text-green-600 mt-1"><?php echo formatMoney($salesReport['total_revenue']); ?></p>
                </div>
                <i class="fas fa-chart-line text-3xl text-green-600 opacity-20"></i>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Payments</p>
                    <p class="text-lg font-bold text-blue-600 mt-1"><?php echo formatMoney($totalPayments); ?></p>
                </div>
                <i class="fas fa-money-bill-wave text-3xl text-blue-600 opacity-20"></i>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Conversion Rate</p>
                    <p class="text-2xl font-bold text-secondary mt-1"><?php echo number_format($conversionRate, 1); ?>%</p>
                </div>
                <i class="fas fa-percentage text-3xl text-secondary opacity-20"></i>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Sales by Agent -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4">Sales by Agent</h3>
            <div class="space-y-3">
                <?php foreach ($salesByAgent as $agent): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex-1">
                        <p class="font-semibold"><?php echo sanitize($agent['full_name']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo $agent['sales_count']; ?> sales</p>
                    </div>
                    <p class="font-bold text-primary"><?php echo formatMoney($agent['total_revenue']); ?></p>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($salesByAgent)): ?>
                <p class="text-center text-gray-500 py-4">No sales data available</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Plot Status -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4">Plot Inventory</h3>
            <div class="space-y-3">
                <?php foreach ($plotStatus as $status): ?>
                <div class="flex items-center justify-between p-3 rounded-lg <?php 
                    echo $status['status'] === 'available' ? 'bg-green-50' : 
                        ($status['status'] === 'booked' ? 'bg-yellow-50' : 'bg-red-50'); 
                ?>">
                    <div class="flex-1">
                        <p class="font-semibold <?php 
                            echo $status['status'] === 'available' ? 'text-green-800' : 
                                ($status['status'] === 'booked' ? 'text-yellow-800' : 'text-red-800'); 
                        ?>">
                            <?php echo ucfirst($status['status']); ?>
                        </p>
                        <p class="text-sm text-gray-600"><?php echo $status['count']; ?> plots</p>
                    </div>
                    <p class="font-bold text-gray-800"><?php echo formatMoney($status['total_value']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Payments by Method -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4">Payments by Method</h3>
            <div class="space-y-3">
                <?php foreach ($paymentsByMethod as $method): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex-1">
                        <p class="font-semibold"><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></p>
                        <p class="text-sm text-gray-600"><?php echo $method['method_count']; ?> transactions</p>
                    </div>
                    <p class="font-bold text-primary"><?php echo formatMoney($method['total_amount']); ?></p>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($paymentsByMethod)): ?>
                <p class="text-center text-gray-500 py-4">No payment data available</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Outstanding Balance -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4">Financial Summary</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                    <span class="font-semibold text-blue-800">Total Sales Value</span>
                    <span class="font-bold text-blue-800"><?php echo formatMoney($salesReport['total_revenue']); ?></span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                    <span class="font-semibold text-green-800">Total Collected</span>
                    <span class="font-bold text-green-800"><?php echo formatMoney($salesReport['total_revenue'] - $salesReport['total_balance']); ?></span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-orange-50 rounded-lg">
                    <span class="font-semibold text-orange-800">Outstanding Balance</span>
                    <span class="font-bold text-orange-800"><?php echo formatMoney($salesReport['total_balance']); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Export Options -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold mb-4">Export Reports</h3>
        <div class="flex flex-wrap gap-3">
            <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-print mr-2"></i>Print Report
            </button>
            <button onclick="alert('PDF export feature coming soon!')" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                <i class="fas fa-file-pdf mr-2"></i>Export to PDF
            </button>
            <button onclick="alert('Excel export feature coming soon!')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-file-excel mr-2"></i>Export to Excel
            </button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>