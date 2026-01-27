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

// Get the record ID from the request
$recordId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($recordId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid record ID'
    ]);
    exit();
}

// Query to fetch the specific record data
$sql = "SELECT id, pr_number, po_number, project, start_procurement, end_procurement, 
               expected_delivery, amount, pr_status, delivery_status,
               pre_submitted, pre_approved, pre_declined, pre_bac_declined,
               bac_submitted, bac_categorized, bac_posted, bac_bidding,
               post_awarded, post_approved
        FROM ppmp 
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database preparation error'
    ]);
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $recordId);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database execution error'
    ]);
    mysqli_stmt_close($stmt);
    exit();
}

$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $record = mysqli_fetch_assoc($result);
    
    echo json_encode([
        'success' => true,
        'data' => $record
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Record not found'
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
