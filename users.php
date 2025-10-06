<?php
$pageTitle = 'Staff';
require_once 'config.php';
requirePermission('users', 'view');

$action = $_GET['action'] ?? 'list';
$userId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' && hasPermission('users', 'create')) {
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $role = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            flashMessage('Email already exists!', 'error');
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
            if ($stmt->execute([$fullName, $email, $phone, $password, $role])) {
                logActivity('Create User', "Created user: $fullName");
                flashMessage('User created successfully!');
                redirect('/users.php');
            }
        }
    } elseif ($action === 'edit' && hasPermission('users', 'edit')) {
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$fullName, $email, $phone, $role, $status, $userId])) {
            // Update password if provided
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$password, $userId]);
            }
            
            logActivity('Update User', "Updated user: $fullName");
            flashMessage('User updated successfully!');
            redirect('/users.php');
        }
    }
}

// Get user data for edit
if ($action === 'edit' && $userId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        redirect('/users.php');
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <!-- Users List -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Staff Management</h1>
                <p class="text-gray-600 mt-1">Manage team members and access</p>
            </div>
            <?php if (hasPermission('users', 'create')): ?>
            <a href="/users.php?action=create" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-plus mr-2"></i>
                <span>Add User</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Users Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($users as $u): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center text-xl font-bold">
                            <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h3 class="font-bold"><?php echo sanitize($u['full_name']); ?></h3>
                            <p class="text-xs text-gray-500"><?php echo ucfirst(str_replace('_', ' ', $u['role'])); ?></p>
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full <?php echo $u['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo ucfirst($u['status']); ?>
                    </span>
                </div>
                
                <div class="space-y-2 mb-4">
                    <p class="text-sm">
                        <i class="fas fa-envelope w-4 text-gray-400"></i>
                        <span class="ml-2"><?php echo sanitize($u['email']); ?></span>
                    </p>
                    <?php if ($u['phone']): ?>
                    <p class="text-sm">
                        <i class="fas fa-phone w-4 text-gray-400"></i>
                        <span class="ml-2"><?php echo sanitize($u['phone']); ?></span>
                    </p>
                    <?php endif; ?>
                </div>
                
                <?php if (hasPermission('users', 'edit')): ?>
                <a href="/users.php?action=edit&id=<?php echo $u['id']; ?>" class="block w-full text-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
    <?php elseif ($action === 'create' || $action === 'edit'): ?>
        <!-- Create/Edit Form -->
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/users.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Staff
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6"><?php echo $action === 'create' ? 'Add New User' : 'Edit User'; ?></h2>
                
                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                            <input type="text" name="full_name" required
                                   value="<?php echo $action === 'edit' ? sanitize($user['full_name']) : ''; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                                <input type="email" name="email" required
                                       value="<?php echo $action === 'edit' ? sanitize($user['email']) : ''; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                                <input type="tel" name="phone"
                                       value="<?php echo $action === 'edit' ? sanitize($user['phone']) : ''; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Role *</label>
                                <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">Select Role</option>
                                    <option value="admin" <?php echo ($action === 'edit' && $user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    <option value="manager" <?php echo ($action === 'edit' && $user['role'] === 'manager') ? 'selected' : ''; ?>>Manager</option>
                                    <option value="sales_agent" <?php echo ($action === 'edit' && $user['role'] === 'sales_agent') ? 'selected' : ''; ?>>Sales Agent</option>
                                    <option value="finance" <?php echo ($action === 'edit' && $user['role'] === 'finance') ? 'selected' : ''; ?>>Finance</option>
                                    <option value="reception" <?php echo ($action === 'edit' && $user['role'] === 'reception') ? 'selected' : ''; ?>>Reception</option>
                                </select>
                            </div>
                            
                            <?php if ($action === 'edit'): ?>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Password <?php echo $action === 'edit' ? '(leave blank to keep current)' : '*'; ?>
                            </label>
                            <input type="password" name="password" <?php echo $action === 'create' ? 'required' : ''; ?>
                                   minlength="6"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            <?php echo $action === 'create' ? 'Add User' : 'Update User'; ?>
                        </button>
                        <a href="/users.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>