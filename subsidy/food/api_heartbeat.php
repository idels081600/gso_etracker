<?php
/**
 * Session Heartbeat - Keep Session Alive During Active Use
 * 
 * This endpoint receives periodic heartbeat requests from the client
 * to prevent session expiry during active use.
 */

// Suppress error display and log to file instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';

// Set response headers FIRST (before any output)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired or not authenticated',
        'redirect' => '../../check_login.php'
    ]);
    exit;
}

try {
    // Initialize/Reset login time on first heartbeat (fresh session)
    if (!isset($_SESSION['_login_time']) || empty($_SESSION['_login_time'])) {
        $_SESSION['_login_time'] = time();
    }
    
    // Update session last activity time - this extends the session
    $_SESSION['_last_activity'] = time();
    $_SESSION['_heartbeat_count'] = isset($_SESSION['_heartbeat_count']) ? $_SESSION['_heartbeat_count'] + 1 : 1;
    $_SESSION['_last_heartbeat'] = date('Y-m-d H:i:s');
    
    // Get session lifetime (in seconds)
    $sessionLifetime = intval(env('SESSION_LIFETIME', 3600));
    
    // Calculate session remaining time from last activity (activity-based expiry)
    // This means the session will expire if IDLE for SESSION_LIFETIME seconds
    $lastActivity = $_SESSION['_last_activity'] ?? time();
    $timeRemaining = max(0, $sessionLifetime - (time() - $lastActivity));
    
    // Log heartbeat activity (optional - for debugging)
    error_log(sprintf(
        "[HEARTBEAT] User: %s | Count: %d | Remaining: %d seconds | Last Activity: %s",
        $_SESSION['username'] ?? 'unknown',
        $_SESSION['_heartbeat_count'],
        $timeRemaining,
        date('Y-m-d H:i:s', $lastActivity)
    ));
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Session refreshed',
        'session_remaining' => $timeRemaining,
        'heartbeat_count' => $_SESSION['_heartbeat_count'],
        'last_heartbeat' => $_SESSION['_last_heartbeat'],
        'username' => $_SESSION['username'] ?? null,
        'session_lifetime' => $sessionLifetime
    ]);
    
} catch (Exception $e) {
    error_log('Heartbeat Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Heartbeat failed',
        'debug' => env('APP_DEBUG', false) ? $e->getMessage() : null
    ]);
}

