<?php
include 'db.php'; // Adjust the path to your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $sr_no = $_POST['sr_no'];
    $date = $_POST['date'];
    $quantity = $_POST['quantity'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $office = $_POST['office'];
    $vehicle = $_POST['vehicle'];
    $plate = $_POST['plate'];

    // Update the row in the database
    $query = "UPDATE sir_bayong SET SR_DR = ?, Date = ?, Quantity = ?, Description = ?, Amount = ?, Office = ?, Vehicle = ?, Plate = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssssssi', $sr_no, $date, $quantity, $description, $amount, $office, $vehicle, $plate, $id);

    if ($stmt->execute()) {
        echo 'Row updated successfully.';
    } else {
        echo 'Failed to update row.';
    }

    $stmt->close();
    $conn->close();
}
