<?php
$servername = "157.245.193.124";
$username = "bryanmysql";
$password = "gsotagbilaran";
$dbname = "leave_db";

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
?>
