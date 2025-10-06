<?php
$pageTitle = 'Payroll';
require_once 'config.php';
requirePermission('payroll', 'view');

$action = $_GET['action'] ?? 'list';
$userId = getUserId();
$userRole = getUserRole();

// Handle payroll creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create' && hasPermission('payroll', 'create')) {
    $staffId = intval($_POST['user_id']);
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    $basicSalary = floatval($_POST['basic_salary']);
    $allowances = floatval($_POST['allowances']);
    $commissions = floatval($_POST['commissions']);
    $deductions = floatval($_POST['deductions']);
    $netSalary = $basicSalary + $allowances + $commissions - $deductions;
    
    $stmt = $pdo->prepare("INSERT INTO payroll (user_id, month, year, basic_salary, allowances, commissions, deductions, net_salary, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$staffId, $month, $year, $basicSalary, $allowances, $commissions, $deductions, $netSalary, $userId])) {
        logActivity('Create Payroll', "Created payroll for month $month/$year");
        flashMessage('Payroll record created successfully!');
        redirect('/payroll.php');
    }
}

// Get payroll records
if ($userRole === 'sales_agent') {
    $stmt = $pdo->prepare("SELECT p.*, u.full_name FROM payroll p JOIN users u ON p.user_id = u.id WHERE p.user_id = ? ORDER BY p.year DESC, p.month DESC");
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->query("SELECT p.*, u.full_name, u.role FROM payroll p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
}
$payrollRecords = $stmt->fetchAll();

// Get staff for dropdown
$stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name");
$staff = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Payroll</h1>
                <p class="text-gray-600 mt-1"><?php echo $userRole === 'sales_agent' ? 'View your salary records' : 'Manage staff payroll'; ?></p>
            </div>
            <?php if (hasPermission('payroll', 'create')): ?>
            <a href="/payroll.php?action=create" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus mr-2"></i>
                <span>Add Payroll</span>
            </a>
            <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php if ($userRole !== 'sales_agent'): ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Employee</th>
                            <?php endif; ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Period</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Basic Salary</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Allowances</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Commissions</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Deductions</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Net Salary</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($payrollRecords as $pr): ?>
                        <tr class="hover:bg-gray-50">
                            <?php if ($userRole !== 'sales_agent'): ?>
                            <td class="px-4 py-3">
                                <p class="font-semibold"><?php echo sanitize($pr['full_name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo ucfirst(str_replace('_', ' ', $pr['role'])); ?></p>
                            </td>
                            <?php endif; ?>
                            <td class="px-4 py-3 text-sm">
                                <?php echo date('F Y', mktime(0, 0, 0, $pr['month'], 1, $pr['year'])); ?>
                            </td>
                            <td class="px-4 py-3 font-semibold"><?php echo formatMoney($pr['basic_salary']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo formatMoney($pr['allowances']); ?></td>
                            <td class="px-4 py-3 text-sm text-green-600"><?php echo formatMoney($pr['commissions']); ?></td>
                            <td class="px-4 py-3 text-sm text-red-600"><?php echo formatMoney($pr['deductions']); ?></td>
                            <td class="px-4 py-3 font-bold text-primary"><?php echo formatMoney($pr['net_salary']); ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $pr['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst($pr['payment_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($payrollRecords)): ?>
                        <tr>
                            <td colspan="<?php echo $userRole === 'sales_agent' ? '7' : '8'; ?>" class="px-4 py-8 text-center text-gray-500">
                                No payroll records found
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($action === 'create'): ?>
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/payroll.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Payroll
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6">Add Payroll Record</h2>
                
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Employee *</label>
                            <select name="user_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select Employee</option>
                                <?php foreach ($staff as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo sanitize($s['full_name']) . ' - ' . ucfirst(str_replace('_', ' ', $s['role'])); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Month *</label>
                                <select name="month" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Year *</label>
                                <select name="year" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Basic Salary *</label>
                            <input type="number" name="basic_salary" required step="0.01" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Allowances</label>
                                <input type="number" name="allowances" step="0.01" min="0" value="0"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Commissions</label>
                                <input type="number" name="commissions" step="0.01" min="0" value="0"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Deductions</label>
                                <input type="number" name="deductions" step="0.01" min="0" value="0"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            Create Payroll Record
                        </button>
                        <a href="/payroll.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>