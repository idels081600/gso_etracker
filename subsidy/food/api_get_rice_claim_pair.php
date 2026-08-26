<?php
session_start();
require_once __DIR__ . '/rice_claim_consolidation_lib.php';

riceConsolidationRequireVerifier();
$conn = require __DIR__ . '/config/database.php';
mysqli_report(MYSQLI_REPORT_OFF);

$householdCode = trim((string)($_GET['household_code'] ?? ''));
if ($householdCode === '' || strlen($householdCode) > 50) {
    riceConsolidationJsonResponse(['success' => false, 'message' => 'A valid household code is required.'], 422);
}

$first = riceConsolidationFetchWaveRecord($conn, 'first_wave', $householdCode);
$second = riceConsolidationFetchWaveRecord($conn, 'second_wave', $householdCode);
$firstClaimExists = $first && $first['claim_id'] !== null;
$secondClaimExists = $second && $second['claim_id'] !== null;

if (!$firstClaimExists && !$secondClaimExists) {
    riceConsolidationJsonResponse(['success' => false, 'message' => 'No claimed record was found for this household code.'], 404);
}

$nameDifference = $first && $second
    && strcasecmp(trim((string)$first['household_name']), trim((string)$second['household_name'])) !== 0;
$canConsolidate = $firstClaimExists && $secondClaimExists;

$currentByWave = [
    'first_wave' => $first,
    'second_wave' => $second,
];
$historyStmt = $conn->prepare(
    "SELECT
        audit.id,
        audit.action_type,
        audit.direction,
        audit.source_wave,
        audit.target_wave,
        audit.source_claim_id,
        audit.target_claim_id,
        audit.result_signature_hash,
        audit.previous_claimant_name,
        audit.result_claimant_name,
        audit.operator_name,
        audit.restored_from_id,
        audit.created_at,
        CASE WHEN audit.previous_signature IS NOT NULL THEN 1 ELSE 0 END AS has_previous_signature,
        CASE WHEN restored.id IS NULL THEN 0 ELSE 1 END AS has_been_restored
     FROM rice_claim_consolidation_audit audit
     LEFT JOIN rice_claim_consolidation_audit restored ON restored.restored_from_id = audit.id
     WHERE audit.household_code = ?
     ORDER BY audit.id DESC
     LIMIT 25"
);
$historyStmt->bind_param('s', $householdCode);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$history = [];

while ($row = $historyResult->fetch_assoc()) {
    $target = $currentByWave[$row['target_wave']] ?? null;
    $restorable = false;

    if ($target && (int)$target['claim_id'] === (int)$row['target_claim_id'] && (int)$row['has_been_restored'] === 0) {
        if ($row['action_type'] === 'copy_signature') {
            $restorable = riceConsolidationSignatureHash($target['e_signature'] ?? null) === (string)$row['result_signature_hash'];
        } elseif ($row['action_type'] === 'update_claimant') {
            $restorable = (string)($target['claimant_name'] ?? '') === (string)($row['result_claimant_name'] ?? '');
        }
    }

    $history[] = [
        'id' => (int)$row['id'],
        'action_type' => $row['action_type'],
        'direction' => $row['direction'],
        'source_wave' => $row['source_wave'],
        'target_wave' => $row['target_wave'],
        'operator_name' => $row['operator_name'],
        'created_at' => $row['created_at'],
        'restored_from_id' => $row['restored_from_id'] !== null ? (int)$row['restored_from_id'] : null,
        'has_been_restored' => (int)$row['has_been_restored'],
        'restorable' => $restorable ? 1 : 0,
    ];
}

$normalize = static function (?array $record): ?array {
    if (!$record) {
        return null;
    }

    return [
        'household_id' => $record['household_id'],
        'household_code' => $record['household_code'],
        'household_name' => $record['household_name'],
        'address' => $record['address'],
        'claim_id' => $record['claim_id'],
        'claimant_name' => $record['claimant_name'],
        'claim_date' => $record['claim_date'],
        'verifier_name' => $record['verifier_name'],
        'e_signature' => $record['e_signature'],
        'has_signature' => $record['has_signature'],
    ];
};

riceConsolidationJsonResponse([
    'success' => true,
    'data' => [
        'household_code' => $householdCode,
        'first_wave' => $normalize($first),
        'second_wave' => $normalize($second),
        'can_consolidate' => $canConsolidate ? 1 : 0,
        'is_matched' => ($first && $second) ? 1 : 0,
        'name_difference' => $nameDifference ? 1 : 0,
        'history' => $history,
    ],
]);