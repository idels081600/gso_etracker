<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'logi_db.php';

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['request_id']) || !isset($data['status'])) {
        throw new Exception('Invalid request data');
    }
    
    $request_id = intval($data['request_id']);
    $status = mysqli_real_escape_string($conn, $data['status']);
    $approved_quantity = isset($data['approved_quantity']) ? intval($data['approved_quantity']) : 0;
    $admin_remarks = isset($data['admin_remarks']) ? mysqli_real_escape_string($conn, $data['admin_remarks']) : '';
    
    // Build update query based on status
    if ($status === 'Approved' && $approved_quantity > 0) {
        $query = "UPDATE items_requested SET 
                    status = ?, 
                    approved_quantity = ?,
                    remarks_admin = ?,
                    date_approved = NOW()
                  WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sisi", $status, $approved_quantity, $admin_remarks, $request_id);
    } else {
        $query = "UPDATE items_requested SET 
                    status = ?,
                    remarks_admin = ?,
                    date_approved = NOW()
                  WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssi", $status, $admin_remarks, $request_id);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Request updated successfully']);
    } else {
        throw new Exception('Failed to update request');
    }
    
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn);
?>
