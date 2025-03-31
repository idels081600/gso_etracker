<?php
require_once 'db.php';

if(isset($_POST['ids']) && isset($_POST['po_no']) && isset($_POST['po_amount'])) {
    $ids = array_map('intval', $_POST['ids']);
    $idList = implode(',', $ids);
    $po_no = mysqli_real_escape_string($conn, $_POST['po_no']);
    $po_amount = str_replace(',', '', $_POST['po_amount']);

    $query = "UPDATE sir_bayong_print 
              SET PO_no = '$po_no', 
                  PO_amount = '$po_amount'
              WHERE id IN ($idList)";

    $result = mysqli_query($conn, $query);
    
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false]);
}
?>

