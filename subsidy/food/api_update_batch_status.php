<?php
session_start();
require_once 'db_fuel.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

// Validate required fields
$batch_id = isset($input['batch_id']) ? intval($input['batch_id']) : 0;
$status = isset($input['status']) ? trim($input['status']) : '';

if ($batch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
    exit();
}

if (!in_array($status, ['pending', 'completed', 'cancelled'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status. Must be pending, completed, or cancelled']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get current batch info
    $batch_sql = "SELECT status FROM food_redemption_batches WHERE id = $batch_id LIMIT 1";
    $batch_result = mysqli_query($conn, $batch_sql);
    
    if (!$batch_result || mysqli_num_rows($batch_result) === 0) {
        throw new Exception('Batch not found');
    }
    
    $batch = mysqli_fetch_assoc($batch_result);
    $current_status = $batch['status'];
    
    // If cancelling, reverse voucher redemptions
    if ($status === 'cancelled' && $current_status !== 'cancelled') {
        // Get all voucher IDs in this batch
        $items_sql = "SELECT voucher_id FROM food_redemption_items WHERE batch_id = $batch_id";
        $items_result = mysqli_query($conn, $items_sql);
        
        if ($items_result) {
            while ($row = mysqli_fetch_assoc($items_result)) {
                $voucher_id = (int)$row['voucher_id'];
                $update_sql = "UPDATE food_voucher_claims 
                    SET is_redeemed = 0, batch_id = NULL 
                    WHERE id = $voucher_id";
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception('Failed to reverse voucher redemption: ' . mysqli_error($conn));
                }
            }
        }
    }
    
    // If reactivating from cancelled, mark vouchers as redeemed again
    if ($current_status === 'cancelled' && ($status === 'pending' || $status === 'completed')) {
        $items_sql = "SELECT voucher_id FROM food_redemption_items WHERE batch_id = $batch_id";
        $items_result = mysqli_query($conn, $items_sql);
        
        if ($items_result) {
            while ($row = mysqli_fetch_assoc($items_result)) {
                $voucher_id = (int)$row['voucher_id'];
                $update_sql = "UPDATE food_voucher_claims 
                    SET is_redeemed = 1, batch_id = $batch_id 
                    WHERE id = $voucher_id";
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception('Failed to re-activate voucher redemption: ' . mysqli_error($conn));
                }
            }
        }
    }
    
    // Update batch status
    $update_batch_sql = "UPDATE food_redemption_batches 
        SET status = ? 
        WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_batch_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare update: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'si', $status, $batch_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update batch status: ' . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_close($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Batch status updated successfully',
        'batch_id' => $batch_id,
        'status' => $status
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();
?>