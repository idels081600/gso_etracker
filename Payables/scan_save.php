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
payables_ensure_workflow_table();

$barcodeCode = payables_normalize_barcode_code($_POST['barcode_code'] ?? '');
$direction = payables_normalize_scan_direction($_POST['direction'] ?? '');
$office = payables_normalize_scan_office($_POST['office'] ?? '');
$scanSource = payables_normalize_scan_source($_POST['scan_source'] ?? 'MANUAL');

if ($barcodeCode === '') {
    payables_json_response(['success' => false, 'error' => 'Scan a registered barcode before saving.'], 422);
}
if ($direction === '') {
    payables_json_response(['success' => false, 'error' => 'Choose Scan In or Scan Out.'], 422);
}
if ($office === '') {
    payables_json_response(['success' => false, 'error' => 'Choose a valid office.'], 422);
}

$document = payables_find_assigned_document_by_barcode($barcodeCode);
if (!$document) {
    payables_json_response(['success' => false, 'error' => 'The registered barcode is no longer assigned to a document.'], 404);
}

$recordType = $document['record_type'];
$recordId = (int)$document['record_id'];
$scannedBy = $_SESSION['pay_name'] ?? '';
$workflow = [
    'applied' => false,
    'previous_main_status' => null,
    'previous_location' => null,
    'main_status' => null,
    'location' => null,
];
$eventId = 0;

$conn->begin_transaction();
try {
    $workflow = payables_apply_scan_workflow($document, $direction, $office, $scannedBy);

    $stmt = $conn->prepare("
        INSERT INTO payables_document_scan_events (
            record_type, record_id, document_no, direction, office, scan_source, scanned_by,
            previous_main_status, previous_location, result_main_status, result_location
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare scan event.');
    }

    $previousStatus = $workflow['previous_main_status'];
    $previousLocation = $workflow['previous_location'];
    $resultStatus = $workflow['main_status'];
    $resultLocation = $workflow['location'];
    $stmt->bind_param(
        'sisssssssss',
        $recordType,
        $recordId,
        $document['document_no'],
        $direction,
        $office,
        $scanSource,
        $scannedBy,
        $previousStatus,
        $previousLocation,
        $resultStatus,
        $resultLocation
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to save scan event.');
    }
    $eventId = $stmt->insert_id;
    $stmt->close();
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    payables_log_error('Scan save failed: ' . $error->getMessage());
    payables_json_response(['success' => false, 'error' => 'Unable to save the scan and workflow update right now.'], 500);
}

$event = [
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
    'workflow' => [
        'updated' => !empty($workflow['applied']),
        'main_status' => $workflow['main_status'],
        'location' => $workflow['location'],
    ],
];

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
        $event = payables_format_scan_event($eventRow);
    }
    $eventStmt->close();
}

payables_json_response([
    'success' => true,
    'event' => $event,
]);
