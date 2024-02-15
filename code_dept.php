<?php
session_start();
require_once 'dbh.php'; // Include your database connection file
date_default_timezone_set('Asia/Manila');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Escape the scanned data to prevent SQL injection
    $scannedData = mysqli_real_escape_string($conn, $_POST['scannedData']);

    // Query to check if the scanned data exists in the request table and has status 'Approved'
    $query = "SELECT * FROM request WHERE name = '$scannedData' AND Status = 'Approved'";
    $result = mysqli_query($conn, $query);

    // Check if the query executed successfully and there are rows returned
    if ($result && mysqli_num_rows($result) > 0) {
        // Scanned data exists in the database and has status 'Approved'
        // Update the Status column to 'Done' and set time_returned to current time
        $update_query = "UPDATE request SET `time_returned` = DATE_FORMAT(CURRENT_TIMESTAMP, '%H:%i'), Status = 'Done', `status1` = 'Present'";

        // Query to get the estimated time return
        $est_query = "SELECT esttime FROM request WHERE name = '$scannedData' ORDER BY id DESC LIMIT 1";
        $est_result = mysqli_query($conn, $est_query);
        
        // Check if the estimated time query executed successfully and there are rows returned
        if ($est_result && mysqli_num_rows($est_result) > 0) {
            // Fetch the estimated time value
            $row = mysqli_fetch_assoc($est_result);
            $estimatedTime = new DateTime($row['esttime']);
            $actualTime = new DateTime();
        
            // Check if actual time is less than estimated time
            if ($actualTime < $estimatedTime) {
                // If actual time is less than estimated time, update remarks for being early
                $interval = $estimatedTime->diff($actualTime);
                $hoursDifference = $interval->format('%h');
                $minutesDifference = $interval->format('%i');
                $timeDifferenceString = $hoursDifference . ' hours ' . $minutesDifference . ' minutes';
                $update_query .= ", `remarks` = 'Arrived $timeDifferenceString early'";
            } else {
                // If actual time is greater than estimated time, update remarks for being late
                $interval = $actualTime->diff($estimatedTime);
                $hoursDifference = $interval->format('%h');
                $minutesDifference = $interval->format('%i');
                $timeDifferenceString = $hoursDifference . ' hours ' . $minutesDifference . ' minutes';
                $update_query .= ", `remarks` = 'Arrived $timeDifferenceString late'";
            }
        } else {
            // Error fetching estimated time
            echo 'est_error';
            exit; // Exit script to prevent further execution
        }

        // Complete the update query
        $update_query .= " WHERE name = '$scannedData' ORDER BY id DESC LIMIT 1";
        $update_result = mysqli_query($conn, $update_query);

        // Check if the update query executed successfully
        if ($update_result) {
            // Status and remarks updated successfully
            echo 'exists';
        } else {
            // Error updating status
            echo 'update_error';
        }
    } else {
        // Scanned data does not exist in the database or does not have status 'Approved'
        echo 'not_exists';
    }
} else {
    // Invalid request method
    echo 'invalid_request';
}
?>
