<?php
session_start();
require_once 'dbh.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scannedData = mysqli_real_escape_string($conn, $_POST['scannedData']);

    // Check the current time
    date_default_timezone_set('Asia/Manila'); // Set timezone to Philippine time
    $currentTime = date("H:i");

    // Check if the time is within the restricted intervals (8:00 am - 9:00 am and 1:00 pm - 1:30 pm)
    $isRestrictedTime = (($currentTime >= "08:00" && $currentTime <= "09:00") || ($currentTime >= "13:00" && $currentTime <= "14:50"));

    // Check if the typeofbusiness is "Personal"
    if ($isRestrictedTime && isset($_POST['typeofbusiness']) && $_POST['typeofbusiness'] === 'Personal') {
        // It's within the restricted time intervals and typeofbusiness is "Personal"
        echo 'restricted';
    } else {
        // Query to check if the scanned data exists in the request table
        $query = "SELECT * FROM request WHERE name = '$scannedData' AND Status = 'Partially Approved' ORDER BY `id` DESC";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            // Scanned data exists in the database
            // Update the Status column to 'Approved'
            $update_query = "UPDATE request 
                     SET `timedept` = NOW(), 
                         Status = 'Approved', 
                         `status1` = 'Pass-Slip',  
                         `ImageName`= 'Check-Approved.png' 
                     WHERE name = '$scannedData' 
                     ORDER BY id DESC 
                     LIMIT 1";
            $update_result = mysqli_query($conn, $update_query);

            if ($update_result) {
                // Status updated successfully
                echo 'exists';
            } else {
                // Error updating status
                echo 'update_error';
            }
        } else {
            // Scanned data does not exist in the database
            echo 'not_exists';
        }
    }
} else {
    // Invalid request method
    echo 'invalid_request';
}


