<?php
// Include your database connection file
include 'db_asset.php';

// Check if form is submitted via POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input to prevent SQL injection
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Update query for tent table
    $queryTent = "UPDATE tent SET status = '$status' WHERE id = '$id'";

    // Execute query for tent table
    if (mysqli_query($conn, $queryTent)) {
        echo "Status updated successfully in tent table\n";
    } else {
        echo "Error updating status in tent table: " . mysqli_error($conn) . "\n";
    }

    // Retrieve tent_no data from tent table and make it an array
    $queryTentNo = "SELECT tent_no FROM tent WHERE id = '$id'";
    $result = mysqli_query($conn, $queryTentNo);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $tentNos = explode(',', $row['tent_no']); // Split tent_no data into array
    } else {
        echo "Error retrieving tent_no data: " . mysqli_error($conn) . "\n";
    }

    // Update query for tent_status table
    if (isset($tentNos)) {
        foreach ($tentNos as $tentNo) {
            $tentStatus = $status; // default status to $status
            // if ($status == "Retrieved") {
            //     $tentStatus = "Retrieved"; // Set status to "on" if $status is "Retrieved"
            // }
            $queryTentStatus = "UPDATE tent_status SET Status = '$tentStatus' WHERE id = ' $tentNo'";
            if (mysqli_query($conn, $queryTentStatus)) {
                echo "Status updated successfully for tent_no $tentNo in tent_status table\n";
            } else {
                echo "Error updating status for tent_no $tentNo in tent_status table: " . mysqli_error($conn) . "\n";
            }
        }
    }
} else {
    // If not a POST request, return an error
    echo "Error: Invalid request method\n";
}
