<?php
// Include the database connection file
include 'db.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve POST data
    $id = $_POST['id'];
    $sr_no = $_POST['sr_no'];
    $date = $_POST['date'];
    $quantity = $_POST['quantity'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $office = $_POST['office'];
    $vehicle = $_POST['vehicle'];
    $plate = $_POST['plate'];

    // Prepare and execute the update query
    $query = "UPDATE sir_bayong SET SR_DR = ?, Date = ?, Quantity = ?, Description = ?, Amount = ?, Office = ?, Vehicle = ?, Plate = ? WHERE id = ?";
    $stmt = $conn->prepare($query);

    // Bind parameters to the statement
    $stmt->bind_param('ssssssssi', $sr_no, $date, $quantity, $description, $amount, $office, $vehicle, $plate, $id);

    // Execute the query
    if ($stmt->execute()) {
        echo 'Row updated successfully.';
    } else {
        echo 'Failed to update row: ' . $conn->error;
    }

    // Close statement
    $stmt->close();

    // Close database connection
    $conn->close();
} else {
    // If the request method is not POST, display an error message
    echo 'Invalid request method.';
}
