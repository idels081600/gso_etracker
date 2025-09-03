<?php
// Start output buffering to catch any unexpected output
ob_start();

session_start();

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

require_once 'logi_db.php';

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }

    // Get data from request
    $reason = isset($data['reason']) ? trim($data['reason']) : '';
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $office_id = isset($data['office_id']) ? $data['office_id'] : $user_id;
    $office_name = isset($data['office_name']) ? $data['office_name'] : ($username . "'s Office");
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    // Allow remarks-only submissions
    if (empty($reason) && empty($items)) {
        throw new Exception('Please provide remarks or items in the request');
    }

    // Start transaction
    mysqli_autocommit($conn, false);

    // Check if there's already a request for this specific office today
    $today = date('Y-m-d');
    $check_query = "SELECT COUNT(*) as count FROM items_requested 
                    WHERE office_id = ? AND DATE(date_requested) = ? AND remarks != ''";

    $check_stmt = mysqli_prepare($conn, $check_query);

    if (!$check_stmt) {
        throw new Exception('Failed to prepare check query: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($check_stmt, "is", $office_id, $today);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $row = mysqli_fetch_assoc($check_result);
    $has_remarks_today = $row['count'] > 0;
    mysqli_stmt_close($check_stmt);

    if (!empty($items)) {
        // Insert request items directly - Added unit field
        $item_query = "INSERT INTO items_requested (
            office_id,
            office_name,
            item_id,
            item_name,
            unit,
            quantity,
            approved_quantity,
            date_requested,
            remarks,
            status,
            uploaded
        ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), ?, 'Pending', 0)";

        $item_stmt = mysqli_prepare($conn, $item_query);

        if (!$item_stmt) {
            throw new Exception('Failed to prepare item query: ' . mysqli_error($conn));
        }

        $item_counter = 0;
        foreach ($items as $item) {
            // Validate item data - Added unit validation
            if (!isset($item['id']) || !isset($item['name']) || !isset($item['quantity']) || !isset($item['unit'])) {
                throw new Exception('Invalid item data structure - missing required fields (id, name, quantity, or unit)');
            }

            // Only include remarks for the first item to avoid duplication
            $item_remarks = ($item_counter === 0) ? $reason : '';

            mysqli_stmt_bind_param(
                $item_stmt,
                "isissis",
                $office_id,       // i - integer
                $username,        // s - string
                $item['id'],      // i - string
                $item['name'],    // s - string
                $item['unit'],    // s - string (added unit)
                $item['quantity'], // i - integer
                $item_remarks     // s - string (only for first item)
            );

            if (!mysqli_stmt_execute($item_stmt)) {
                throw new Exception('Failed to insert request item: ' . mysqli_stmt_error($item_stmt));
            }

            $item_counter++;
        }

        mysqli_stmt_close($item_stmt);
    } else {
        // Insert remarks-only request
        $remarks_query = "INSERT INTO items_requested (
            office_id,
            office_name,
            item_id,
            item_name,
            unit,
            quantity,
            approved_quantity,
            date_requested,
            remarks,
            status,
            uploaded
        ) VALUES (?, ?, NULL, NULL, NULL, 0, 0, NOW(), ?, 'Pending', 0)";

        $remarks_stmt = mysqli_prepare($conn, $remarks_query);

        if (!$remarks_stmt) {
            throw new Exception('Failed to prepare remarks query: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $remarks_stmt,
            "iss",
            $office_id,       // i - integer
            $username,        // s - string
            $reason           // s - string (remarks only)
        );

        if (!mysqli_stmt_execute($remarks_stmt)) {
            throw new Exception('Failed to insert remarks-only request: ' . mysqli_stmt_error($remarks_stmt));
        }

        mysqli_stmt_close($remarks_stmt);
    }

    // Commit transaction
    mysqli_commit($conn);
    mysqli_autocommit($conn, true);

    // Clear any unexpected output before sending JSON
    ob_clean();

    echo json_encode([
        'success' => true,
        'message' => 'Request submitted successfully',
        'items_count' => count($items)
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
    }

    // Clear any unexpected output before sending JSON
    ob_clean();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

    // Log the error (don't echo it)
    error_log("Error in Logi_submit_request.php: " . $e->getMessage());
} finally {
    // Close database connection
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
