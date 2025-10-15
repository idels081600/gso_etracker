<?php
require_once 'advance_po_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['edit_row_id']) || !isset($_POST['edit_store']) || !isset($_POST['edit_date']) || !isset($_POST['edit_invoice_number']) || !isset($_POST['edit_description'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$id = (int) $_POST['edit_row_id'];
$store = mysqli_real_escape_string($conn, $_POST['edit_store']);
$date = mysqli_real_escape_string($conn, $_POST['edit_date']);
$invoice_number = mysqli_real_escape_string($conn, $_POST['edit_invoice_number']);
$description = mysqli_real_escape_string($conn, $_POST['edit_description']);
$pcs = isset($_POST['edit_pcs']) ? (float) $_POST['edit_pcs'] : 0;
$unit_price = isset($_POST['edit_unit_price']) ? (float) $_POST['edit_unit_price'] : 0;
$amount = $pcs * $unit_price;

$query = "UPDATE advancePo SET
          store = ?,
          date = ?,
          invoice_number = ?,
          description = ?,
          pcs = ?,
          unit_price = ?,
          amount = ?
          WHERE id = ? AND delete_status = 0";

$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ssssdddi", $store, $date, $invoice_number, $description, $pcs, $unit_price, $amount, $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update record']);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

mysqli_close($conn);
?>
