<?php
require_once 'db.php';

error_log('Received POST data: ' . print_r($_POST, true));

if(isset($_POST['ids']) && !empty($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $idList = implode(',', $ids);
   
    error_log('Processing IDs: ' . $idList);
   
    $query = "INSERT INTO bq_print (SR_DR, date, requestor, activity, description, supplier, quantity, amount, PO_no, PO_amount, remarks)
              SELECT SR_DR, date, requestor, activity, description, supplier, quantity, amount, PO_no, PO_amount, remarks
              FROM bq
              WHERE id IN ($idList)";
             
    error_log('Executing query: ' . $query);
   
    $result = mysqli_query($conn, $query);
   
    if (!$result) {
        error_log('MySQL Error: ' . mysqli_error($conn));
    }
   
    echo json_encode(['success' => $result]);
} else {
    error_log('No IDs received');
    echo json_encode(['success' => false]);
}
?>
