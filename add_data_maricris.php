<?php
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_data'])) {
    // Existing form data
    $sr_no = mysqli_real_escape_string($conn, $_POST['sr_no']);
    $date = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_POST['date'])));
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $activity = mysqli_real_escape_string($conn, $_POST['activity']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $department = mysqli_real_escape_string($conn, $_POST['office']);
    $store = mysqli_real_escape_string($conn, $_POST['supplier']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
   
    // Calculate total amount
    $total = $quantity * $amount;
   
    // Get payment data
    $payment = mysqli_real_escape_string($conn, $_POST['payment']);
   
    // Get payment amount and current used_amount from Maam_mariecris_payments table
    $payment_query = "SELECT amount, used_amount FROM Maam_mariecris_payments WHERE po = ?";
    $stmt_payment = mysqli_prepare($conn, $payment_query);
    mysqli_stmt_bind_param($stmt_payment, "s", $payment);
    mysqli_stmt_execute($stmt_payment);
    $payment_result = mysqli_stmt_get_result($stmt_payment);
    $payment_data = mysqli_fetch_assoc($payment_result);
    $payment_amount = $payment_data['amount'] ?? 0;
    $current_used_amount = $payment_data['used_amount'] ?? 0;
   
    // Calculate new balance by subtracting total from payment_amount
    $new_balance = $payment_amount - $total;
    
    // Calculate new used amount by adding the total to current used amount
    $new_used_amount = $current_used_amount + $total;
   
    // Begin transaction
    mysqli_begin_transaction($conn);
   
    try {
        // Insert into Maam_mariecris table
        $query = "INSERT INTO Maam_mariecris (SR_DR, date, department, store, activity, no_of_pax, amount, total, PO_no, PO_amount, Remarks)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
       
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssssssddsds", $sr_no, $date, $department, $store, $activity, $quantity, $amount, $total, $payment, $payment_amount, $remarks);
       
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error inserting into Maam_mariecris: " . mysqli_error($conn));
        }
       
        // Update the balance and used_amount in Maam_mariecris_payments table
        $update_query = "UPDATE Maam_mariecris_payments SET balance = ?, used_amount = ? WHERE po = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "dds", $new_balance, $new_used_amount, $payment);
       
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Error updating balance: " . mysqli_error($conn));
        }
       
        // Commit transaction
        mysqli_commit($conn);
       
        header("Location: maam_maricris.php");
        exit();
    }
    catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $error = "Error: " . $e->getMessage();
        echo "<script>alert('$error');</script>";
    }
   
    mysqli_stmt_close($stmt);
}
?>
