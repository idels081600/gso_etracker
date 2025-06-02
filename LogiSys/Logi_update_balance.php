<?php
header('Content-Type: application/json');

require_once 'logi_db.php'; // your database connection

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['item_id']) || !isset($data['new_balance'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$item_id = intval($data['item_id']);
$new_balance = floatval($data['new_balance']);

$sql = "UPDATE inventory_items SET current_balance = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'SQL error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("di", $new_balance, $item_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
