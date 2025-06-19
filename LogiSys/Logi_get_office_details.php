<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'logi_db.php'; // Adjust path as needed based on your database connection file location

try {
    // Check if office ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Office ID is required');
    }

    $office_id = intval($_GET['id']);

    // Get office details
    $office_query = "SELECT * FROM office_balances WHERE id = ?";
    $office_stmt = mysqli_prepare($conn, $office_query);

    if (!$office_stmt) {
        throw new Exception('Failed to prepare office query: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($office_stmt, "i", $office_id);
    mysqli_stmt_execute($office_stmt);
    $office_result = mysqli_stmt_get_result($office_stmt);
    $office_data = mysqli_fetch_assoc($office_result);
    mysqli_stmt_close($office_stmt);

    if (!$office_data) {
        throw new Exception('Office not found');
    }

    // Get assigned supplies for this office
    $supplies_query = "SELECT 
        s.id,
        s.item_name,
        s.quantity,
        s.unit,
        s.po_number,
        s.assigned_date,
        s.status
    FROM office_balance_items s 
    WHERE s.office_balance_id = ? 
    ORDER BY s.assigned_date DESC";

    $supplies_stmt = mysqli_prepare($conn, $supplies_query);

    if (!$supplies_stmt) {
        throw new Exception('Failed to prepare supplies query: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($supplies_stmt, "i", $office_id);
    mysqli_stmt_execute($supplies_stmt);
    $supplies_result = mysqli_stmt_get_result($supplies_stmt);

    $supplies = [];
    while ($row = mysqli_fetch_assoc($supplies_result)) {
        $supplies[] = $row;
    }
    mysqli_stmt_close($supplies_stmt);

    // Return the data as JSON
    $response = [
        'success' => true,
        'office' => $office_data,
        'supplies' => $supplies
    ];

    echo json_encode($response);
} catch (Exception $e) {
    // Return error response
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];

    echo json_encode($response);

    // Log the error for debugging
    error_log("Error in Logi_get_office_details.php: " . $e->getMessage());
}

// Close database connection
if (isset($conn)) {
    mysqli_close($conn);
}
