<?php
define('PAYABLES_API', true);
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("SELECT id, ib_no, project_name, date_received, office, received_by, winning_bidders, amount, NOA_no, COA_date, notice_proceed, deadline, transmittal_type, calendar_days FROM transmittal_bac WHERE id = ? AND delete_status = 0 LIMIT 1");
if (!$stmt) {
    payables_log_error('Transmittal fetch prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Unable to fetch this record right now.']);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'row' => $row]);
} else {
    echo json_encode(['success' => false, 'error' => 'Not found']);
} 
$stmt->close();
