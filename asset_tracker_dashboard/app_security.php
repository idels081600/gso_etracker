<?php

require_once dirname(__DIR__) . '/auth_security.php';

const ASSET_ALLOWED_ROLES = ['ASSET', 'Admin', 'master_admin'];
const ASSET_ADMIN_ROLES = ['Admin', 'master_admin'];

function asset_start_session(): void
{
    start_secure_session();

    if (!isset($_SESSION['_asset_csrf_token'])) {
        $_SESSION['_asset_csrf_token'] = bin2hex(random_bytes(32));
    }
}

function asset_is_api_request(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return str_contains($accept, 'application/json')
        || strtolower($requestedWith) === 'xmlhttprequest';
}

function asset_current_role(): string
{
    asset_start_session();
    return (string) ($_SESSION['role'] ?? '');
}

function asset_require_auth(array $allowedRoles = ASSET_ALLOWED_ROLES): void
{
    asset_start_session();

    $authenticated = !empty($_SESSION['logged_in']) && !empty($_SESSION['username']);
    $authorized = $authenticated && in_array(asset_current_role(), $allowedRoles, true);

    if ($authorized) {
        $_SESSION['_last_activity'] = time();
        return;
    }

    if (asset_is_api_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($authenticated ? 403 : 401);
        echo json_encode([
            'success' => false,
            'message' => $authenticated ? 'You are not authorized to perform this action.' : 'Authentication required.',
        ]);
        exit;
    }

    header('Location: ../login_v2.php');
    exit;
}

function asset_csrf_token(): string
{
    asset_start_session();
    return $_SESSION['_asset_csrf_token'];
}

function asset_csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(asset_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function asset_csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . htmlspecialchars(asset_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function asset_validate_csrf(): void
{
    asset_start_session();

    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    $expected = $_SESSION['_asset_csrf_token'] ?? '';

    if ($expected !== '' && is_string($provided) && hash_equals($expected, $provided)) {
        return;
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'message' => 'Your security token expired. Refresh the page and try again.',
    ]);
    exit;
}

function asset_require_post(bool $validateCsrf = true): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
        exit;
    }

    if ($validateCsrf) {
        asset_validate_csrf();
    }
}

