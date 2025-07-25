<?php
session_start();
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'dbh.php';

if ($conn === false) {
    die("Connection error");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = $_POST['username']; // Assume the input can be either username or ID
    $password = $_POST['password'];

    // Check if the input is numeric to determine if it's an ID
    if (is_numeric($input)) {
        // Input is numeric, assume it's an ID
        // Use a prepared statement to fetch the username associated with the ID
        $sql = "SELECT * FROM logindb WHERE id = ? AND BINARY password = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $input, $password);
    } else {
        // Input is not numeric, assume it's a username
        // Use a prepared statement to fetch the user by username and password
        $sql = "SELECT * FROM logindb WHERE BINARY username = ? AND BINARY password = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $input, $password);
    }

    // Execute the statement
    mysqli_stmt_execute($stmt);

    // Get the result
    $result = mysqli_stmt_get_result($stmt);

    // Fetch the row
    $row = mysqli_fetch_array($result);

    if ($row) {
        // User found, set session variables based on role
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['pay_name'] = $row['name'];
        // Redirect based on role
        switch ($row['role']) {
            case "Admin":
                header("location:index.php");
                break;
            case "Employee":
                header("location:add_req_emp.php");
                break;
            // case "Desk Clerk":
            //     header("location:qrcode_scanner_dept.php");
            //     break;
            // case "Division Head":
            //     header("location:qrcode_scanner_dept.php");
            //     break;
            case "TCWS Division Head":
                header("location:index_tcws.php");
                break;
            case "TCWS Employee":
                header("location:add_req_emp.php");
                break;
            case "Admin2":
                header("location:index_r.php");
                break;
            case "Admin1":
                header("location:index_desk.php");
                break;
            case "TCWS Scanner":
                header("location:qrcode_scanner_desk_tcws.php");
                break;
            case "SAP":
                header("location:sir_bayong.php");
                break;
            case "ASSET":
                header("location:dashboard_asset_tracker.php");
                break;
            case "TENT INSTALLERS":
                header("location:tent_installers.php");
                break;
            case "SUPPLIES":
                header("location:LogiSys/Logi_Sys_Dashboard.php");
                break;
            case "Pay_admin":
                header("location:Payables/transmittal_bac.php");
                break;
            case "fuel_admin":
                header("location:fuel_tracker/fuel_dashboard.php");
                break;
            default:
                header("location:login_v2.php");
                break;
        }
    } else {
        // Username/ID and password don't match
        $_SESSION['LoginMessage'] = "Invalid username or password";
        header("location:login_v2.php");
    }

    // Close the statement
    mysqli_stmt_close($stmt);

    // Close the database connection
    mysqli_close($conn);
}
