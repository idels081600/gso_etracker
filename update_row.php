<?php
include 'db.php'; // Adjust the path to your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debugging: print the POST data
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';

    $id = $_POST['id'];
    $sr_no = $_POST['sr_no'];
    $date = $_POST['date'];
    $quantity = $_POST['quantity'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $office = $_POST['office'];
    $vehicle = $_POST['vehicle'];
    $plate = $_POST['plate'];
    $supplier = $_POST['supplier'];

    // Debugging: check if variables have values
    echo "ID: $id<br>";
    echo "SR No: $sr_no<br>";
    echo "Date: $date<br>";
    echo "Quantity: $quantity<br>";
    echo "Description: $description<br>";
    echo "Amount: $amount<br>";
    echo "Office: $office<br>";
    echo "Vehicle: $vehicle<br>";
    echo "Plate: $plate<br>";
    echo "Supplier: $supplier<br>";

    // Update the row in the database
    $query = "UPDATE sir_bayong SET SR_DR = ?, Date = ?, Supplier = ?, Description = ?, Quantity = ?,  Amount = ?, Office = ?, Vehicle = ?, Plate = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssssssssi', $sr_no, $date, $supplier, $description, $quantity, $amount, $office, $vehicle, $plate, $id);

    if ($stmt->execute()) {
        echo 'Row updated successfully.';
    } else {
        echo 'Failed to update row: ' . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
