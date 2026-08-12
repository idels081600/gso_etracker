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

$scanEventId = filter_input(INPUT_POST, 'scan_event_id', FILTER_VALIDATE_INT) ?: 0;
if ($scanEventId < 1) {
    payables_json_response(['success' => false, 'error' => 'Choose a valid scan history entry.'], 422);
}

$conn->begin_transaction();
try {
    $eventStmt = $conn->prepare('SELECT * FROM payables_document_scan_events WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$eventStmt) {
        throw new RuntimeException('Unable to prepare scan history lookup.');
    }
    $eventStmt->bind_param('i', $scanEventId);
    if (!$eventStmt->execute()) {
        $eventStmt->close();
        throw new RuntimeException('Unable to load scan history.');
    }
    $eventResult = $eventStmt->get_result();
    $event = $eventResult ? $eventResult->fetch_assoc() : null;
    $eventStmt->close();

    if (!$event) {
        $conn->rollback();
        payables_json_response(['success' => false, 'error' => 'Scan history entry was already removed.'], 404);
    }

    $workflowRestored = false;
    $previousStatus = strtoupper(trim((string)($event['previous_main_status'] ?? '')));
    $previousLocationRaw = trim((string)($event['previous_location'] ?? ''));
    $resultStatus = strtoupper(trim((string)($event['result_main_status'] ?? '')));
    $resultLocationRaw = trim((string)($event['result_location'] ?? ''));

    if (
        ($event['record_type'] ?? '') === 'IB' &&
        in_array($previousStatus, PAYABLES_WORKFLOW_STATUSES, true) &&
        in_array($resultStatus, PAYABLES_WORKFLOW_STATUSES, true) &&
        $previousLocationRaw !== '' &&
        $resultLocationRaw !== ''
    ) {
        $recordId = (int)$event['record_id'];
        $newerStmt = $conn->prepare("SELECT id FROM payables_document_scan_events
            WHERE record_type = 'IB'
              AND record_id = ?
              AND id > ?
              AND result_main_status IS NOT NULL
            ORDER BY id ASC
            LIMIT 1");
        if (!$newerStmt) {
            throw new RuntimeException('Unable to check newer scans.');
        }
        $newerStmt->bind_param('ii', $recordId, $scanEventId);
        $newerStmt->execute();
        $newerResult = $newerStmt->get_result();
        $hasNewerWorkflowScan = $newerResult && $newerResult->fetch_assoc();
        $newerStmt->close();
        if ($hasNewerWorkflowScan) {
            $conn->rollback();
            payables_json_response(['success' => false, 'error' => 'Undo the newer scan for this document first.'], 409);
        }

        $workflowStmt = $conn->prepare("SELECT main_status, current_location FROM payables_workflow_status
            WHERE record_type = 'bac_monitoring' AND record_id = ? LIMIT 1 FOR UPDATE");
        if (!$workflowStmt) {
            throw new RuntimeException('Unable to prepare workflow rollback.');
        }
        $workflowStmt->bind_param('i', $recordId);
        $workflowStmt->execute();
        $workflowResult = $workflowStmt->get_result();
        $currentWorkflow = $workflowResult ? $workflowResult->fetch_assoc() : null;
        $workflowStmt->close();

        $resultLocation = payables_normalize_location($resultLocationRaw);
        $currentStatus = strtoupper(trim((string)($currentWorkflow['main_status'] ?? 'GSO')));
        $currentLocation = payables_normalize_location($currentWorkflow['current_location'] ?? 'ACCOUNTING');
        if ($currentStatus !== $resultStatus || $currentLocation !== $resultLocation) {
            $conn->rollback();
            payables_json_response(['success' => false, 'error' => 'The workflow changed after this scan and cannot be safely restored.'], 409);
        }

        $previousLocation = payables_normalize_location($previousLocationRaw);
        $changedBy = $_SESSION['pay_name'] ?? '';
        $restoreStmt = $conn->prepare("UPDATE payables_workflow_status
            SET main_status = ?, current_location = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
            WHERE record_type = 'bac_monitoring' AND record_id = ?");
        if (!$restoreStmt) {
            throw new RuntimeException('Unable to prepare workflow restore.');
        }
        $restoreStmt->bind_param('sssi', $previousStatus, $previousLocation, $changedBy, $recordId);
        if (!$restoreStmt->execute()) {
            $restoreStmt->close();
            throw new RuntimeException('Unable to restore the previous workflow.');
        }
        $restoreStmt->close();

        if ($previousLocation !== $currentLocation) {
            $historyBy = trim($changedBy . ' (Scanner Undo)');
            $historyStmt = $conn->prepare("INSERT INTO payables_location_history (
                record_type, record_id, location, changed_by
            ) VALUES ('bac_monitoring', ?, ?, ?)");
            if (!$historyStmt) {
                throw new RuntimeException('Unable to prepare rollback history.');
            }
            $historyStmt->bind_param('iss', $recordId, $previousLocation, $historyBy);
            if (!$historyStmt->execute()) {
                $historyStmt->close();
                throw new RuntimeException('Unable to save rollback history.');
            }
            $historyStmt->close();
        }
        $workflowRestored = true;
    }

    $deleteStmt = $conn->prepare('DELETE FROM payables_document_scan_events WHERE id = ? LIMIT 1');
    if (!$deleteStmt) {
        throw new RuntimeException('Unable to prepare scan undo.');
    }
    $deleteStmt->bind_param('i', $scanEventId);
    if (!$deleteStmt->execute() || $deleteStmt->affected_rows < 1) {
        $deleteStmt->close();
        throw new RuntimeException('Unable to remove scan history.');
    }
    $deleteStmt->close();
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    payables_log_error('Scan undo failed: ' . $error->getMessage());
    payables_json_response(['success' => false, 'error' => 'Unable to undo scan right now.'], 500);
}

payables_json_response([
    'success' => true,
    'deleted_id' => $scanEventId,
    'workflow_restored' => $workflowRestored,
]);
