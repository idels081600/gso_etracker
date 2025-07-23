<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

// Set content type to JSON
header('Content-Type: application/json');

// Database connection
try {
    include 'logi_db.php';
    
    // Check if connection is successful
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error: ' . $e->getMessage()
    ]);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid JSON: ' . json_last_error_msg(),
        'raw_input' => substr($rawInput, 0, 200) // First 200 chars for debugging
    ]);
    exit;
}

// Validate input
if (!isset($input['action']) || $input['action'] !== 'save_common_items') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

if (!isset($input['items']) || !is_array($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'No items provided']);
    exit;
}

try {
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    // Check if table exists
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'common_items'");
    if (mysqli_num_rows($tableCheck) == 0) {
        throw new Exception("Table 'common_item' does not exist");
    }
    
    // Clear existing common items (optional - remove if you want to append instead of replace)
    $clearQuery = "DELETE FROM common_items";
    if (!mysqli_query($conn, $clearQuery)) {
        throw new Exception("Failed to clear existing items: " . mysqli_error($conn));
    }
    
    // Prepare insert statement
    $insertQuery = "INSERT INTO common_items(item_no, item_name, quantity, status, unit) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insertQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . mysqli_error($conn));
    }
    
    $successCount = 0;
    $errors = [];
    
    // Insert each item
    foreach ($input['items'] as $item) {
        // Validate required fields
        if (empty($item['item_no']) || empty($item['item_name'])) {
            $errors[] = "Missing required fields for item: " . ($item['item_name'] ?? 'Unknown');
            continue;
        }
        
        // Use available_stock as quantity if quantity is not set
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : intval($item['available_stock']);
        
        // Ensure quantity is positive
        if ($quantity <= 0) {
            $quantity = 1; // Default to 1 if invalid
        }
        
        // Default status to 'Active' if not provided
        $status = isset($item['status']) ? $item['status'] : 'Active';
        
        // Get unit from item data
        $unit = isset($item['unit']) ? $item['unit'] : '';
        
        // Bind parameters
        mysqli_stmt_bind_param($stmt, "ssiss", 
            $item['item_no'],
            $item['item_name'],
            $quantity,
            $status,
            $unit
        );
        
        // Execute the statement
        if (mysqli_stmt_execute($stmt)) {
            $successCount++;
        } else {
            $errors[] = "Failed to save item {$item['item_name']}: " . mysqli_stmt_error($stmt);
        }
    }
    
    // Close statement
    mysqli_stmt_close($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => "Successfully saved $successCount common use item(s)",
        'saved_count' => $successCount
    ];
    
    // Add errors to response if any
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    // Close connection
    mysqli_close($conn);
}
?> 