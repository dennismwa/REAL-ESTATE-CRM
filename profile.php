<?php
$pageTitle = 'My Profile';
require_once 'config.php';
requireLogin();

$userId = getUserId();

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    
    // Update basic info
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
    if ($stmt->execute([$fullName, $email, $phone, $userId])) {
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;
        
        // Update password if provided
        if (!empty($_POST['new_password'])) {
            if (password_verify($_POST['current_password'], $user['password'])) {
                $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$newPassword, $userId]);
                flashMessage('Profile and password updated successfully!');
            } else {
                flashMessage('Current password is incorrect!', 'error');
            }
        } else {
            flashMessage('Profile updated successfully!');
        }
        
        logActivity('Update Profile', 'Updated profile information');
        redirect('/profile.php');
    }
}

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">My Profile</h1>
            <p class="text-gray-600 mt-1">Manage your account settings</p>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center space-x-4 mb-6 pb-6 border-b border-gray-200">
                <div class="w-20 h-20 rounded-full bg-primary text-white flex items-center justify-center text-3xl font-bold">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h2 class="text-xl font-bold"><?php echo sanitize($user['full_name']); ?></h2>
                    <p class="text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
                </div>
            </div>
            
            <form method="POST" action="">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                        <input type="text" name="full_name" required
                               value="<?php echo sanitize($user['full_name']); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required
                               value="<?php echo sanitize($user['email']); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                        <input type="tel" name="phone"
                               value="<?php echo sanitize($user['phone']); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    
                    <div class="pt-4 border-t border-gray-200">
                        <h3 class="font-semibold mb-4">Change Password</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                                <input type="password" name="current_password"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                                <input type="password" name="new_password" minlength="6"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                        Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>