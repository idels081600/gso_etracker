<?php
require_once 'db_asset.php';

header('Content-Type: application/json');

if ($_POST['repair_id']) {
    $repair_id = mysqli_real_escape_string($conn, $_POST['repair_id']);
    
    $query = "DELETE FROM motorpool_repair WHERE id = '$repair_id'";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Repair record deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting repair record: ' . mysqli_error($conn)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid repair ID']);
}
?>
