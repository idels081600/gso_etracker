<?php
// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);

require_once 'db.php';

// Set JSON header at the very beginning
header('Content-Type: application/json');

// Initialize response array
$response = array();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate required fields
    $required_fields = ['id', 'sr_no', 'date', 'activity', 'quantity', 'amount', 'office', 'supplier', 'remarks'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $sr_no = mysqli_real_escape_string($conn, $_POST['sr_no']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $activity = mysqli_real_escape_string($conn, $_POST['activity']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $office = mysqli_real_escape_string($conn, $_POST['office']);
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    $payment = isset($_POST['payment']) ? mysqli_real_escape_string($conn, $_POST['payment']) : '';

    // Validate numeric fields
    if (!is_numeric($quantity) || !is_numeric($amount)) {
        throw new Exception('Quantity and amount must be numeric values');
    }

    // Calculate total
    $total = floatval($quantity) * floatval($amount);

    // First, get the current record's PO information
    $current_query = "SELECT PO_no, PO_amount, total FROM Maam_mariecris WHERE id = ?";
    $current_stmt = mysqli_prepare($conn, $current_query);
    
    if (!$current_stmt) {
        throw new Exception("Database prepare error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($current_stmt, "i", $id);
    
    if (!mysqli_stmt_execute($current_stmt)) {
        throw new Exception("Database execute error: " . mysqli_stmt_error($current_stmt));
    }

    $current_result = mysqli_stmt_get_result($current_stmt);
    $current_data = mysqli_fetch_assoc($current_result);
    
    if (!$current_data) {
        throw new Exception("Record not found");
    }

    $current_po = $current_data['PO_no'];
    $current_po_amount = $current_data['PO_amount'];
    $current_total = $current_data['total'];
    mysqli_stmt_close($current_stmt);

    // Begin transaction
    if (!mysqli_begin_transaction($conn)) {
        throw new Exception("Failed to begin transaction");
    }

    // Check if the payment value is "delete_payment"
    if ($payment === "delete_payment") {
        // If current PO is not empty, restore its balance
        if (!empty($current_po)) {
            $update_po_balance = "UPDATE Maam_mariecris_payments 
                                 SET balance = balance + ?, used_amount = used_amount - ? 
                                 WHERE po = ?";
            $po_balance_stmt = mysqli_prepare($conn, $update_po_balance);
            
            if (!$po_balance_stmt) {
                throw new Exception("Database prepare error: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($po_balance_stmt, "dds", $current_total, $current_total, $current_po);
            
            if (!mysqli_stmt_execute($po_balance_stmt)) {
                throw new Exception("Error updating PO balance: " . mysqli_stmt_error($po_balance_stmt));
            }
            mysqli_stmt_close($po_balance_stmt);
        }

        // Update the record to remove PO_no and PO_amount
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
            PO_no = '', 
            PO_amount = 0, 
            remaining_balance = 0 
            WHERE id = ?";

        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sssdddsssi",
            $sr_no,
            $date,
            $activity,
            $quantity,
            $amount,
            $total,
            $office,
            $supplier,
            $remarks,
            $id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating record: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // Commit transaction
        if (!mysqli_commit($conn)) {
            throw new Exception("Failed to commit transaction");
        }

        $response['status'] = 'success';
        $response['message'] = "Payment information removed successfully.";

    } else {
        // Original code for normal payment processing
        // If payment is empty, use the current PO
        if (empty($payment)) {
            $payment = $current_po;
            $payment_amount = $current_po_amount;
        } else {
            // Get the payment amount from the Maam_mariecris_payments table
            $payment_query = "SELECT amount, balance FROM Maam_mariecris_payments WHERE po = ?";
            $payment_stmt = mysqli_prepare($conn, $payment_query);
            
            if (!$payment_stmt) {
                throw new Exception("Database prepare error: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($payment_stmt, "s", $payment);
            
            if (!mysqli_stmt_execute($payment_stmt)) {
                throw new Exception("Database execute error: " . mysqli_stmt_error($payment_stmt));
            }

            $payment_result = mysqli_stmt_get_result($payment_stmt);
            $payment_data = mysqli_fetch_assoc($payment_result);
            $payment_amount = $payment_data['amount'] ?? $current_po_amount;
            $payment_balance = $payment_data['balance'] ?? 0;
            mysqli_stmt_close($payment_stmt);
        }

        // REMOVED THE PO BALANCE CHECK - Allow updates regardless of PO balance
        // Calculate the difference between payment amount and total
        $difference = $payment_amount - $total;
        
        // Set remaining_balance (can be negative if overspent)
        $remaining_balance = $difference;
        
        // Update remarks based on balance status
        if ($remaining_balance <= 0) {
            $remarks = "PAID";
        }

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
            PO_amount = ?, 
            remaining_balance = ? 
            WHERE id = ?";

        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sssdddssssddi",
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
            $remaining_balance,
            $id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating record: " . mysqli_stmt_error($stmt));
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
                
                if (!$old_po_stmt) {
                    throw new Exception("Database prepare error: " . mysqli_error($conn));
                }

                mysqli_stmt_bind_param($old_po_stmt, "dds", $current_total, $current_total, $current_po);
                
                if (!mysqli_stmt_execute($old_po_stmt)) {
                    throw new Exception("Error updating old PO balance: " . mysqli_stmt_error($old_po_stmt));
                }
                mysqli_stmt_close($old_po_stmt);
            }

            // Update the balance of the new PO (allow negative balance)
            if (!empty($payment)) {
                $update_new_po = "UPDATE Maam_mariecris_payments 
                                 SET balance = balance - ?, used_amount = used_amount + ? 
                                 WHERE po = ?";
                $new_po_stmt = mysqli_prepare($conn, $update_new_po);
                
                if (!$new_po_stmt) {
                    throw new Exception("Database prepare error: " . mysqli_error($conn));
                }

                mysqli_stmt_bind_param($new_po_stmt, "dds", $total, $total, $payment);
                
                if (!mysqli_stmt_execute($new_po_stmt)) {
                    throw new Exception("Error updating new PO balance: " . mysqli_stmt_error($new_po_stmt));
                }
                mysqli_stmt_close($new_po_stmt);
            }
        }

        // Commit transaction
        if (!mysqli_commit($conn)) {
            throw new Exception("Failed to commit transaction");
        }

        $response['status'] = 'success';
        $response['message'] = 'Record updated successfully';
    }

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

// Ensure we only output JSON
echo json_encode($response);
exit;
?>
