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

$direction = payables_normalize_scan_direction($_POST['direction'] ?? '');
$office = payables_normalize_scan_office($_POST['office'] ?? '');
$itemsJson = trim($_POST['items'] ?? '');
$items = json_decode($itemsJson, true);

if ($direction === '') {
    payables_json_response(['success' => false, 'error' => 'Choose Scan In or Scan Out.'], 422);
}
if ($office === '') {
    payables_json_response(['success' => false, 'error' => 'Choose a valid office.'], 422);
}
if (!is_array($items) || count($items) === 0) {
    payables_json_response(['success' => false, 'error' => 'Scan at least one document before saving the batch.'], 422);
}
if (count($items) > 100) {
    payables_json_response(['success' => false, 'error' => 'Save up to 100 documents per batch.'], 422);
}

$documents = [];
foreach ($items as $item) {
    $recordType = strtoupper(trim((string)($item['record_type'] ?? '')));
    $recordId = (int)($item['record_id'] ?? 0);
    $scanSource = payables_normalize_scan_source((string)($item['scan_source'] ?? 'MANUAL'));

    if (!in_array($recordType, ['IB', 'RFQ'], true) || $recordId < 1) {
        payables_json_response(['success' => false, 'error' => 'One scanned item is invalid. Remove it and try again.'], 422);
    }

    $key = $recordType . ':' . $recordId;
    if (isset($documents[$key])) {
        continue;
    }

    $document = payables_get_document_by_record($recordType, $recordId);
    if (!$document) {
        payables_json_response(['success' => false, 'error' => 'A scanned document was not found. Remove it and try again.'], 404);
    }

    $document['scan_source'] = $scanSource;
    $documents[$key] = $document;
}

if (!$documents) {
    payables_json_response(['success' => false, 'error' => 'No valid documents to save.'], 422);
}

$scannedBy = $_SESSION['pay_name'] ?? '';
$events = [];
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO payables_document_scan_events (
            record_type, record_id, document_no, direction, office, scan_source, scanned_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare scan batch.');
    }

    foreach ($documents as $document) {
        $recordType = $document['record_type'];
        $recordId = (int)$document['record_id'];
        $documentNo = $document['document_no'];
        $scanSource = $document['scan_source'];
        $stmt->bind_param(
            'sisssss',
            $recordType,
            $recordId,
            $documentNo,
            $direction,
            $office,
            $scanSource,
            $scannedBy
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('Unable to save one scan in the batch.');
        }
        $events[] = [
            'id' => $stmt->insert_id,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'document_no' => $documentNo,
            'direction' => $direction,
            'office' => $office,
            'scan_source' => $scanSource,
            'scanned_by' => $scannedBy,
            'scanned_at' => payables_current_datetime(),
            'title' => $document['title'],
            'party' => $document['party'],
        ];
    }
    $stmt->close();
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    payables_log_error('Scan batch save failed: ' . $error->getMessage());
    payables_json_response(['success' => false, 'error' => 'Unable to save the batch right now.'], 500);
}

payables_json_response([
    'success' => true,
    'saved_count' => count($events),
    'events' => $events,
]);
