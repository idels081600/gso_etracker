<?php
// Database connection for Fuel Subsidy System
$host = '157.245.193.124';
$username = 'bryanmysql';
$password = 'gsotagbilaran';
$database = 'subsidy';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8
mysqli_set_charset($conn, "utf8");

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>