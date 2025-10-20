<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database connection
require_once 'advance_po_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
    exit;
}

// Get the JSON data from the request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

try {
    $response = [];

    // Check if it's a single item or multiple items
    if (isset($data['single_item']) && $data['single_item'] === true) {
        // Single item insertion
        $item = $data['item'];
        if (insertSingleItem($conn, $item)) {
            $response = ['success' => true, 'message' => 'Single item inserted successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Failed to insert single item'];
        }
    } elseif (isset($data['items']) && is_array($data['items'])) {
        // Multiple items insertion
        $items = $data['items'];
        $insertedCount = insertMultipleItems($conn, $items);

        if ($insertedCount > 0) {
            $response = ['success' => true, 'message' => "$insertedCount items inserted successfully"];
        } else {
            $response = ['success' => false, 'message' => 'Failed to insert items'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Invalid data format'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
}

// Close the database connection if needed
mysqli_close($conn);

echo json_encode($response);

function insertSingleItem($conn, $item)
{
    // Validate required fields
    if (
        !isset($item['store']) || !isset($item['date']) || !isset($item['invoice_number']) ||
        !isset($item['description']) || !isset($item['pcs']) || !isset($item['unit_price'])
    ) {
        throw new Exception('Missing required fields');
    }

    $stmt = $conn->prepare("INSERT INTO advancePo (store, date, invoice_number, description, pcs, unit_price, delete_status,amount,status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $store = trim($item['store']);
    $date = trim($item['date']);
    $invoice_number = trim($item['invoice_number']);
    $description = trim($item['description']);
    $pcs = (int)$item['pcs'];
    $amount = $pcs * $item['unit_price'];
    $unit_price = (float)$item['unit_price'];
    $delete = 0;
    $status = 'Pending';


    $stmt->bind_param("ssssdiiis", $store, $date, $invoice_number, $description, $pcs, $unit_price, $delete, $amount, $status);

    $success = $stmt->execute();

    $stmt->close();

    return $success;
}

function insertMultipleItems($conn, $items)
{
    $insertedCount = 0;

    foreach ($items as $item) {
        if (insertSingleItem($conn, $item)) {
            $insertedCount++;
        }
    }

    return $insertedCount;
}
