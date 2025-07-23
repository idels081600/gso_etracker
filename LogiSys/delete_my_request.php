<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'logi_db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['request_id'])) {
        throw new Exception('Missing request ID');
    }

    $request_id = (int)$input['request_id'];
    $user_id = $_SESSION['user_id'];

    // Verify the request belongs to the current user and is still pending
    $verify_query = "SELECT id, item_name FROM items_requested WHERE id = ? AND office_id = ? AND status = 'Pending'";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "ii", $request_id, $user_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);

    if (mysqli_num_rows($verify_result) === 0) {
        throw new Exception('Request not found or cannot be deleted');
    }

    $request_data = mysqli_fetch_assoc($verify_result);
    mysqli_stmt_close($verify_stmt);

    // Delete the request
    $delete_query = "DELETE FROM items_requested WHERE id = ? AND office_id = ? AND status = 'Pending'";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, "ii", $request_id, $user_id);

    if (mysqli_stmt_execute($delete_stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($delete_stmt);

        if ($affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Request deleted successfully',
                'data' => [
                    'request_id' => $request_id,
                    'item_name' => $request_data['item_name']
                ]
            ]);
        } else {
            throw new Exception('Request could not be deleted');
        }
    } else {
        throw new Exception('Failed to delete request: ' . mysqli_stmt_error($delete_stmt));
    }

    mysqli_stmt_close($delete_stmt);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
