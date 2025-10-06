<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            
            logActivity('Login', 'User logged in');
            redirect('/dashboard.php');
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$settings = getSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo sanitize($settings['site_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary-color: <?php echo $settings['primary_color']; ?>;
            --secondary-color: <?php echo $settings['secondary_color']; ?>;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <img src="<?php echo $settings['logo_path']; ?>" alt="Logo" class="h-16 mx-auto mb-4">
                <h1 class="text-2xl font-bold" style="color: var(--primary-color);">
                    <?php echo sanitize($settings['site_name']); ?>
                </h1>
                <p class="text-gray-600 mt-2">Sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo sanitize($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Email Address</label>
                    <input type="email" name="email" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50"
                           style="focus:ring-color: var(--primary-color);"
                           placeholder="Enter your email">
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50"
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" 
                        class="w-full text-white font-semibold py-3 rounded-lg transition duration-200 hover:opacity-90"
                        style="background-color: var(--primary-color);">
                    Sign In
                </button>
            </form>
            
            <div class="mt-6 text-center text-sm text-gray-600">
                <p>&copy; <?php echo date('Y'); ?> <?php echo sanitize($settings['site_name']); ?>. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>