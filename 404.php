<?php
require_once 'config.php';
$settings = getSettings();
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary-color: <?php echo $settings['primary_color']; ?>;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="text-center">
        <h1 class="text-9xl font-bold" style="color: var(--primary-color);">404</h1>
        <p class="text-2xl font-semibold text-gray-800 mt-4">Page Not Found</p>
        <p class="text-gray-600 mt-2">The page you're looking for doesn't exist.</p>
        <a href="/" class="inline-block mt-6 px-6 py-3 text-white rounded-lg hover:opacity-90 transition" style="background-color: var(--primary-color);">
            Go Home
        </a>
    </div>
</body>
</html>