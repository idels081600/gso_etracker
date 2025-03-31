<?php
require_once 'db.php';

if(isset($_POST['id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    
    $query = "SELECT * FROM Maam_mariecris WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    
    if($row = mysqli_fetch_assoc($result)) {
        echo json_encode($row);
    }
}
?>
