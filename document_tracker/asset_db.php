<?php
require_once __DIR__ . '/vendor/autoload.php';

$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    die('Missing .env file. Copy .env.example to .env and set your database credentials.');
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$servername = $_ENV['DB_HOST'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$dbname = $_ENV['DB_NAME'];

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
