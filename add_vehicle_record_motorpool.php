<?php
// Include database connection
require_once 'db_asset.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data and sanitize inputs
    $original_plate_no = mysqli_real_escape_string($conn, $_POST['original_plate_no']);
    $plate_no = mysqli_real_escape_string($conn, $_POST['plate_no']);
    $car_model = mysqli_real_escape_string($conn, $_POST['car_model'] ?? '');
    $no_dispatch = !empty($_POST['no_dispatch']) ? intval($_POST['no_dispatch']) : 0;
    $old_mileage = !empty($_POST['old_mileage']) ? intval($_POST['old_mileage']) : 0;
    $latest_mileage = !empty($_POST['latest_mileage']) ? intval($_POST['latest_mileage']) : 0;
    $no_of_repairs = !empty($_POST['no_of_repairs']) ? intval($_POST['no_of_repairs']) : 0;
    $latest_repair_date = !empty($_POST['latest_repair_date']) ? $_POST['latest_repair_date'] : NULL;
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $date_procured = !empty($_POST['date_procured']) ? $_POST['date_procured'] : NULL;
    
    // Create SQL query for update
    $sql = "UPDATE vehicle_records SET 
            plate_no = '$plate_no',
            car_model = '$car_model',
            no_dispatch = $no_dispatch,
            old_mileage = $old_mileage,
            latest_mileage = $latest_mileage,
            no_of_repairs = $no_of_repairs,
            new_repair_date = " . ($latest_repair_date ? "'$latest_repair_date'" : "NULL") . ",
            status = '$status',
            date_procured = " . ($date_procured ? "'$date_procured'" : "NULL") . "
            WHERE plate_no = '$original_plate_no'";
    
    // Execute query
    if (mysqli_query($conn, $sql)) {
        // Success
        $response = [
            'status' => 'success',
            'message' => 'Vehicle updated successfully!'
        ];
    } else {
        // Error
        $response = [
            'status' => 'error',
            'message' => 'Error: ' . mysqli_error($conn)
        ];
    }
    
    // Close connection
    mysqli_close($conn);
    
 
    if ($response['status'] === 'success') {
        header("Location: motorpool_admin.php?success=1&message=" . urlencode($response['message']));
    } else {
        header("Location: motorpool_admin.php?error=1&message=" . urlencode($response['message']));
    }
    exit;
}

// If not a POST request, redirect to the main page
header("Location: motorpool_admin.php");
exit;
