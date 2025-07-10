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
    $car_model_query = "SELECT car_model FROM vehicle_records WHERE plate_no = ? LIMIT 1";
    $car_model_stmt = mysqli_prepare($conn, $car_model_query);
    mysqli_stmt_bind_param($car_model_stmt, "s", $plate_no);
    mysqli_stmt_execute($car_model_stmt);
    $car_model_result = mysqli_stmt_get_result($car_model_stmt);
    
    if ($car_model_result && mysqli_num_rows($car_model_result) > 0) {
        $car_model_row = mysqli_fetch_assoc($car_model_result);
        $car_model = $car_model_row['car_model'];
    }
    mysqli_stmt_close($car_model_stmt);
    
    // Sanitize other inputs
    $repair_date = mysqli_real_escape_string($conn, $_POST['repair_date']);
    if (isset($_POST['repair_type']) && is_array($_POST['repair_type'])) {
        $repair_types = array_map(function($type) use ($conn) {
            return mysqli_real_escape_string($conn, $type);
        }, $_POST['repair_type']);
        $repair_type = implode(', ', $repair_types);
    } else {
        $repair_type = '';
    }
    $mileage = intval($_POST['mileage']);
    
    // Handle multi-line parts_replaced field
    $parts_replaced = isset($_POST['parts_replaced']) ? trim($_POST['parts_replaced']) : '';
    $parts_replaced = mysqli_real_escape_string($conn, $parts_replaced);
    
    $cost = floatval($_POST['cost']);
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, trim($_POST['notes'])) : '';
    $office = isset($_POST['office']) ? mysqli_real_escape_string($conn, $_POST['office']) : '';
    
    // Set status to 'Pending'
    $status = 'Pending';
    
    // Use prepared statement for better security
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    // FIXED: Changed type string to have 10 characters for 10 parameters
    mysqli_stmt_bind_param($stmt, "ssssisdsss", 
        $plate_no,       // s - string
        $car_model,      // s - string  
        $repair_date,    // s - string
        $repair_type,    // s - string
        $mileage,        // i - integer
        $parts_replaced, // s - string
        $cost,           // d - double/float
        $notes,          // s - string
        $status,         // s - string
        $office          // s - string
    );
    
    // Execute query
    if (mysqli_stmt_execute($stmt)) {
        // Update the vehicle's repair count and latest repair date
        $update_vehicle = "UPDATE vehicle_records SET 
                              no_of_repairs = no_of_repairs + 1,
                              new_repair_date = ?,
                              latest_mileage = ?
                           WHERE plate_no = ?";
        $update_stmt = mysqli_prepare($conn, $update_vehicle);
        mysqli_stmt_bind_param($update_stmt, "sis", $repair_date, $mileage, $plate_no);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        // Redirect with success parameter
        header("Location: motorpool_admin.php?success=1");
        exit();
    } else {
        // Redirect with error parameter
        header("Location: motorpool_admin.php?error=1");
        exit();
    }
    
    mysqli_stmt_close($stmt);
} else {
    // Not a POST request, redirect to main page
    header("Location: motorpool_admin.php");
    exit();
}

// Close connection
mysqli_close($conn);
?>
