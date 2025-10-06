<?php
// Include database connection
require_once 'db_asset.php';

// Set header for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Suppress error output for clean JSON response
error_reporting(0);
ini_set('display_errors', 0);

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'error' => 'Only POST requests are allowed'
        ]);
        exit;
    }

    // Get POST data
    $tent_ids = isset($_POST['tent_ids']) ? $_POST['tent_ids'] : null;
    $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : null;

    // Validate input
    if (empty($tent_ids)) {
        echo json_encode([
            'success' => false,
            'error' => 'No tent IDs provided'
        ]);
        exit;
    }

    if ($new_status === null || $new_status === '') {
        echo json_encode([
            'success' => false,
            'error' => 'No status provided'
        ]);
        exit;
    }

    // Ensure tent_ids is an array
    if (!is_array($tent_ids)) {
        $tent_ids = json_decode($tent_ids, true);
        if (!is_array($tent_ids)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid tent IDs format'
            ]);
            exit;
        }
    }

    // Convert IDs to integers and validate
    $valid_ids = [];
    foreach ($tent_ids as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $valid_ids[] = $id;
        }
    }

    if (empty($valid_ids)) {
        echo json_encode([
            'success' => false,
            'error' => 'No valid tent IDs provided'
        ]);
        exit;
    }

    // Sanitize the new status
    $allowed_statuses = ['null', 'Installed', 'For Retrieval', 'Retrieved', 'Long Term', 'Pending'];
    $new_status = in_array($new_status, $allowed_statuses) ? $new_status : 'null';

    // Prepare the IN clause for SQL
    $ids_placeholder = str_repeat('?,', count($valid_ids) - 1) . '?';
    $query = "UPDATE `tent_status` SET `Status` = ? WHERE `id` IN ($ids_placeholder)";

    // Prepare statement
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to prepare statement: ' . mysqli_error($conn)
        ]);
        exit;
    }

    // Bind parameters
    $types = 's' . str_repeat('i', count($valid_ids));
    $params = array_merge([$new_status], $valid_ids);

    mysqli_stmt_bind_param($stmt, $types, ...$params);

    // Execute the query
    $result = mysqli_stmt_execute($stmt);
    $affected_rows = mysqli_stmt_affected_rows($stmt);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update tent status: ' . mysqli_stmt_error($stmt)
        ]);
        mysqli_stmt_close($stmt);
        exit;
    }

    mysqli_stmt_close($stmt);

    // Log the action for debugging
    error_log("Updated $affected_rows tent(s) to status: $new_status");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Updated $affected_rows tent(s) to '$new_status' successfully",
        'updated_count' => $affected_rows
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
} finally {
    // Close database connection
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>
