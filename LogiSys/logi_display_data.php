<?php
require_once "logi_db.php";

function display_inventory_items(){
    global $conn;
    $query = "SELECT * FROM `inventory_items` ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    
    return $result;
}
?>
