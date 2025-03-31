<?php
require_once 'db.php';

// Set proper content type for JSON response
header('Content-Type: application/json');

error_log('Received POST data: ' . print_r($_POST, true));

if(isset($_POST['ids']) && !empty($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    
    // Check if we have valid IDs after conversion
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
        exit;
    }
    
    $idList = implode(',', $ids);
    
    error_log('Processing IDs: ' . $idList);
    
    $query = "INSERT INTO Maam_mariecris_print (SR_DR, date, department, store, activity, no_of_pax, amount, total, PO_no, PO_amount, Remarks)
              SELECT SR_DR, date, department, store, activity, no_of_pax, amount, total, PO_no, PO_amount, Remarks
              FROM Maam_mariecris
              WHERE id IN ($idList)";
              
    error_log('Executing query: ' . $query);
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        error_log('MySQL Error: ' . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    } else {
        $affected_rows = mysqli_affected_rows($conn);
        echo json_encode(['success' => true, 'rows_affected' => $affected_rows]);
    }
} else {
    error_log('No IDs received');
    echo json_encode(['success' => false, 'message' => 'No IDs received']);
}
?>
