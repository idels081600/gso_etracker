<?php
require_once 'db.php';

$query = "DELETE FROM sir_bayong_print";
$result = mysqli_query($conn, $query);

echo json_encode(['success' => $result]);
?>
