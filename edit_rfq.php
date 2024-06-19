<?php
include 'db_asset.php'; // Include your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $rfq_no = $_POST['rfq_no'];
    $pr_no = $_POST['pr_no'];
    $rfq_name = $_POST['rfq_name'];
    $date = $_POST['date'];
    $amount = $_POST['amount'];
    $requestor = $_POST['requestor'];
    $supplier = $_POST['supplier'];
    $status = $_POST['status'];
    $query = "UPDATE RFQ SET 
              rfq_no = '$rfq_no', 
              pr_no = '$pr_no', 
              rfq_name = '$rfq_name', 
              date = '$date', 
              amount = '$amount', 
              requestor = '$requestor', 
              supplier = '$supplier'
              WHERE id = $id";

    if ($conn->query($query) === TRUE) {
        echo 'success';
    } else {
        echo 'error: ' . $conn->error;
    }

    $conn->close();
} else {
    echo 'error: Invalid request method';
}
