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
        $transaction_type = strtolower(trim($transaction['transaction_type']));

        if ($transaction_type === 'stock out' || $transaction_type === 'deduction') {
            // Undo deduction: add quantity to previous_balance and current_balance
            $new_previous_balance = $previous_balance + $transaction_quantity;
            $new_current_balance = $current_balance + $transaction_quantity;

            // Update inventory_items current_balance
            $update_inventory_query = "UPDATE inventory_items SET current_balance = ? WHERE item_no = ?";
            $update_stmt = mysqli_prepare($conn, $update_inventory_query);
            if (!$update_stmt) {
                throw new Exception("Database prepare error: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($update_stmt, "is", $new_current_balance, $transaction['item_no']);
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Failed to update inventory: " . mysqli_stmt_error($update_stmt));
            }
            mysqli_stmt_close($update_stmt);
        } elseif ($transaction_type === 'stock in' || $transaction_type === 'addition') {
            // Undo addition: deduct quantity from current_balance
            $new_current_balance = $current_balance - $transaction_quantity;
            if ($new_current_balance < 0) {
                throw new Exception("Cannot undo transaction: Insufficient stock after deduction.");
            }
            $update_inventory_query = "UPDATE inventory_items SET current_balance = ? WHERE item_no = ?";
            $update_stmt = mysqli_prepare($conn, $update_inventory_query);
            if (!$update_stmt) {
                throw new Exception("Database prepare error: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($update_stmt, "is", $new_current_balance, $transaction['item_no']);
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Failed to update inventory: " . mysqli_stmt_error($update_stmt));
            }
            mysqli_stmt_close($update_stmt);
        } else {
            throw new Exception("Unsupported transaction type for undo operation.");
        }

        // Delete the transaction from inventory_transactions
        $delete_transaction_query = "DELETE FROM inventory_transactions WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_transaction_query);
        if (!$delete_stmt) {
            throw new Exception("Database prepare error: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($delete_stmt, "i", $transaction_id);
        if (!mysqli_stmt_execute($delete_stmt)) {
            throw new Exception("Failed to delete transaction: " . mysqli_stmt_error($delete_stmt));
        }
        mysqli_stmt_close($delete_stmt);

        // Commit the transaction
        mysqli_commit($conn);

        // Return success response
        $response = [
            'success' => true,
            'message' => 'Transaction undone and deleted successfully',
            'details' => [
                'item_name' => $transaction['item_name'],
                'item_no' => $transaction['item_no'],
                'quantity' => $transaction_quantity,
                'transaction_type' => $transaction['transaction_type'],
                'new_current_balance' => $new_current_balance
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
