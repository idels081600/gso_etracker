<?php
$envPath = __DIR__ . '/.env';
$env = file_exists($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : false;

if ($env === false) {
    echo "Database configuration file is missing.";
    exit();
}

$servername = $env['DB_HOST'] ?? '';
$username = $env['DB_USERNAME'] ?? '';
$password = $env['DB_PASSWORD'] ?? '';
$dbname = $env['DB_NAME'] ?? '';

if ($servername === '' || $username === '' || $dbname === '') {
    echo "Database configuration is incomplete.";
    exit();
}

// Attempt to establish a connection to the database
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check if the connection was successful
if (!$conn) {
    error_log('Asset database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    echo "Database service is unavailable.";
    exit(); // Exit the script to prevent further execution
}

// Set the character set
$conn->set_charset("utf8mb4");
