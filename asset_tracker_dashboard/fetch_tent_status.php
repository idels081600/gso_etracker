<?php
require_once __DIR__ . '/app_security.php';
asset_require_auth();
// Include your database connection file
include __DIR__ . '/db_asset.php';

// Select all data from the tent_status table
$query = "SELECT id, Status FROM tent_status";
$result = mysqli_query($conn, $query);

if ($result) {
    $data = array();
    // Fetch associative array
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    // Free result set
    mysqli_free_result($result);
    // Close connection
    mysqli_close($conn);
    // Send the data as JSON response
    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    // If query fails, return an error message
    error_log('fetch_tent_status query failed: ' . mysqli_error($conn));
    http_response_code(500);
    echo json_encode([]);
}

