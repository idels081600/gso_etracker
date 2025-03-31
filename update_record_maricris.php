<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $sr_no = mysqli_real_escape_string($conn, $_POST['sr_no']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $activity = mysqli_real_escape_string($conn, $_POST['activity']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $office = mysqli_real_escape_string($conn, $_POST['office']);
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    $payment = mysqli_real_escape_string($conn, $_POST['payment']);

    // Calculate total
    $total = floatval($quantity) * floatval($amount);

    // First, get the current record's PO information
    $current_query = "SELECT PO_no, PO_amount, total FROM Maam_mariecris WHERE id = ?";
    $current_stmt = mysqli_prepare($conn, $current_query);
    mysqli_stmt_bind_param($current_stmt, "i", $id);
    mysqli_stmt_execute($current_stmt);
    $current_result = mysqli_stmt_get_result($current_stmt);
    $current_data = mysqli_fetch_assoc($current_result);
    $current_po = $current_data['PO_no'];
    $current_po_amount = $current_data['PO_amount'];
    $current_total = $current_data['total'];
    mysqli_stmt_close($current_stmt);

    // If payment is empty, use the current PO
    if (empty($payment)) {
        $payment = $current_po;
        $payment_amount = $current_po_amount;
    } else {
        // Get the payment amount from the Maam_mariecris_payments table
        $payment_query = "SELECT amount FROM Maam_mariecris_payments WHERE po = ?";
        $payment_stmt = mysqli_prepare($conn, $payment_query);
        mysqli_stmt_bind_param($payment_stmt, "s", $payment);
        mysqli_stmt_execute($payment_stmt);
        $payment_result = mysqli_stmt_get_result($payment_stmt);
        $payment_data = mysqli_fetch_assoc($payment_result);
        $payment_amount = $payment_data['amount'] ?? $current_po_amount;
        mysqli_stmt_close($payment_stmt);
    }

    // Begin transaction
    mysqli_begin_transaction($conn);

    try {
        // Update the record
        $query = "UPDATE Maam_mariecris SET
            SR_DR = ?,
            date = ?,
            activity = ?,
            no_of_pax = ?,
            amount = ?,
            total = ?,
            department = ?,
            store = ?,
            remarks = ?,
            PO_no = ?,
            PO_amount = ?
            WHERE id = ?";

        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param(
            $stmt,
            "sssdddssssdi",
            $sr_no,
            $date,
            $activity,
            $quantity,
            $amount,
            $total,
            $office,
            $supplier,
            $remarks,
            $payment,
            $payment_amount,
            $id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating record: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt);

        // If PO has changed or total amount has changed, update the balances
        if ($current_po != $payment || $current_total != $total) {
            // If PO has changed, restore the balance of the old PO
            if ($current_po != $payment && !empty($current_po)) {
                $update_old_po = "UPDATE Maam_mariecris_payments 
                                  SET balance = balance + ?, used_amount = used_amount - ? 
                                  WHERE po = ?";
                $old_po_stmt = mysqli_prepare($conn, $update_old_po);
                mysqli_stmt_bind_param($old_po_stmt, "dds", $current_total, $current_total, $current_po);
                
                if (!mysqli_stmt_execute($old_po_stmt)) {
                    throw new Exception("Error updating old PO balance: " . mysqli_error($conn));
                }
                mysqli_stmt_close($old_po_stmt);
            }

            // Update the balance of the new PO
            if (!empty($payment)) {
                $update_new_po = "UPDATE Maam_mariecris_payments 
                                  SET balance = balance - ?, used_amount = used_amount + ? 
                                  WHERE po = ?";
                $new_po_stmt = mysqli_prepare($conn, $update_new_po);
                mysqli_stmt_bind_param($new_po_stmt, "dds", $total, $total, $payment);
                
                if (!mysqli_stmt_execute($new_po_stmt)) {
                    throw new Exception("Error updating new PO balance: " . mysqli_error($conn));
                }
                mysqli_stmt_close($new_po_stmt);
            }
        }

        // Commit transaction
        mysqli_commit($conn);
        $response['status'] = 'success';
    } 
    catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $response['status'] = 'error';
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
