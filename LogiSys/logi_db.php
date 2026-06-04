<?php
function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function envValue(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    return $_ENV[$key] ?? $default;
}

loadEnvFile(__DIR__ . '/.env');

$servername = envValue('DB_HOST');
$username = envValue('DB_USER');
$password = envValue('DB_PASS', '');
$dbname = envValue('DB_NAME');

$missing = [];
foreach (['DB_HOST' => $servername, 'DB_USER' => $username, 'DB_NAME' => $dbname] as $key => $value) {
    if ($value === null || $value === '') {
        $missing[] = $key;
    }
}

if (!empty($missing)) {
    error_log('Missing database environment variables: ' . implode(', ', $missing));
    echo 'Database configuration is incomplete.';
    exit();
}

// Attempt to establish a connection to the database
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check if the connection was successful
if (!$conn) {
    // If connection fails, output the error message
    echo "Connection failed: " . mysqli_connect_error();
    exit(); // Exit the script to prevent further execution
}

// Set the character set
$conn->set_charset("utf8mb4");
 
