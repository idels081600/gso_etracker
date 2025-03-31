<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_number = mysqli_real_escape_string($conn, $_POST['po_number']);
    $payment_amount = mysqli_real_escape_string($conn, $_POST['payment_amount']);
    
    $query = "INSERT INTO bq_payments (po, amount) VALUES ('$po_number', '$payment_amount')";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Payment added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error adding payment']);
    }
}
?>
