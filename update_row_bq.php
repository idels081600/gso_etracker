<?php
include 'db.php'; // Adjust the path to your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $sr_no = $_POST['sr_no'];
    $date = $_POST['date'];
    $quantity = intval($_POST['quantity']);
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $requestor = $_POST['requestor'];
    $activity = $_POST['activity'];
    $supplier = $_POST['supplier'];

    // Validate the input data
    if (empty($id) || empty($sr_no) || empty($date) || empty($supplier) || empty($requestor) || empty($activity) || empty($description) || empty($quantity) || empty($amount)) {
        die('All fields are required.');
    }

    // Check if ID is an integer
    if (!filter_var($id, FILTER_VALIDATE_INT)) {
        die('Invalid ID.');
    }

    // Update the row in the database
    $query = "UPDATE bq SET SR_DR = ?, date = ?, supplier = ?, requestor = ?, activity = ?, description = ?, quantity = ?, amount = ? WHERE id = ?";
    $stmt = $conn->prepare($query);

    // Debug: Check if the statement was prepared successfully
    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }

    // Bind parameters (correcting the number of 's' and 'i' types for bind_param)
    $stmt->bind_param('ssssssisi', $sr_no, $date, $supplier, $requestor, $activity, $description, $quantity, $amount, $id);

    // Debug: Check if the parameters were bound successfully
    if (!$stmt->execute()) {
        die('Execute failed: ' . $stmt->error);
    } else {
        echo 'Row updated successfully.';
    }

    $stmt->close();
    $conn->close();
}
