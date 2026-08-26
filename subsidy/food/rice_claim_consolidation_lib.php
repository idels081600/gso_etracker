<?php

function riceConsolidationJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload);
    exit();
}

function riceConsolidationRequireVerifier(): void
{
    if (
        !isset($_SESSION['username'], $_SESSION['logged_in'], $_SESSION['role'])
        || $_SESSION['logged_in'] !== true
        || $_SESSION['role'] !== 'RICE_VERIFIER'
    ) {
        riceConsolidationJsonResponse(['success' => false, 'message' => 'Unauthorized access'], 403);
    }
}

function riceConsolidationWaveConfig(string $wave): array
{
    $configs = [
        'first_wave' => [
            'household_table' => 'rice_households',
            'claim_table' => 'rice_voucher_claims',
            'label' => 'First Wave',
        ],
        'second_wave' => [
            'household_table' => 'rice_claimed_households',
            'claim_table' => 'rice_next_wave_claims',
            'label' => 'Second Wave',
        ],
    ];

    if (!isset($configs[$wave])) {
        throw new InvalidArgumentException('Invalid rice claim wave.');
    }

    return $configs[$wave];
}

function riceConsolidationPairCte(): string
{
    return <<<'SQL'
WITH claim_pairs AS (
    SELECT
        fh.household_code,
        COALESCE(NULLIF(fh.household_code_prefix, ''), NULLIF(sh.household_code_prefix, ''), '') AS sort_prefix,
        COALESCE(NULLIF(fh.household_code_number, 0), NULLIF(sh.household_code_number, 0), 0) AS sort_number,
        fh.id AS first_household_id,
        fh.household_name AS first_household_name,
        fh.address AS first_address,
        fc.id AS first_claim_id,
        fc.claimant_name AS first_claimant_name,
        fc.claim_date AS first_claim_date,
        fc.verifier_name AS first_verifier_name,
        sh.id AS second_household_id,
        sh.household_name AS second_household_name,
        sh.address AS second_address,
        sc.id AS second_claim_id,
        sc.claimant_name AS second_claimant_name,
        sc.claim_date AS second_claim_date,
        sc.verifier_name AS second_verifier_name,
        CASE
            WHEN sh.id IS NULL THEN 'unmatched'
            WHEN sc.id IS NULL THEN 'first_wave_only'
            WHEN UPPER(TRIM(fh.household_name)) <> UPPER(TRIM(sh.household_name)) THEN 'name_difference'
            ELSE 'both_waves'
        END AS pair_state
    FROM rice_voucher_claims fc
    INNER JOIN rice_households fh ON fh.id = fc.household_id
    LEFT JOIN rice_claimed_households sh ON sh.household_code = fh.household_code
    LEFT JOIN rice_next_wave_claims sc ON sc.household_id = sh.id

    UNION ALL

    SELECT
        sh.household_code,
        COALESCE(NULLIF(sh.household_code_prefix, ''), NULLIF(fh.household_code_prefix, ''), '') AS sort_prefix,
        COALESCE(NULLIF(sh.household_code_number, 0), NULLIF(fh.household_code_number, 0), 0) AS sort_number,
        fh.id AS first_household_id,
        fh.household_name AS first_household_name,
        fh.address AS first_address,
        fc.id AS first_claim_id,
        fc.claimant_name AS first_claimant_name,
        fc.claim_date AS first_claim_date,
        fc.verifier_name AS first_verifier_name,
        sh.id AS second_household_id,
        sh.household_name AS second_household_name,
        sh.address AS second_address,
        sc.id AS second_claim_id,
        sc.claimant_name AS second_claimant_name,
        sc.claim_date AS second_claim_date,
        sc.verifier_name AS second_verifier_name,
        CASE WHEN fh.id IS NULL THEN 'unmatched' ELSE 'second_wave_only' END AS pair_state
    FROM rice_next_wave_claims sc
    INNER JOIN rice_claimed_households sh ON sh.id = sc.household_id
    LEFT JOIN rice_households fh ON fh.household_code = sh.household_code
    LEFT JOIN rice_voucher_claims fc ON fc.household_id = fh.id
    WHERE fc.id IS NULL
)
SQL;
}

function riceConsolidationFetchWaveRecord(mysqli $conn, string $wave, string $householdCode, bool $forUpdate = false): ?array
{
    $config = riceConsolidationWaveConfig($wave);
    $lock = $forUpdate ? ' FOR UPDATE' : '';
    $sql = "SELECT
                h.id AS household_id,
                h.household_code,
                h.household_name,
                h.address,
                c.id AS claim_id,
                c.claimant_name,
                c.claim_date,
                c.verifier_name,
                c.e_signature
            FROM {$config['household_table']} h
            LEFT JOIN {$config['claim_table']} c ON c.household_id = h.id
            WHERE h.household_code = ?
            LIMIT 1{$lock}";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $householdCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if (!$row) {
        return null;
    }

    $row['household_id'] = (int)$row['household_id'];
    $row['claim_id'] = $row['claim_id'] !== null ? (int)$row['claim_id'] : null;
    $row['has_signature'] = !empty(trim((string)($row['e_signature'] ?? ''))) ? 1 : 0;
    return $row;
}

function riceConsolidationLockPair(mysqli $conn, string $householdCode): array
{
    return [
        'first_wave' => riceConsolidationFetchWaveRecord($conn, 'first_wave', $householdCode, true),
        'second_wave' => riceConsolidationFetchWaveRecord($conn, 'second_wave', $householdCode, true),
    ];
}

function riceConsolidationSignatureIsValid(?string $signature): bool
{
    if (!is_string($signature) || !preg_match('/^data:image\/(?:png|jpeg|jpg);base64,/', $signature)) {
        return false;
    }

    $encoded = preg_replace('/^data:image\/(?:png|jpeg|jpg);base64,/', '', $signature);
    $decoded = base64_decode($encoded, true);
    return $decoded !== false && strlen($decoded) >= 100;
}

function riceConsolidationSignatureHash(?string $signature): string
{
    return hash('sha256', (string)$signature);
}

function riceConsolidationInsertAudit(mysqli $conn, array $audit): int
{
    $sql = "INSERT INTO rice_claim_consolidation_audit (
                action_type, direction, household_code, source_wave, target_wave,
                source_claim_id, target_claim_id, previous_signature, result_signature_hash,
                previous_claimant_name, result_claimant_name, operator_name, restored_from_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    $actionType = $audit['action_type'];
    $direction = $audit['direction'] ?? null;
    $householdCode = $audit['household_code'];
    $sourceWave = $audit['source_wave'] ?? null;
    $targetWave = $audit['target_wave'];
    $sourceClaimId = $audit['source_claim_id'] ?? null;
    $targetClaimId = $audit['target_claim_id'];
    $previousSignature = $audit['previous_signature'] ?? null;
    $resultSignatureHash = $audit['result_signature_hash'] ?? null;
    $previousClaimantName = $audit['previous_claimant_name'] ?? null;
    $resultClaimantName = $audit['result_claimant_name'] ?? null;
    $operatorName = $audit['operator_name'];
    $restoredFromId = $audit['restored_from_id'] ?? null;

    $stmt->bind_param(
        'sssssiisssssi',
        $actionType,
        $direction,
        $householdCode,
        $sourceWave,
        $targetWave,
        $sourceClaimId,
        $targetClaimId,
        $previousSignature,
        $resultSignatureHash,
        $previousClaimantName,
        $resultClaimantName,
        $operatorName,
        $restoredFromId
    );
    $stmt->execute();
    return (int)$conn->insert_id;
}