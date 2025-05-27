<?php
require_once 'db_asset.php';

header('Content-Type: application/json');

if ($_POST['edit_repair_id']) {
    $repair_id = mysqli_real_escape_string($conn, $_POST['edit_repair_id']);
    $plate_no = mysqli_real_escape_string($conn, $_POST['edit_vehicle_id']);
    $repair_date = mysqli_real_escape_string($conn, $_POST['edit_repair_date']);
    $repair_type = mysqli_real_escape_string($conn, $_POST['edit_repair_type']);
    $mileage = mysqli_real_escape_string($conn, $_POST['edit_mileage']);
    $parts_replaced = mysqli_real_escape_string($conn, $_POST['edit_parts_replaced']);
    $cost = mysqli_real_escape_string($conn, $_POST['edit_cost']);
    $office = mysqli_real_escape_string($conn, $_POST['edit_office']);
    $remarks = mysqli_real_escape_string($conn, $_POST['edit_notes']);
    $status = mysqli_real_escape_string($conn, $_POST['edit_status']);
    
    // Get car_model from vehicles table using plate_no
    $vehicle_query = "SELECT car_model FROM vehicle_records WHERE plate_no = '$plate_no'";
    $vehicle_result = mysqli_query($conn, $vehicle_query);
    
    if ($vehicle_result && mysqli_num_rows($vehicle_result) > 0) {
        $vehicle_data = mysqli_fetch_assoc($vehicle_result);
        $car_model = mysqli_real_escape_string($conn, $vehicle_data['car_model']);
    } else {
        $car_model = ''; // Default to empty if vehicle not found
    }
    
    $query = "UPDATE motorpool_repair SET 
              plate_no = '$plate_no',
              car_model = '$car_model',
              repair_date = '$repair_date',
              repair_type = '$repair_type',
              mileage = '$mileage',
              parts_replaced = '$parts_replaced',
              cost = '$cost',
              office = '$office',
              remarks = '$remarks',
              status = '$status'
              WHERE id = '$repair_id'";
    
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Repair record updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating repair record: ' . mysqli_error($conn)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid repair data']);
}
?>
