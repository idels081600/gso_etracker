<?php
require_once 'dbh.php';
session_start();
$username = $_SESSION['username'];


function display_data()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` LIKE 'Pending' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_tcws()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` = 'Pending' AND `Role` = 'TCWS Employee' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}

function display_data_r()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` = 'Pending' AND `Role` = 'Employee' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_cviraa()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` = 'Pending' AND `Role` = 'CVIRAA' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_emp()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `name` = '$username' ORDER BY `id` DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    return $result;
}

function display_data_approved()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` LIKE 'Approved' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_approved_emp()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` = 'Approved' AND `name` = '$username' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}

function display_data_declined()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` LIKE 'Declined' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_declined_r()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` LIKE 'Declined' AND `Role` = 'Employee' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_declined_tcws()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` LIKE 'Declined' AND `Role` = 'TCWS Employee' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_declined_emp()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` = 'Declined' AND `name` = '$username' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_emp_status()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_emp_status_tcws()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Role` = 'TCWS Employee' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_emp_status_r()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Role` = 'Employee' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_emp_status_cviraa()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Role` = 'CVIRAA' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}

function display_users()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `logindb`";
    $result = mysqli_query($conn, $query);
    return $result;
}

function display_request()
{
    global $conn, $username; // Add $username as a global variable
    $query = "SELECT * FROM `request` WHERE `Status` = 'Pending' AND `Role` = 'Employee' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_total_pass_slip()
{
    global $conn, $username; // Add $username as a global variable

    // Query to get the rows where Status is either 'Done' or 'Approved' and Role is 'Employee'
    $query = "SELECT * FROM `request` WHERE `Status` IN ('Done', 'Approved') AND `Role` = 'Employee' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);

    // Count the number of rows returned
    $total_count = mysqli_num_rows($result);

    // Return both the result set and the total count
    return [
        'result' => $result,    // The result set
        'count' => $total_count // Total number of rows
    ];
}
