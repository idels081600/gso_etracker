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
        'third_wave' => [
            'household_table' => 'rice_third_wave_households',
            'claim_table' => 'rice_third_wave_claims',
            'label' => 'Third Wave',
        ],
        'fourth_wave' => [
            'household_table' => 'rice_fourth_wave_households',
            'claim_table' => 'rice_fourth_wave_claims',
            'label' => 'Fourth Wave',
        ],
    ];

    if (!isset($configs[$wave])) {
        throw new InvalidArgumentException('Invalid rice claim wave.');
    }

    return $configs[$wave];
}

function riceConsolidationPairCte(bool $searching = false): string
{
    $matchingCodesCte = '';
    $searchJoin = '';

    if ($searching) {
        $matchingCodesCte = <<<'SQL'
matching_codes AS (
    SELECT household_code FROM rice_households WHERE household_code LIKE ?
    UNION
    SELECT household_code FROM rice_households WHERE household_name LIKE ?
    UNION
    SELECT fh.household_code
    FROM rice_voucher_claims fc
    INNER JOIN rice_households fh ON fh.id = fc.household_id
    WHERE fc.claimant_name LIKE ?
    UNION
    SELECT household_code FROM rice_claimed_households WHERE household_code LIKE ?
    UNION
    SELECT household_code FROM rice_claimed_households WHERE household_name LIKE ?
    UNION
    SELECT sh.household_code
    FROM rice_next_wave_claims sc
    INNER JOIN rice_claimed_households sh ON sh.id = sc.household_id
    WHERE sc.claimant_name LIKE ?
    UNION
    SELECT household_code FROM rice_third_wave_households WHERE household_code LIKE ?
    UNION
    SELECT household_code FROM rice_third_wave_households WHERE household_name LIKE ?
    UNION
    SELECT th.household_code
    FROM rice_third_wave_claims tc
    INNER JOIN rice_third_wave_households th ON th.id = tc.household_id
    WHERE tc.claimant_name LIKE ?
    UNION
    SELECT household_code FROM rice_fourth_wave_households WHERE household_code LIKE ?
    UNION
    SELECT household_code FROM rice_fourth_wave_households WHERE household_name LIKE ?
    UNION
    SELECT qh.household_code
    FROM rice_fourth_wave_claims qc
    INNER JOIN rice_fourth_wave_households qh ON qh.id = qc.household_id
    WHERE qc.claimant_name LIKE ?
),
SQL;
        $searchJoin = 'INNER JOIN matching_codes mc ON mc.household_code = codes.household_code';
    }

    return "WITH {$matchingCodesCte}claimed_codes AS (
    SELECT fh.household_code
    FROM rice_voucher_claims fc INNER JOIN rice_households fh ON fh.id = fc.household_id
    UNION
    SELECT sh.household_code
    FROM rice_next_wave_claims sc INNER JOIN rice_claimed_households sh ON sh.id = sc.household_id
    UNION
    SELECT th.household_code
    FROM rice_third_wave_claims tc INNER JOIN rice_third_wave_households th ON th.id = tc.household_id
    UNION
    SELECT qh.household_code
    FROM rice_fourth_wave_claims qc INNER JOIN rice_fourth_wave_households qh ON qh.id = qc.household_id
),
claim_pairs AS (
    SELECT
        codes.household_code,
        COALESCE(NULLIF(fh.household_code_prefix, ''), NULLIF(sh.household_code_prefix, ''), NULLIF(th.household_code_prefix, ''), NULLIF(qh.household_code_prefix, ''), '') AS sort_prefix,
        COALESCE(NULLIF(fh.household_code_number, 0), NULLIF(sh.household_code_number, 0), NULLIF(th.household_code_number, 0), NULLIF(qh.household_code_number, 0), 0) AS sort_number,
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
        th.id AS third_household_id,
        th.household_name AS third_household_name,
        th.address AS third_address,
        tc.id AS third_claim_id,
        tc.claimant_name AS third_claimant_name,
        tc.claim_date AS third_claim_date,
        tc.verifier_name AS third_verifier_name,
        qh.id AS fourth_household_id,
        qh.household_name AS fourth_household_name,
        qh.address AS fourth_address,
        qc.id AS fourth_claim_id,
        qc.claimant_name AS fourth_claimant_name,
        qc.claim_date AS fourth_claim_date,
        qc.verifier_name AS fourth_verifier_name,
        CASE
            WHEN (
                (fh.household_name IS NOT NULL AND sh.household_name IS NOT NULL AND UPPER(TRIM(fh.household_name)) <> UPPER(TRIM(sh.household_name)))
                OR (fh.household_name IS NOT NULL AND th.household_name IS NOT NULL AND UPPER(TRIM(fh.household_name)) <> UPPER(TRIM(th.household_name)))
                OR (fh.household_name IS NOT NULL AND qh.household_name IS NOT NULL AND UPPER(TRIM(fh.household_name)) <> UPPER(TRIM(qh.household_name)))
                OR (sh.household_name IS NOT NULL AND th.household_name IS NOT NULL AND UPPER(TRIM(sh.household_name)) <> UPPER(TRIM(th.household_name)))
                OR (sh.household_name IS NOT NULL AND qh.household_name IS NOT NULL AND UPPER(TRIM(sh.household_name)) <> UPPER(TRIM(qh.household_name)))
                OR (th.household_name IS NOT NULL AND qh.household_name IS NOT NULL AND UPPER(TRIM(th.household_name)) <> UPPER(TRIM(qh.household_name)))
            ) THEN 'name_difference'
            WHEN fc.id IS NOT NULL AND sc.id IS NOT NULL AND tc.id IS NOT NULL AND qc.id IS NOT NULL THEN 'all_four'
            ELSE 'partial_claims'
        END AS pair_state
    FROM claimed_codes codes
    {$searchJoin}
    LEFT JOIN rice_households fh ON fh.household_code = codes.household_code
    LEFT JOIN rice_voucher_claims fc ON fc.household_id = fh.id
    LEFT JOIN rice_claimed_households sh ON sh.household_code = codes.household_code
    LEFT JOIN rice_next_wave_claims sc ON sc.household_id = sh.id
    LEFT JOIN rice_third_wave_households th ON th.household_code = codes.household_code
    LEFT JOIN rice_third_wave_claims tc ON tc.household_id = th.id
    LEFT JOIN rice_fourth_wave_households qh ON qh.household_code = codes.household_code
    LEFT JOIN rice_fourth_wave_claims qc ON qc.household_id = qh.id
)
";
}
function riceConsolidationFetchWaveRecord(mysqli $conn, string $wave, string $householdCode, bool $forUpdate = false): ?array
{
    $config = riceConsolidationWaveConfig($wave);
    $lock = $forUpdate ? ' FOR UPDATE' : '';
    $sql = "SELECT
                h.id AS household_id,
                h.household_code,
                h.household_name,
                h.first_name,
                h.last_name,
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
        'third_wave' => riceConsolidationFetchWaveRecord($conn, 'third_wave', $householdCode, true),
        'fourth_wave' => riceConsolidationFetchWaveRecord($conn, 'fourth_wave', $householdCode, true),
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
                target_household_id, source_claim_id, target_claim_id, previous_signature, result_signature_hash,
                previous_claimant_name, result_claimant_name,
                previous_first_name, previous_last_name, result_first_name, result_last_name,
                operator_name, restored_from_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    $actionType = $audit['action_type'];
    $direction = $audit['direction'] ?? null;
    $householdCode = $audit['household_code'];
    $sourceWave = $audit['source_wave'] ?? null;
    $targetWave = $audit['target_wave'];
    $targetHouseholdId = $audit['target_household_id'] ?? null;
    $sourceClaimId = $audit['source_claim_id'] ?? null;
    $targetClaimId = $audit['target_claim_id'] ?? null;
    $previousSignature = $audit['previous_signature'] ?? null;
    $resultSignatureHash = $audit['result_signature_hash'] ?? null;
    $previousClaimantName = $audit['previous_claimant_name'] ?? null;
    $resultClaimantName = $audit['result_claimant_name'] ?? null;
    $previousFirstName = $audit['previous_first_name'] ?? null;
    $previousLastName = $audit['previous_last_name'] ?? null;
    $resultFirstName = $audit['result_first_name'] ?? null;
    $resultLastName = $audit['result_last_name'] ?? null;
    $operatorName = $audit['operator_name'];
    $restoredFromId = $audit['restored_from_id'] ?? null;

    $stmt->bind_param(
        'sssssiiisssssssssi',
        $actionType,
        $direction,
        $householdCode,
        $sourceWave,
        $targetWave,
        $targetHouseholdId,
        $sourceClaimId,
        $targetClaimId,
        $previousSignature,
        $resultSignatureHash,
        $previousClaimantName,
        $resultClaimantName,
        $previousFirstName,
        $previousLastName,
        $resultFirstName,
        $resultLastName,
        $operatorName,
        $restoredFromId
    );
    $stmt->execute();
    return (int)$conn->insert_id;
}
