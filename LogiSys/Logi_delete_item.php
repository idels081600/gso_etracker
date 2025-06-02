<?php
// Prevent any HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

require_once 'logi_db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get and decode the item IDs
        $item_ids_json = isset($_POST['item_ids']) ? $_POST['item_ids'] : '';
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        // Debug: Log the received data
        error_log("Received item_ids: " . $item_ids_json);
        error_log("Action: " . $action);

        // Validate input
        if (empty($item_ids_json)) {
            echo json_encode([
                'success' => false,
                'message' => 'No items selected for deletion.'
            ]);
            exit;
        }

        // Decode JSON array
        $item_ids = json_decode($item_ids_json, true);

        if (!is_array($item_ids) || empty($item_ids)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid item selection.'
            ]);
            exit;
        }

        // Sanitize the item IDs (assuming they are item numbers, not database IDs)
        $safe_item_ids = array_map(function ($id) use ($conn) {
            return mysqli_real_escape_string($conn, $id);
        }, $item_ids);

        // Create placeholders for prepared statement
        $placeholders = str_repeat('?,', count($safe_item_ids) - 1) . '?';

        // First, get the items that will be deleted for confirmation
        $check_query = "SELECT id, item_no, item_name FROM inventory_items WHERE item_no IN ($placeholders)";
        $check_stmt = $conn->prepare($check_query);

        if (!$check_stmt) {
            throw new Exception("Prepare check failed: " . $conn->error);
        }

        // Create type string for bind_param (all strings)
        $types = str_repeat('s', count($safe_item_ids));
        $check_stmt->bind_param($types, ...$safe_item_ids);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        $found_items = [];
        while ($row = $result->fetch_assoc()) {
            $found_items[] = $row;
        }

        if (empty($found_items)) {
            echo json_encode([
                'success' => false,
                'message' => 'No items found in database.'
            ]);
            exit;
        }

        error_log("Found items: " . json_encode($found_items));

        // Delete the items
        $delete_query = "DELETE FROM inventory_items WHERE item_no IN ($placeholders)";
        $delete_stmt = $conn->prepare($delete_query);

        if (!$delete_stmt) {
            throw new Exception("Prepare delete failed: " . $conn->error);
        }

        $delete_stmt->bind_param($types, ...$safe_item_ids);

        if ($delete_stmt->execute()) {
            $deleted_count = $delete_stmt->affected_rows;

            if ($deleted_count > 0) {
                error_log("Items deleted successfully. Count: " . $deleted_count);
                echo json_encode([
                    'success' => true,
                    'message' => 'Items deleted successfully!',
                    'deleted_count' => $deleted_count,
                    'deleted_items' => $found_items
                ]);
            } else {
                error_log("No rows affected");
                echo json_encode([
                    'success' => false,
                    'message' => 'No items were deleted.'
                ]);
            }
        } else {
            throw new Exception("Execute delete failed: " . $delete_stmt->error);
        }

        $delete_stmt->close();
        $check_stmt->close();
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST requests are allowed.'
    ]);
}

$conn->close();
