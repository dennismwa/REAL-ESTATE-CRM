<?php
$pageTitle = 'Sales';
require_once 'config.php';
requirePermission('sales', 'view');

$action = $_GET['action'] ?? 'list';
$saleId = $_GET['id'] ?? null;
$userId = getUserId();
$userRole = getUserRole();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create' && hasPermission('sales', 'create')) {
    $clientId = intval($_POST['client_id']);
    $plotId = intval($_POST['plot_id']);
    $agentId = $userRole === 'sales_agent' ? $userId : intval($_POST['agent_id']);
    $salePrice = floatval($_POST['sale_price']);
    $depositAmount = floatval($_POST['deposit_amount']);
    $balance = $salePrice - $depositAmount;
    $paymentPlan = $_POST['payment_plan'];
    $saleDate = $_POST['sale_date'];
    
    try {
        $pdo->beginTransaction();
        
        // Create sale
        $stmt = $pdo->prepare("INSERT INTO sales (client_id, plot_id, agent_id, sale_price, deposit_amount, balance, payment_plan, sale_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$clientId, $plotId, $agentId, $salePrice, $depositAmount, $balance, $paymentPlan, $saleDate]);
        $newSaleId = $pdo->lastInsertId();
        
        // Update plot status
        $stmt = $pdo->prepare("UPDATE plots SET status = 'sold' WHERE id = ?");
        $stmt->execute([$plotId]);
        
        // Record deposit payment if any
        if ($depositAmount > 0) {
            $stmt = $pdo->prepare("INSERT INTO payments (sale_id, amount, payment_method, reference_number, payment_date, received_by) VALUES (?, ?, 'cash', ?, ?, ?)");
            $stmt->execute([$newSaleId, $depositAmount, 'DEPOSIT-' . $newSaleId, $saleDate, $userId]);
        }
        
        $pdo->commit();
        
        logActivity('Create Sale', "Created sale ID: $newSaleId");
        createNotification($agentId, 'New Sale Recorded', "A new sale has been recorded", 'success', "/sales.php?action=view&id=$newSaleId");
        
        flashMessage('Sale recorded successfully!');
        redirect('/sales.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        flashMessage('Error recording sale: ' . $e->getMessage(), 'error');
    }
}

// Get sale details
if ($action === 'view' && $saleId) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               c.full_name as client_name, c.phone as client_phone, c.email as client_email,
               p.plot_number, p.size,
               pr.project_name, pr.location,
               u.full_name as agent_name
        FROM sales s
        JOIN clients c ON s.client_id = c.id
        JOIN plots p ON s.plot_id = p.id
        JOIN projects pr ON p.project_id = pr.id
        JOIN users u ON s.agent_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();
    
    if (!$sale) {
        redirect('/sales.php');
    }
    
    // Get payment history
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as received_by_name
        FROM payments p
        JOIN users u ON p.received_by = u.id
        WHERE p.sale_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$saleId]);
    $payments = $stmt->fetchAll();
}

// Get all sales
$query = "
    SELECT s.*, 
           c.full_name as client_name,
           p.plot_number,
           pr.project_name,
           u.full_name as agent_name
    FROM sales s
    JOIN clients c ON s.client_id = c.id
    JOIN plots p ON s.plot_id = p.id
    JOIN projects pr ON p.project_id = pr.id
    JOIN users u ON s.agent_id = u.id
    WHERE s.status != 'cancelled'
";

if ($userRole === 'sales_agent') {
    $query .= " AND s.agent_id = ?";
    $stmt = $pdo->prepare($query . " ORDER BY s.created_at DESC");
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->query($query . " ORDER BY s.created_at DESC");
}

$sales = $stmt->fetchAll();

// Get clients for dropdown
$stmt = $pdo->query("SELECT id, full_name FROM clients ORDER BY full_name");
$clients = $stmt->fetchAll();

// Get available plots
$stmt = $pdo->query("SELECT p.id, p.plot_number, pr.project_name FROM plots p JOIN projects pr ON p.project_id = pr.id WHERE p.status = 'available' ORDER BY pr.project_name, p.plot_number");
$availablePlots = $stmt->fetchAll();

