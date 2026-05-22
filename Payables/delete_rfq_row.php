<?php
define('PAYABLES_API', true);
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';
header('Content-Type: application/json');

function log_error($message) {
    payables_log_error($message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

payables_verify_csrf_token();

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing id']);
    exit;
}

$id = intval($_POST['id']);
if ($id < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

$stmt = $conn->prepare("UPDATE PO_sap SET delete_status=1 WHERE id=? AND delete_status=0");
if (!$stmt) {
    log_error('Prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Unable to delete this record right now.']);
    exit;
}
$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
    log_error('Execute failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Unable to delete this record right now.']);
    $stmt->close();
    exit;
}
$affectedRows = $stmt->affected_rows;
$stmt->close();
if ($affectedRows < 1) {
    echo json_encode(['success' => false, 'error' => 'Record not found or already deleted.']);
    exit;
}
echo json_encode(['success' => true]); 
