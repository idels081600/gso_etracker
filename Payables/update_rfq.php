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

// Validate required fields
$required = [
    'id',
    'rfq_no',
    'description',
    'office',
    'received_by',
    'supplier',
    'amount',
    'status'
];

foreach ($required as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
        exit;
    }
}

$id = intval($_POST['id']);
if ($id < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid record.']);
    exit;
}

$existsStmt = $conn->prepare("SELECT id FROM PO_sap WHERE id = ? AND delete_status = 0 LIMIT 1");
if (!$existsStmt) {
    log_error('Lookup prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Unable to update this record right now.']);
    exit;
}
$existsStmt->bind_param('i', $id);
$existsStmt->execute();
$existsResult = $existsStmt->get_result();
if (!$existsResult || !$existsResult->fetch_assoc()) {
    $existsStmt->close();
    echo json_encode(['success' => false, 'error' => 'Record not found.']);
    exit;
}
$existsStmt->close();

$stmt = $conn->prepare("UPDATE PO_sap SET RFQ_no=?, description=?, office=?, received_by=?, supplier=?, amount=?, date_received=?, status=? WHERE id=? AND delete_status=0");

if (!$stmt) {
    log_error('Prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Unable to update this record right now.']);
    exit;
}

$amount = (float)payables_sanitize_amount($_POST['amount']);
$date_received = payables_post_date_or_now('date_received');
$status = $_POST['status'];

$stmt->bind_param(
    "sssssdssi",
    $_POST['rfq_no'],
    $_POST['description'],
    $_POST['office'],
    $_POST['received_by'],
    $_POST['supplier'],
    $amount,
    $date_received,
    $status,
    $id
);

if (!$stmt->execute()) {
    log_error('Execute failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Unable to update this record right now.']);
    $stmt->close();
    exit;
}

$stmt->close();
echo json_encode(['success' => true]);
?>
