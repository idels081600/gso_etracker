<?php
// Include database connection
require_once 'db_asset.php';

// Set content type for JSON response
header('Content-Type: application/json');

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['plate_no'])) {
    // Get the plate number
    $plate_no = mysqli_real_escape_string($conn, $_POST['plate_no']);
    
    // Create SQL query for deletion
    $sql = "DELETE FROM vehicle_records WHERE plate_no = '$plate_no'";
    
    // Execute query
    if (mysqli_query($conn, $sql)) {
        // Check if any rows were affected
        $affected_rows = mysqli_affected_rows($conn);
        
        if ($affected_rows > 0) {
            // Success with rows deleted
            $response = [
                'status' => 'success',
                'message' => 'Vehicle deleted successfully!',
                'affected_rows' => $affected_rows
            ];
        } else {
            // Query executed but no rows were deleted
            $response = [
                'status' => 'warning',
                'message' => 'No vehicle was deleted. The record might not exist.',
                'affected_rows' => 0
            ];
        }
    } else {
        // Error in query execution
        $response = [
            'status' => 'error',
            'message' => 'Error: ' . mysqli_error($conn)
        ];
    }
    
    // Close connection
    mysqli_close($conn);
    
    // Return JSON response
    echo json_encode($response);
    exit;
} else {
    // Invalid request
    $response = [
        'status' => 'error',
        'message' => 'Invalid request. Plate number is required.'
    ];
    echo json_encode($response);
    exit;
}
?>
