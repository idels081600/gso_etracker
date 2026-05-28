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
$released = !empty($_POST['released']) && $_POST['released'] === '1' ? 1 : 0;
$updatedBy = $_SESSION['pay_name'] ?? '';

if (!$recordId || $recordId < 1) {
    payables_json_response(['success' => false, 'error' => 'Invalid record.'], 422);
}

$existsStmt = $conn->prepare("SELECT id FROM bac_monitoring WHERE id = ? LIMIT 1");
if (!$existsStmt) {
    payables_log_error('Release source lookup prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to update release status right now.'], 500);
}

$existsStmt->bind_param('i', $recordId);
$existsStmt->execute();
$existsResult = $existsStmt->get_result();
if (!$existsResult || !$existsResult->fetch_assoc()) {
    $existsStmt->close();
    payables_json_response(['success' => false, 'error' => 'Record not found.'], 404);
}
$existsStmt->close();

payables_ensure_workflow_table();

$stmt = $conn->prepare("INSERT INTO payables_workflow_status (
    record_type, record_id, main_status, released, updated_by
) VALUES ('bac_monitoring', ?, 'CTO', ?, ?)
ON DUPLICATE KEY UPDATE
    released = VALUES(released),
    updated_by = VALUES(updated_by),
    updated_at = CURRENT_TIMESTAMP");

if (!$stmt) {
    payables_log_error('Release update prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to update release status right now.'], 500);
}

$stmt->bind_param('iis', $recordId, $released, $updatedBy);
if (!$stmt->execute()) {
    payables_log_error('Release update execute failed: ' . $stmt->error);
    $stmt->close();
    payables_json_response(['success' => false, 'error' => 'Unable to update release status right now.'], 500);
}

$stmt->close();

payables_json_response([
    'success' => true,
    'released' => $released,
]);
