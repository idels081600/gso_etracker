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

function login_endpoint_rate_limit_config()
{
    return [
        'max_requests' => 30,
        'window_seconds' => 60,
        'block_seconds' => 5 * 60,
    ];
}

function login_endpoint_rate_limit_dir()
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cgso_login_endpoint_limits';

    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return null;
    }

    return is_writable($dir) ? $dir : null;
}

function login_endpoint_rate_limit_path($ipAddress)
{
    $dir = login_endpoint_rate_limit_dir();
    if ($dir === null) {
        return null;
    }

    return $dir . DIRECTORY_SEPARATOR . hash('sha256', trim((string) $ipAddress)) . '.json';
}

function clear_login_endpoint_rate_limit($ipAddress)
{
    $path = login_endpoint_rate_limit_path($ipAddress);
    if ($path !== null && is_file($path)) {
        @unlink($path);
    }
}

function consume_login_endpoint_rate_limit($ipAddress, $now = null)
{
    $config = login_endpoint_rate_limit_config();
    $now = $now === null ? time() : (int) $now;
    $path = login_endpoint_rate_limit_path($ipAddress);

    if ($path === null) {
        return ['limited' => false, 'retry_after' => 0, 'storage_available' => false];
    }

    $handle = @fopen($path, 'c+');
    if ($handle === false || !@flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            @fclose($handle);
        }
        return ['limited' => false, 'retry_after' => 0, 'storage_available' => false];
    }

    rewind($handle);
    $raw = stream_get_contents($handle);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = [];
    }

    $windowStartedAt = (int) ($data['window_started_at'] ?? 0);
    $blockedUntil = (int) ($data['blocked_until'] ?? 0);

    if ($blockedUntil > $now) {
        $result = [
            'limited' => true,
            'retry_after' => $blockedUntil - $now,
            'storage_available' => true,
        ];
    } else {
        if ($windowStartedAt <= 0 || ($now - $windowStartedAt) >= $config['window_seconds']) {
            $data = [
                'count' => 0,
                'window_started_at' => $now,
                'blocked_until' => 0,
            ];
        }

        $data['count'] = (int) ($data['count'] ?? 0) + 1;

        if ($data['count'] > $config['max_requests']) {
            $data['blocked_until'] = $now + $config['block_seconds'];
            $result = [
                'limited' => true,
                'retry_after' => $config['block_seconds'],
                'storage_available' => true,
            ];
        } else {
            $data['blocked_until'] = 0;
            $result = ['limited' => false, 'retry_after' => 0, 'storage_available' => true];
        }
    }

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $result;
}

function security_env_value($key)
{
    if (isset($_ENV[$key])) {
        return trim((string) $_ENV[$key]);
    }

    if (isset($_SERVER[$key])) {
        return trim((string) $_SERVER[$key]);
    }

    $value = getenv($key);
    return $value === false ? '' : trim((string) $value);
}

function turnstile_site_key()
{
    return security_env_value('TURNSTILE_SITE_KEY');
}

function turnstile_secret_key()
{
    return security_env_value('TURNSTILE_SECRET_KEY');
}

function turnstile_allowed_hostnames()
{
    $configuredHostnames = explode(',', security_env_value('TURNSTILE_ALLOWED_HOSTNAMES'));
    $hostnames = [];

    foreach ($configuredHostnames as $hostname) {
        $hostname = strtolower(rtrim(trim($hostname), '.'));
        if ($hostname !== '') {
            $hostnames[] = $hostname;
        }
    }

    return array_values(array_unique($hostnames));
}

function turnstile_request_hostname()
{
    $httpHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($httpHost === '') {
        return '';
    }

    $hostname = parse_url('http://' . $httpHost, PHP_URL_HOST);
    return is_string($hostname) ? strtolower(rtrim($hostname, '.')) : '';
}

