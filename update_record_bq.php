<?php

require_once 'db.php';
header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $sr_no = mysqli_real_escape_string($conn, $_POST['sr_no']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $activity = mysqli_real_escape_string($conn, $_POST['activity']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $office = mysqli_real_escape_string($conn, $_POST['office']);
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);


    $query = "UPDATE bq SET
              SR_DR = ?,
              date = ?,
              quantity = ?,
              description = ?,
              activity = ?,
              amount = ?,
              requestor = ?,
              supplier = ?,
              remarks = ?
              WHERE id = ?";


    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssssdsssi",
        $sr_no, $date, $quantity, $description, $activity, $amount,
        $office, $supplier, $remarks, $id
    );


    $response = array();
    if(mysqli_stmt_execute($stmt)) {
        $response['status'] = 'success';
    } else {
        $response['status'] = 'error';
        $response['message'] = mysqli_error($conn);
    }
   
    echo json_encode($response);
    mysqli_stmt_close($stmt);
    exit;
}
?>
