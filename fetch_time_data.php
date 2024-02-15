<?php
// Include your database connection code here
require_once 'dbh.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scannedData = mysqli_real_escape_string($conn, $_POST['scannedData']);

    // Query to fetch time_returned and esttime
    $query = "SELECT time_returned, esttime FROM request WHERE name = '$scannedData' AND Status = 'Approved'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        // Fetch the time data
        $row = mysqli_fetch_assoc($result);

        // Prepare the data as an associative array
        $timeData = array(
            'time_returned' => $row['time_returned'],
            'esttime' => $row['esttime']
        );

        // Convert the array to JSON and echo it
        echo json_encode($timeData);
    } else {
        // No data found
        echo 'error';
    }
} else {
    // Invalid request method
    echo 'invalid_request';
}
?>
