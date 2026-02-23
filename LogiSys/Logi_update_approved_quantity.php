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
$approved_quantity = isset($_POST['approved_quantity']) ? intval($_POST['approved_quantity']) : 0;

// Validate input
if ($request_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
    exit;
}

if ($approved_quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Approved quantity cannot be negative.']);
    exit;
}

// Get the current approved quantity before update
$check_query = "SELECT id, item_name, approved_quantity FROM items_requested WHERE id = ?";
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

$old_quantity = $current_record['approved_quantity'];
$item_name = $current_record['item_name'];

// Update the approved quantity
$update_query = "UPDATE items_requested SET approved_quantity = ? WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);

if (!$update_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($update_stmt, 'ii', $approved_quantity, $request_id);
$update_result = mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);

if ($update_result) {
    // Log the change (optional - you can create a separate audit log table)
    // For now, we'll just return success
    
    echo json_encode([
        'success' => true,
        'message' => "Approved quantity updated from {$old_quantity} to {$approved_quantity} for item '{$item_name}'.",
        'data' => [
            'request_id' => $request_id,
            'old_quantity' => $old_quantity,
            'new_quantity' => $approved_quantity,
            'item_name' => $item_name
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update approved quantity. Please try again.']);
}

mysqli_close($conn);
?>