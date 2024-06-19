<?php
// Include your database connection file
require_once 'db_asset.php';

// Check if the plate number is sent via POST request
if (isset($_POST['plate_no'])) {
    // Sanitize the input
    $plate_no = mysqli_real_escape_string($conn, $_POST['plate_no']);

    // Query to fetch the name of the vehicle based on the plate number
    $query = "SELECT Name FROM Vehicle WHERE Plate_No = '$plate_no'";
    $result = mysqli_query($conn, $query);

    // Check if the query was successful and if it returned any rows
    if ($result && mysqli_num_rows($result) > 0) {
        // Fetch the row as an associative array
        $row = mysqli_fetch_assoc($result);
        // Return the name of the vehicle
        echo $row['Name'];
    } else {
        // No vehicle found for the given plate number
        echo 'Unknown'; // You can change this to whatever default value you prefer
    }
} else {
    // Plate number not provided
    echo 'Plate number not provided';
}
