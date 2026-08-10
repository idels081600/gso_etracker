<?php

function start_secure_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'] ?: '/',
        'domain' => $cookieParams['domain'],
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function login_throttle_config()
{
    return [
        'max_attempts' => 5,
        'window_seconds' => 15 * 60,
        'lock_seconds' => 10 * 60,
    ];
}

function login_attempts_dir()
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cgso_login_attempts';

    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return null;
    }

    if (!is_writable($dir)) {
        return null;
    }

    return $dir;
}

function login_attempt_key($username)
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $normalizedUsername = strtolower(trim((string) $username));

    return hash('sha256', $ipAddress . '|' . $normalizedUsername);
}

function login_attempt_path($username)
{
    $dir = login_attempts_dir();

    if ($dir === null) {
        return null;
    }

    return $dir . DIRECTORY_SEPARATOR . login_attempt_key($username) . '.json';
}

function read_login_attempts($username)
{
    $path = login_attempt_path($username);

    if ($path === null || !is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

function write_login_attempts($username, array $data)
{
    $path = login_attempt_path($username);

    if ($path === null) {
        $_SESSION['_login_attempts'] = $data;
        return;
    }

    @file_put_contents($path, json_encode($data), LOCK_EX);
}

function clear_login_attempts($username)
{
    $path = login_attempt_path($username);

    if ($path !== null && is_file($path)) {
        @unlink($path);
    }

    unset($_SESSION['_login_attempts']);
}

function current_login_throttle($username)
{
    $config = login_throttle_config();
    $now = time();
    $data = read_login_attempts($username) ?: ($_SESSION['_login_attempts'] ?? null);

    if (!is_array($data)) {
        return ['locked' => false, 'remaining_seconds' => 0];
    }

    if (!empty($data['locked_until']) && $data['locked_until'] > $now) {
        return [
            'locked' => true,
            'remaining_seconds' => $data['locked_until'] - $now,
        ];
    }

    if (!empty($data['first_attempt']) && ($now - $data['first_attempt']) > $config['window_seconds']) {
        clear_login_attempts($username);
    }

    return ['locked' => false, 'remaining_seconds' => 0];
}

function register_failed_login($username)
{
    $config = login_throttle_config();
    $now = time();
    $data = read_login_attempts($username) ?: ($_SESSION['_login_attempts'] ?? null);

    if (!is_array($data) || empty($data['first_attempt']) || ($now - $data['first_attempt']) > $config['window_seconds']) {
        $data = [
            'count' => 0,
            'first_attempt' => $now,
            'locked_until' => 0,
        ];
    }

    $data['count']++;

    if ($data['count'] >= $config['max_attempts']) {
        $data['locked_until'] = $now + $config['lock_seconds'];
    }

    write_login_attempts($username, $data);

    return current_login_throttle($username);
}

function login_throttle_message($remainingSeconds)
{
    $minutes = max(1, (int) ceil($remainingSeconds / 60));

    return "Too many failed login attempts. Please try again in {$minutes} minute" . ($minutes === 1 ? '.' : 's.');
}

function security_admin_roles()
{
    return ['Admin', 'master_admin', 'Pay_admin', 'fuel_admin', 'advance_PO'];
}

function security_role_requires_2fa($role)
{
    return in_array((string) $role, security_admin_roles(), true);
}

function security_audit_log($action, array $metadata = [])
{
    $entry = [
        'timestamp' => date('c'),
        'action' => $action,
        'username' => $_SESSION['username'] ?? ($_SESSION['_pending_login']['username'] ?? null),
        'role' => $_SESSION['role'] ?? ($_SESSION['_pending_login']['role'] ?? null),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'metadata' => $metadata,
    ];

    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cgso_security_audit.log';
    @file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function admin_2fa_code_path()
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cgso_admin_2fa_code.txt';
}

function current_admin_2fa_code()
{
    $envCode = trim((string) (getenv('CGSO_ADMIN_2FA_CODE') ?: ''));
    if (preg_match('/^\d{6}$/', $envCode)) {
        return $envCode;
    }

    $path = admin_2fa_code_path();
    $code = is_file($path) ? trim((string) @file_get_contents($path)) : '';

    if (!preg_match('/^\d{6}$/', $code)) {
        $code = (string) random_int(100000, 999999);
        @file_put_contents($path, $code, LOCK_EX);
        @chmod($path, 0600);
    }

    return $code;
}

function verify_admin_2fa_code($providedCode)
{
    $providedCode = preg_replace('/\D+/', '', (string) $providedCode);
    return $providedCode !== '' && hash_equals(current_admin_2fa_code(), $providedCode);
}

function login_redirect_for_role($role)
{
    switch ($role) {
        case "Admin": return "passlip/super_admin/index.php";
        case "Employee": return "passlip/requestor/add_req_emp.php";
        case "TCWS Division Head": return "index_tcws.php";
        case "TCWS Employee": return "passlip/requestor/add_req_emp.php";
        case "Admin2": return "passlip/admin_r/index_r.php";
        case "Admin1": return "passlip/admin_approver/index_desk.php";
        case "TCWS Scanner": return "qrcode_scanner_desk_tcws.php";
        case "SAP": return "sir_bayong.php";
        case "ASSET2": return "asset_tracker_dashboard/dashboard_asset_tracker.php";
        case "ASSET": return "dashboard_asset_tracker.php";
        case "TENT INSTALLERS": return "asset_tracker_dashboard/tent_installers.php";
        case "SUPPLIES": return "LogiSys/Logi_Sys_Dashboard.php";
        case "Pay_admin": return "Payables/bac_dashboard.php";
        case "fuel_admin": return "fuel_tracker/fuel_dashboard.php";
        case "gas_checker": return "fuel_tracker/gas_checker.php";
        case "advance_PO": return "advance_request/dashboard.php";
        case "pr_admin": return "prtracking/dashboard.php";
        case "Docu_admin": return "document_tracker/dashboard.php";
        case "super_admin": return "document_tracker/super_admin.php";
        case "Fuel_admin": return "subsidy/fuel/select_station.php";
        case "FOOD_VERIFIER": return "subsidy/food/select_station.php";
        case "FOOD_REDEEMER": return "subsidy/food/redeem_batch.php";
        case "print_admin": return "fuel_tracker/personnel_printing.php";
        case "RICE_VERIFIER": return "subsidy/food/releasing_rice.php";
        case "coa_admin": return "fuel_tracker/sub_admin.php";
        case "master_admin": return "master_dashboard/dashboard.php";
        default: return "login_v2.php";
    }
}

function begin_pending_admin_login(array $row)
{
    $_SESSION['_pending_login'] = [
        'username' => $row['username'],
        'role' => $row['role'],
        'office' => $row['office'] ?? 'ASSET',
        'pay_name' => $row['name'] ?? '',
        'station_id' => $row['station_id'] ?? null,
        'redirect' => login_redirect_for_role($row['role']),
        'created_at' => time(),
    ];
    $_SESSION['logged_in'] = false;
    current_admin_2fa_code();
}

function has_pending_admin_login()
{
    $pending = $_SESSION['_pending_login'] ?? null;
    return is_array($pending)
        && !empty($pending['username'])
        && !empty($pending['role'])
        && !empty($pending['created_at'])
        && (time() - (int) $pending['created_at']) <= 600;
}

function complete_pending_admin_login()
{
    if (!has_pending_admin_login()) {
        return null;
    }

    $pending = $_SESSION['_pending_login'];
    unset($_SESSION['_pending_login']);

    $_SESSION['username'] = $pending['username'];
    $_SESSION['role'] = $pending['role'];
    $_SESSION['office'] = $pending['office'] ?? 'ASSET';
    $_SESSION['pay_name'] = $pending['pay_name'] ?? '';
    $_SESSION['station_id'] = $pending['station_id'] ?? null;
    $_SESSION['logged_in'] = true;
    $_SESSION['_login_time'] = time();
    $_SESSION['_last_activity'] = time();
    $_SESSION['_heartbeat_count'] = 0;

    return $pending['redirect'] ?? login_redirect_for_role($pending['role']);
}
