<?php
require_once '../../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!hasPermission('plots', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$plotId = intval($data['plot_id'] ?? 0);
$plotNumber = sanitize($data['plot_number'] ?? '');
$section = sanitize($data['section'] ?? '');
$size = floatval($data['size'] ?? 0);
$price = floatval($data['price'] ?? 0);
$status = $data['status'] ?? 'available';

if (!$plotId || !$plotNumber || !$size || !$price) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE plots SET plot_number = ?, section = ?, size = ?, price = ?, status = ? WHERE id = ?");
    $stmt->execute([$plotNumber, $section, $size, $price, $status, $plotId]);
    
    logActivity('Update Plot', "Updated plot: $plotNumber");
    
    echo json_encode(['success' => true, 'message' => 'Plot updated successfully']);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}