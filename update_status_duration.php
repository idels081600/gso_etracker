<?php
// Include your database connection file
include 'db_asset.php';

// Check if data is received via POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['redDates'])) {
    // Decode the JSON data received from JavaScript
    $redDates = json_decode($_POST['redDates'], true);

    // Iterate over the redDates array
    foreach ($redDates as $data) {
        $id = mysqli_real_escape_string($conn, $data['id']);
        $tent_no = mysqli_real_escape_string($conn, $data['tent_no']);
        $status = 'For Retrieval'; // Set status to "For Retrieval"

        // Update query for tent table
        $queryTent = "UPDATE tent SET status = '$status' WHERE id = '$id'";
        if (mysqli_query($conn, $queryTent)) {
            echo "Status updated successfully in tent table for ID $id\n";
        } else {
            echo "Error updating status in tent table for ID $id: " . mysqli_error($conn) . "\n";
        }

        // Retrieve tent_no data from tent table
        // Split tent_no data into array if needed (e.g., if tent_no is a comma-separated string)
        $tentNos = explode(',', $tent_no); // Assuming tent_no is in a comma-separated string

        // Update query for tent_status table
        foreach ($tentNos as $tentNo) {
            $tentNo = trim($tentNo); // Remove any extra spaces
            $queryTentStatus = "UPDATE tent_status SET Status = '$status' WHERE id = '$tentNo'";
            if (mysqli_query($conn, $queryTentStatus)) {
                echo "Status updated successfully for tent_no $tentNo in tent_status table\n";
            } else {
                echo "Error updating status for tent_no $tentNo in tent_status table: " . mysqli_error($conn) . "\n";
            }
        }
    }
} else {
    // If not a POST request or no redDates data, return an error
    echo "Error: Invalid request or missing data\n";
}
