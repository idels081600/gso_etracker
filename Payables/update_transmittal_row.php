<?php
define('PAYABLES_API', true);
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';
require_once 'payables_workflow.php';
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
    'ib_no',
    'project_name',
    'office',
    'received_by',
    'winning_bidders',
    'amount',
    'NOA_no',
    'COA_date',
    'notice_proceed',
    'deadline',
    'transmittal_type'
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

$existsStmt = $conn->prepare("SELECT id, remarks FROM transmittal_bac WHERE id = ? AND delete_status = 0 LIMIT 1");
if (!$existsStmt) {
    log_error('Lookup prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Unable to update this record right now.']);
    exit;
}
$existsStmt->bind_param('i', $id);
$existsStmt->execute();
$existsResult = $existsStmt->get_result();
$existingRow = $existsResult ? $existsResult->fetch_assoc() : null;
if (!$existingRow) {
    $existsStmt->close();
    echo json_encode(['success' => false, 'error' => 'Record not found.']);
    exit;
}
$existsStmt->close();
$oldRemarks = trim($existingRow['remarks'] ?? '');

$dateReceived = payables_post_date_or_now('date_received');
$notice_proceed_date = payables_normalize_date_or_empty($_POST['notice_proceed']);
$coaDate = payables_normalize_date_or_empty($_POST['COA_date']);
[$days, $deadline_date] = payables_calculate_deadline($notice_proceed_date, $_POST['deadline']);
$sanitized_amount = payables_sanitize_amount($_POST['amount']);
$remarks = trim($_POST['remarks'] ?? '');

$stmt = $conn->prepare("UPDATE transmittal_bac SET ib_no=?, project_name=?, date_received=?, office=?, received_by=?, winning_bidders=?, amount=?, NOA_no=?, COA_date=?, notice_proceed=?, deadline=?, transmittal_type=?, calendar_days=?, remarks=? WHERE id=? AND delete_status=0");

if (!$stmt) {
    log_error('Prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Unable to update this record right now.']);
    exit;
}

$stmt->bind_param(
    "ssssssssssssssi",
    $_POST['ib_no'],
    $_POST['project_name'],
    $dateReceived,
    $_POST['office'],
    $_POST['received_by'],
    $_POST['winning_bidders'],
    $sanitized_amount,
    $_POST['NOA_no'],
    $coaDate,
    $notice_proceed_date,
    $deadline_date,
    $_POST['transmittal_type'],
    $days,
    $remarks,
    $id
);

if (!$stmt->execute()) {
    log_error('Execute failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Unable to update this record right now.']);
    $stmt->close();
    exit;
}

$stmt->close();
if ($remarks !== $oldRemarks) {
    payables_record_remarks_history('bac', $id, $remarks, $_SESSION['pay_name'] ?? '');
}
echo json_encode(['success' => true]);
?>