if (isset($_POST['approve_req'])) {
    $data_id = mysqli_real_escape_string($conn, $_POST['data_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $confirmed_by = mysqli_real_escape_string($conn, $_POST['confirmed_by']);
    $esttime = mysqli_real_escape_string($conn, $_POST['esttime']);

    // Convert the $esttime to DATETIME format
    $esttime = date('Y-m-d H:i:s', strtotime($esttime));

    $query = "UPDATE request SET esttime = '$esttime', Status = '$status', confirmed_by = '$confirmed_by' WHERE id = '$data_id'";

    // Execute the query and check for errors
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        $_SESSION['message'] = "Request Updated Successfully";
        header("Location: index.php");
        exit(0);
    } else {
        $_SESSION['message'] = "Request Not Updated. Error: " . mysqli_error($conn);
        header("Location: index.php");
        exit(0);
    }
}
if (isset($_POST['approve_req_r'])) {
    $data_id = mysqli_real_escape_string($conn, $_POST['data_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $confirmed_by = mysqli_real_escape_string($conn, $_POST['confirmed_by']);
    $esttime = mysqli_real_escape_string($conn, $_POST['esttime']);

    // Convert the $esttime to DATETIME format
    $esttime = date('Y-m-d H:i:s', strtotime($esttime));

    $query = "UPDATE request SET esttime = '$esttime', Status = '$status', status1 = 'Scan Qrcode', confirmed_by = '$confirmed_by' WHERE id = '$data_id'";

    // Execute the query and check for errors
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        $_SESSION['message'] = "Request Updated Successfully";
        header("Location: index_r.php");
        exit(0);
    } else {
        $_SESSION['message'] = "Request Not Updated. Error: " . mysqli_error($conn);
        header("Location: index_r.php");
        exit(0);
    }
}
if (isset($_POST['approve_req_tcws'])) {
    $data_id = mysqli_real_escape_string($conn, $_POST['data_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $confirmed_by = mysqli_real_escape_string($conn, $_POST['confirmed_by']);
    $esttime = mysqli_real_escape_string($conn, $_POST['esttime']);

    // Convert the $esttime to DATETIME format
    $esttime = date('Y-m-d H:i:s', strtotime($esttime));

    $query = "UPDATE request SET esttime = '$esttime', Status = '$status', confirmed_by = '$confirmed_by' WHERE id = '$data_id'";

    // Execute the query and check for errors
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        $_SESSION['message'] = "Request Updated Successfully";
        header("Location: index_tcws.php");
        exit(0);
    } else {
        $_SESSION['message'] = "Request Not Updated. Error: " . mysqli_error($conn);
        header("Location: index_tcws.php");
        exit(0);
    }
}

if (isset($_POST['decline_req'])) {
    $data_id = mysqli_real_escape_string($conn, $_POST['data_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $confirmed_by = mysqli_real_escape_string($conn, $_POST['confirmed_by']);
    $reason = mysqli_real_escape_string($conn, $_POST['decline_reason']);

    $query = "UPDATE request SET Status = '$status', `status1` = 'Declined', confirmed_by = '$confirmed_by', `reason` = '$reason',`ImageName` = 'declined.png' WHERE id = '$data_id'";

    // Execute the query and check for errors
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        $_SESSION['message'] = "Request Updated Successfully";
        header("Location: index.php");
        exit(0);
    } else {
        $_SESSION['message'] = "Request Not Updated. Error: " . mysqli_error($conn); // Capture and display the error message
        header("Location: index.php");
        exit(0);
    }
}
if (isset($_POST['decline_req_r'])) {
    $data_id = mysqli_real_escape_string($conn, $_POST['data_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $confirmed_by = mysqli_real_escape_string($conn, $_POST['confirmed_by']);
    $reason = mysqli_real_escape_string($conn, $_POST['decline_reason']);

    $query = "UPDATE request SET Status = '$status', `status1` = 'Declined', confirmed_by = '$confirmed_by', `reason` = '$reason',`ImageName` = 'declined.png' WHERE id = '$data_id'";

    // Execute the query and check for errors
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        $_SESSION['message'] = "Request Updated Successfully";
        header("Location: index_r.php");
        exit(0);
    } else {
        $_SESSION['message'] = "Request Not Updated. Error: " . mysqli_error($conn); // Capture and display the error message
        header("Location: index_r.php");
        exit(0);
    }
}
if (isset($_POST['decline_req_tcws'])) {
    $data_id = mysqli_real_escape_string($conn, $_POST['data_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $confirmed_by = mysqli_real_escape_string($conn, $_POST['confirmed_by']);
    $reason = mysqli_real_escape_string($conn, $_POST['decline_reason']);

    $query = "UPDATE request SET Status = '$status', `status1` = 'Declined', confirmed_by = '$confirmed_by', `reason` = '$reason',`ImageName` = 'declined.png' WHERE id = '$data_id'";

    // Execute the query and check for errors
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        $_SESSION['message'] = "Request Updated Successfully";
        header("Location: index_tcws.php");
        exit(0);
    } else {
        $_SESSION['message'] = "Request Not Updated. Error: " . mysqli_error($conn); // Capture and display the error message
        header("Location: index_tcws.php");
        exit(0);
    }
}

if (isset($_POST['returned_emp'])) {
    $data_id = mysqli_real_escape_string($conn, $_POST['data_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $time_ret = mysqli_real_escape_string($conn, $_POST['time_ret']);
    $location = mysqli_real_escape_string($conn, $_POST['designation']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    $query = "UPDATE request SET `destination` = '$location', time_returned = '$time_ret', Status = '$status', `status1` = 'Present', remarks = '$remarks' WHERE id = '$data_id'";
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {

        header("Location: approved.php");
        exit(0);
    } else {

        header("Location: approved.php");
        exit(0);
    }
}
if (isset($_POST['delete'])) {
    $id = $_POST['id'];

    $query = "DELETE FROM logindb WHERE Id='$id'";
    $query_run = mysqli_query($conn, $query);
    if ($query_run) {
        $_SESSION['message'] = "Successfully Deleted";
        header("Location: register.php");
        exit(0);
    } else {
        $_SESSION['message'] = "Not Delete";
        header("Location: register.php");
        exit(0);
    }
}


if (isset($_POST['returned_emp_desk'])) {
    $data_id = mysqli_real_escape_string($conn, $_POST['data_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $time_ret = mysqli_real_escape_string($conn, $_POST['time_ret']);
    $location = mysqli_real_escape_string($conn, $_POST['designation']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    $query = "UPDATE request SET `destination` = '$location', time_returned = '$time_ret', Status = '$status', `status1` = 'Present', remarks = '$remarks' WHERE id = '$data_id'";
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {

        header("Location: approved_desk.php");
        exit(0);
    } else {

        header("Location: approved_desk.php");
        exit(0);
    }
}
if (isset($_POST['save_data2'])) {
    // Check if the user has a pending request
    $username = $_SESSION['username'];
    $query_pending = "SELECT * FROM request WHERE name = '$username' AND Status = 'Pending'";
    $result_pending = mysqli_query($conn, $query_pending);

    if (mysqli_num_rows($result_pending) > 0) {
        echo '<div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <strong>Error!</strong> You already have a pending request.
            </div>';
    } else {
        // Proceed with inserting the new request
        $name = mysqli_real_escape_string($conn, $_POST["name"]);
        $position = mysqli_real_escape_string($conn, $_POST["position"]);
        $date = date('Y-m-d', strtotime($_POST['date']));
        $destination = mysqli_real_escape_string($conn, $_POST["destination"]);
        $purpose = mysqli_real_escape_string($conn, $_POST["purpose"]);
        $role = $_SESSION['role'];
        $typeofbusiness = mysqli_real_escape_string($conn, $_POST["typeofbusiness"]);

        $query_insert = "INSERT INTO request(name, position, date, destination, purpose, esttime, typeofbusiness, time_returned, Status, status1, dest2, ImageName, confirmed_by,remarks ,reason ,Role) VALUES ('$name', '$position', '$date', '$destination', '$purpose', '2024-02-19 11:55:00', '$typeofbusiness', '00:00:00', 'Pending', 'Waiting For Pass Slip Approval', '$destination', 'pending.png', ' ', ' ', ' ', '$role')";
        $query_run = mysqli_query($conn, $query_insert);

        if ($query_run) {
            require_once 'send_notification.php';
            header("Location: index_emp.php");
            exit(); // Make sure to exit after a header redirect
        } else {
            echo '<div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <strong>Error!</strong> Failed to submit request: ' . mysqli_error($conn) . '
                </div>';
        }
    }
}
