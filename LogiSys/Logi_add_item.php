<?php
// Prevent any HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in HTML format
ini_set('log_errors', 1); // Log errors instead

// Set content type to JSON
header('Content-Type: application/json');

require_once 'logi_db.php'; // Make sure this file exists and has no errors

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get form data and sanitize
        $item_no = mysqli_real_escape_string($conn, $_POST['itemNo']);
        $item_name = mysqli_real_escape_string($conn, $_POST['itemName']);
        $rack_no = (int)$_POST['rackNo'];
        $current_balance = (int)$_POST['balance'];
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        // Determine status based on current balance
        $status = 'Available';
        if ($current_balance == 0) {
            $status = 'Out of Stock';
        } elseif ($current_balance <= 10) {
            $status = 'Low Stock';
        }
        
        // Check if item_no already exists
        $check_query = "SELECT id FROM inventory_items WHERE item_no = ?";
        $check_stmt = $conn->prepare($check_query);
        
        if (!$check_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $check_stmt->bind_param("s", $item_no);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Item number already exists. Please use a different item number.'
            ]);
            exit;
        }
        
        // Prepare and bind
        $stmt = $conn->prepare("INSERT INTO inventory_items (item_no, item_name, rack_no, unit, current_balance, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssiisis", $item_no, $item_name, $rack_no, $unit, $current_balance, $description, $status);
        
        // Execute the statement
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Item added successfully!',
                'item_id' => $conn->insert_id
            ]);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
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
        'message' => 'Invalid request method'
    ]);
}

$conn->close();
?>
