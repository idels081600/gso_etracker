<?php
require_once 'auth_security.php';
start_secure_session();

require_once 'passlip/dbh.php';

if ($conn === false) {
    die("Connection error");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location:login_v2.php");
    exit;
}

if (isset($_POST['two_factor_code'])) {
    if (!has_pending_admin_login()) {
        $_SESSION['LoginMessage'] = 'Your security verification expired. Please log in again.';
        security_audit_log('admin_2fa_expired');
        mysqli_close($conn);
        header("location:login_v2.php");
        exit;
    }

    if (!verify_admin_2fa_code($_POST['two_factor_code'] ?? '')) {
        $_SESSION['LoginMessage'] = 'Invalid security code.';
        security_audit_log('admin_2fa_failed');
        mysqli_close($conn);
        header("location:login_v2.php?two_factor=1");
        exit;
    }

    session_regenerate_id(true);
    $redirect = complete_pending_admin_login();
    security_audit_log('admin_2fa_success', ['redirect' => $redirect]);
    mysqli_close($conn);
    header('location:' . ($redirect ?: 'login_v2.php'));
    exit;
}

$input = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$throttle = current_login_throttle($input);
if ($throttle['locked']) {
    $_SESSION['LoginMessage'] = login_throttle_message($throttle['remaining_seconds']);
    security_audit_log('login_locked', ['input' => $input]);
    header("location:login_v2.php");
    mysqli_close($conn);
    exit;
}

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

if ($row && password_verify($password, $row['password'])) {
    clear_login_attempts($input);
    session_regenerate_id(true);

    if (security_role_requires_2fa($row['role'])) {
        begin_pending_admin_login($row);
        security_audit_log('admin_2fa_challenge_created');
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        header("location:login_v2.php?two_factor=1");
        exit;
    }

    $_SESSION['username'] = $row['username'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['office'] = isset($row['office']) ? $row['office'] : 'ASSET';
    $_SESSION['pay_name'] = $row['name'];
    $_SESSION['station_id'] = $row['station_id'];
    $_SESSION['logged_in'] = true;
    $_SESSION['_login_time'] = time();
    $_SESSION['_last_activity'] = time();
    $_SESSION['_heartbeat_count'] = 0;

    $redirect = login_redirect_for_role($row['role']);
    security_audit_log('login_success', ['redirect' => $redirect]);

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    header('location:' . $redirect);
    exit;
}

$throttle = register_failed_login($input);
$_SESSION['LoginMessage'] = $throttle['locked']
    ? login_throttle_message($throttle['remaining_seconds'])
    : "Invalid username or password";
security_audit_log('login_failed', ['input' => $input]);
header("location:login_v2.php");

if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
exit;
