<?php
require 'pr_db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['project']) || !isset($input['start_procurement']) || 
    !isset($input['end_procurement']) || !isset($input['expected_delivery']) || 
    !isset($input['amount']) || !isset($input['pr_status'])) {
    
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required'
    ]);
    exit();
}

// Sanitize input data
$project = mysqli_real_escape_string($conn, $input['project']);
$start_procurement = mysqli_real_escape_string($conn, $input['start_procurement']);
$end_procurement = mysqli_real_escape_string($conn, $input['end_procurement']);
$expected_delivery = mysqli_real_escape_string($conn, $input['expected_delivery']);
$amount = floatval($input['amount']);
$pr_status = mysqli_real_escape_string($conn, $input['pr_status']);

// Prepare SQL statement
$sql = "INSERT INTO ppmp (project, start_procurement, end_procurement, expected_delivery, amount, pr_status, delivery_status) 
        VALUES ('$project', '$start_procurement', '$end_procurement', '$expected_delivery', $amount, '$pr_status', 'pending')";

// Execute query
if (mysqli_query($conn, $sql)) {
    echo json_encode([
        'success' => true,
        'message' => 'Data added successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . mysqli_error($conn)
    ]);
}

mysqli_close($conn);
?>
