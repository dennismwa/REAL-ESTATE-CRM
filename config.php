<?php
// Zuri CRM - Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'vxjtgclw_zuricrm');
define('DB_PASS', 'Eas,B&Bb80iI.,^+');
define('DB_NAME', 'vxjtgclw_crm');

// Application settings
define('APP_NAME', 'Zuri CRM');
define('APP_URL', 'https://' . $_SERVER['HTTP_HOST']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
session_start();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("System error. Please contact administrator.");
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function getUserName() {
    return $_SESSION['user_name'] ?? null;
}

function hasPermission($module, $action = 'view') {
    if (!isLoggedIn()) return false;
    
    global $pdo;
    $role = getUserRole();
    
    $column = 'can_' . $action;
    $stmt = $pdo->prepare("SELECT $column FROM role_permissions WHERE role = ? AND module = ?");
    $stmt->execute([$role, $module]);
    $result = $stmt->fetch();
    
    return $result && $result[$column] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requirePermission($module, $action = 'view') {
    requireLogin();
    if (!hasPermission($module, $action)) {
        header('Location: /403.php');
        exit;
    }
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function flashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type']
        ];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return $message;
    }
    return null;
}

function logActivity($action, $description = null) {
    global $pdo;
    $userId = getUserId();
    if (!$userId) return;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $description, $ip]);
}

function createNotification($userId, $title, $message, $type = 'info', $link = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $title, $message, $type, $link]);
}

function getSettings() {
    global $pdo;
    static $settings = null;
    
    if ($settings === null) {
        $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
        $settings = $stmt->fetch();
    }
    
    return $settings;
}

function formatMoney($amount) {
    return 'KES ' . number_format($amount, 2);
}

function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

function uploadFile($file, $folder = 'general') {
    $uploadDir = UPLOAD_DIR . $folder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => '/uploads/' . $folder . '/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Upload failed'];
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    
    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);
    
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;
    
    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    
    return $earthRadius * $angle;
}

function isWithinOffice($lat, $lon) {
    $settings = getSettings();
    $officeLat = $settings['office_latitude'];
    $officeLon = $settings['office_longitude'];
    $radius = $settings['office_radius'];
    
    if (!$officeLat || !$officeLon) {
        return true; // No office location set
    }
    
    $distance = calculateDistance($lat, $lon, $officeLat, $officeLon);
    return $distance <= $radius;
}