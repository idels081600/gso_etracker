<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Default rate limit: 30 requests per 60 seconds per session.
define('PAYABLES_RATE_LIMIT_MAX_REQUESTS', 30);
define('PAYABLES_RATE_LIMIT_WINDOW_SECONDS', 60);

function payables_is_api_request(): bool
{
    if (defined('PAYABLES_API') && PAYABLES_API) {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }

    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        return true;
    }

    return false;
}

function payables_unauthorized(string $message = 'Access denied', int $statusCode = 403): void
{
    http_response_code($statusCode);

    if (payables_is_api_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }

    $redirect = '../login_v2.php';
    if (headers_sent()) {
        echo '<script>window.location.href = ' . json_encode($redirect) . ';</script>';
        exit;
    }

    header('Location: ' . $redirect);
    exit;
}

function payables_require_pay_admin_role(): void
{
    if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        payables_unauthorized('Authentication required.', 401);
    }

    if (empty($_SESSION['role']) || strcasecmp($_SESSION['role'], 'Pay_admin') !== 0) {
        payables_unauthorized('Pay_admin role required.', 403);
    }
}

function payables_apply_rate_limit(int $maxRequests = PAYABLES_RATE_LIMIT_MAX_REQUESTS, int $windowSeconds = PAYABLES_RATE_LIMIT_WINDOW_SECONDS): void
{
    if (!isset($_SESSION['_payables_rate_limit'])) {
        $_SESSION['_payables_rate_limit'] = [
            'count' => 0,
            'window_start' => time(),
        ];
    }

    $now = time();
    if ($now - $_SESSION['_payables_rate_limit']['window_start'] >= $windowSeconds) {
        $_SESSION['_payables_rate_limit'] = [
            'count' => 0,
            'window_start' => $now,
        ];
    }

    $_SESSION['_payables_rate_limit']['count']++;

    if ($_SESSION['_payables_rate_limit']['count'] > $maxRequests) {
        payables_unauthorized("Rate limit exceeded. Please wait {$windowSeconds} seconds and try again.", 429);
    }
}

payables_require_pay_admin_role();

if (payables_is_api_request()) {
    payables_apply_rate_limit();
}
