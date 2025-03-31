<?php
require_once 'db.php';

// Set proper content type for JSON response
header('Content-Type: application/json');

if(isset($_POST['ids']) && isset($_POST['po_no']) && isset($_POST['po_amount'])) {
    $ids = array_map('intval', $_POST['ids']);
    $idList = implode(',', $ids);
    $po_no = mysqli_real_escape_string($conn, $_POST['po_no']);
    $po_amount = str_replace(',', '', $_POST['po_amount']);
    $po_amount = floatval($po_amount);
   
    // Begin transaction
    mysqli_begin_transaction($conn);
   
    try {
        // First, calculate the total sum of all selected records
        $total_query = "SELECT SUM(total) as total_sum FROM Maam_mariecris_print WHERE id IN ($idList)";
        $total_result = mysqli_query($conn, $total_query);
       
        if (!$total_result) {
            throw new Exception("Error calculating total: " . mysqli_error($conn));
        }
       
        $total_row = mysqli_fetch_assoc($total_result);
        $total_sum = floatval($total_row['total_sum']);
       
        // Calculate the remaining balance
        $balance = $po_amount - $total_sum;
       
        // If balance would be negative, throw an error
        if ($balance < 0) {
            throw new Exception("Total amount exceeds PO amount. Cannot proceed.");
        }
       
        // Update the selected records with the new PO information
        $update_query = "UPDATE Maam_mariecris_print
                  SET PO_no = '$po_no',
                      PO_amount = '$po_amount'
                  WHERE id IN ($idList)";
       
        $update_result = mysqli_query($conn, $update_query);
       
        if (!$update_result) {
            throw new Exception("Error updating records: " . mysqli_error($conn));
        }
       
        // Check if this PO already exists in the payments table
        $check_query = "SELECT * FROM Maam_mariecris_payments WHERE po = '$po_no'";
        $check_result = mysqli_query($conn, $check_query);
       
        if (!$check_result) {
            throw new Exception("Error checking PO existence: " . mysqli_error($conn));
        }
       
        if (mysqli_num_rows($check_result) > 0) {
            // Get current PO data
            $po_data = mysqli_fetch_assoc($check_result);
            $current_used_amount = floatval($po_data['used_amount']);
            
            // Update the PO with the new balance and used_amount
            // Explicitly using po = '$po_no' in the WHERE clause to ensure we update the correct record
            $update_po_query = "UPDATE Maam_mariecris_payments
                               SET balance = '$balance',
                                   used_amount = '$total_sum',
                                   amount = '$po_amount'
                               WHERE po = '$po_no'";
        } else {
            // PO doesn't exist, insert a new record with the specified PO_no
            $update_po_query = "INSERT INTO Maam_mariecris_payments
                               (po, amount, balance, used_amount)
                               VALUES ('$po_no', '$po_amount', '$balance', '$total_sum')";
        }
       
        $update_po_result = mysqli_query($conn, $update_po_query);
       
        if (!$update_po_result) {
            throw new Exception("Error updating PO payment record: " . mysqli_error($conn));
        }

        // Get the details of the records in the print table
        $get_print_details_query = "SELECT date, activity, total, store FROM Maam_mariecris_print WHERE id IN ($idList)";
        $get_print_details_result = mysqli_query($conn, $get_print_details_query);

        if (!$get_print_details_result) {
            throw new Exception("Error retrieving print details: " . mysqli_error($conn));
        }

        // For each print record, find and update the matching record in the original table
        while ($print_record = mysqli_fetch_assoc($get_print_details_result)) {
            $date = mysqli_real_escape_string($conn, $print_record['date']);
            $activity = mysqli_real_escape_string($conn, $print_record['activity']);
            $total = floatval($print_record['total']);
            $store = mysqli_real_escape_string($conn, $print_record['store']);
            
            // Create a query to find the matching record in the original table
            $update_original_query = "UPDATE Maam_mariecris
                             SET PO_no = '$po_no',
                                 PO_amount = '$po_amount'
                             WHERE date = '$date' 
                             AND activity = '$activity'
                             AND amount = '$total'
                             AND store = '$store'";
            
            $update_original_result = mysqli_query($conn, $update_original_query);
            
            if (!$update_original_result && mysqli_affected_rows($conn) == 0) {
                // Log this but don't throw exception as some records might not match
                error_log("Could not find matching record for date: $date, activity: $activity, amount: $total");
            }
        }
       
        // Commit the transaction
        mysqli_commit($conn);
       
        echo json_encode([
            'success' => true,
            'total_sum' => $total_sum,
            'balance' => $balance,
            'po_no' => $po_no,
            'message' => 'Records and PO information updated successfully'
        ]);
       
    } catch (Exception $e) {
        // Rollback the transaction on error
        mysqli_rollback($conn);
       
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
}
?>
