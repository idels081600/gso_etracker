<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_data";
$conn = mysqli_connect($servername,$username,$password,$dbname);

if(!$conn) {
    echo "connection failed";
    exit();
}
$conn->set_charset("utf8mb4");
?>