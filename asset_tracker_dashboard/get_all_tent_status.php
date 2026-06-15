<?php
require_once __DIR__ . '/app_security.php';
asset_require_auth();
// Include database connection
require_once __DIR__ . '/db_asset.php';

// Set header for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Suppress error output for clean JSON response
error_reporting(0);
ini_set('display_errors', 0);

try {
    // Query to get all tents with their status information
    $query = "SELECT id, Status FROM `tent_status` ORDER BY `id` ASC";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to load tent status data.'
        ]);
        exit;
    }

    $tents = array();

    // Fetch all tent records
    while ($row = mysqli_fetch_assoc($result)) {
        $tents[] = array(
            'id' => (int)$row['id'],
            'Status' => $row['Status'] ?: ''
        );
    }

    // Return success response with data
    echo json_encode([
        'success' => true,
        'data' => $tents
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

// Close database connection
if (isset($conn)) {
    mysqli_close($conn);
}
?>

