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
$location = payables_normalize_location($_POST['location'] ?? '');
$updatedBy = $_SESSION['pay_name'] ?? '';

if (!$recordId || $recordId < 1) {
    payables_json_response(['success' => false, 'error' => 'Invalid record.'], 422);
}

$existsStmt = $conn->prepare("SELECT id FROM transmittal_bac WHERE id = ? AND delete_status = 0 LIMIT 1");
if (!$existsStmt) {
    payables_log_error('Location source lookup prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to update location right now.'], 500);
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
$workflow = payables_get_workflow('bac', $recordId);
$previousLocation = payables_normalize_location($workflow['current_location'] ?? '');

$stmt = $conn->prepare("INSERT INTO payables_workflow_status (
    record_type, record_id, main_status, current_location, updated_by
) VALUES ('bac', ?, 'ACCOUNTING', ?, ?)
ON DUPLICATE KEY UPDATE
    current_location = VALUES(current_location),
    updated_by = VALUES(updated_by),
    updated_at = CURRENT_TIMESTAMP");

if (!$stmt) {
    payables_log_error('Location update prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to update location right now.'], 500);
}

$stmt->bind_param('iss', $recordId, $location, $updatedBy);
if (!$stmt->execute()) {
    payables_log_error('Location update execute failed: ' . $stmt->error);
    $stmt->close();
    payables_json_response(['success' => false, 'error' => 'Unable to update location right now.'], 500);
}
$stmt->close();

if ($location !== $previousLocation) {
    $historyStmt = $conn->prepare("INSERT INTO payables_location_history (
        record_type, record_id, location, changed_by
    ) VALUES ('bac', ?, ?, ?)");
    if ($historyStmt) {
        $historyStmt->bind_param('iss', $recordId, $location, $updatedBy);
        if (!$historyStmt->execute()) {
            payables_log_error('Location history insert failed: ' . $historyStmt->error);
        }
        $historyStmt->close();
    } else {
        payables_log_error('Location history prepare failed: ' . $conn->error);
    }
}

$history = payables_get_location_history_map('bac', [$recordId]);

payables_json_response([
    'success' => true,
    'location' => $location,
    'history' => $history[$recordId] ?? [],
]);
