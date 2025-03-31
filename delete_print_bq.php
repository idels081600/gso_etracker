<?php
require_once 'db.php';

if(isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $query = "DELETE FROM bq_print WHERE id = $id";
    $result = mysqli_query($conn, $query);
    
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false]);
}
?>
