<?php
require_once 'db.php'; // Ensure this file contains your database connection code

$data = json_decode(file_get_contents("php://input"), true);

$payment_name = $data['payment_name'];
$payment_amount = $data['payment_amount'];

$query = "INSERT INTO bq_payments (name, amount) VALUES (?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $payment_name, $payment_amount);

if ($stmt->execute()) {
    echo "Payment saved successfully";
} else {
    http_response_code(500);
    echo "Error saving payment";
}

$stmt->close();
$conn->close();
?>