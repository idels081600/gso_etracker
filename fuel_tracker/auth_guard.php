<?php
declare(strict_types=1);

function fuelAuthStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (empty($_SESSION['_fuel_auth_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['_fuel_auth_regenerated'] = true;
    }
}

function fuelAuthSecurityHeaders(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

function fuelAuthRole(): string
{
    fuelAuthStartSession();
    return strtolower(trim((string) ($_SESSION['role'] ?? '')));
}

function fuelAuthCsrfToken(): string
{
    fuelAuthStartSession();

    $token = $_SESSION['_fuel_csrf_token'] ?? null;
    if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_fuel_csrf_token'] = $token;
    }

    return $token;
}

function requireFuelCsrfToken(mixed $submittedToken, string $responseType = 'json'): void
{
    fuelAuthStartSession();
    fuelAuthSecurityHeaders();

    $sessionToken = $_SESSION['_fuel_csrf_token'] ?? '';
    $submittedToken = is_string($submittedToken) ? trim($submittedToken) : '';

    if (
        is_string($sessionToken)
        && $sessionToken !== ''
        && $submittedToken !== ''
        && hash_equals($sessionToken, $submittedToken)
    ) {
        return;
    }

    http_response_code(403);

    if ($responseType === 'json') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Security check failed. Refresh the page and try again.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Security check failed. Refresh the page and try again.';
    exit;
}

function requireFuelRole(array|string $roles, string $responseType = 'html'): void
{
    fuelAuthStartSession();
    fuelAuthSecurityHeaders();

    $allowedRoles = array_map(
        static fn(string $role): string => strtolower(trim($role)),
        is_array($roles) ? $roles : [$roles]
    );

    $isLoggedIn = !empty($_SESSION['logged_in']);
    $currentRole = fuelAuthRole();
    if ($isLoggedIn && ($currentRole !== '') && in_array($currentRole, $allowedRoles, true)) {
        return;
    }

    $statusCode = $isLoggedIn ? 403 : 401;
    http_response_code($statusCode);

    if ($responseType === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $isLoggedIn ? 'You are not allowed to access this resource.' : 'Please log in to continue.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($responseType === 'text') {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $isLoggedIn ? 'Forbidden.' : 'Please log in to continue.';
        exit;
    }

    $loginPath = '../login_v2.php';
    if (!$isLoggedIn && !headers_sent()) {
        header('Location: ' . $loginPath);
        exit;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Access denied</title></head><body><h1>Access denied</h1><p>You are not allowed to access this page.</p></body></html>';
    exit;
}

function requireFuelAjaxRequest(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($requestedWith === 'xmlhttprequest') {
        return;
    }

    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request source.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
