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