// Get sales agents
$stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'sales_agent' AND status = 'active' ORDER BY full_name");
$agents = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <!-- Sales List -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Sales</h1>
                <p class="text-gray-600 mt-1">Track all plot sales and transactions</p>
            </div>
            <?php if (hasPermission('sales', 'create')): ?>
            <a href="/sales.php?action=create" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus mr-2"></i>
                <span>Record Sale</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <?php
            $totalSales = count($sales);
            $totalRevenue = array_sum(array_column($sales, 'sale_price'));
            $totalBalance = array_sum(array_column($sales, 'balance'));
            $completedSales = count(array_filter($sales, fn($s) => $s['status'] === 'completed'));
            ?>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Total Sales</p>
                <p class="text-2xl font-bold text-primary mt-1"><?php echo $totalSales; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Total Revenue</p>
                <p class="text-xl font-bold text-green-600 mt-1"><?php echo formatMoney($totalRevenue); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Balance Due</p>
                <p class="text-xl font-bold text-secondary mt-1"><?php echo formatMoney($totalBalance); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Completed</p>
                <p class="text-2xl font-bold text-blue-600 mt-1"><?php echo $completedSales; ?></p>
            </div>
        </div>
        
        <!-- Sales Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Sale ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Plot</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Sale Price</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Balance</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($sales as $s): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-sm">#<?php echo str_pad($s['id'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td class="px-4 py-3">
                                <p class="font-semibold"><?php echo sanitize($s['client_name']); ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <p class="font-semibold"><?php echo sanitize($s['project_name']); ?></p>
                                <p class="text-xs text-gray-500">Plot <?php echo sanitize($s['plot_number']); ?></p>
                            </td>
                            <td class="px-4 py-3 font-semibold"><?php echo formatMoney($s['sale_price']); ?></td>
                            <td class="px-4 py-3">
                                <span class="font-semibold <?php echo $s['balance'] > 0 ? 'text-secondary' : 'text-green-600'; ?>">
                                    <?php echo formatMoney($s['balance']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full <?php 
                                    echo $s['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                        ($s['status'] === 'active' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); 
                                ?>">
                                    <?php echo ucfirst($s['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo formatDate($s['sale_date']); ?></td>
                            <td class="px-4 py-3">
                                <a href="/sales.php?action=view&id=<?php echo $s['id']; ?>" class="text-primary hover:text-opacity-80" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                No sales records found
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($action === 'create'): ?>
        <!-- Create Sale Form -->
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/sales.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Sales
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6">Record New Sale</h2>
                
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Client *</label>
                            <select name="client_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo sanitize($client['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Plot *</label>
                            <select name="plot_id" required id="plotSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select Plot</option>
                                <?php foreach ($availablePlots as $plot): ?>
                                <option value="<?php echo $plot['id']; ?>">
                                    <?php echo sanitize($plot['project_name'] . ' - Plot ' . $plot['plot_number']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (in_array($userRole, ['admin', 'manager'])): ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Sales Agent *</label>
                            <select name="agent_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select Agent</option>
                                <?php foreach ($agents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>"><?php echo sanitize($agent['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sale Price *</label>
                                <input type="number" name="sale_price" required step="0.01" min="0"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Deposit Amount</label>
                                <input type="number" name="deposit_amount" step="0.01" min="0" value="0"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Plan *</label>
                                <select name="payment_plan" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="full_payment">Full Payment</option>
                                    <option value="installment">Installment</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sale Date *</label>
                                <input type="date" name="sale_date" required value="<?php echo date('Y-m-d'); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            Record Sale
                        </button>
                        <a href="/sales.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($action === 'view'): ?>
        <!-- View Sale Details -->
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <a href="/sales.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Sales
                </a>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Sale Details -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h2 class="text-2xl font-bold">Sale #<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?></h2>
                                <p class="text-gray-600 mt-1"><?php echo formatDate($sale['sale_date'], 'F d, Y'); ?></p>
                            </div>
                            <span class="px-3 py-1 text-sm rounded-full <?php 
                                echo $sale['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; 
                            ?>">
                                <?php echo ucfirst($sale['status']); ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Client</p>
                                <p class="font-semibold"><?php echo sanitize($sale['client_name']); ?></p>
                                <p class="text-sm text-gray-600"><?php echo sanitize($sale['client_phone']); ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm text-gray-600">Plot</p>
                                <p class="font-semibold"><?php echo sanitize($sale['project_name']); ?></p>
                                <p class="text-sm text-gray-600">Plot <?php echo sanitize($sale['plot_number']); ?> - <?php echo number_format($sale['size'], 2); ?> m²</p>
                            </div>
                            
                            <div>
                                <p class="text-sm text-gray-600">Sales Agent</p>
                                <p class="font-semibold"><?php echo sanitize($sale['agent_name']); ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm text-gray-600">Payment Plan</p>
                                <p class="font-semibold"><?php echo ucfirst(str_replace('_', ' ', $sale['payment_plan'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment History -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-bold mb-4">Payment History</h3>
                        
                        <div class="space-y-3">
                            <?php foreach ($payments as $payment): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-semibold"><?php echo formatMoney($payment['amount']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo formatDate($payment['payment_date']); ?> - 
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                    </p>
                                    <?php if ($payment['reference_number']): ?>
                                    <p class="text-xs text-gray-500">Ref: <?php echo sanitize($payment['reference_number']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">Received by</p>
                                    <p class="text-sm font-semibold"><?php echo sanitize($payment['received_by_name']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($payments)): ?>
                            <p class="text-center text-gray-500 py-4">No payments recorded yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow p-6 sticky top-20">
                        <h3 class="text-lg font-bold mb-4">Payment Summary</h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Sale Price</span>
                                <span class="font-semibold"><?php echo formatMoney($sale['sale_price']); ?></span>
                            </div>
                            
                            <div class="flex justify-between">
                                <span class="text-gray-600">Paid Amount</span>
                                <span class="font-semibold text-green-600"><?php echo formatMoney($sale['sale_price'] - $sale['balance']); ?></span>
                            </div>
                            
                            <div class="border-t border-gray-200 pt-3 flex justify-between">
                                <span class="font-semibold">Balance Due</span>
                                <span class="font-bold text-lg <?php echo $sale['balance'] > 0 ? 'text-secondary' : 'text-green-600'; ?>">
                                    <?php echo formatMoney($sale['balance']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($sale['balance'] > 0 && hasPermission('payments', 'create')): ?>
                        <a href="/payments.php?action=create&sale_id=<?php echo $sale['id']; ?>" 
                           class="block mt-6 w-full text-center px-4 py-3 bg-primary text-white rounded-lg hover:opacity-90 transition">
                            <i class="fas fa-plus mr-2"></i>Add Payment
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>