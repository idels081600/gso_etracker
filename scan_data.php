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
        if ($time_diff < 5) {
            // If less than 10 seconds, output a message indicating that the user needs to wait before scanning again
            echo 'Wait for 5 seconds';
            exit; // Exit the script to prevent further processing
        }
    }

    $scannedData = mysqli_real_escape_string($conn, $_POST['scannedData']);

    // Check the current time
    date_default_timezone_set('Asia/Manila'); // Set timezone to Philippine time
    $currentTime = date("H:i");

    // Query to check if the scanned data exists in the request table
    $query = "SELECT * FROM Transportation WHERE Plate_no = '$scannedData' AND Status IN ('Stand By', 'Departed') ORDER BY FIELD(Status, 'Departed', 'Stand By'), id DESC";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $status = $row['Status'];

            if ($status === 'Departed') {
                // Always prioritize updating the 'Departed' status first
                $update_query = "UPDATE Transportation 
                                 SET Arrival = NOW(), 
                                     Status = 'Arrived',
                                     Status1 = 'Arrived'
                                 WHERE Plate_no = '$scannedData' 
                                 AND Status = 'Departed' 
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
                        echo 'Arrived';
                    } else {
                        echo 'update_vehicle_error: ' . mysqli_error($conn);
                    }
                } else {
                    echo 'update_error: ' . mysqli_error($conn);
                }
                exit; // Stop after processing the Departed status
            }
        }

        // If no 'Departed' entry was found or updated, handle the 'Stand By' status next
        if ($status === 'Stand By') {
            $update_query = "UPDATE Transportation 
                             SET Departure = NOW(), 
                                 Status = 'Departed',
                                 Status1 = 'Departed'
                             WHERE Plate_no = '$scannedData' 
                             AND Status = 'Stand By' 
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
                    echo 'exists';
                } else {
                    echo 'update_vehicle_error: ' . mysqli_error($conn);
                }
            } else {
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
