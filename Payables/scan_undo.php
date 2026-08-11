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

$scanEventId = filter_input(INPUT_POST, 'scan_event_id', FILTER_VALIDATE_INT) ?: 0;
if ($scanEventId < 1) {
    payables_json_response(['success' => false, 'error' => 'Choose a valid scan history entry.'], 422);
}

$stmt = $conn->prepare('DELETE FROM payables_document_scan_events WHERE id = ? LIMIT 1');
if (!$stmt) {
    payables_log_error('Scan undo prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to undo scan right now.'], 500);
}

$stmt->bind_param('i', $scanEventId);
if (!$stmt->execute()) {
    payables_log_error('Scan undo failed: ' . $stmt->error);
    $stmt->close();
    payables_json_response(['success' => false, 'error' => 'Unable to undo scan right now.'], 500);
}

$deleted = $stmt->affected_rows;
$stmt->close();

if ($deleted < 1) {
    payables_json_response(['success' => false, 'error' => 'Scan history entry was already removed.'], 404);
}

payables_json_response([
    'success' => true,
    'deleted_id' => $scanEventId,
]);