<?php

require_once __DIR__ . '/Logi_ib_auth.php';

function logi_security_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function logi_csrf_token(): string
{
    logi_security_start_session();
    if (empty($_SESSION['ib_csrf']) || !is_string($_SESSION['ib_csrf'])) {
        $_SESSION['ib_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ib_csrf'];
}

function logi_security_json_error(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function logi_require_admin_page(mysqli $conn): string
{
    logi_security_start_session();
    if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) {
        header('Location: Logi_login.php');
        exit;
    }
    $actor = logi_ib_admin_actor($conn);
    if ($actor === null) {
        http_response_code(403);
        exit('This page requires the Version 1 ADMIN_SAP account.');
    }
    logi_csrf_token();
    return $actor;
}

function logi_require_admin_csrf(mysqli $conn): string
{
    logi_security_start_session();
    if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) {
        logi_security_json_error(401, 'Your session has expired. Please sign in again.');
    }
    $actor = logi_ib_admin_actor($conn);
    if ($actor === null) {
        logi_security_json_error(403, 'Only the Version 1 ADMIN_SAP account can perform this action.');
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $provided = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '')));
        $expected = logi_csrf_token();
        if ($provided === '' || !hash_equals($expected, $provided)) {
            logi_security_json_error(403, 'The security token is invalid. Refresh the page and try again.');
        }
    }
    return $actor;
}

function logi_security_meta(): string
{
    return '<meta name="logisys-csrf" content="' . htmlspecialchars(logi_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

