<?php
session_start();
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'passlip/dbh.php';

if ($conn === false) {
    die("Connection error");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = $_POST['username'];
    $password = $_POST['password'];

    // UPDATED: We only search by ID/Username. We do NOT check password in SQL anymore.
    if (is_numeric($input)) {
        $sql = "SELECT * FROM logindb WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $input);
    } else {
        $sql = "SELECT * FROM logindb WHERE BINARY username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $input);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_array($result);

    // UPDATED: Use password_verify to check the Bcrypt hash
    if ($row && password_verify($password, $row['password'])) {
        // User found and password matches
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role']; 
        $_SESSION['office'] = isset($row['office']) ? $row['office'] : 'ASSET';
        $_SESSION['pay_name'] = $row['name'];
        $_SESSION['station_id'] = $row['station_id'];
        $_SESSION['logged_in'] = true;

        $_SESSION['_login_time'] = time();
        $_SESSION['_last_activity'] = time();
        $_SESSION['_heartbeat_count'] = 0;

        // Change #1: close session write
        session_write_close();
        
        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        // Change #2: Use exit;
        switch ($row['role']) {
            case "Admin":
                header("location:passlip/super_admin/index.php");
                exit;
            case "Employee":
                header("location:passlip/requestor/add_req_emp.php");
                exit;
            case "TCWS Division Head":
                header("location:index_tcws.php");
                exit;
            case "TCWS Employee":
                header("location:passlip/requestor/add_req_emp.php");
                exit;
            case "Admin2":
                header("location:passlip/admin_r/index_r.php");
                exit;
            case "Admin1":
                header("location:passlip/admin_approver/index_desk.php");
                exit;
            case "TCWS Scanner":
                header("location:qrcode_scanner_desk_tcws.php");
                exit;
            case "SAP":
                header("location:sir_bayong.php");
                exit;
            case "ASSET":
                header("location:dashboard_asset_tracker.php");
                exit;
            case "TENT INSTALLERS":
                header("location:tent_installers.php");
                exit;
            case "SUPPLIES":
                header("location:LogiSys/Logi_Sys_Dashboard.php");
                exit;
            case "Pay_admin":
                header("location:Payables/transmittal_bac.php");
                exit;
            case "fuel_admin":
                header("location:fuel_tracker/fuel_dashboard.php");
                exit;
            case "advance_PO":
                header("location:advance_request/dashboard.php");
                exit;
            case "pr_admin":
                header("location:prtracking/dashboard.php");
                exit;
            case "Docu_admin":
                header("location:document_tracker/dashboard.php");
                exit;
            case "super_admin":
                header("location:document_tracker/super_admin.php");
                exit;
            case "Fuel_admin":
                header("location:subsidy/fuel/select_station.php");
                exit;
            case "FOOD_VERIFIER":
                header("location:subsidy/food/select_station.php");
                exit;
            case "FOOD_REDEEMER":
                header("location:subsidy/food/redeem_batch.php");
                exit;
            default:
                header("location:login_v2.php");
                exit;
        }
    } else {
        // Invalid credentials
        $_SESSION['LoginMessage'] = "Invalid username or password";
        header("location:login_v2.php");
        
        // Cleanup before exit
        if(isset($stmt)) mysqli_stmt_close($stmt);
        mysqli_close($conn);
        exit;
    }
}
?>