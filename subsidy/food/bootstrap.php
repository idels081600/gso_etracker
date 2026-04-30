<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Session Configuration with Heartbeat Support
// Only set ini settings if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', env('SESSION_LIFETIME', 3600)); // 1 hour default
    ini_set('session.gc_divisor', 100);
    ini_set('session.gc_probability', 1);
    
    session_set_cookie_params([
        'lifetime' => env('SESSION_LIFETIME', 3600), // 1 hour default
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Helper function
function env($key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

// Heartbeat function - update session last activity
function updateSessionHeartbeat() {
    if (isset($_SESSION['_last_activity'])) {
        $_SESSION['_last_activity'] = time();
        $_SESSION['_heartbeat_count'] = isset($_SESSION['_heartbeat_count']) ? $_SESSION['_heartbeat_count'] + 1 : 1;
    }
}