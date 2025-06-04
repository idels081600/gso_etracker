<?php
require_once 'db_asset.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $plate_no = mysqli_real_escape_string($conn, $_POST['plate_no']);
    $car_model = mysqli_real_escape_string($conn, $_POST['car_model'] ?? '');
    $no_dispatch = !empty($_POST['no_dispatch']) ? intval($_POST['no_dispatch']) : 0;
    $old_mileage = !empty($_POST['old_mileage']) ? intval($_POST['old_mileage']) : 0;
    $latest_mileage = !empty($_POST['latest_mileage']) ? intval($_POST['latest_mileage']) : 0;
    $no_of_repairs = !empty($_POST['no_of_repairs']) ? intval($_POST['no_of_repairs']) : 0;
    $latest_repair_date = !empty($_POST['latest_repair_date']) ? $_POST['latest_repair_date'] : NULL;
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $date_procured = !empty($_POST['date_procured']) ? $_POST['date_procured'] : NULL;
    
    // Check if plate number already exists
    $check_sql = "SELECT plate_no FROM vehicle_records WHERE plate_no = '$plate_no'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $response = [
            'status' => 'error',
            'message' => 'Vehicle with this plate number already exists!'
        ];
    } else {
        // Insert new vehicle
        $sql = "INSERT INTO vehicle_records (
                    plate_no, car_model, no_dispatch, old_mileage, 
                    latest_mileage, no_of_repairs, new_repair_date, 
                    status, date_procured
                ) VALUES (
                    '$plate_no', '$car_model', $no_dispatch, $old_mileage,
                    $latest_mileage, $no_of_repairs, 
                    " . ($latest_repair_date ? "'$latest_repair_date'" : "NULL") . ",
                    '$status', 
                    " . ($date_procured ? "'$date_procured'" : "NULL") . "
                )";
        
        if (mysqli_query($conn, $sql)) {
            $response = [
                'status' => 'success',
                'message' => 'Vehicle added successfully!'
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Error: ' . mysqli_error($conn)
            ];
        }
    }
    
    mysqli_close($conn);
    
    if ($response['status'] === 'success') {
        header("Location: motorpool_admin.php?success=1&message=" . urlencode($response['message']));
    } else {
        header("Location: motorpool_admin.php?error=1&message=" . urlencode($response['message']));
    }
    exit;
}

header("Location: motorpool_admin.php");
exit;
?>
