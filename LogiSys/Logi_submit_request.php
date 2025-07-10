<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

require_once 'logi_db.php';

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        throw new Exception('No items in request');
    }

    // Remarks (reason) is now optional
    $reason = isset($data['reason']) ? trim($data['reason']) : '';

    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $items = $data['items'];

    // Start transaction
    mysqli_autocommit($conn, false);

    // Insert request header
    $request_query = "INSERT INTO supply_requests (
        user_id, 
        username, 
        reason, 
        status, 
        created_at
    ) VALUES (?, ?, ?, 'Pending', NOW())";

    $request_stmt = mysqli_prepare($conn, $request_query);
    
    if (!$request_stmt) {
        throw new Exception('Failed to prepare request query: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($request_stmt, "iss", $user_id, $username, $reason);

    if (!mysqli_stmt_execute($request_stmt)) {
        throw new Exception('Failed to insert request: ' . mysqli_stmt_error($request_stmt));
    }

    $request_id = mysqli_insert_id($conn);
    mysqli_stmt_close($request_stmt);

    // Insert request items
    $item_query = "INSERT INTO supply_request_items (
        request_id,
        item_no,
        item_name,
        requested_quantity,
        unit,
        created_at
    ) VALUES (?, ?, ?, ?, ?, NOW())";

    $item_stmt = mysqli_prepare($conn, $item_query);
    
    if (!$item_stmt) {
        throw new Exception('Failed to prepare item query: ' . mysqli_error($conn));
    }

    foreach ($items as $item) {
        mysqli_stmt_bind_param(
            $item_stmt,
            "issis",
            $request_id,
            $item['id'],
            $item['name'],
            $item['quantity'],
            $item['unit']
        );
 
        if (!mysqli_stmt_execute($item_stmt)) {
            throw new Exception('Failed to insert request item: ' . mysqli_stmt_error($item_stmt));
        }
    }

    mysqli_stmt_close($item_stmt);

    // Commit transaction
    mysqli_commit($conn);
    mysqli_autocommit($conn, true);

    echo json_encode([
        'success' => true,
        'message' => 'Request submitted successfully',
        'request_id' => $request_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

    // Log the error
    error_log("Error in Logi_submit_request.php: " . $e->getMessage());

} finally {
    // Close database connection
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?> 