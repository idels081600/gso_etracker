<?php
session_start();
require_once __DIR__ . '/rice_claim_consolidation_lib.php';

riceConsolidationRequireVerifier();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = require __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    riceConsolidationJsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    riceConsolidationJsonResponse(['success' => false, 'message' => 'Invalid JSON request.'], 400);
}

$csrfToken = (string)($input['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['rice_consolidation_csrf'] ?? '');
if ($sessionToken === '' || $csrfToken === '' || !hash_equals($sessionToken, $csrfToken)) {
    riceConsolidationJsonResponse(['success' => false, 'message' => 'The security token is invalid or expired. Reload the page and try again.'], 403);
}

$action = (string)($input['action'] ?? '');
$operator = (string)($_SESSION['pay_name'] ?? $_SESSION['username']);

try {
    if ($action === 'copy_signature') {
        $householdCode = trim((string)($input['household_code'] ?? ''));
        $direction = (string)($input['direction'] ?? '');
        $confirmed = (int)($input['confirmed'] ?? 0) === 1;
        $directions = [
            'first_to_second' => ['source' => 'first_wave', 'target' => 'second_wave'],
            'second_to_first' => ['source' => 'second_wave', 'target' => 'first_wave'],
        ];

        if ($householdCode === '' || strlen($householdCode) > 50 || !isset($directions[$direction]) || !$confirmed) {
            riceConsolidationJsonResponse(['success' => false, 'message' => 'A confirmed signature-copy direction and household code are required.'], 422);
        }

        $conn->begin_transaction();
        $pair = riceConsolidationLockPair($conn, $householdCode);
        $sourceWave = $directions[$direction]['source'];
        $targetWave = $directions[$direction]['target'];
        $source = $pair[$sourceWave] ?? null;
        $target = $pair[$targetWave] ?? null;

        if (!$source || !$target || $source['claim_id'] === null || $target['claim_id'] === null) {
            throw new RuntimeException('Both claimed wave records are required before a signature can be copied.');
        }
        if (!riceConsolidationSignatureIsValid($source['e_signature'] ?? null)) {
            throw new RuntimeException('The source signature is missing or invalid.');
        }

        $sourceHash = riceConsolidationSignatureHash($source['e_signature']);
        $targetHash = riceConsolidationSignatureHash($target['e_signature'] ?? null);
        if (hash_equals($sourceHash, $targetHash)) {
            throw new RuntimeException('The target already contains the same signature.');
        }

        riceConsolidationInsertAudit($conn, [
            'action_type' => 'copy_signature',
            'direction' => $direction,
            'household_code' => $householdCode,
            'source_wave' => $sourceWave,
            'target_wave' => $targetWave,
            'source_claim_id' => $source['claim_id'],
            'target_claim_id' => $target['claim_id'],
            'previous_signature' => $target['e_signature'],
            'result_signature_hash' => $sourceHash,
            'operator_name' => $operator,
        ]);

        $targetConfig = riceConsolidationWaveConfig($targetWave);
        $updateStmt = $conn->prepare("UPDATE {$targetConfig['claim_table']} SET e_signature = ? WHERE id = ? LIMIT 1");
        $sourceSignature = $source['e_signature'];
        $targetClaimId = $target['claim_id'];
        $updateStmt->bind_param('si', $sourceSignature, $targetClaimId);
        $updateStmt->execute();
        $conn->commit();

        riceConsolidationJsonResponse([
            'success' => true,
            'message' => "Signature copied from {$directions[$direction]['source']} to {$directions[$direction]['target']}.",
        ]);
    }

    if ($action === 'update_claimant') {
        $householdCode = trim((string)($input['household_code'] ?? ''));
        $wave = (string)($input['wave'] ?? '');
        $claimantName = trim((string)($input['claimant_name'] ?? ''));
        $length = function_exists('mb_strlen') ? mb_strlen($claimantName) : strlen($claimantName);

        if ($householdCode === '' || strlen($householdCode) > 50 || !in_array($wave, ['first_wave', 'second_wave'], true)) {
            riceConsolidationJsonResponse(['success' => false, 'message' => 'A valid wave and household code are required.'], 422);
        }
        if ($claimantName === '' || $length > 150) {
            riceConsolidationJsonResponse(['success' => false, 'message' => 'Claimant name is required and must not exceed 150 characters.'], 422);
        }

        $conn->begin_transaction();
        $pair = riceConsolidationLockPair($conn, $householdCode);
        $target = $pair[$wave] ?? null;
        $counterpartWave = $wave === 'first_wave' ? 'second_wave' : 'first_wave';
        $counterpart = $pair[$counterpartWave] ?? null;
        if (!$target || !$counterpart || $target['claim_id'] === null) {
            throw new RuntimeException('Matched household records are required before a claimant name can be edited.');
        }

        $previousName = (string)($target['claimant_name'] ?? '');
        if ($previousName === $claimantName) {
            throw new RuntimeException('The claimant name has not changed.');
        }

        riceConsolidationInsertAudit($conn, [
            'action_type' => 'update_claimant',
            'household_code' => $householdCode,
            'target_wave' => $wave,
            'target_claim_id' => $target['claim_id'],
            'previous_claimant_name' => $previousName,
            'result_claimant_name' => $claimantName,
            'operator_name' => $operator,
        ]);

        $targetConfig = riceConsolidationWaveConfig($wave);
        $updateStmt = $conn->prepare("UPDATE {$targetConfig['claim_table']} SET claimant_name = ? WHERE id = ? LIMIT 1");
        $targetClaimId = $target['claim_id'];
        $updateStmt->bind_param('si', $claimantName, $targetClaimId);
        $updateStmt->execute();
        $conn->commit();

        riceConsolidationJsonResponse(['success' => true, 'message' => 'Claimant name updated.']);
    }

    if ($action === 'restore') {
        $auditId = (int)($input['audit_id'] ?? 0);
        if ($auditId <= 0) {
            riceConsolidationJsonResponse(['success' => false, 'message' => 'A valid audit entry is required.'], 422);
        }

        $conn->begin_transaction();
        $auditStmt = $conn->prepare('SELECT * FROM rice_claim_consolidation_audit WHERE id = ? LIMIT 1 FOR UPDATE');
        $auditStmt->bind_param('i', $auditId);
        $auditStmt->execute();
        $auditResult = $auditStmt->get_result();
        $audit = $auditResult ? $auditResult->fetch_assoc() : null;

        if (!$audit || !in_array($audit['action_type'], ['copy_signature', 'update_claimant'], true)) {
            throw new RuntimeException('This audit entry cannot be restored.');
        }

        $restoredStmt = $conn->prepare('SELECT id FROM rice_claim_consolidation_audit WHERE restored_from_id = ? LIMIT 1');
        $restoredStmt->bind_param('i', $auditId);
        $restoredStmt->execute();
        if ($restoredStmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('This audit entry has already been restored.');
        }

        $householdCode = (string)$audit['household_code'];
        $targetWave = (string)$audit['target_wave'];
        $pair = riceConsolidationLockPair($conn, $householdCode);
        $target = $pair[$targetWave] ?? null;
        if (!$target || $target['claim_id'] === null || (int)$target['claim_id'] !== (int)$audit['target_claim_id']) {
            throw new RuntimeException('The original target claim can no longer be matched safely.');
        }

        $targetConfig = riceConsolidationWaveConfig($targetWave);
        if ($audit['action_type'] === 'copy_signature') {
            $currentHash = riceConsolidationSignatureHash($target['e_signature'] ?? null);
            if (!hash_equals((string)$audit['result_signature_hash'], $currentHash)) {
                throw new RuntimeException('The signature changed after this action and cannot be restored safely.');
            }

            $restoredSignature = $audit['previous_signature'];
            riceConsolidationInsertAudit($conn, [
                'action_type' => 'restore_signature',
                'direction' => 'restore',
                'household_code' => $householdCode,
                'target_wave' => $targetWave,
                'target_claim_id' => $target['claim_id'],
                'previous_signature' => $target['e_signature'],
                'result_signature_hash' => riceConsolidationSignatureHash($restoredSignature),
                'operator_name' => $operator,
                'restored_from_id' => $auditId,
            ]);

            $updateStmt = $conn->prepare("UPDATE {$targetConfig['claim_table']} SET e_signature = ? WHERE id = ? LIMIT 1");
            $targetClaimId = $target['claim_id'];
            $updateStmt->bind_param('si', $restoredSignature, $targetClaimId);
            $updateStmt->execute();
        } else {
            $currentName = (string)($target['claimant_name'] ?? '');
            if ($currentName !== (string)$audit['result_claimant_name']) {
                throw new RuntimeException('The claimant name changed after this action and cannot be restored safely.');
            }

            $restoredName = (string)($audit['previous_claimant_name'] ?? '');
            riceConsolidationInsertAudit($conn, [
                'action_type' => 'restore_claimant',
                'direction' => 'restore',
                'household_code' => $householdCode,
                'target_wave' => $targetWave,
                'target_claim_id' => $target['claim_id'],
                'previous_claimant_name' => $currentName,
                'result_claimant_name' => $restoredName,
                'operator_name' => $operator,
                'restored_from_id' => $auditId,
            ]);

            $updateStmt = $conn->prepare("UPDATE {$targetConfig['claim_table']} SET claimant_name = ? WHERE id = ? LIMIT 1");
            $targetClaimId = $target['claim_id'];
            $updateStmt->bind_param('si', $restoredName, $targetClaimId);
            $updateStmt->execute();
        }

        $conn->commit();
        riceConsolidationJsonResponse(['success' => true, 'message' => 'The previous value was restored.']);
    }

    riceConsolidationJsonResponse(['success' => false, 'message' => 'Unsupported consolidation action.'], 422);
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }
    if ($error instanceof mysqli_sql_exception) {
        error_log('Rice claim consolidation database error: ' . $error->getMessage());
        riceConsolidationJsonResponse(['success' => false, 'message' => 'The consolidation change could not be completed. Please try again.'], 500);
    }
    riceConsolidationJsonResponse(['success' => false, 'message' => $error->getMessage()], 409);
}