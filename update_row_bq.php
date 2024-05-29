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

    // Update the row in the database
    $query = "UPDATE bq SET SR_DR = ?, date = ?, requestor = ?, activity = ?, description = ?, quantity = ?, amount = ? WHERE id = ?";
    $stmt = $conn->prepare($query);

    // Debug: Check if the statement was prepared successfully
    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('sssssssi', $sr_no, $date, $requestor,  $activity,  $description,  $quantity,  $amount, $id);

    // Debug: Check if the parameters were bound successfully
    if (!$stmt->execute()) {
        die('Execute failed: ' . $stmt->error);
    } else {
        echo 'Row updated successfully.';
    }

    $stmt->close();
    $conn->close();
}
