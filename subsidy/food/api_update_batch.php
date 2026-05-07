<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

// Security check - only FOOD_REDEEMER allowed
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'FOOD_REDEEMER') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Only FOOD_REDEEMER can edit batches.']);
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
$remove_voucher_ids = isset($input['remove_voucher_ids']) && is_array($input['remove_voucher_ids']) ? $input['remove_voucher_ids'] : [];
$add_voucher_ids = isset($input['add_voucher_ids']) && is_array($input['add_voucher_ids']) ? $input['add_voucher_ids'] : [];
$reorder_voucher_ids = isset($input['reorder_voucher_ids']) && is_array($input['reorder_voucher_ids']) ? $input['reorder_voucher_ids'] : [];

if ($batch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
    exit();
}

if (empty($remove_voucher_ids) && empty($add_voucher_ids) && empty($reorder_voucher_ids)) {
    echo json_encode(['success' => false, 'message' => 'No changes detected. Please add or remove vouchers.']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Get current batch info
    $batch_sql = "SELECT b.*, v.vendor_serial, v.vendor_name 
                  FROM food_redemption_batches b
                  LEFT JOIN food_vendors v ON b.vendor_id = v.id
                  WHERE b.id = $batch_id LIMIT 1";
    $batch_result = mysqli_query($conn, $batch_sql);
    
    if (!$batch_result || mysqli_num_rows($batch_result) === 0) {
        throw new Exception('Batch not found');
    }
    
    $batch = mysqli_fetch_assoc($batch_result);
    
    // Only allow editing non-cancelled batches
    if ($batch['status'] === 'cancelled') {
        throw new Exception('Cannot edit a cancelled batch');
    }
    
    $current_vouchers = (int)$batch['total_vouchers'];
    $current_amount = (float)$batch['total_amount'];
    
    // === PROCESS REMOVALS ===
    if (!empty($remove_voucher_ids)) {
        // Clean and validate IDs
        $remove_ids = [];
        foreach ($remove_voucher_ids as $id) {
            $remove_ids[] = intval($id);
        }
        $remove_ids = array_unique($remove_ids);
        $remove_id_str = implode(',', $remove_ids);
        
        // Check that all vouchers to remove actually belong to this batch
        $check_sql = "SELECT id, voucher_id FROM food_redemption_items 
                      WHERE batch_id = $batch_id AND voucher_id IN ($remove_id_str)";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (!$check_result) {
            throw new Exception('Failed to verify batch items');
        }
        
        $valid_remove_ids = [];
        while ($row = mysqli_fetch_assoc($check_result)) {
            $valid_remove_ids[] = (int)$row['voucher_id'];
        }
        
        if (count($valid_remove_ids) !== count($remove_ids)) {
            throw new Exception('Some vouchers to remove do not belong to this batch');
        }
        
        $valid_id_str = implode(',', $valid_remove_ids);
        
        // Step 1: Delete from food_redemption_items
        $delete_items_sql = "DELETE FROM food_redemption_items 
                             WHERE batch_id = $batch_id AND voucher_id IN ($valid_id_str)";
        if (!mysqli_query($conn, $delete_items_sql)) {
            throw new Exception('Failed to remove batch items: ' . mysqli_error($conn));
        }
        
        // Step 2: Update food_voucher_claims - set is_redeemed = 0, batch_id = NULL
        $update_claims_sql = "UPDATE food_voucher_claims 
                              SET is_redeemed = 0, batch_id = NULL 
                              WHERE id IN ($valid_id_str) AND batch_id = $batch_id";
        if (!mysqli_query($conn, $update_claims_sql)) {
            throw new Exception('Failed to unredeem vouchers: ' . mysqli_error($conn));
        }
        
        $removed_count = count($valid_remove_ids);
        $current_vouchers -= $removed_count;
        $current_amount -= ($removed_count * 200.00);
    }
    
    // === PROCESS ADDITIONS ===
    if (!empty($add_voucher_ids)) {
        // Clean and validate IDs
        $add_ids = [];
        foreach ($add_voucher_ids as $id) {
            $add_ids[] = intval($id);
        }
        $add_ids = array_unique($add_ids);
        $add_id_str = implode(',', $add_ids);
        
        // Check that vouchers exist, are verified, and are not already redeemed/in a batch
        $check_add_sql = "SELECT id, beneficiary_id, voucher_number, claimant_name, is_verified, is_redeemed, batch_id 
                          FROM food_voucher_claims 
                          WHERE id IN ($add_id_str)";
        $check_add_result = mysqli_query($conn, $check_add_sql);
        
        if (!$check_add_result) {
            throw new Exception('Failed to verify vouchers to add');
        }
        
        $valid_add_vouchers = [];
        $invalid_add_vouchers = [];
        
        while ($row = mysqli_fetch_assoc($check_add_result)) {
            $vid = (int)$row['id'];
            if ($row['is_verified'] != 1) {
                $invalid_add_vouchers[] = ['id' => $vid, 'reason' => 'Not verified'];
                continue;
            }
            if ($row['is_redeemed'] == 1 && (int)$row['batch_id'] !== 0 && (int)$row['batch_id'] !== $batch_id) {
                $invalid_add_vouchers[] = ['id' => $vid, 'reason' => 'Already redeemed in another batch'];
                continue;
            }
            $valid_add_vouchers[] = [
                'id' => $vid,
                'beneficiary_id' => (int)$row['beneficiary_id'],
                'voucher_number' => (int)$row['voucher_number'],
                'claimant_name' => $row['claimant_name']
            ];
        }
        
        if (empty($valid_add_vouchers)) {
            throw new Exception('No valid vouchers to add' . (!empty($invalid_add_vouchers) ? ' (' . $invalid_add_vouchers[0]['reason'] . ')' : ''));
        }
        
        // Get the highest selection order for this batch
        $max_order_sql = "SELECT COALESCE(MAX(selection_order), 0) as max_order FROM food_redemption_items WHERE batch_id = $batch_id";
        $max_order_result = mysqli_query($conn, $max_order_sql);
        $max_order_row = mysqli_fetch_assoc($max_order_result);
        $next_order = (int)$max_order_row['max_order'] + 1;
        
        // Insert new batch items and update voucher claims
        $insert_item_sql = "INSERT INTO food_redemption_items 
            (batch_id, voucher_id, amount, beneficiary_name, beneficiary_code, voucher_number, selection_order) 
            VALUES (?, ?, 200.00, ?, ?, ?, ?)";
        
        $item_stmt = mysqli_prepare($conn, $insert_item_sql);
        if (!$item_stmt) {
            throw new Exception('Failed to prepare item insert: ' . mysqli_error($conn));
        }
        
        $update_voucher_sql = "UPDATE food_voucher_claims 
            SET is_redeemed = 1, batch_id = ? 
            WHERE id = ?";
        
        $update_stmt = mysqli_prepare($conn, $update_voucher_sql);
        if (!$update_stmt) {
            throw new Exception('Failed to prepare voucher update: ' . mysqli_error($conn));
        }
        
        foreach ($valid_add_vouchers as $idx => $voucher) {
            // Get beneficiary info
            $beneficiary_id = $voucher['beneficiary_id'];
            $beneficiary_sql = "SELECT full_name, beneficiary_code FROM food_beneficiaries WHERE id = $beneficiary_id LIMIT 1";
            $beneficiary_result = mysqli_query($conn, $beneficiary_sql);
            $beneficiary = mysqli_fetch_assoc($beneficiary_result);
            
            $beneficiary_name = $beneficiary ? $beneficiary['full_name'] : $voucher['claimant_name'];
            $beneficiary_code = $beneficiary ? $beneficiary['beneficiary_code'] : '';
            $voucher_number = $voucher['voucher_number'];
            $voucher_id = $voucher['id'];
            $selection_order = $next_order + $idx;
            
            // Insert batch item
            mysqli_stmt_bind_param($item_stmt, 'iisssi', $batch_id, $voucher_id, $beneficiary_name, $beneficiary_code, $voucher_number, $selection_order);
            if (!mysqli_stmt_execute($item_stmt)) {
                throw new Exception('Failed to insert batch item: ' . mysqli_stmt_error($item_stmt));
            }
            
            // Update voucher claim
            mysqli_stmt_bind_param($update_stmt, 'ii', $batch_id, $voucher_id);
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception('Failed to update voucher: ' . mysqli_stmt_error($update_stmt));
            }
        }
        
        mysqli_stmt_close($item_stmt);
        mysqli_stmt_close($update_stmt);
        
        $added_count = count($valid_add_vouchers);
        $current_vouchers += $added_count;
        $current_amount += ($added_count * 200.00);
    }
    
    // === PROCESS REORDER ===
    // Update selection_order based on the reordered voucher_id list (current items in display order)
    if (!empty($reorder_voucher_ids)) {
        $order_ids = [];
        foreach ($reorder_voucher_ids as $id) {
            $order_ids[] = intval($id);
        }
        $order_ids = array_unique($order_ids);
        
        // Only update selection_order for items that are still in the batch
        $update_order_sql = "UPDATE food_redemption_items SET selection_order = ? WHERE batch_id = $batch_id AND voucher_id = ?";
        $order_stmt = mysqli_prepare($conn, $update_order_sql);
        if (!$order_stmt) {
            throw new Exception('Failed to prepare order update: ' . mysqli_error($conn));
        }
        
        foreach ($order_ids as $order => $voucher_id) {
            $seq = $order + 1; // 1-based order
            mysqli_stmt_bind_param($order_stmt, 'ii', $seq, $voucher_id);
            if (!mysqli_stmt_execute($order_stmt)) {
                throw new Exception('Failed to update selection order: ' . mysqli_stmt_error($order_stmt));
            }
        }
        mysqli_stmt_close($order_stmt);
    }
    
    // Update batch totals
    $update_batch_sql = "UPDATE food_redemption_batches 
        SET total_vouchers = ?, total_amount = ? 
        WHERE id = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_batch_sql);
    if (!$update_stmt) {
        throw new Exception('Failed to prepare batch update: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($update_stmt, 'idi', $current_vouchers, $current_amount, $batch_id);
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Failed to update batch totals: ' . mysqli_stmt_error($update_stmt));
    }
    mysqli_stmt_close($update_stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    $response = [
        'success' => true,
        'message' => 'Batch updated successfully',
        'batch_id' => $batch_id,
        'total_vouchers' => $current_vouchers,
        'total_amount' => $current_amount
    ];
    
    if (!empty($remove_voucher_ids)) {
        $response['removed'] = $removed_count;
    }
    if (!empty($add_voucher_ids)) {
        $response['added'] = $added_count;
        if (!empty($invalid_add_vouchers)) {
            $response['invalid_add_vouchers'] = $invalid_add_vouchers;
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();
?>