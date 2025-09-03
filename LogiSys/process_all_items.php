<?php
// Prevent any output before JSON
ob_start();

// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start(); // Start the session if not already started
require_once 'logi_db.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

// Function to log errors
function log_error($message)
{
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/error.log');
}

// Function to send JSON response and exit
function send_json_response($success, $message, $data = [])
{
    $response = array_merge(['success' => $success, 'message' => $message], $data);
    echo json_encode($response);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, 'Invalid request method');
}

// Check if user is logged in and has a session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    send_json_response(false, 'Authentication required');
}

// Function to sanitize data
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get JSON input
$json_str = file_get_contents('php://input');
$data = json_decode($json_str, true);

if (!isset($data['items']) || !is_array($data['items'])) {
    send_json_response(false, 'Invalid input: items array is required');
}

$items = $data['items'];
$processed_items = [];
$errors = [];

// Get user information from the session
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

try {
    // Check if connection exists
    if (!isset($conn) || !$conn) {
        log_error('Database connection not available');
        send_json_response(false, 'Database connection error');
    }

    // Check if connection is still alive
    if (!mysqli_ping($conn)) {
        log_error('Database connection lost');
        send_json_response(false, 'Database connection lost');
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    // Prepare office lookup statement once
    $office_query = "SELECT id FROM office_balances WHERE office_name = ?";
    $office_stmt = mysqli_prepare($conn, $office_query);

    // Prepare item insert statement once
    $insert_query = "INSERT INTO items_requested (item_id, item_name, quantity, unit, approved_quantity, office_id, office_name, date_requested, status, uploaded) VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Pending', 0)";
    $insert_stmt = mysqli_prepare($conn, $insert_query);

    if (!$office_stmt || !$insert_stmt) {
        throw new Exception('Failed to prepare statements: ' . mysqli_error($conn));
    }

    // Process each item
    foreach ($items as $item) {
        // Validate item data
        if (empty($item['itemNo']) || empty($item['itemName']) || empty($item['unit']) ||
            empty($item['office']) || !isset($item['requestedQty']) || empty($item['dateReceived'])) {
            $errors[] = "Missing required fields for item: " . ($item['itemName'] ?? 'Unknown');
            continue;
        }

        // Validate date format
        $date = DateTime::createFromFormat('Y-m-d', $item['dateReceived']);
        if (!$date || $date->format('Y-m-d') !== $item['dateReceived']) {
            $errors[] = "Invalid date format for item {$item['itemName']}. Use YYYY-MM-DD";
            continue;
        }

        // Get office ID
        mysqli_stmt_bind_param($office_stmt, "s", $item['office']);
        if (!mysqli_stmt_execute($office_stmt)) {
            throw new Exception('Failed to look up office: ' . mysqli_stmt_error($office_stmt));
        }

        $office_result = mysqli_stmt_get_result($office_stmt);
        $office_row = mysqli_fetch_assoc($office_result);

        if (!$office_row) {
            $errors[] = "Office not found: {$item['office']}";
            continue;
        }

        $office_id = $office_row['id'];

        // Insert item
        mysqli_stmt_bind_param($insert_stmt, "sssisss",
            $item['itemNo'],
            $item['itemName'],
            $item['requestedQty'],
            $item['unit'],
            $office_id,
            $item['office'],
            $item['dateReceived']
        );

        if (!mysqli_stmt_execute($insert_stmt)) {
            throw new Exception('Failed to insert item: ' . mysqli_stmt_error($insert_stmt));
        }

        $insert_id = mysqli_insert_id($conn);
        $processed_items[] = [
            'id' => $insert_id,
            'itemNo' => $item['itemNo'],
            'itemName' => $item['itemName'],
            'quantity' => $item['approvedQuantity'],
            'unit' => $item['unit'],
            'office' => $item['office'],
            'office_id' => $office_id,
            'dateReceived' => $item['dateReceived']
        ];
    }

    // If there were any errors but some items were processed, commit those changes
    if (!empty($processed_items)) {
        mysqli_commit($conn);
    }

    // Close statements
    mysqli_stmt_close($office_stmt);
    mysqli_stmt_close($insert_stmt);

    // Send response
    if (!empty($errors)) {
        if (!empty($processed_items)) {
            send_json_response(true, 'Some items processed with errors', [
                'processed' => $processed_items,
                'errors' => $errors
            ]);
        } else {
            send_json_response(false, 'Failed to process items', [
                'errors' => $errors
            ]);
        }
    } else {
        send_json_response(true, 'All items processed successfully', [
            'processed' => $processed_items
        ]);
    }

} catch (Exception $e) {
    mysqli_rollback($conn);
    log_error('Exception: ' . $e->getMessage());
    send_json_response(false, 'An error occurred while processing your request');
} finally {
    // Close connection
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
}
