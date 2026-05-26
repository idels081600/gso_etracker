<?php
define('PAYABLES_API', true);
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_workflow.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    payables_json_response(['success' => false, 'error' => 'Invalid request method'], 405);
}

payables_verify_csrf_token();

$recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
if ($recordId === null || $recordId === false) {
    $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
}
$remarks = trim($_POST['remarks'] ?? '');
$updatedBy = $_SESSION['pay_name'] ?? '';

if (!$recordId || $recordId < 1) {
    payables_json_response(['success' => false, 'error' => 'Invalid record.'], 422);
}

$existsStmt = $conn->prepare("SELECT id, remarks FROM transmittal_bac WHERE id = ? AND delete_status = 0 LIMIT 1");
if (!$existsStmt) {
    payables_log_error('Workflow remarks source lookup prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to update remarks right now.'], 500);
}

$existsStmt->bind_param('i', $recordId);
$existsStmt->execute();
$existsResult = $existsStmt->get_result();
$existingRow = $existsResult ? $existsResult->fetch_assoc() : null;
if (!$existingRow) {
    $existsStmt->close();
    payables_json_response(['success' => false, 'error' => 'Record not found.'], 404);
}
$existsStmt->close();

$oldRemarks = trim($existingRow['remarks'] ?? '');
$stmt = $conn->prepare("UPDATE transmittal_bac SET remarks = ? WHERE id = ? AND delete_status = 0");
if (!$stmt) {
    payables_log_error('Workflow remarks update prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to update remarks right now.'], 500);
}

$stmt->bind_param('si', $remarks, $recordId);
if (!$stmt->execute()) {
    payables_log_error('Workflow remarks update execute failed: ' . $stmt->error);
    $stmt->close();
    payables_json_response(['success' => false, 'error' => 'Unable to update remarks right now.'], 500);
}
$stmt->close();

if ($remarks !== $oldRemarks) {
    payables_record_remarks_history('bac', $recordId, $remarks, $updatedBy);
}

$historyMap = payables_get_remarks_history_map('bac', [$recordId]);
$history = $historyMap[$recordId] ?? [];
if (!$history) {
    $history[] = [
        'remarks' => $remarks !== '' ? $remarks : 'No remarks yet.',
        'changed_by' => '',
        'changed_at' => '',
    ];
}

payables_json_response([
    'success' => true,
    'remarks' => $remarks,
    'history' => $history,
]);
