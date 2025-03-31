<?php
require_once 'db.php';

// Check if request is POST and has required data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['po_number']) && isset($_POST['status'])) {
    $po_number = mysqli_real_escape_string($conn, $_POST['po_number']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Update the status in the database
    $query = "UPDATE Maam_mariecris_payments SET status = ? WHERE po = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $status, $po_number);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>
