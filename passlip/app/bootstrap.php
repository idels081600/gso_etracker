<?php
declare(strict_types=1);

use Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/.env')) {
    Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Manila');

$fallbackSessionPath = $_ENV['SESSION_SAVE_PATH'] ?? dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($fallbackSessionPath)) {
    mkdir($fallbackSessionPath, 0775, true);
}

$sessionPath = session_save_path();
if (in_array(PHP_SAPI, ['cli', 'cli-server'], true) || $sessionPath === '' || !is_dir($sessionPath) || !is_writable($sessionPath)) {
    session_save_path($fallbackSessionPath);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

spl_autoload_register(function (string $class): void {
    $prefix = 'PassSlip\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
    $path = __DIR__ . DIRECTORY_SEPARATOR . $relative;
    if (is_file($path)) {
        require_once $path;
    }
});

function config_value(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_user(): array
{
    return [
        'username' => $_SESSION['username'] ?? null,
        'name' => $_SESSION['pay_name'] ?? $_SESSION['username'] ?? 'Signed-out user',
        'role' => $_SESSION['role'] ?? null,
    ];
}

function role_area(?string $role = null): string
{
    $role = $role ?? current_user()['role'];
    return match ($role) {
        'Employee', 'TCWS Employee', 'CVIRAA' => 'employee',
        'Desk Clerk' => 'desk',
        'Super Admin' => 'super_admin',
        'Admin', 'Department Head' => 'approver',
        default => 'guest',
    };
}

function is_authenticated(): bool
{
    return !empty($_SESSION['username']);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', (string) $token)) {
        http_response_code(419);
        exit('Invalid security token.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function app_url(array $params = []): string
{
    $base = strtok($_SERVER['SCRIPT_NAME'], '?');
    return $base . ($params ? '?' . http_build_query($params) : '');
}

function redirect_to(array $params = []): never
{
    header('Location: ' . app_url($params));
    exit;
}

function render_view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewPath = __DIR__ . '/Views/' . $template . '.php';
    ob_start();
    require $viewPath;
    $content = ob_get_clean();
    require __DIR__ . '/Views/layout.php';
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}
