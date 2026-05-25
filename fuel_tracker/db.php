<?php
declare(strict_types=1);

/*
 * Fuel Tracker database connection.
 *
 * This file lives inside the fuel_tracker folder and connects to the
 * fuel_tracker database. It exposes both $pdo and $conn because the current
 * project still contains a mix of PDO and MySQLi endpoints.
 */

function loadFuelTrackerEnv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

loadFuelTrackerEnv(__DIR__ . '/.env');

$dbHost = getenv('FUEL_DB_HOST') ?: 'localhost';
$dbPort = (int) (getenv('FUEL_DB_PORT') ?: 3306);
$dbName = getenv('FUEL_DB_NAME') ?: 'fuel_tracker';
$dbUser = getenv('FUEL_DB_USER') ?: 'root';
$dbPass = getenv('FUEL_DB_PASS') ?: '';
$dbCharset = getenv('FUEL_DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    error_log('Fuel Tracker database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed.');
}

$conn = mysqli_init();
if (!$conn || !mysqli_real_connect($conn, $dbHost, $dbUser, $dbPass, $dbName, $dbPort)) {
    error_log('Fuel Tracker MySQLi connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    exit('Database connection failed.');
}

$conn->set_charset($dbCharset);

function getPDO(): PDO
{
    global $pdo;
    return $pdo;
}
