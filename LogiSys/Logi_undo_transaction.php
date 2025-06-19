<?php
require_once 'logi_display_data.php'; // Include database connection

header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Get the transaction ID from POST data
    $transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
    
    if (empty($transaction_id)) {
        throw new Exception("Transaction ID is required");
    }

    // Validate transaction ID is numeric
    if (!is_numeric($transaction_id)) {
        throw new Exception("Invalid transaction ID format");
    }

    // Start database transaction
    mysqli_begin_transaction($conn);

    try {
        // First, get the transaction details
        $get_transaction_query = "SELECT * FROM inventory_transactions WHERE id = ? LIMIT 1";
        $get_stmt = mysqli_prepare($conn, $get_transaction_query);
        
        if (!$get_stmt) {
            throw new Exception("Database prepare error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($get_stmt, "i", $transaction_id);
        
        if (!mysqli_stmt_execute($get_stmt)) {
            throw new Exception("Failed to fetch transaction: " . mysqli_stmt_error($get_stmt));
        }

        $result = mysqli_stmt_get_result($get_stmt);
        $transaction = mysqli_fetch_assoc($result);
        mysqli_stmt_close($get_stmt);

        if (!$transaction) {
            throw new Exception("Transaction not found");
        }

        // Get current item details
        $get_item_query = "SELECT * FROM inventory_items WHERE item_no = ? LIMIT 1";
        $item_stmt = mysqli_prepare($conn, $get_item_query);
        
        if (!$item_stmt) {
            throw new Exception("Database prepare error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($item_stmt, "s", $transaction['item_no']);
        
        if (!mysqli_stmt_execute($item_stmt)) {
            throw new Exception("Failed to fetch item: " . mysqli_stmt_error($item_stmt));
        }

        $item_result = mysqli_stmt_get_result($item_stmt);
        $item = mysqli_fetch_assoc($item_result);
        mysqli_stmt_close($item_stmt);

        if (!$item) {
            throw new Exception("Item not found in inventory");
        }

        $current_balance = (int)$item['current_balance'];
        $transaction_quantity = (int)$transaction['quantity'];
        $previous_balance = (int)$transaction['previous_balance'];
        $new_balance_from_transaction = (int)$transaction['new_balance'];

        // Determine the undo operation based on the original transaction
        // If the transaction increased the balance, we need to decrease it
        // If the transaction decreased the balance, we need to increase it
        $undo_balance = 0;
        $undo_reason = "";

        if ($new_balance_from_transaction > $previous_balance) {
            // Original transaction was an addition (Stock In)
            $undo_balance = $current_balance - $transaction_quantity;
            $undo_reason = "Undo Stock In - " . $transaction['reason'];
            
            // Check if we have enough stock to undo
            if ($undo_balance < 0) {
                throw new Exception("Cannot undo transaction: Insufficient stock. Current balance: {$current_balance}, trying to remove: {$transaction_quantity}");
            }
        } else {
            // Original transaction was a deduction (Stock Out)
            $undo_balance = $current_balance + $transaction_quantity;
            $undo_reason = "Undo Stock Out - " . $transaction['reason'];
        }

        // Update the inventory item balance
        $update_inventory_query = "UPDATE inventory_items SET current_balance = ? WHERE item_no = ?";
        $update_stmt = mysqli_prepare($conn, $update_inventory_query);
        
        if (!$update_stmt) {
            throw new Exception("Database prepare error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($update_stmt, "is", $undo_balance, $transaction['item_no']);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Failed to update inventory: " . mysqli_stmt_error($update_stmt));
        }
        mysqli_stmt_close($update_stmt);

        // Create a new transaction record for the undo operation
        $undo_transaction_type = ($new_balance_from_transaction > $previous_balance) ? 'Stock Out' : 'Stock In';
        $undo_quantity = $transaction_quantity;
        
        $insert_undo_query = "INSERT INTO inventory_transactions 
                             (item_no, item_name, transaction_type, quantity, previous_balance, new_balance, reason, requestor, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $insert_stmt = mysqli_prepare($conn, $insert_undo_query);
        
        if (!$insert_stmt) {
            throw new Exception("Database prepare error: " . mysqli_error($conn));
        }

        $requestor = "System - Undo Transaction #" . $transaction_id;
        
        mysqli_stmt_bind_param($insert_stmt, "sssiiiss", 
            $transaction['item_no'],
            $transaction['item_name'],
            $undo_transaction_type,
            $undo_quantity,
            $current_balance,
            $undo_balance,
            $undo_reason,
            $requestor
        );
        
        if (!mysqli_stmt_execute($insert_stmt)) {
            throw new Exception("Failed to create undo transaction: " . mysqli_stmt_error($insert_stmt));
        }
        mysqli_stmt_close($insert_stmt);

        // Mark the original transaction as undone (optional - you can add an 'undone' column to track this)
        $mark_undone_query = "UPDATE inventory_transactions SET reason = CONCAT(reason, ' [UNDONE]') WHERE id = ?";
        $mark_stmt = mysqli_prepare($conn, $mark_undone_query);
        
        if ($mark_stmt) {
            mysqli_stmt_bind_param($mark_stmt, "i", $transaction_id);
            mysqli_stmt_execute($mark_stmt);
            mysqli_stmt_close($mark_stmt);
        }

        // Commit the transaction
        mysqli_commit($conn);

        // Return success response
        $response = [
            'success' => true,
            'message' => 'Transaction undone successfully',
            'details' => [
                'item_name' => $transaction['item_name'],
                'original_quantity' => $transaction_quantity,
                'previous_balance' => $current_balance,
                'new_balance' => $undo_balance,
                'undo_type' => $undo_transaction_type
            ]
        ];

        echo json_encode($response);

    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    // Return error response
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'details' => [
            'file' => __FILE__,
            'line' => $e->getLine()
        ]
    ];

    echo json_encode($response);
}

// Close database connection
if (isset($conn)) {
    mysqli_close($conn);
}
?>
