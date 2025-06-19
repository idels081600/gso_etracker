<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'logi_db.php'; // Adjust path as needed based on your database connection file location

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate required fields
    if (!isset($_POST['officeId']) || empty($_POST['officeId'])) {
        throw new Exception('Office selection is required');
    }

    if (!isset($_POST['items']) || !is_array($_POST['items']) || empty($_POST['items'])) {
        throw new Exception('At least one item must be selected');
    }

    $office_id = intval($_POST['officeId']);
    $items = $_POST['items'];
    $quantities = $_POST['quantities'];
    $po_numbers = $_POST['po_numbers'];
    $assigned_dates = $_POST['assigned_dates'];
    $notes = $_POST['notes'] ?? [];

    // Validate arrays have same length
    $item_count = count($items);
    if (count($quantities) !== $item_count || count($po_numbers) !== $item_count || count($assigned_dates) !== $item_count) {
        throw new Exception('Form data mismatch. Please try again.');
    }

    // Start transaction
    mysqli_autocommit($conn, false);

    $assigned_items = [];
    $errors = [];

    for ($i = 0; $i < $item_count; $i++) {
        $item_no = trim($items[$i]);
        $quantity = intval($quantities[$i]);
        $po_number = trim($po_numbers[$i]);
        $assigned_date = $assigned_dates[$i];
        $note = isset($notes[$i]) ? trim($notes[$i]) : '';

        // Skip empty items
        if (empty($item_no) || $quantity <= 0) {
            continue;
        }

        // Validate date
        if (empty($assigned_date)) {
            $assigned_date = date('Y-m-d');
        }

        // Get item details
        $item_query = "SELECT item_name, unit FROM inventory_items WHERE item_no = ?";
        $item_stmt = mysqli_prepare($conn, $item_query);

        if (!$item_stmt) {
            throw new Exception('Failed to prepare item query: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($item_stmt, "s", $item_no);
        mysqli_stmt_execute($item_stmt);
        $item_result = mysqli_stmt_get_result($item_stmt);
        $item_data = mysqli_fetch_assoc($item_result);
        mysqli_stmt_close($item_stmt);

        if (!$item_data) {
            $errors[] = "Item with number '{$item_no}' not found";
            continue;
        }

        // Insert new record directly
        $insert_query = "INSERT INTO office_balance_items (
            office_balance_id, 
            item_no, 
            item_name, 
            quantity, 
            unit, 
            po_number, 
            assigned_date, 
            status, 
            notes,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?, NOW())";

        $insert_stmt = mysqli_prepare($conn, $insert_query);

        if (!$insert_stmt) {
            throw new Exception('Failed to prepare insert query: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $insert_stmt,
            "ississss",
            $office_id,
            $item_no,
            $item_data['item_name'],
            $quantity,
            $item_data['unit'],
            $po_number,
            $assigned_date,
            $note
        );

        if (!mysqli_stmt_execute($insert_stmt)) {
            $errors[] = "Failed to assign {$item_data['item_name']}: " . mysqli_stmt_error($insert_stmt);
            mysqli_stmt_close($insert_stmt);
            continue;
        }
        mysqli_stmt_close($insert_stmt);

        $assigned_items[] = [
            'item_name' => $item_data['item_name'],
            'quantity' => $quantity,
            'action' => 'assigned'
        ];
    }

    // Check if we have any successful assignments
    if (empty($assigned_items) && !empty($errors)) {
        throw new Exception('No items were assigned. Errors: ' . implode(', ', $errors));
    }

    // Commit transaction
    mysqli_commit($conn);
    mysqli_autocommit($conn, true);

    // Prepare success message
    $success_message = "Successfully assigned " . count($assigned_items) . " item(s)";
    if (!empty($errors)) {
        $success_message .= ". Some items had issues: " . implode(', ', $errors);
    }

    // Return success response
    $response = [
        'success' => true,
        'message' => $success_message,
        'assigned_items' => $assigned_items,
        'errors' => $errors
    ];

    echo json_encode($response);
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
    }

    // Return error response
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];

    echo json_encode($response);

    // Log the error for debugging
    error_log("Error in Logi_assign_supplies.php: " . $e->getMessage());
}

// Close database connection
if (isset($conn)) {
    mysqli_close($conn);
}
