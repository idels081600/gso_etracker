<?php
require_once 'db_asset.php';

header('Content-Type: application/json');

if ($_POST['repair_id']) {
    $repair_id = mysqli_real_escape_string($conn, $_POST['repair_id']);
    
    $query = "SELECT * FROM motorpool_repair WHERE id = '$repair_id'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $repair = mysqli_fetch_assoc($result);
        echo json_encode(['success' => true, 'repair' => $repair]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Repair record not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid repair ID']);
}
?>
