<?php
require_once 'transmit_db.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}
$id = intval($_GET['id']);
$sql = "SELECT * FROM PO_sap WHERE id = $id LIMIT 1";
$result = mysqli_query($conn, $sql);
if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Database query failed: ' . mysqli_error($conn)]);
    exit;
}
if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'row' => $row]);
} else {
    echo json_encode(['success' => false, 'error' => 'Not found']);
}
