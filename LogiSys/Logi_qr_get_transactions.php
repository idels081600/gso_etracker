<?php
// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);

// Include your database connection
require_once 'logi_db.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Initialize response array
$response = array();

try {
    // Get the last 10 transactions
    $query = "SELECT 
                item_no,
                item_name,
                transaction_type,
                quantity,
                previous_balance,
                new_balance,
                requestor,
                reference_no,
                created_at
              FROM inventory_transactions 
              ORDER BY created_at DESC 
              LIMIT 10";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        throw new Exception("Database query error: " . mysqli_error($conn));
    }

    $transactions = array();
    while ($row = mysqli_fetch_assoc($result)) {
        // Format the transaction data
        $transaction = array(
            'item_no' => $row['item_no'],
            'item_name' => $row['item_name'],
            'type' => strtolower($row['transaction_type']),
            'quantity' => (int)$row['quantity'],
            'previous_balance' => (int)$row['previous_balance'],
            'new_balance' => (int)$row['new_balance'],
            'requestor' => $row['requestor'],
            'reference_no' => $row['reference_no'],
            'timestamp' => $row['created_at']
        );
        $transactions[] = $transaction;
    }

    $response = array(
        'success' => true,
        'transactions' => $transactions
    );

} catch (Exception $e) {
    $response = array(
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    );
}

// Output JSON response
echo json_encode($response);
exit; 