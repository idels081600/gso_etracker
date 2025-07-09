<?php
require_once 'transmit_db.php';
header('Content-Type: application/json');

function log_error($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/transmittal_error.log');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

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
$notice_proceed_date = $_POST['notice_proceed'];
$deadline_raw = $_POST['deadline'];
if (is_numeric($deadline_raw)) {
    $days = intval($deadline_raw);
    $deadline_date = date('Y-m-d', strtotime($notice_proceed_date . ' + ' . ($days - 1) . ' days'));
} else {
    $days = $deadline_raw;
    $deadline_date = $deadline_raw;
}

$stmt = $conn->prepare("UPDATE transmittal_bac SET ib_no=?, project_name=?, office=?, received_by=?, winning_bidders=?, amount=?, NOA_no=?, COA_date=?, notice_proceed=?, deadline=?, transmittal_type=?, calendar_days=? WHERE id=?");
if (!$stmt) {
    log_error('Prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param(
    "ssssssssssssi",
    $_POST['ib_no'],
    $_POST['project_name'],
    $_POST['office'],
    $_POST['received_by'],
    $_POST['winning_bidders'],
    $_POST['amount'],
    $_POST['NOA_no'],
    $_POST['COA_date'],
    $_POST['notice_proceed'],
    $deadline_date,
    $_POST['transmittal_type'],
    $days,
    $id
);
if (!$stmt->execute()) {
    log_error('Execute failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();
echo json_encode(['success' => true]); 