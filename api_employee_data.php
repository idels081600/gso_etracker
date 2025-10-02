<?php
// Suppress error output for clean JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'dbh.php';
require_once 'functions.php';

// Set content type to JSON (after suppressing errors)
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Get the JSON data for employees
$result = display_emp_status_r();

$employees = array();
while ($row = mysqli_fetch_assoc($result)) {
    $employees[] = array(
        'id' => $row['id'],
        'name' => $row['name'],
        'destination' => $row['destination'],
        'status' => $row['status1'], // Note: status1 from the query
        'typeofbusiness' => $row['typeofbusiness'],
    );
}

// Return JSON response
echo json_encode($employees);
?>
