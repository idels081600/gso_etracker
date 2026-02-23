<?php
session_start();
header('Content-Type: application/json');

require_once 'logi_db.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get POST data
$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;

// Validate input
if ($request_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
    exit;
}

// Get the record before update (for confirmation message)
$check_query = "SELECT id, item_name, office_name, approved_quantity FROM items_requested WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);

if (!$check_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($check_stmt, 'i', $request_id);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);
$current_record = mysqli_fetch_assoc($result);
mysqli_stmt_close($check_stmt);

if (!$current_record) {
    echo json_encode(['success' => false, 'message' => 'Request record not found.']);
    exit;
}

$item_name = $current_record['item_name'];
$office_name = $current_record['office_name'];

// Update the record: set status = 'Rejected' and uploaded = 0
$update_query = "UPDATE items_requested SET status = 'Rejected', uploaded = 0 WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);

if (!$update_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($update_stmt, 'i', $request_id);
$update_result = mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);

if ($update_result) {
    echo json_encode([
        'success' => true,
        'message' => "Item request for '{$item_name}' from '{$office_name}' has been rejected.",
        'data' => [
            'request_id' => $request_id,
            'item_name' => $item_name,
            'office_name' => $office_name
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to reject item request. Please try again.']);
}

mysqli_close($conn);
?>