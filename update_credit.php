<?php
// update_credit.php
require_once 'dbh.php'; // Include your database connection

if (isset($_POST['id']) && isset($_POST['credits'])) {
    $employeeId = (int)$_POST['id'];
    $updatedCredits = (int)$_POST['credits'];

    // Prepare the query to update the credits
    $query = "UPDATE logindb SET credits = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $updatedCredits, $employeeId); // Bind the values

    // Execute the query
    if ($stmt->execute()) {
        echo 'success'; // Return success if the update was successful
    } else {
        echo 'error'; // Return error if something went wrong
    }
} else {
    echo 'error'; // Return error if the required parameters are not provided
}
