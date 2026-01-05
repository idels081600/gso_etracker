<?php
require_once 'logi_display_data.php'; // Include database connection

// Set header to return JSON response
header('Content-Type: application/json');

try {

    // Sanitize and assign
    $item_name = mysqli_real_escape_string($conn, $_POST['itemName']);
    $item_no = mysqli_real_escape_string($conn, $_POST['itemNo']);
    $quantity = (int)$_POST['quantity'];
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $transaction_date = isset($_POST['transaction_date']) && !empty($_POST['transaction_date'])
        ? mysqli_real_escape_string($conn, $_POST['transaction_date'])
        : date('Y-m-d H:i:s'); // Default to current datetime if not provided
    $previous_balance = (int)$_POST['previous_balance'];
    $new_balance = (int)$_POST['new_balance'];
    $transaction_type = 'ADDITION';

    // Validate quantity
    if ($quantity <= 0) {
        throw new Exception("Quantity must be greater than 0");
    }
    if ($new_balance !== ($previous_balance + $quantity)) {
        throw new Exception("Invalid balance calculation");
    }

    // Insert transaction
    $insert_sql = "INSERT INTO inventory_transactions (item_name, item_no, quantity, previous_balance, new_balance, reason, transaction_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param('ssiiisss', $item_name, $item_no, $quantity, $previous_balance, $new_balance, $reason, $transaction_type, $transaction_date);
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

    echo json_encode(['success' => true, 'message' => 'Stock in transaction completed successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>
