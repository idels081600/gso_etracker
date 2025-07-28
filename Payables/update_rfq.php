<?php
require_once 'transmit_db.php';
header('Content-Type: application/json');

function log_error($message)
{
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/transmittal_error.log');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Validate required fields
$required = [
    'id',
    'rfq_no',
    'description',
    'office',
    'received_by',
    'supplier',
    'amount',
    'date_received'
];

foreach ($required as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
        exit;
    }
}

$id = intval($_POST['id']);

$stmt = $conn->prepare("UPDATE PO_sap SET RFQ_no=?, description=?, office=?, received_by=?, supplier=?, amount=?, date_received=? WHERE id=?");

if (!$stmt) {
    log_error('Prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

//The $amount must be cast to double, so the code must be "ssssssdsi"
$amount = doubleval($_POST['amount']);
$date_received = $_POST['date_received'];

$stmt->bind_param(
    "sssssdsi",
    $_POST['rfq_no'],
    $_POST['description'],
    $_POST['office'],
    $_POST['received_by'],
    $_POST['supplier'],
    $amount,
    $date_received,
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