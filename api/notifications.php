<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'notifications' => []]);
    exit;
}

try {
    $userId = getUserId();

    // Get notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();

    // Format notifications with time ago
    $formattedNotifications = array_map(function($notif) {
        $timestamp = strtotime($notif['created_at']);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            $timeAgo = 'Just now';
        } elseif ($diff < 3600) {
            $timeAgo = floor($diff / 60) . ' min ago';
        } elseif ($diff < 86400) {
            $timeAgo = floor($diff / 3600) . ' hours ago';
        } else {
            $timeAgo = floor($diff / 86400) . ' days ago';
        }
        
        return [
            'id' => (int)$notif['id'],
            'title' => htmlspecialchars($notif['title'], ENT_QUOTES, 'UTF-8'),
            'message' => htmlspecialchars($notif['message'], ENT_QUOTES, 'UTF-8'),
            'type' => $notif['type'],
            'link' => $notif['link'],
            'is_read' => (bool)$notif['is_read'],
            'time_ago' => $timeAgo,
            'created_at' => $notif['created_at']
        ];
    }, $notifications);

    echo json_encode([
        'success' => true,
        'notifications' => $formattedNotifications
    ]);
    
} catch (Exception $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error loading notifications',
        'notifications' => [],
        'error' => $e->getMessage()
    ]);
}