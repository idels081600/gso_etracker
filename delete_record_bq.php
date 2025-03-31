<?php

require_once 'db.php';

header('Content-Type: application/json');


if (isset($_POST['id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);


    $query = "DELETE FROM bq WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);


    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
}
