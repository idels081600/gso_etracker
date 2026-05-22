<?php
define('PAYABLES_API', true);
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_workflow.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    payables_json_response(['success' => false, 'error' => 'Invalid request method'], 405);
}

payables_verify_csrf_token();

$requestedRecordType = $_POST['record_type'] ?? '';
if (!in_array($requestedRecordType, ['bac', 'rfq'], true)) {
    payables_json_response(['success' => false, 'error' => 'Invalid record type.'], 422);
}

$recordType = payables_normalize_record_type($requestedRecordType);
$recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
if ($recordId === null || $recordId === false) {
    $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
}
$mainStatus = strtoupper(trim($_POST['main_status'] ?? ''));

if (!$recordId || $recordId < 1) {
    payables_json_response(['success' => false, 'error' => 'Invalid record.'], 422);
}

if (!in_array($mainStatus, PAYABLES_WORKFLOW_STATUSES, true)) {
    payables_json_response(['success' => false, 'error' => 'Invalid workflow status.'], 422);
}

$sourceTable = $recordType === 'rfq' ? 'PO_sap' : 'transmittal_bac';
$existsStmt = $conn->prepare("SELECT id FROM {$sourceTable} WHERE id = ? AND delete_status = 0 LIMIT 1");
if (!$existsStmt) {
    payables_log_error('Workflow source lookup prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to save workflow right now.'], 500);
}
$existsStmt->bind_param('i', $recordId);
$existsStmt->execute();
$existsResult = $existsStmt->get_result();
if (!$existsResult || !$existsResult->fetch_assoc()) {
    $existsStmt->close();
    payables_json_response(['success' => false, 'error' => 'Record not found.'], 404);
}
$existsStmt->close();

$checklist = $_POST['checklist'] ?? [];
if (!is_array($checklist)) {
    $checklist = [];
}

$inspection = in_array('inspection', $checklist, true) ? 1 : 0;
$obr = in_array('obr', $checklist, true) ? 1 : 0;
$ics = in_array('ics', $checklist, true) ? 1 : 0;
$par = in_array('par', $checklist, true) ? 1 : 0;
$ris = in_array('ris', $checklist, true) ? 1 : 0;
$updatedBy = $_SESSION['pay_name'] ?? '';
$currentWorkflow = payables_get_workflow($recordType, $recordId);
$currentLocation = payables_normalize_location($currentWorkflow['current_location'] ?? '');
if ($mainStatus === 'ACCOUNTING' && empty($currentWorkflow['id'])) {
    $currentLocation = 'ACCOUNTING';
}

if ($mainStatus !== 'GSO' && (!$inspection || !$obr)) {
    payables_json_response([
        'success' => false,
        'error' => 'Complete Inspection and OBR before selecting the next status.',
    ], 422);
}

payables_ensure_workflow_table();

$stmt = $conn->prepare("INSERT INTO payables_workflow_status (
    record_type, record_id, main_status, inspection, obr, ics, par, ris, current_location, updated_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    main_status = VALUES(main_status),
    inspection = VALUES(inspection),
    obr = VALUES(obr),
    ics = VALUES(ics),
    par = VALUES(par),
    ris = VALUES(ris),
    current_location = VALUES(current_location),
    updated_by = VALUES(updated_by),
    updated_at = CURRENT_TIMESTAMP");

if (!$stmt) {
    payables_log_error('Workflow update prepare failed: ' . $conn->error);
    payables_json_response(['success' => false, 'error' => 'Unable to save workflow right now.'], 500);
}

$stmt->bind_param('sisiiiiiss', $recordType, $recordId, $mainStatus, $inspection, $obr, $ics, $par, $ris, $currentLocation, $updatedBy);

if (!$stmt->execute()) {
    payables_log_error('Workflow update execute failed: ' . $stmt->error);
    $stmt->close();
    payables_json_response(['success' => false, 'error' => 'Unable to save workflow right now.'], 500);
}

$stmt->close();
$workflow = payables_get_workflow($recordType, $recordId);

payables_json_response([
    'success' => true,
    'workflow' => $workflow,
]);
