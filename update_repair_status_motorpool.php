<?php
// Include database connection
require_once 'db_asset.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request is POST and has required parameters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair_id']) && isset($_POST['status'])) {
    // Sanitize inputs
    $repair_id = intval($_POST['repair_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Validate status value
    $valid_statuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status value',
            'currentStatus' => getCurrentStatus($conn, $repair_id)
        ]);
        exit;
    }
    
    // Update the status in the database
    $sql = "UPDATE motorpool_repair SET status = '$status' WHERE id = $repair_id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => mysqli_error($conn),
            'currentStatus' => getCurrentStatus($conn, $repair_id)
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
}

// Function to get current status
function getCurrentStatus($conn, $repair_id) {
    $sql = "SELECT status FROM motorpool_repair WHERE id = $repair_id LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['status'];
    }
    
    return 'Pending'; // Default value
}

// Close connection
mysqli_close($conn);
?>
