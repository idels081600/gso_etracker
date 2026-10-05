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
        $sourceWaveInput = (string)($input['source_wave'] ?? '');
        $targetWaveInput = (string)($input['target_wave'] ?? '');
        $confirmed = (int)($input['confirmed'] ?? 0) === 1;
        $directions = [
            'first_to_second' => ['source' => 'first_wave', 'target' => 'second_wave'],
            'second_to_first' => ['source' => 'second_wave', 'target' => 'first_wave'],
        ];

        $validWaves = ['first_wave', 'second_wave', 'third_wave', 'fourth_wave'];
        if (in_array($sourceWaveInput, $validWaves, true) && in_array($targetWaveInput, $validWaves, true) && $sourceWaveInput !== $targetWaveInput) {
            $direction = $sourceWaveInput . '_to_' . $targetWaveInput;
            $directions[$direction] = ['source' => $sourceWaveInput, 'target' => $targetWaveInput];
        }

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

        if ($householdCode === '' || strlen($householdCode) > 50 || !in_array($wave, ['first_wave', 'second_wave', 'third_wave', 'fourth_wave'], true)) {
            riceConsolidationJsonResponse(['success' => false, 'message' => 'A valid wave and household code are required.'], 422);
        }
        if ($claimantName === '' || $length > 150) {
            riceConsolidationJsonResponse(['success' => false, 'message' => 'Claimant name is required and must not exceed 150 characters.'], 422);
        }

        $conn->begin_transaction();
        $pair = riceConsolidationLockPair($conn, $householdCode);
        $target = $pair[$wave] ?? null;
        if (!$target || $target['claim_id'] === null) {
            throw new RuntimeException('An existing claim record is required before a claimant name can be edited.');
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

    if ($action === 'update_first_wave_household_name') {
        $householdCode = trim((string)($input['household_code'] ?? ''));
        $mode = (string)($input['mode'] ?? 'edit');

        if ($householdCode === '' || strlen($householdCode) > 50 || !in_array($mode, ['edit', 'swap'], true)) {
            riceConsolidationJsonResponse(['success' => false, 'message' => 'A valid first-wave household and name action are required.'], 422);
        }

        $conn->begin_transaction();
        $pair = riceConsolidationLockPair($conn, $householdCode);
        $firstTarget = $pair['first_wave'] ?? null;
        $secondTarget = $pair['second_wave'] ?? null;
        if (!$firstTarget || $firstTarget['claim_id'] === null) {
            throw new RuntimeException('A claimed first-wave household is required before its name can be edited.');
        }

        $previousFirstName = trim((string)($firstTarget['first_name'] ?? ''));
        $previousLastName = trim((string)($firstTarget['last_name'] ?? ''));

        if ($mode === 'swap') {
            if ($previousFirstName === '' || $previousLastName === '') {
                throw new RuntimeException('Both first name and last name are required before they can be swapped.');
            }
            $firstName = $previousLastName;
            $lastName = $previousFirstName;
        } else {
            $firstName = trim((string)($input['first_name'] ?? ''));
            $lastName = trim((string)($input['last_name'] ?? ''));
        }

        $firstLength = function_exists('mb_strlen') ? mb_strlen($firstName) : strlen($firstName);
        $lastLength = function_exists('mb_strlen') ? mb_strlen($lastName) : strlen($lastName);
        $householdName = $lastName . ',' . $firstName;
        $householdLength = function_exists('mb_strlen') ? mb_strlen($householdName) : strlen($householdName);
        if ($firstName === '' || $lastName === '' || $firstLength > 150 || $lastLength > 150 || $householdLength > 150) {
            throw new RuntimeException('First name and last name are required, and the combined household name must not exceed 150 characters.');
        }

        $firstNeedsUpdate = $previousFirstName !== $firstName
            || $previousLastName !== $lastName
            || trim((string)$firstTarget['household_name']) !== $householdName;
        $secondNeedsUpdate = $secondTarget && (
            trim((string)($secondTarget['first_name'] ?? '')) !== $firstName
            || trim((string)($secondTarget['last_name'] ?? '')) !== $lastName
            || trim((string)$secondTarget['household_name']) !== $householdName
        );

        if (!$firstNeedsUpdate && !$secondNeedsUpdate) {
            throw new RuntimeException('The household name has not changed in either wave.');
        }

        if ($firstNeedsUpdate) {
            riceConsolidationInsertAudit($conn, [
                'action_type' => $mode === 'swap' ? 'swap_household_name' : 'update_household_name',
                'direction' => $mode === 'swap' ? 'swap_first_last' : null,
                'household_code' => $householdCode,
                'target_wave' => 'first_wave',
                'target_household_id' => $firstTarget['household_id'],
                'target_claim_id' => $firstTarget['claim_id'],
                'previous_first_name' => $previousFirstName,
                'previous_last_name' => $previousLastName,
                'result_first_name' => $firstName,
                'result_last_name' => $lastName,
                'operator_name' => $operator,
            ]);

            $updateFirstStmt = $conn->prepare(
                'UPDATE rice_households
                 SET first_name = ?, last_name = ?, household_name = ?
                 WHERE id = ? LIMIT 1'
            );
            $firstHouseholdId = (int)$firstTarget['household_id'];
            $updateFirstStmt->bind_param('sssi', $firstName, $lastName, $householdName, $firstHouseholdId);
            $updateFirstStmt->execute();
        }

        if ($secondNeedsUpdate) {
            $secondPreviousFirstName = trim((string)($secondTarget['first_name'] ?? ''));
            $secondPreviousLastName = trim((string)($secondTarget['last_name'] ?? ''));
            riceConsolidationInsertAudit($conn, [
                'action_type' => 'update_household_name',
                'direction' => 'sync_from_first_wave',
                'household_code' => $householdCode,
                'source_wave' => 'first_wave',
                'target_wave' => 'second_wave',
                'target_household_id' => $secondTarget['household_id'],
                'source_claim_id' => $firstTarget['claim_id'],
                'target_claim_id' => $secondTarget['claim_id'],
                'previous_first_name' => $secondPreviousFirstName,
                'previous_last_name' => $secondPreviousLastName,
                'result_first_name' => $firstName,
                'result_last_name' => $lastName,
                'operator_name' => $operator,
            ]);

            $updateSecondStmt = $conn->prepare(
                'UPDATE rice_claimed_households
                 SET first_name = ?, last_name = ?, household_name = ?
                 WHERE id = ? LIMIT 1'
            );
            $secondHouseholdId = (int)$secondTarget['household_id'];
            $updateSecondStmt->bind_param('sssi', $firstName, $lastName, $householdName, $secondHouseholdId);
            $updateSecondStmt->execute();
        }

        $conn->commit();

        $message = $mode === 'swap'
            ? 'First-wave first name and last name swapped.'
            : 'First-wave household name updated.';
        if ($secondTarget) {
            $message .= $secondNeedsUpdate
                ? ' The matching second-wave name was synchronized.'
                : ' The matching second-wave name was already synchronized.';
        } else {
            $message .= ' No matching second-wave household was found.';
        }

        riceConsolidationJsonResponse(['success' => true, 'message' => $message]);
    }
    if ($action === 'keep_first_wave_household_name') {
        $householdCode = trim((string)($input['household_code'] ?? ''));
        $expectedName = preg_replace('/\s+/', ' ', strtoupper(trim((string)($input['expected_name'] ?? ''))));
        if ($householdCode === '' || strlen($householdCode) > 50 || $expectedName === '') {
            riceConsolidationJsonResponse(['success' => false, 'message' => 'A valid household and reviewed name are required.'], 422);
        }

        $conn->begin_transaction();
        $pair = riceConsolidationLockPair($conn, $householdCode);
        $target = $pair['first_wave'] ?? null;
        if (!$target || $target['claim_id'] === null) {
            throw new RuntimeException('The claimed first-wave household can no longer be found.');
        }

        $currentName = preg_replace('/\s+/', ' ', strtoupper(trim((string)$target['household_name'])));
        if ($currentName !== $expectedName) {
            throw new RuntimeException('The household name changed after the review list was created. Reload the review queue.');
        }

        riceConsolidationInsertAudit($conn, [
            'action_type' => 'keep_household_name',
            'direction' => 'medium_confidence_keep',
            'household_code' => $householdCode,
            'target_wave' => 'first_wave',
            'target_household_id' => $target['household_id'],
            'target_claim_id' => $target['claim_id'],
            'previous_first_name' => trim((string)$target['first_name']),
            'previous_last_name' => trim((string)$target['last_name']),
            'result_first_name' => trim((string)$target['first_name']),
            'result_last_name' => trim((string)$target['last_name']),
            'operator_name' => $operator,
        ]);

        $conn->commit();
        riceConsolidationJsonResponse(['success' => true, 'message' => 'Name reviewed and kept as entered.']);
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

        if (!$audit || !in_array($audit['action_type'], ['copy_signature', 'update_claimant', 'update_household_name', 'swap_household_name'], true)) {
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
        $isHouseholdNameAction = in_array($audit['action_type'], ['update_household_name', 'swap_household_name'], true);
        if (!$target) {
            throw new RuntimeException('The original target household can no longer be matched safely.');
        }
        if ($isHouseholdNameAction) {
            if ($audit['target_household_id'] !== null && (int)$target['household_id'] !== (int)$audit['target_household_id']) {
                throw new RuntimeException('The original target household can no longer be matched safely.');
            }
        } elseif ($target['claim_id'] === null || (int)$target['claim_id'] !== (int)$audit['target_claim_id']) {
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
        } elseif ($audit['action_type'] === 'update_claimant') {
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
        } else {
            $currentFirstName = trim((string)($target['first_name'] ?? ''));
            $currentLastName = trim((string)($target['last_name'] ?? ''));
            if (
                $currentFirstName !== (string)($audit['result_first_name'] ?? '')
                || $currentLastName !== (string)($audit['result_last_name'] ?? '')
            ) {
                throw new RuntimeException('The household name changed after this action and cannot be restored safely.');
            }

            $restoredFirstName = (string)($audit['previous_first_name'] ?? '');
            $restoredLastName = (string)($audit['previous_last_name'] ?? '');
            $restoredHouseholdName = $restoredLastName . ',' . $restoredFirstName;
            riceConsolidationInsertAudit($conn, [
                'action_type' => 'restore_household_name',
                'direction' => 'restore',
                'household_code' => $householdCode,
                'target_wave' => $targetWave,
                'target_household_id' => $target['household_id'],
                'target_claim_id' => $target['claim_id'],
                'previous_first_name' => $currentFirstName,
                'previous_last_name' => $currentLastName,
                'result_first_name' => $restoredFirstName,
                'result_last_name' => $restoredLastName,
                'operator_name' => $operator,
                'restored_from_id' => $auditId,
            ]);

            $updateStmt = $conn->prepare(
                "UPDATE {$targetConfig['household_table']}
                 SET first_name = ?, last_name = ?, household_name = ?
                 WHERE id = ? LIMIT 1"
            );
            $targetHouseholdId = (int)$target['household_id'];
            $updateStmt->bind_param(
                'sssi',
                $restoredFirstName,
                $restoredLastName,
                $restoredHouseholdName,
                $targetHouseholdId
            );
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
