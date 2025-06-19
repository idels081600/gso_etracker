<?php
require_once 'logi_db.php'; // Include database connection

// Set header to return JSON response
header('Content-Type: application/json');

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON data");
    }

    // Validate required fields
    if (!isset($data['item_ids']) || !is_array($data['item_ids']) || empty($data['item_ids'])) {
        throw new Exception("No items selected for deletion");
    }

    $item_ids = $data['item_ids'];
    $item_names = isset($data['item_names']) ? $data['item_names'] : [];

    // Validate item IDs
    foreach ($item_ids as $item_id) {
        if (empty($item_id)) {
            throw new Exception("Invalid item ID provided");
        }
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        $deleted_count = 0;
        $failed_items = [];

        foreach ($item_ids as $index => $item_id) {
            $item_id = mysqli_real_escape_string($conn, $item_id);
            
            // First, check if item exists
            $check_query = "SELECT item_no, item_name FROM inventory_items WHERE item_no = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 's', $item_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($result) === 0) {
                $failed_items[] = "Item {$item_id} not found";
                mysqli_stmt_close($check_stmt);
                continue;
            }
            
            $item_data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($check_stmt);

            // Delete the item directly
            $delete_query = "DELETE FROM inventory_items WHERE item_no = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($delete_stmt, 's', $item_id);
            
            if (mysqli_stmt_execute($delete_stmt)) {
                if (mysqli_stmt_affected_rows($delete_stmt) > 0) {
                    $deleted_count++;
                } else {
                    $failed_items[] = "Failed to delete item {$item_id}";
                }
            } else {
                $failed_items[] = "Database error for item {$item_id}: " . mysqli_stmt_error($delete_stmt);
            }
            
            mysqli_stmt_close($delete_stmt);
        }

        // Check if any items were deleted
        if ($deleted_count === 0) {
            throw new Exception("No items were deleted. " . implode(", ", $failed_items));
        }

        // Commit transaction
        mysqli_commit($conn);

        // Prepare response
        $response = [
            'success' => true,
            'message' => "Successfully deleted {$deleted_count} item(s)",
            'deleted_count' => $deleted_count
        ];

        // Add warnings if some items failed
        if (!empty($failed_items)) {
            $response['warnings'] = $failed_items;
            $response['message'] .= ". Some items could not be deleted: " . implode(", ", $failed_items);
        }

        echo json_encode($response);

    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'details' => [
            'file' => __FILE__,
            'line' => $e->getLine()
        ]
    ]);
}

// Close database connection
mysqli_close($conn);
?>
