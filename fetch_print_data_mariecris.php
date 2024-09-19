<?php
// Include your database connection file
require 'db.php';

// Fetch data from the Maam_mariecris_print table
$result2 = $conn->query("SELECT id, SR_DR, date, department, store, activity, no_of_pax, amount, total FROM Maam_mariecris_print");

$data = [];
while ($row = $result2->fetch_assoc()) {
    $data[] = $row;
}

// Return the data as JSON
echo json_encode($data);
?>
