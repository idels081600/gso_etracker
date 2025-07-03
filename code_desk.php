<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'dbh.php';

    // Sanitize input
    $scannedData = mysqli_real_escape_string($conn, $_POST['scannedData']);


    date_default_timezone_set('Asia/Manila');

    // STEP 1: Check if Status is 'Partially Approved'
    $query_in = "SELECT * FROM request WHERE name = '$scannedData' AND Status = 'Partially Approved' ORDER BY id DESC LIMIT 1";
    $result_in = mysqli_query($conn, $query_in);

    if ($result_in && mysqli_num_rows($result_in) > 0) {
        $row_in = mysqli_fetch_assoc($result_in);

        $update_query = "UPDATE request 
                         SET `timedept` = NOW(), 
                             Status = 'Approved', 
                             `status1` = 'Pass-Slip',  
                             `ImageName` = 'Check-Approved.png' 
                         WHERE id = " . intval($row_in['id']);

        if (mysqli_query($conn, $update_query)) {
            echo json_encode([
                'status' => 'exists',
                'name' => $row_in['name']
            ]);
        } else {
            echo json_encode(['status' => 'update_error']);
        }
        exit;
    }

    // STEP 2: Check if Status is 'Approved' and it's a return scan
    $query_out = "SELECT * FROM request WHERE name = '$scannedData' AND Status = 'Approved' ORDER BY id DESC LIMIT 1";
    $result_out = mysqli_query($conn, $query_out);

    if ($result_out && mysqli_num_rows($result_out) > 0) {
        $row = mysqli_fetch_assoc($result_out);
        $estimatedTime = new DateTime($row['esttime']);
        $actualTime = new DateTime();

        if ($actualTime < $estimatedTime) {
            $interval = $estimatedTime->diff($actualTime);
            $hours = $interval->h;
            $minutes = $interval->i;
            $timeDifference = trim(($hours > 0 ? $hours . " hour" . ($hours > 1 ? "s " : " ") : "") . ($minutes > 0 ? $minutes . " minute" . ($minutes > 1 ? "s" : "") : ""));
            $remarks = "Arrived $timeDifference early";
        } else {
            $interval = $actualTime->diff($estimatedTime);
            $hours = $interval->h;
            $minutes = $interval->i;
            $timeDifference = trim(($hours > 0 ? $hours . " hour" . ($hours > 1 ? "s " : " ") : "") . ($minutes > 0 ? $minutes . " minute" . ($minutes > 1 ? "s" : "") : ""));
            $remarks = "Arrived $timeDifference late";
        }

        $update_query = "UPDATE request 
                         SET `time_returned` = DATE_FORMAT(CURRENT_TIMESTAMP, '%H:%i'), 
                             Status = 'Done', 
                             `status1` = 'Present', 
                             `remarks` = '$remarks' 
                         WHERE id = " . intval($row['id']);

        if (mysqli_query($conn, $update_query)) {
            echo json_encode([
                'status' => "Arrived $timeDifference " . ($actualTime < $estimatedTime ? "early" : "late"),
                'name' => $row['name']
            ]);
        } else {
            echo json_encode(['status' => 'update_error']);
        }
        exit;
    }

    // If not found
    echo json_encode(['status' => 'not_exists']);
} else {
    echo json_encode(['status' => 'invalid_request']);
}
