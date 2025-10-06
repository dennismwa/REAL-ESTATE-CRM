<?php
require_once 'config.php';
$settings = getSettings();
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary-color: <?php echo $settings['primary_color']; ?>;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="text-center">
        <h1 class="text-9xl font-bold" style="color: var(--primary-color);">403</h1>
        <p class="text-2xl font-semibold text-gray-800 mt-4">Access Denied</p>
        <p class="text-gray-600 mt-2">You don't have permission to access this page.</p>
        <a href="/dashboard.php" class="inline-block mt-6 px-6 py-3 text-white rounded-lg hover:opacity-90 transition" style="background-color: var(--primary-color);">
            Go to Dashboard
        </a>
    </div>
</body>
</html>