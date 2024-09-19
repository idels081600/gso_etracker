<?php
// Include your database connection file
require 'db.php';

// Fetch data from the Maam_mariecris_print table
$result2 = $conn->query("SELECT * FROM `bq_print` ORDER BY `id` DESC");

$data = [];
while ($row = $result2->fetch_assoc()) {
    $data[] = $row;
}

// Return the data as JSON
echo json_encode($data);
?>
