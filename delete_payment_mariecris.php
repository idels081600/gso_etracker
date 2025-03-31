<?php
require_once 'db.php';

// Set proper content type for JSON response
header('Content-Type: application/json');

if(isset($_POST['po_number'])) {
    $po_number = mysqli_real_escape_string($conn, $_POST['po_number']);
    
    // Delete the payment record
    $delete_query = "DELETE FROM Maam_mariecris_payments WHERE po = '$po_number'";
    $delete_result = mysqli_query($conn, $delete_query);
    
    if ($delete_result) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting payment: ' . mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing PO number'
    ]);
}
?>
