<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = require dirname(__DIR__) . '/config/database.php';

function batchFingerprint($conn, $table, $claims = false)
{
    $fields = $claims
        ? 'id, household_id, claimant_name, SHA2(COALESCE(e_signature, ""), 256) AS signature_hash, claim_date, verifier_name'
        : '*';
    $rows = $conn->query("SELECT {$fields} FROM {$table} ORDER BY id");
    $hash = hash_init('sha256');
    $count = 0;
    while ($row = $rows->fetch_assoc()) {
        hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
        ++$count;
    }
    return ['count' => $count, 'hash' => hash_final($hash)];
}

function previousBatchFingerprints($conn)
{
    return [
        'rice_households' => batchFingerprint($conn, 'rice_households'),
        'rice_voucher_claims' => batchFingerprint($conn, 'rice_voucher_claims', true),
        'rice_claimed_households' => batchFingerprint($conn, 'rice_claimed_households'),
        'rice_next_wave_claims' => batchFingerprint($conn, 'rice_next_wave_claims', true),
    ];
}

$baselinePath = $argv[2] ?? null;
if (!$baselinePath) {
    throw new RuntimeException('Usage: php tests/rice_third_wave_test.php --baseline|--verify <baseline.json>');
}
if (($argv[1] ?? '') === '--baseline') {
    file_put_contents($baselinePath, json_encode(previousBatchFingerprints($conn), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "PASS: Saved first- and second-batch fingerprints without exposing signature data.\n";
    exit;
}

$baseline = json_decode(file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
if (previousBatchFingerprints($conn) !== $baseline) {
    throw new RuntimeException('Previous-batch household or claim data changed.');
}
echo "PASS: Every first- and second-batch household and proof record is unchanged.\n";

$columns = $conn->query('SHOW COLUMNS FROM rice_claimed_households');
$comparisons = [];
while ($column = $columns->fetch_assoc()) {
    $name = $column['Field'];
    if (in_array($name, ['is_claimed', 'claimed_at', 'is_checked', 'modified'], true)) {
        continue;
    }
    $quoted = '`' . str_replace('`', '``', $name) . '`';
    $comparisons[] = "NOT (s.{$quoted} <=> t.{$quoted})";
}
$mismatches = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_claimed_households s '
    . 'LEFT JOIN rice_third_wave_households t ON t.id = s.id WHERE t.id IS NULL OR '
    . implode(' OR ', $comparisons))->fetch_assoc()['total'];
$thirdCount = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_third_wave_households')->fetch_assoc()['total'];
if ($mismatches !== 0 || $thirdCount !== $baseline['rice_claimed_households']['count']) {
    throw new RuntimeException('Third-batch snapshot does not match the entire second-batch household list.');
}
$dirty = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_third_wave_households '
    . 'WHERE is_claimed <> 0 OR claimed_at IS NOT NULL OR is_checked <> 0 OR modified IS NOT NULL')->fetch_assoc()['total'];
$proofs = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_third_wave_claims')->fetch_assoc()['total'];
if ($dirty !== 0 || $proofs !== 0) {
    throw new RuntimeException('New third-batch claim/check state is not empty.');
}
echo "PASS: All {$thirdCount} second-batch households copied, including unclaimed households; all third-batch claim/check state is clear.\n";

$beforeHouseholds = batchFingerprint($conn, 'rice_third_wave_households');
$beforeClaims = batchFingerprint($conn, 'rice_third_wave_claims', true);
$conn->begin_transaction();
try {
    $household = $conn->query("SELECT id FROM rice_third_wave_households WHERE status = 'Active' LIMIT 1 FOR UPDATE")->fetch_assoc();
    if (!$household) {
        throw new RuntimeException('No active household available for rollback-only claim test.');
    }
    $id = (int)$household['id'];
    $conn->query("INSERT INTO rice_third_wave_claims (household_id, claimant_name, verifier_name) VALUES ({$id}, 'Rollback test', 'CODEX_TEST')");
    $conn->query("UPDATE rice_third_wave_households SET is_claimed = 1, claimed_at = NOW() WHERE id = {$id}");
    $duplicateRejected = false;
    try {
        $conn->query("INSERT INTO rice_third_wave_claims (household_id) VALUES ({$id})");
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() !== 1062) {
            throw $error;
        }
        $duplicateRejected = true;
    }
    if (!$duplicateRejected) {
        throw new RuntimeException('Duplicate third-batch claim was accepted.');
    }
    echo "PASS: Third-batch proof/status write and duplicate-claim protection work in a rollback-only transaction.\n";
} finally {
    $conn->rollback();
}
if (batchFingerprint($conn, 'rice_third_wave_households') !== $beforeHouseholds
    || batchFingerprint($conn, 'rice_third_wave_claims', true) !== $beforeClaims
    || previousBatchFingerprints($conn) !== $baseline) {
    throw new RuntimeException('Rollback test changed real data.');
}
echo "PASS: Rollback left all three batches unchanged.\n";
