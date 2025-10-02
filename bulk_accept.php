<?php
session_start();
require_once 'dbh.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the received data
error_log("bulk_accept.php triggered. POST data: " . print_r($_POST, true));

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if selected_ids is set and not empty
    if (isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
        // Parse the JSON string to get the array of IDs
        $data_ids = json_decode($_POST['selected_ids'], true);

        // Log the decoded IDs
        error_log("Decoded IDs: " . print_r($data_ids, true));

        if (empty($data_ids) || !is_array($data_ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request data format']);
            exit;
        }

        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $confirmed_by = mysqli_real_escape_string($conn, $_POST['confirmed_by']);
        $esttime = mysqli_real_escape_string($conn, $_POST['esttime']);

        // Determine status1 based on the status
        $status1 = ($status === 'Declined') ? 'Declined' : 'Scan Qrcode';

        // Convert the $esttime to DATETIME format only if it's not empty
        if (!empty($esttime)) {
            $esttime = date('Y-m-d H:i:s', strtotime($esttime));
        } else {
            // Set to NULL or empty string for declined requests
            $esttime = null;
        }
        // Get the fixed time hours and minutes
        $fix_hours = intval($_POST['fix_hours'] ?? 0);
        $fix_minutes = intval($_POST['fix_minutes'] ?? 0);

        // Calculate total minutes
        $total_minutes = ($fix_hours * 60) + $fix_minutes;

        // Get current time and add the total minutes
        date_default_timezone_set('Asia/Manila'); // Ensure timezone is set
        $current_time = new DateTime();
        $current_time->modify("+$total_minutes minutes");
        $time_allotted = $current_time->format('H:i:s');
        $time_allotted_formatted = $fix_hours . ' hours ' . $fix_minutes . ' minutes';
        
        $success_count = 0;
        $error_count = 0;
        $error_messages = [];

        // Process each selected request
        foreach ($data_ids as $data_id) {
            $data_id = mysqli_real_escape_string($conn, $data_id);

            // Build query based on whether esttime is provided
            if ($esttime !== null) {
                $query = "UPDATE request SET esttime = '$time_allotted', time_allotted = '$time_allotted_formatted', Status = '$status', status1 = '$status1', confirmed_by = '$confirmed_by' WHERE id = '$data_id'";
            } else {
                $query = "UPDATE request SET esttime = '$time_allotted', time_allotted= '$time_allotted_formatted', Status = '$status', status1 = '$status1', confirmed_by = '$confirmed_by' WHERE id = '$data_id'";
            }

            // Log the query for debugging
            error_log("Executing query: $query");

            // Execute the query and check for errors
            $query_run = mysqli_query($conn, $query);

            if ($query_run) {
                $success_count++;
                error_log("Successfully updated request ID: $data_id");
            } else {
                $error_count++;
                $error_message = "Error updating request ID $data_id: " . mysqli_error($conn);
                $error_messages[] = $error_message;
                error_log($error_message);
            }
        }

        $response = [
            'success' => ($error_count === 0),
            'successCount' => $success_count,
            'errorCount' => $error_count,
            'errors' => $error_messages,
            'message' => ($error_count === 0)
                ? "$success_count Requests Updated Successfully"
                : "$success_count Requests Updated Successfully, $error_count Failed"
        ];

        // Store message in session for display after redirect
        $_SESSION['message'] = $response['message'];

        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'No requests selected for approval']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
