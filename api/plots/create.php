<?php
require_once '../../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!hasPermission('plots', 'create')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$projectId = intval($data['project_id'] ?? 0);
$plotNumber = sanitize($data['plot_number'] ?? '');
$section = sanitize($data['section'] ?? '');
$size = floatval($data['size'] ?? 0);
$price = floatval($data['price'] ?? 0);
$status = $data['status'] ?? 'available';

if (!$projectId || !$plotNumber || !$size || !$price) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Check if plot number already exists in this project
$stmt = $pdo->prepare("SELECT id FROM plots WHERE project_id = ? AND plot_number = ?");
$stmt->execute([$projectId, $plotNumber]);

if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Plot number already exists in this project']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO plots (project_id, plot_number, section, size, price, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$projectId, $plotNumber, $section, $size, $price, $status]);
    
    logActivity('Create Plot', "Created plot: $plotNumber");
    
    echo json_encode(['success' => true, 'message' => 'Plot created successfully', 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}