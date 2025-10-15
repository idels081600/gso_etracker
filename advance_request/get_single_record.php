<?php
require_once 'advance_po_db.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID not provided']);
    exit;
}

$id = (int) $_GET['id'];

$query = "SELECT id, store, date, invoice_number, description, pcs, unit_price, amount, status FROM advancePo WHERE id = ? AND delete_status = 0";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

mysqli_close($conn);
?>
