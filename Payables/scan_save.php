<?php
define('PAYABLES_API', true);
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_document_tracking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    payables_json_response(['success' => false, 'error' => 'Invalid request method.'], 405);
}

payables_verify_csrf_token();
payables_ensure_scan_events_table();

$recordType = strtoupper(trim($_POST['record_type'] ?? ''));
$recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT) ?: 0;
$direction = payables_normalize_scan_direction($_POST['direction'] ?? '');
$office = payables_normalize_scan_office($_POST['office'] ?? '');
$scanSource = payables_normalize_scan_source($_POST['scan_source'] ?? 'MANUAL');

if (!in_array($recordType, ['IB', 'RFQ'], true) || $recordId < 1) {
    payables_json_response(['success' => false, 'error' => 'Choose a valid document before saving the scan.'], 422);
}
if ($direction === '') {
    payables_json_response(['success' => false, 'error' => 'Choose Scan In or Scan Out.'], 422);
}
if ($office === '') {
    payables_json_response(['success' => false, 'error' => 'Choose a valid office.'], 422);
}

$document = payables_get_document_by_record($recordType, $recordId);
if (!$document) {
    payables_json_response(['success' => false, 'error' => 'Document was not found.'], 404);
}

$scannedBy = $_SESSION['pay_name'] ?? '';
$stmt = $conn->prepare("
    INSERT INTO payables_document_scan_events (
        record_type, record_id, document_no, direction, office, scan_source, scanned_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?)
");
if (!$stmt) {
    payables_log_error('Scan event insert prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to save scan right now.'], 500);
}

$stmt->bind_param(
    'sisssss',
    $recordType,
    $recordId,
    $document['document_no'],
    $direction,
    $office,
    $scanSource,
    $scannedBy
);
if (!$stmt->execute()) {
    payables_log_error('Scan event insert failed: ' . $stmt->error);
    $stmt->close();
    payables_json_response(['success' => false, 'error' => 'Unable to save scan right now.'], 500);
}
$eventId = $stmt->insert_id;
$stmt->close();

$event = [[
    'id' => $eventId,
    'record_type' => $recordType,
    'record_id' => $recordId,
    'document_no' => $document['document_no'],
    'direction' => $direction,
    'office' => $office,
    'scan_source' => $scanSource,
    'scanned_by' => $scannedBy,
    'scanned_at' => payables_current_datetime(),
    'title' => $document['title'],
    'party' => $document['party'],
]];

$eventStmt = $conn->prepare("
    SELECT
        e.*,
        COALESCE(b.project_name, r.description, '') AS title,
        COALESCE(b.bidder, r.supplier, '') AS party
    FROM payables_document_scan_events e
    LEFT JOIN bac_monitoring b
        ON e.record_type = 'IB'
       AND e.record_id = b.id
    LEFT JOIN PO_sap r
        ON e.record_type = 'RFQ'
       AND e.record_id = r.id
    WHERE e.id = ?
    LIMIT 1
");
if ($eventStmt) {
    $eventStmt->bind_param('i', $eventId);
    $eventStmt->execute();
    $eventResult = $eventStmt->get_result();
    if ($eventResult && $eventRow = $eventResult->fetch_assoc()) {
        $event = [payables_format_scan_event($eventRow)];
    }
    $eventStmt->close();
}

payables_json_response([
    'success' => true,
    'event' => $event[0],
]);