function turnstile_uses_test_credentials()
{
    $testSiteKeys = [
        '1x00000000000000000000AA',
        '2x00000000000000000000AB',
        '1x00000000000000000000BB',
        '2x00000000000000000000BB',
        '3x00000000000000000000FF',
    ];
    $testSecretKeys = [
        '1x0000000000000000000000000000000AA',
        '2x0000000000000000000000000000000AA',
        '3x0000000000000000000000000000000AA',
    ];

    return in_array(turnstile_site_key(), $testSiteKeys, true)
        || in_array(turnstile_secret_key(), $testSecretKeys, true);
}

function turnstile_test_credentials_allowed()
{
    return in_array(turnstile_request_hostname(), ['localhost', '127.0.0.1', '::1'], true);
}

function turnstile_configuration_issue()
{
    if (turnstile_site_key() === '' || turnstile_secret_key() === '' || turnstile_allowed_hostnames() === []) {
        return 'not_configured';
    }

    if (turnstile_uses_test_credentials() && !turnstile_test_credentials_allowed()) {
        return 'test_keys_not_allowed';
    }

    return null;
}

function turnstile_is_configured()
{
    return turnstile_configuration_issue() === null;
}

function validate_turnstile_response(array $verification)
{
    $errorCodes = isset($verification['error-codes']) && is_array($verification['error-codes'])
        ? array_values(array_filter($verification['error-codes'], 'is_string'))
        : [];

    if (($verification['success'] ?? false) !== true) {
        return ['success' => false, 'reason' => 'challenge_rejected', 'error_codes' => $errorCodes];
    }

    if (turnstile_uses_test_credentials() && turnstile_test_credentials_allowed()) {
        return ['success' => true, 'reason' => 'verified_test_response', 'error_codes' => []];
    }

    if (($verification['action'] ?? '') !== 'login') {
        return ['success' => false, 'reason' => 'action_mismatch', 'error_codes' => $errorCodes];
    }

    $verifiedHostname = strtolower(rtrim(trim((string) ($verification['hostname'] ?? '')), '.'));
    if ($verifiedHostname === '' || !in_array($verifiedHostname, turnstile_allowed_hostnames(), true)) {
        return ['success' => false, 'reason' => 'hostname_mismatch', 'error_codes' => $errorCodes];
    }

    return ['success' => true, 'reason' => 'verified', 'error_codes' => []];
}

function verify_turnstile_token($token, $remoteIp = null)
{
    $token = trim((string) $token);

    $configurationIssue = turnstile_configuration_issue();
    if ($configurationIssue !== null) {
        return ['success' => false, 'reason' => $configurationIssue, 'error_codes' => []];
    }

    if ($token === '') {
        return ['success' => false, 'reason' => 'missing_token', 'error_codes' => []];
    }

    if (strlen($token) > 2048) {
        return ['success' => false, 'reason' => 'invalid_token_length', 'error_codes' => []];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'reason' => 'curl_unavailable', 'error_codes' => []];
    }

    $payload = [
        'secret' => turnstile_secret_key(),
        'response' => $token,
    ];

    $remoteIp = trim((string) $remoteIp);
    if ($remoteIp !== '') {
        $payload['remoteip'] = $remoteIp;
    }

    $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($curl === false) {
        return ['success' => false, 'reason' => 'curl_initialization_failed', 'error_codes' => []];
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
    ]);

    $responseBody = curl_exec($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $transportFailed = $responseBody === false;

    if ($transportFailed) {
        return ['success' => false, 'reason' => 'provider_unreachable', 'error_codes' => []];
    }

    if ($httpStatus !== 200) {
        return ['success' => false, 'reason' => 'provider_http_error', 'error_codes' => []];
    }

    $verification = json_decode($responseBody, true);
    if (!is_array($verification)) {
        return ['success' => false, 'reason' => 'invalid_provider_response', 'error_codes' => []];
    }

    return validate_turnstile_response($verification);
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
    return ['Admin', 'master_admin', 'Pay_admin', 'fuel_admin', 'private_issuance_admin', 'advance_PO'];
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
        case "private_issuance_admin": return "fuel_tracker/private_gas_issuance.php";
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
