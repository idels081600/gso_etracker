<?php
// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);

// Include your database connection
require_once 'logi_db.php';

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
    if (!$data || !isset($data['qr_code'])) {
        throw new Exception('QR code is required');
    }

    $item_no = mysqli_real_escape_string($conn, trim($data['qr_code']));

    if (empty($item_no)) {
        throw new Exception('QR code cannot be empty');
    }

    // Search for item by item_no
    $query = "SELECT 
                item_no,
                item_name,
                current_balance,
                unit,
                rack_no,
                status
              FROM inventory_items 
              WHERE item_no = ?
              LIMIT 1";

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

    if ($item) {
        // Item found
        $balance = (int)$item['current_balance'];

        // Determine status based on balance
        if ($balance == 0) {
            $status = "Out of Stock";
            $statusClass = "danger";
        } else if ($balance <= 10) {
            $status = "Low Stock";
            $statusClass = "warning";
        } else {
            $status = "Available";
            $statusClass = "success";
        }

        $response = array(
            'success' => true,
            'message' => 'Item found successfully',
            'item' => array(
                'item_no' => $item['item_no'],
                'item_name' => $item['item_name'],
                'current_balance' => $item['current_balance'],
                'unit' => $item['unit'],
                'rack_no' => $item['rack_no'],
                'status' => $status,
                'status_class' => $statusClass,
                'original_status' => $item['status'] ?? ''
            )
        );
    } else {
        // Item not found
        $response = array(
            'success' => false,
            'message' => 'Item not found',
            'qr_code' => $item_no,
            'suggestion' => 'Please check the QR code and try again, or add this item to inventory.'
        );
    }
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