<?php
// Include your database connection (which uses mysqli)
require_once 'leave_db.php';

// Check if the POST request contains the necessary data
if (isset($_POST['title']) && isset($_POST['name']) && isset($_POST['dates'])) {
    $leaveTitle = $_POST['title'];
    $leaveName = $_POST['name'];
    $leaveDates = $_POST['dates'];  // Store dates as VARCHAR

    // Prepare SQL to insert data into the leave_reg table (exclude remark and status columns)
    $sql = "INSERT INTO leave_reg (title,name, dates) VALUES (?,?, ?)";

    // Use mysqli prepare statement
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind parameters (s for string type)
        mysqli_stmt_bind_param($stmt, "sss",   $leaveTitle, $leaveName, $leaveDates);

        // Execute the query and check if it was successful
        if (mysqli_stmt_execute($stmt)) {
            echo "Leave saved successfully!";
        } else {
            echo "Error saving leave: " . mysqli_error($conn);
        }

        // Close the prepared statement
        mysqli_stmt_close($stmt);
    } else {
        echo "Error preparing statement: " . mysqli_error($conn);
    }
} else {
    echo "Missing data.";
}

// Close the database connection
mysqli_close($conn);
