<?php
// Turn off error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

require 'pr_db.php';

// Clean any output that might have been generated
ob_clean();

// Set content type to JSON
header('Content-Type: application/json');

// Get the POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data'
    ]);
    exit();
}

// Validate required fields
$required_fields = ['id', 'pr_number', 'po_number', 'project', 'start_procurement', 
                   'end_procurement', 'expected_delivery', 'amount', 'pr_status', 'delivery_status'];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        echo json_encode([
            'success' => false,
            'message' => "Missing required field: $field"
        ]);
        exit();
    }
}

// Prepare the update query
$sql = "UPDATE ppmp SET 
        pr_number = ?, 
        po_number = ?, 
        project = ?, 
        start_procurement = ?, 
        end_procurement = ?, 
        expected_delivery = ?, 
        amount = ?, 
        pr_status = ?, 
        delivery_status = ?,
        pre_submitted = ?,
        pre_approved = ?,
        pre_declined = ?,
        pre_bac_declined = ?,
        bac_submitted = ?,
        bac_categorized = ?,
        bac_posted = ?,
        bac_bidding = ?,
        post_awarded = ?,
        post_approved = ?
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database preparation error'
    ]);
    exit();
}

// Bind parameters
mysqli_stmt_bind_param($stmt, "ssssssdssiiiiiiiiiii", 
    $data['pr_number'],
    $data['po_number'],
    $data['project'],
    $data['start_procurement'],
    $data['end_procurement'],
    $data['expected_delivery'],
    $data['amount'],
    $data['pr_status'],
    $data['delivery_status'],
    $data['pre_submitted'],
    $data['pre_approved'],
    $data['pre_declined'],
    $data['pre_bac_declined'],
    $data['bac_submitted'],
    $data['bac_categorized'],
    $data['bac_posted'],
    $data['bac_bidding'],
    $data['post_awarded'],
    $data['post_approved'],
    $data['id']
);

// Execute the query
if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Record updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No changes made or record not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating record'
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
