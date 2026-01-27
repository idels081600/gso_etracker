<?php
require 'pr_db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Query to fetch PPMP data
$sql = "SELECT id, pr_number, po_number,project, start_procurement, end_procurement, expected_delivery, amount, pr_status, delivery_status 
        FROM ppmp 
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

$data = array();

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

echo json_encode($data);

mysqli_close($conn);
?>
