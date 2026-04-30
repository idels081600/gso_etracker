<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', env('SESSION_LIFETIME', 3600)); // 1 hour default
    ini_set('session.gc_divisor', 100);
    ini_set('session.gc_probability', 1);
    
    session_set_cookie_params([
        'lifetime' => env('SESSION_LIFETIME', 3600),
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

// Set response headers
header('Content-Type: application/json');
header('X-Requested-With: XMLHttpRequest');

// Check if user is authenticated
if (!isset($_SESSION['_last_activity']) || !isset($_SESSION['_login_time'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session not found or expired',
        'session_remaining' => 0
    ]);
    exit;
}

try {
    // Get session lifetime from environment or config
    $sessionLifetime = (int) env('SESSION_LIFETIME', 3600); // Default 1 hour
    
    // Calculate when session will expire
    $lastActivity = (int) $_SESSION['_last_activity'];
    $currentTime = time();
    $sessionExpireTime = $lastActivity + $sessionLifetime;
    
    // Calculate remaining time in milliseconds
    $timeRemaining = max(0, ($sessionExpireTime - $currentTime) * 1000);
    
    // Update last activity timestamp
    $_SESSION['_last_activity'] = time();
    $_SESSION['_heartbeat_count'] = isset($_SESSION['_heartbeat_count']) ? $_SESSION['_heartbeat_count'] + 1 : 1;
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Heartbeat received',
        'session_remaining' => $timeRemaining,  // Time remaining in milliseconds
        'session_lifetime' => $sessionLifetime * 1000,  // Total session lifetime in milliseconds
        'last_activity' => $_SESSION['_last_activity'],
        'heartbeat_count' => $_SESSION['_heartbeat_count'],
        'timestamp' => $currentTime
    ];
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'session_remaining' => 0
    ]);
}
?>