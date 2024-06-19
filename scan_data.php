<?php
session_start();
require_once 'db_asset.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the last scanned time is stored in the session
    if (isset($_SESSION['last_scan_time'])) {
        $last_scan_time = $_SESSION['last_scan_time'];
        $current_time = time(); // Get the current timestamp

        // Calculate the difference in seconds between the current time and the last scanned time
        $time_diff = $current_time - $last_scan_time;

        // Check if the time difference is less than 10 seconds
        if ($time_diff < 10) {
            // If less than 10 seconds, output a message indicating that the user needs to wait before scanning again
            echo 'scan_limit_exceeded';
            exit; // Exit the script to prevent further processing
        }
    }

    $scannedData = mysqli_real_escape_string($conn, $_POST['scannedData']);

    // Check the current time
    date_default_timezone_set('Asia/Manila'); // Set timezone to Philippine time
    $currentTime = date("H:i");

    // Query to check if the scanned data exists in the request table
    $query = "SELECT * FROM Transportation WHERE Plate_no = '$scannedData' AND Status IN ('Stand By', 'Departed') ORDER BY id DESC";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $status = $row['Status'];

        if ($status === 'Stand By') {
            // Scanned data exists in the database and status is 'Stand By'
            // Update the Status column to 'Departed' in the Transportation table
            $update_query = "UPDATE Transportation 
                             SET Departure = NOW(), 
                                 Status = 'Departed',
                                 Status1 = 'Departed'
                             WHERE Plate_no = '$scannedData' 
                             ORDER BY id DESC 
                             LIMIT 1";
            $update_result = mysqli_query($conn, $update_query);

            if ($update_result) {
                // Update the Status column in the Vehicle table
                $update_vehicle_query = "UPDATE Vehicle 
                                         SET Status = 'Departed' 
                                         WHERE Plate_no = '$scannedData'";
                $update_vehicle_result = mysqli_query($conn, $update_vehicle_query);

                if ($update_vehicle_result) {
                    // Store the current time in the session after successfully updating the status
                    $_SESSION['last_scan_time'] = time();
                    // Status updated successfully in both tables
                    echo 'exists';
                } else {
                    // Error updating status in Vehicle table
                    echo 'update_vehicle_error: ' . mysqli_error($conn);
                }
            } else {
                // Error updating status in Transportation table
                echo 'update_error: ' . mysqli_error($conn);
            }
        } elseif ($status === 'Departed') {
            // echo 'departed';
            $update_query = "UPDATE Transportation 
            SET Arrival = NOW(), 
                Status = 'Arrived',
                Status1 = 'Arrived'
            WHERE Plate_no = '$scannedData' 
            ORDER BY id DESC 
            LIMIT 1";
            $update_result = mysqli_query($conn, $update_query);
            if ($update_result) {
                // Update the Status column in the Vehicle table
                $update_vehicle_query = "UPDATE Vehicle 
                                         SET Status = 'Stand By' 
                                         WHERE Plate_no = '$scannedData'";
                $update_vehicle_result = mysqli_query($conn, $update_vehicle_query);

                if ($update_vehicle_result) {
                    // Store the current time in the session after successfully updating the status
                    $_SESSION['last_scan_time'] = time();
                    // Status updated successfully in both tables
                    echo 'Arrived';
                } else {
                    // Error updating status in Vehicle table
                    echo 'update_vehicle_error: ' . mysqli_error($conn);
                }
            } else {
                // Error updating status in Transportation table
                echo 'update_error: ' . mysqli_error($conn);
            }
        }
    } else {
        // Scanned data does not exist in the database
        echo 'not_exists';
    }
} else {
    // Invalid request method
    echo 'invalid_request';
}
