<?php
$pageTitle = 'Payments';
require_once 'config.php';
requirePermission('payments', 'view');

$action = $_GET['action'] ?? 'list';
$saleId = $_GET['sale_id'] ?? null;

// Handle payment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create' && hasPermission('payments', 'create')) {
    $saleIdPost = intval($_POST['sale_id']);
    $amount = floatval($_POST['amount']);
    $paymentMethod = $_POST['payment_method'];
    $referenceNumber = sanitize($_POST['reference_number']);
    $paymentDate = $_POST['payment_date'];
    $notes = sanitize($_POST['notes']);
    
    try {
        $pdo->beginTransaction();
        
        // Record payment
        $stmt = $pdo->prepare("INSERT INTO payments (sale_id, amount, payment_method, reference_number, payment_date, received_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$saleIdPost, $amount, $paymentMethod, $referenceNumber, $paymentDate, getUserId(), $notes]);
        
        // Update sale balance
        $stmt = $pdo->prepare("UPDATE sales SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $saleIdPost]);
        
        // Check if fully paid and update status
        $stmt = $pdo->prepare("SELECT balance FROM sales WHERE id = ?");
        $stmt->execute([$saleIdPost]);
        $sale = $stmt->fetch();
        
        if ($sale['balance'] <= 0) {
            $stmt = $pdo->prepare("UPDATE sales SET status = 'completed', balance = 0 WHERE id = ?");
            $stmt->execute([$saleIdPost]);
        }
        
        $pdo->commit();
        
        logActivity('Record Payment', "Recorded payment of " . formatMoney($amount));
        flashMessage('Payment recorded successfully!');
        redirect('/payments.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        flashMessage('Error recording payment: ' . $e->getMessage(), 'error');
    }
}

// Get sale info if creating payment
if ($saleId && $action === 'create') {
    $stmt = $pdo->prepare("
        SELECT s.*, c.full_name as client_name, p.plot_number, pr.project_name
        FROM sales s
        JOIN clients c ON s.client_id = c.id
        JOIN plots p ON s.plot_id = p.id
        JOIN projects pr ON p.project_id = pr.id
        WHERE s.id = ?
    ");
    $stmt->execute([$saleId]);
    $saleInfo = $stmt->fetch();
}

// Get all payments
$stmt = $pdo->query("
    SELECT p.*, 
           s.sale_price, s.balance as sale_balance,
           c.full_name as client_name,
           pl.plot_number,
           pr.project_name,
           u.full_name as received_by_name
    FROM payments p
    JOIN sales s ON p.sale_id = s.id
    JOIN clients c ON s.client_id = c.id
    JOIN plots pl ON s.plot_id = pl.id
    JOIN projects pr ON pl.project_id = pr.id
    JOIN users u ON p.received_by = u.id
    ORDER BY p.created_at DESC
    LIMIT 100
");
$payments = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <!-- Payments List -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Payments</h1>
                <p class="text-gray-600 mt-1">Track all payment transactions</p>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <?php
        $totalPayments = array_sum(array_column($payments, 'amount'));
        $paymentCount = count($payments);
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE())");
        $monthlyTotal = $stmt->fetch()['total'];
        ?>
        
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Total Payments</p>
                <p class="text-2xl font-bold text-primary mt-1"><?php echo $paymentCount; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Total Amount</p>
                <p class="text-lg font-bold text-green-600 mt-1"><?php echo formatMoney($totalPayments); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">This Month</p>
                <p class="text-lg font-bold text-blue-600 mt-1"><?php echo formatMoney($monthlyTotal); ?></p>
            </div>
        </div>
        
        <!-- Payments Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Plot</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Method</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Received By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($payments as $payment): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm"><?php echo formatDate($payment['payment_date']); ?></td>
                            <td class="px-4 py-3">
                                <p class="font-semibold"><?php echo sanitize($payment['client_name']); ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <p><?php echo sanitize($payment['project_name']); ?></p>
                                <p class="text-xs text-gray-500">Plot <?php echo sanitize($payment['plot_number']); ?></p>
                            </td>
                            <td class="px-4 py-3 font-bold text-green-600"><?php echo formatMoney($payment['amount']); ?></td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm font-mono"><?php echo sanitize($payment['reference_number']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo sanitize($payment['received_by_name']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No payments recorded yet
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($action === 'create'): ?>
        <!-- Create Payment Form -->
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/payments.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Payments
                </a>
            </div>
            
            <?php if ($saleInfo): ?>
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="font-bold mb-3">Sale Information</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-600">Client</p>
                        <p class="font-semibold"><?php echo sanitize($saleInfo['client_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Plot</p>
                        <p class="font-semibold"><?php echo sanitize($saleInfo['project_name'] . ' - ' . $saleInfo['plot_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Sale Price</p>
                        <p class="font-semibold"><?php echo formatMoney($saleInfo['sale_price']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Balance Due</p>
                        <p class="font-semibold text-secondary"><?php echo formatMoney($saleInfo['balance']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6">Record Payment</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="sale_id" value="<?php echo $saleId; ?>">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Amount *</label>
                            <input type="number" name="amount" required step="0.01" min="0.01" max="<?php echo $saleInfo['balance']; ?>"
                                   placeholder="Enter payment amount"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <p class="text-xs text-gray-500 mt-1">Maximum: <?php echo formatMoney($saleInfo['balance']); ?></p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method *</label>
                                <select name="payment_method" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">Select Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="mpesa">M-Pesa</option>
                                    <option value="card">Card</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Date *</label>
                                <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Reference Number</label>
                            <input type="text" name="reference_number" placeholder="Transaction/Receipt number"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3" placeholder="Additional notes about this payment"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            Record Payment
                        </button>
                        <a href="/payments.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-exclamation-triangle text-6xl text-yellow-500 mb-4"></i>
                <p class="text-gray-600">Invalid sale ID. Please select a sale from the sales page.</p>
                <a href="/sales.php" class="inline-block mt-4 px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                    Go to Sales
                </a>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>