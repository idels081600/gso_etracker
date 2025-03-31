<?php
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_data'])) {
    // Existing form data
    $sr_no = mysqli_real_escape_string($conn, $_POST['sr_no']);
    $date = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_POST['date'])));
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $office = mysqli_real_escape_string($conn, $_POST['office']);
    $vehicle = mysqli_real_escape_string($conn, $_POST['vehicle']);
    $plate = mysqli_real_escape_string($conn, $_POST['plate']);
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
    $remarks = strtoupper(mysqli_real_escape_string($conn, $_POST['remarks']));
    
    // Get PO data
    $payment = mysqli_real_escape_string($conn, $_POST['payment']);
    
    // Get PO amount from sir_bayong_payments table
    $po_query = "SELECT amount FROM sir_bayong_payments WHERE po = ?";
    $stmt_po = mysqli_prepare($conn, $po_query);
    mysqli_stmt_bind_param($stmt_po, "s", $payment);
    mysqli_stmt_execute($stmt_po);
    $po_result = mysqli_stmt_get_result($stmt_po);
    $po_data = mysqli_fetch_assoc($po_result);
    $po_amount = $po_data['amount'];

    $query = "INSERT INTO sir_bayong (SR_DR, Date, Supplier, Quantity, Description, Amount, Office, Vehicle, Plate, Remarks, PO_no, PO_amount)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
   
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssssssssssd", $sr_no, $date, $supplier, $quantity, $description, $amount, $office, $vehicle, $plate, $remarks, $payment, $po_amount);
   
    if (mysqli_stmt_execute($stmt)) {
        header("Location: sir_bayong.php");
        exit();
    } else {
        $error = "Error: " . mysqli_error($conn);
        echo "<script>alert('$error');</script>";
    }
   
    mysqli_stmt_close($stmt);
}
?>
