<?php
// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);

// Include your database connection
require_once 'logi_db.php'; // or your database connection file

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Initialize response array
$response = array();

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate input
    if (!$data || !isset($data['item_no']) || !isset($data['quantity'])) {
        throw new Exception('Item number and quantity are required');
    }

    $item_no = mysqli_real_escape_string($conn, trim($data['item_no']));
    $quantity = (int)$data['quantity'];
    $requestor = isset($data['requestor']) ? mysqli_real_escape_string($conn, trim($data['requestor'])) : '';

    if (empty($item_no)) {
        throw new Exception('Item number cannot be empty');
    }

    if ($quantity <= 0) {
        throw new Exception('Quantity must be greater than 0');
    }

    if (empty($requestor)) {
        throw new Exception('Requestor is required');
    }

    // Begin transaction
    if (!mysqli_begin_transaction($conn)) {
        throw new Exception("Failed to begin transaction");
    }

    // Get current item information
    $query = "SELECT item_no, item_name, current_balance, unit FROM inventory_items WHERE item_no = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "s", $item_no);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Database execute error: " . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$item) {
        throw new Exception('Item not found');
    }

    $current_balance = (int)$item['current_balance'];

    // Check if sufficient stock is available
    if ($quantity > $current_balance) {
        throw new Exception("Insufficient stock. Available: {$current_balance}, Requested: {$quantity}");
    }

    // Calculate new balance
    $new_balance = $current_balance - $quantity;

    // Update inventory
    $update_query = "UPDATE inventory_items SET current_balance = ? WHERE item_no = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    
    if (!$update_stmt) {
        throw new Exception("Database prepare error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($update_stmt, "is", $new_balance, $item_no);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception("Failed to update inventory: " . mysqli_stmt_error($update_stmt));
    }
    mysqli_stmt_close($update_stmt);

    // Log the transaction with requestor
    $log_query = "INSERT INTO inventory_transactions 
                  (item_no, item_name, transaction_type, quantity, previous_balance, new_balance, requestor, created_at) 
                  VALUES (?, ?, 'DEDUCTION', ?, ?, ?, ?, NOW())";
    
    $log_stmt = mysqli_prepare($conn, $log_query);
    
    if ($log_stmt) {
        mysqli_stmt_bind_param($log_stmt, "ssiiis", 
            $item_no, 
            $item['item_name'], 
            $quantity, 
            $current_balance, 
            $new_balance,
            $requestor
        );
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    }

    // Commit transaction
    if (!mysqli_commit($conn)) {
        throw new Exception("Failed to commit transaction");
    }

    // Determine new status
    if ($new_balance == 0) {
        $status = "Out of Stock";
        $status_class = "danger";
    } else if ($new_balance <= 10) {
        $status = "Low Stock";
        $status_class = "warning";
    } else {
        $status = "Available";
        $status_class = "success";
    }

    $response = array(
        'success' => true,
        'message' => "Successfully deducted {$quantity} {$item['unit']} from {$item['item_name']} for {$requestor}",
        'transaction' => array(
            'item_no' => $item_no,
            'item_name' => $item['item_name'],
            'quantity_deducted' => $quantity,
            'previous_balance' => $current_balance,
            'new_balance' => $new_balance,
            'unit' => $item['unit'],
            'requestor' => $requestor,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $status,
            'status_class' => $status_class,
            'transaction_type' => 'DEDUCTION'
        )
    );

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    $response = array(
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    );
}

// Output JSON response
echo json_encode($response);
exit;
?>
