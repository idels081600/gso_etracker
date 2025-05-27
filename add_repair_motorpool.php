<?php
// Include database connection
require_once 'db_asset.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize inputs
    $vehicle_id = mysqli_real_escape_string($conn, $_POST['vehicle_id']);

    // Extract plate_no from vehicle_id
    $plate_no = trim($vehicle_id);

    // For car_model, we need to query the database to get it based on the plate_no
    $car_model = '';
    $car_model_query = "SELECT car_model FROM vehicle_records WHERE plate_no = '$plate_no' LIMIT 1";
    $car_model_result = mysqli_query($conn, $car_model_query);

    if ($car_model_result && mysqli_num_rows($car_model_result) > 0) {
        $car_model_row = mysqli_fetch_assoc($car_model_result);
        $car_model = $car_model_row['car_model'];
    }

    $repair_date = mysqli_real_escape_string($conn, $_POST['repair_date']);
    $repair_type = mysqli_real_escape_string($conn, $_POST['repair_type']);
    $mileage = intval($_POST['mileage']);
    $parts_replaced = mysqli_real_escape_string($conn, $_POST['parts_replaced'] ?? '');
    $cost = floatval($_POST['cost']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    $office = mysqli_real_escape_string($conn, $_POST['office'] ?? ''); // Added office field

    // Set status to 'Pending'
    $status = 'Pending';

    // Create SQL query with separate plate_no and car_model columns and status field
    $sql = "INSERT INTO motorpool_repair (
                plate_no,
                car_model,
                repair_date,
                repair_type,
                mileage,
                parts_replaced,
                cost,
                remarks,
                status,
                office
            ) VALUES (
                '$plate_no',
                '$car_model',
                '$repair_date',
                '$repair_type',
                $mileage,
                '$parts_replaced',
                $cost,
                '$notes',
                '$status',
                '$office'
            )";
    // Execute query
    if (mysqli_query($conn, $sql)) {
        // Update the vehicle's repair count and latest repair date
        $update_vehicle = "UPDATE vehicle_records SET
                                  no_of_repairs = no_of_repairs + 1,
                                  new_repair_date = '$repair_date',
                                  latest_mileage = $mileage
                           WHERE plate_no = '$plate_no'";

        mysqli_query($conn, $update_vehicle);

        // Redirect with success parameter only, no message
        header("Location: motorpool_admin.php?success=1");
        exit();
    } else {
        // Redirect with error parameter only, no message
        header("Location: motorpool_admin.php?error=1");
        exit();
    }
} else {
    // Not a POST request, redirect to main page
    header("Location: motorpool_admin.php");
    exit();
}

// Close connection
mysqli_close($conn);
