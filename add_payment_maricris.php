<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_name = mysqli_real_escape_string($conn, $_POST['payment_name']);
    $payment_amount = mysqli_real_escape_string($conn, $_POST['payment_amount']);
   
    // Set the initial balance equal to the payment amount
    // Also initialize used_amount as 0
    $query = "INSERT INTO Maam_mariecris_payments (po, amount, balance, used_amount) 
              VALUES ('$payment_name', '$payment_amount', '$payment_amount', 0)";
   
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Payment added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error adding payment']);
    }
}
?>
