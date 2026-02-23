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
$office_name = isset($_POST['office_name']) ? trim($_POST['office_name']) : '';
$item_no = isset($_POST['item_no']) ? trim($_POST['item_no']) : '';
$item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
$approved_quantity = isset($_POST['approved_quantity']) ? intval($_POST['approved_quantity']) : 0;
$date_requested = isset($_POST['date_requested']) ? trim($_POST['date_requested']) : date('Y-m-d');
$status = isset($_POST['status']) ? trim($_POST['status']) : 'Approved';

// Validate input
if (empty($office_name)) {
    echo json_encode(['success' => false, 'message' => 'Office name is required.']);
    exit;
}

if (empty($item_no) || empty($item_name)) {
    echo json_encode(['success' => false, 'message' => 'Item information is required.']);
    exit;
}

if ($approved_quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Approved quantity must be greater than 0.']);
    exit;
}

// Insert the new item request
$insert_query = "INSERT INTO items_requested (office_name, item_id, item_name, approved_quantity, date_requested, status) VALUES (?, ?, ?, ?, ?, ?)";
$insert_stmt = mysqli_prepare($conn, $insert_query);

if (!$insert_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($insert_stmt, 'sssiss', $office_name, $item_no, $item_name, $approved_quantity, $date_requested, $status);
$insert_result = mysqli_stmt_execute($insert_stmt);
$insert_id = mysqli_insert_id($conn);
mysqli_stmt_close($insert_stmt);

if ($insert_result) {
    echo json_encode([
        'success' => true,
        'message' => "New item request added successfully for '{$item_name}'.",
        'data' => [
            'id' => $insert_id,
            'office_name' => $office_name,
            'item_name' => $item_name,
            'approved_quantity' => $approved_quantity,
            'date_requested' => $date_requested,
            'status' => $status
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add item request. Please try again.']);
}

mysqli_close($conn);
?>