<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$servername = $_ENV['DB_HOST'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];
$dbname = $_ENV['DB_DATABASE'];

// Attempt to establish a connection to the database
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check if the connection was successful
if (!$conn) {
    error_log(date('[Y-m-d H:i:s] ') . 'Database connection failed: ' . mysqli_connect_error() . PHP_EOL, 3, rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'payables_error.log');
    http_response_code(500);
    echo "Database connection failed. Please try again later.";
    exit();
}

// Set the character set
$conn->set_charset("utf8mb4");
?>
