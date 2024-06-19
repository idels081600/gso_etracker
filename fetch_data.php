<?php
// Include your database connection file
include 'db_asset.php';

// Check if the ID parameter is set
if (isset($_GET['id'])) {
    // Sanitize the input to prevent SQL injection
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // Perform the database query to fetch the data
    $query = "SELECT * FROM tent WHERE id='$id'";
    $result = mysqli_query($conn, $query);

    // Check if the query was successful and if data was found
    if ($result && mysqli_num_rows($result) > 0) {
        // Fetch the data as an associative array
        $row = mysqli_fetch_assoc($result);

        // Return the data as JSON
        echo json_encode($row);
    } else {
        // If no data found, return an empty JSON object
        echo json_encode((object)[]);
    }
} else {
    // If no ID parameter provided, return an empty JSON object
    echo json_encode((object)[]);
}
