<?php
// Include your database connection file
include 'db_asset.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input to prevent SQL injection
    $tentNumber = mysqli_real_escape_string($conn, $_POST['tentno']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Update query
    $query = "UPDATE tent_status SET Status = '$status' WHERE id = '$tentNumber'";

    // Execute query
    if (mysqli_query($conn, $query)) {
        echo "Status updated successfully for tent number " . $tentNumber;
    } else {
        echo "Error updating status for tent number " . $tentNumber . ": " . mysqli_error($conn);
    }
} else {
    echo "Invalid request method";
}
