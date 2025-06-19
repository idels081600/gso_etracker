<?php
require_once 'logi_display_data.php'; // Include database connection

// Set header to return JSON response
header('Content-Type: application/json');

try {
    // Validate required fields
    $required_fields = ['itemNo', 'itemName', 'quantity', 'reason', 'previous_balance', 'new_balance', 'requestor_name'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize and assign
    $item_name = mysqli_real_escape_string($conn, $_POST['itemName']);
    $item_no = mysqli_real_escape_string($conn, $_POST['itemNo']);
    $quantity = (int)$_POST['quantity'];
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $previous_balance = (int)$_POST['previous_balance'];
    $new_balance = (int)$_POST['new_balance'];
    $requestor = mysqli_real_escape_string($conn, $_POST['requestor_name']);
    $transaction_type = 'DEDUCTION';

    // Validate quantity
    if ($quantity <= 0) {
        throw new Exception("Quantity must be greater than 0");
    }

    // Validate balance calculation
    if ($new_balance !== ($previous_balance - $quantity)) {
        throw new Exception("Invalid balance calculation");
    }

    // Validate sufficient stock
    if ($new_balance < 0) {
        throw new Exception("Insufficient stock for this transaction");
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert transaction
        $insert_sql = "INSERT INTO inventory_transactions (
            item_name, 
            item_no, 
            quantity, 
            previous_balance, 
            new_balance, 
            reason, 
            transaction_type,
            requestor,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param(
            'ssiiisss',
            $item_name,
            $item_no,
            $quantity,
            $previous_balance,
            $new_balance,
            $reason,
            $transaction_type,
            $requestor
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert transaction");
        }

        // Update inventory
        $update_sql = "UPDATE inventory_items SET current_balance = ?, updated_at = NOW() WHERE item_no = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('is', $new_balance, $item_no);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update inventory");
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Stock out transaction completed successfully'
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?> 