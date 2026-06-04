<?php
// Prevent any HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in HTML format
ini_set('log_errors', 1); // Log errors instead

// Set content type to JSON
header('Content-Type: application/json');

require_once 'logi_db.php'; // Include the database connection file

function hasInventoryColumn($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items' AND COLUMN_NAME = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $column);
    $stmt->execute();
    $result = $stmt->get_result();
    return ((int)($result->fetch_assoc()['count'] ?? 0)) > 0;
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get form data and sanitize
        $item_id = mysqli_real_escape_string($conn, $_POST['itemNo']); // This is the item_no
        $item_name = mysqli_real_escape_string($conn, $_POST['itemName']);
        $rack_no = (int)$_POST['rackNo'];
        $unit = mysqli_real_escape_string($conn, $_POST['unit']); // Added unit field
        $current_balance = (int)$_POST['balance'];
        $low_stock_threshold = max(1, (int)($_POST['lowStockThreshold'] ?? 10));
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $has_low_stock_threshold = hasInventoryColumn($conn, 'low_stock_threshold');

        // Validate required fields
        if (empty($item_id) || empty($item_name) || empty($unit) || empty($status)) {
            echo json_encode([
                'success' => false,
                'message' => 'All required fields must be filled.'
            ]);
            exit;
        }

        // Auto-determine status based on current balance if not manually set
        if ($current_balance == 0 && $status !== 'Discontinued') {
            $status = 'Out of Stock';
        } elseif ($current_balance <= $low_stock_threshold && $current_balance > 0 && $status !== 'Discontinued') {
            $status = 'Low Stock';
        } elseif ($current_balance > $low_stock_threshold && $status !== 'Discontinued') {
            $status = 'Available';
        }

        // Check if the item exists
        $check_query = "SELECT id FROM inventory_items WHERE item_no = ?";
        $check_stmt = $conn->prepare($check_query);

        if (!$check_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $check_stmt->bind_param("s", $item_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Item not found in database.'
            ]);
            exit;
        }

        // Get the actual database ID
        $row = $result->fetch_assoc();
        $db_id = $row['id'];

        // Prepare update statement (with unit field)
        if ($has_low_stock_threshold) {
            $update_query = "UPDATE inventory_items SET 
                        item_name = ?, 
                        rack_no = ?, 
                        unit = ?, 
                        current_balance = ?, 
                        low_stock_threshold = ?,
                        status = ?, 
                        description = ?, 
                        updated_at = NOW() 
                        WHERE id = ?";
        } else {
            $update_query = "UPDATE inventory_items SET 
                        item_name = ?, 
                        rack_no = ?, 
                        unit = ?, 
                        current_balance = ?, 
                        status = ?, 
                        description = ?, 
                        updated_at = NOW() 
                        WHERE id = ?";
        }

        $update_stmt = $conn->prepare($update_query);

        if (!$update_stmt) {
            throw new Exception("Prepare update failed: " . $conn->error);
        }

        // Updated bind_param (with unit parameter)
        if ($has_low_stock_threshold) {
            $update_stmt->bind_param("sisiissi", $item_name, $rack_no, $unit, $current_balance, $low_stock_threshold, $status, $description, $db_id);
        } else {
            $update_stmt->bind_param("sissisi", $item_name, $rack_no, $unit, $current_balance, $status, $description, $db_id);
        }

        // Execute the update
        if ($update_stmt->execute()) {
            // Check if any rows were actually updated
            if ($update_stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Item updated successfully!',
                    'item_no' => $item_id,
                    'updated_fields' => [
                        'item_name' => $item_name,
                        'rack_no' => $rack_no,
                        'unit' => $unit, // Added unit to response
                        'current_balance' => $current_balance,
                        'low_stock_threshold' => $low_stock_threshold,
                        'status' => $status,
                        'description' => $description
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'No changes were made to the item.',
                    'item_no' => $item_id
                ]);
            }
        } else {
            throw new Exception("Execute update failed: " . $update_stmt->error);
        }

        $update_stmt->close();
        $check_stmt->close();
    } catch (Exception $e) {
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
