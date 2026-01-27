<?php
require 'pr_db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['ids']) || empty($input['ids'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No records selected for deletion'
    ]);
    exit();
}

// Sanitize input data
$ids = array_map(function($id) use ($conn) {
    return mysqli_real_escape_string($conn, $id);
}, $input['ids']);

// Prepare SQL statement
$ids_list = implode(',', $ids);
$sql = "DELETE FROM ppmp WHERE id IN ($ids_list)";

// Execute query
if (mysqli_query($conn, $sql)) {
    $deleted_count = mysqli_affected_rows($conn);
    echo json_encode([
        'success' => true,
        'message' => "Successfully deleted $deleted_count record(s)"
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . mysqli_error($conn)
    ]);
}

mysqli_close($conn);
?>
