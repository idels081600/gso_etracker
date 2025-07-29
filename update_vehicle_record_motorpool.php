<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db_asset.php';

// Set content type for JSON response when using AJAX
header('Content-Type: application/json');

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Log received data
    error_log("POST data received: " . print_r($_POST, true));
    
    // Get the plate number (used as identifier)
    $plate_no = mysqli_real_escape_string($conn, $_POST['plate_no']);
    $id = mysqli_real_escape_string($conn, $_POST['original_plate_no'] ?? '');
    // Get the updated values
    $car_model = mysqli_real_escape_string($conn, $_POST['car_model'] ?? '');
    $no_dispatch = !empty($_POST['no_dispatch']) ? intval($_POST['no_dispatch']) : 0;
    $old_mileage = !empty($_POST['old_mileage']) ? intval($_POST['old_mileage']) : 0;
    $latest_mileage = !empty($_POST['latest_mileage']) ? intval($_POST['latest_mileage']) : 0;
    $no_of_repairs = !empty($_POST['no_of_repairs']) ? intval($_POST['no_of_repairs']) : 0;
    $latest_repair_date = !empty($_POST['latest_repair_date']) ? $_POST['latest_repair_date'] : NULL;
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $date_procured = !empty($_POST['date_procured']) ? $_POST['date_procured'] : NULL;
    $office = mysqli_real_escape_string($conn, $_POST['update_office'] ?? '');
    
    // Create SQL query for update
    $sql = "UPDATE vehicle_records SET
            plate_no = '$plate_no', 
            car_model = '$car_model',
            office = '$office',
            no_dispatch = $no_dispatch,
            old_mileage = $old_mileage,
            latest_mileage = $latest_mileage,
            no_of_repairs = $no_of_repairs,
            new_repair_date = " . ($latest_repair_date ? "'$latest_repair_date'" : "NULL") . ",
            status = '$status',
            date_procured = " . ($date_procured ? "'$date_procured'" : "NULL") . "
            WHERE id = '$id'";
    
    // Log the SQL query
    error_log("SQL Query: " . $sql);
    
    // Execute query
    if (mysqli_query($conn, $sql)) {
        // Check if any rows were affected
        $affected_rows = mysqli_affected_rows($conn);
        error_log("Affected rows: " . $affected_rows);
        
        if ($affected_rows > 0) {
            // Success with rows updated
            $response = [
                'status' => 'success',
                'message' => 'Vehicle updated successfully!',
                'affected_rows' => $affected_rows
            ];
        } else {
            // Query executed but no rows were updated
            $response = [
                'status' => 'warning',
                'message' => 'No changes were made. The record might not exist or no values were changed.',
                'affected_rows' => 0
            ];
        }
    } else {
        // Error in query execution
        $response = [
            'status' => 'error',
            'message' => 'Error: ' . mysqli_error($conn),
            'query' => $sql
        ];
    }
    
    // Close connection
    mysqli_close($conn);
    
    // Return JSON response
    echo json_encode($response);
    exit;
} else {
    // Not a POST request
    $response = [
        'status' => 'error',
        'message' => 'Invalid request method. Only POST is allowed.'
    ];
    echo json_encode($response);
    exit;
}
?>
