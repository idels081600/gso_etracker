<?php
require_once 'db.php';

$query = "DELETE FROM Maam_mariecris_print";
$result = mysqli_query($conn, $query);

echo json_encode(['success' => $result]);
?>
