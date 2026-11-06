<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Your session has expired.']);
    exit;
}

$now = time();
$_SESSION['last_activity'] = $now;
$_SESSION['LAST_ACTIVITY'] = $now;

echo json_encode(['success' => true, 'active' => true, 'time' => $now]);
