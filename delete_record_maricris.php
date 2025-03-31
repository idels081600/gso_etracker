<?php
require_once 'db.php';
header('Content-Type: application/json');

if (isset($_POST['id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
   
    // Begin transaction
    mysqli_begin_transaction($conn);
   
    try {
        // First, get the record details to update the PO balance
        $get_record_query = "SELECT PO_no, total FROM Maam_mariecris WHERE id = ?";
        $get_record_stmt = mysqli_prepare($conn, $get_record_query);
        mysqli_stmt_bind_param($get_record_stmt, "i", $id);
        mysqli_stmt_execute($get_record_stmt);
        $result = mysqli_stmt_get_result($get_record_stmt);
        $record = mysqli_fetch_assoc($result);
        mysqli_stmt_close($get_record_stmt);
       
        // If the record has a PO_no and total, update the PO balance
        if (!empty($record['PO_no']) && !empty($record['total'])) {
            $po_no = $record['PO_no'];
            $total = $record['total'];
           
            // Get current PO payment details
            $get_po_query = "SELECT amount, used_amount FROM Maam_mariecris_payments WHERE po = ?";
            $get_po_stmt = mysqli_prepare($conn, $get_po_query);
            mysqli_stmt_bind_param($get_po_stmt, "s", $po_no);
            mysqli_stmt_execute($get_po_stmt);
            $po_result = mysqli_stmt_get_result($get_po_stmt);
            $po_data = mysqli_fetch_assoc($po_result);
            mysqli_stmt_close($get_po_stmt);
            
            if ($po_data) {
                $original_amount = $po_data['amount'];
                $current_used_amount = $po_data['used_amount'];
                
                // Calculate new values with safeguards
                $new_used_amount = max(0, $current_used_amount - $total); // Ensure used_amount doesn't go below 0
                $new_balance = min($original_amount, $original_amount - $new_used_amount); // Ensure balance doesn't exceed original amount
                
                // Update the Maam_mariecris_payments table with the calculated values
                $update_po_query = "UPDATE Maam_mariecris_payments 
                                   SET balance = ?, used_amount = ? 
                                   WHERE po = ?";
                $update_po_stmt = mysqli_prepare($conn, $update_po_query);
                mysqli_stmt_bind_param($update_po_stmt, "dds", $new_balance, $new_used_amount, $po_no);
               
                if (!mysqli_stmt_execute($update_po_stmt)) {
                    throw new Exception("Error updating PO balance: " . mysqli_error($conn));
                }
                mysqli_stmt_close($update_po_stmt);
            }
        }
       
        // Now delete the record
        $delete_query = "DELETE FROM Maam_mariecris WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $id);
       
        if (!mysqli_stmt_execute($delete_stmt)) {
            throw new Exception("Error deleting record: " . mysqli_error($conn));
        }
        mysqli_stmt_close($delete_stmt);
       
        // Commit the transaction
        mysqli_commit($conn);
       
        echo json_encode(['status' => 'success']);
    }
    catch (Exception $e) {
        // Rollback the transaction on error
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
