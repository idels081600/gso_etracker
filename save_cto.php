<?php
// Include your database connection file (adjust the path as necessary)
require_once 'leave_db.php';

// Check if the form is submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve data from the form
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $dates = mysqli_real_escape_string($conn, $_POST['dates']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    // Prepare an SQL statement to insert the data into the CTO table
    $query = "INSERT INTO cto (title, name, dates, remarks) 
              VALUES ('$title', '$name', '$dates', '$remarks')";

    // Execute the query
    if (mysqli_query($conn, $query)) {
        echo 'success'; // Return success if data is saved
    } else {
        echo 'error'; // Return error if the query fails
    }
} else {
    echo 'invalid_request'; // Return error if the request method is not POST
}

// Close the database connection
mysqli_close($conn);
