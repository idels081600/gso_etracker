<?php
// Prevent any output before JSON
ob_start();

// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start(); // Start the session if not already started
require_once 'logi_db.php';

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

// Function to log errors
function log_error($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/error.log');
}

// Function to send JSON response and exit
function send_json_response($success, $message, $data = []) {
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
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get data from POST and sanitize
$itemNo = isset($_POST['itemNo']) ? sanitize_input($_POST['itemNo']) : '';
$itemName = isset($_POST['itemName']) ? sanitize_input($_POST['itemName']) : '';
$unit = isset($_POST['unit']) ? sanitize_input($_POST['unit']) : '';
$office = isset($_POST['office']) ? sanitize_input($_POST['office']) : '';
$approved_Quantity = isset($_POST['approved_Quantity']) ? intval($_POST['approved_Quantity']) : 0;
$dateReceived = isset($_POST['dateReceived']) ? sanitize_input($_POST['dateReceived']) : '';

// Validate data
$errors = [];
if (empty($itemNo)) {
    $errors[] = 'Item Number is required';
}
if (empty($itemName)) {
    $errors[] = 'Item Name is required';
}
if (empty($unit)) {
    $errors[] = 'Unit is required';
}
if (empty($office)) {
    $errors[] = 'Office is required';
}
if (!is_numeric($approved_Quantity) || $approved_Quantity <= 0) {
    $errors[] = 'Approved Quantity must be a positive number';
}
if (empty($dateReceived)) {
    $errors[] = 'Date Received is required';
} else {
    // Validate date format
    $date = DateTime::createFromFormat('Y-m-d', $dateReceived);
    if (!$date || $date->format('Y-m-d') !== $dateReceived) {
        $errors[] = 'Invalid date format. Use YYYY-MM-DD';
    }
}

// If there are validation errors, return them
if (!empty($errors)) {
    send_json_response(false, 'Validation error(s): ' . implode(', ', $errors));
}

// Get user information from the session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

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

    // Prepare SQL statement
    $query = "INSERT INTO items_requested (item_id, item_name, quantity, unit, approved_quantity, office_name, date_requested, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        log_error('Prepare failed: ' . mysqli_error($conn));
        send_json_response(false, 'Failed to prepare database statement');
    }

    // Bind parameters (fixed the binding - using quantity as approved_Quantity for both fields)
    mysqli_stmt_bind_param($stmt, "ssidsss", $itemNo, $itemName, $approved_Quantity, $unit, $approved_Quantity, $office, $dateReceived);

    // Execute statement
    if (mysqli_stmt_execute($stmt)) {
        $insert_id = mysqli_insert_id($conn);
        
        send_json_response(true, 'Item added successfully', [
            'id' => $insert_id,
            'itemNo' => $itemNo,
            'itemName' => $itemName,
            'quantity' => $approved_Quantity,
            'unit' => $unit,
            'office' => $office,
            'dateReceived' => $dateReceived
        ]);
    } else {
        log_error('Execute failed: ' . mysqli_stmt_error($stmt));
        send_json_response(false, 'Failed to insert record into database');
    }

    // Close statement
    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    log_error('Exception: ' . $e->getMessage());
    send_json_response(false, 'An error occurred while processing your request');
} catch (Error $e) {
    log_error('Fatal Error: ' . $e->getMessage());
    send_json_response(false, 'A system error occurred');
}

// Close connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>