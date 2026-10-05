<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once dirname(__DIR__) . '/rice_release_batches.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = require dirname(__DIR__) . '/config/database.php';

function fourthWaveAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fourthWaveFingerprint(mysqli $conn, string $table): array
{
    $count = (int)$conn->query("SELECT COUNT(*) AS total FROM `{$table}`")->fetch_assoc()['total'];
    $checksum = $conn->query("CHECKSUM TABLE `{$table}`")->fetch_assoc();
    return [$count, (string)$checksum['Checksum']];
}

$protectedTables = [
    'rice_households', 'rice_voucher_claims',
    'rice_claimed_households', 'rice_next_wave_claims',
    'rice_third_wave_households', 'rice_third_wave_claims',
];
$before = [];
foreach ($protectedTables as $table) {
    $before[$table] = fourthWaveFingerprint($conn, $table);
}

$summary = $conn->query(
    "SELECT COUNT(*) AS total,
            COUNT(DISTINCT household_code) AS unique_codes,
            COUNT(DISTINCT cohort_sequence) AS unique_sequences,
            MIN(cohort_sequence) AS first_sequence,
            MAX(cohort_sequence) AS last_sequence,
            COALESCE(SUM(is_claimed = 1), 0) AS claimed,
            COALESCE(SUM(household_code IN ('PWD901', 'R677', 'PWD264')), 0) AS excluded_present
     FROM rice_fourth_wave_households"
)->fetch_assoc();
fourthWaveAssert((int)$summary['total'] === 5404, 'Fourth-wave snapshot must contain 5,404 recipients.');
fourthWaveAssert((int)$summary['unique_codes'] === 5404, 'Fourth-wave household codes must be unique.');
fourthWaveAssert((int)$summary['unique_sequences'] === 5404, 'Fourth-wave cohort sequences must be unique.');
fourthWaveAssert((int)$summary['first_sequence'] === 1 && (int)$summary['last_sequence'] === 5404, 'Fourth-wave sequence must cover 1 through 5,404.');
fourthWaveAssert((int)$summary['claimed'] === 0, 'Fourth-wave snapshot must start unclaimed.');
fourthWaveAssert((int)$summary['excluded_present'] === 0, 'The three later first-batch claims must be excluded.');
fourthWaveAssert(riceReleaseBatchIsActive($conn, 'third_wave'), 'Third-wave claiming must remain active.');
fourthWaveAssert(!riceReleaseBatchIsActive($conn, 'fourth_wave'), 'Fourth-wave claiming must remain locked.');

$candidate = $conn->query(
    "SELECT id, household_code, household_name
     FROM rice_fourth_wave_households
     WHERE status = 'Active' AND is_claimed = 0
     ORDER BY cohort_sequence LIMIT 1"
)->fetch_assoc();
fourthWaveAssert((bool)$candidate, 'A rollback claim candidate is required.');

$conn->begin_transaction();
try {
    $insert = $conn->prepare(
        'INSERT INTO rice_fourth_wave_claims (household_id, claimant_name, e_signature, verifier_name) VALUES (?, ?, ?, ?)'
    );
    $householdId = (int)$candidate['id'];
    $claimant = (string)$candidate['household_name'];
    $signature = 'data:image/png;base64,' . base64_encode(str_repeat('rollback-test', 60));
    $verifier = 'ROLLBACK TEST';
    $insert->bind_param('isss', $householdId, $claimant, $signature, $verifier);
    $insert->execute();

    $duplicateRejected = false;
    try {
        $insert->execute();
    } catch (mysqli_sql_exception $error) {
        $duplicateRejected = $error->getCode() === 1062;
    }
    fourthWaveAssert($duplicateRejected, 'A household must not receive two fourth-wave claim records.');

    $update = $conn->prepare('UPDATE rice_fourth_wave_households SET is_claimed = 1, claimed_at = NOW() WHERE id = ?');
    $update->bind_param('i', $householdId);
    $update->execute();
    fourthWaveAssert($update->affected_rows === 1, 'Rollback claim status update failed.');
} finally {
    $conn->rollback();
}

$afterCandidate = $conn->query('SELECT is_claimed, claimed_at FROM rice_fourth_wave_households WHERE id = ' . (int)$candidate['id'])->fetch_assoc();
fourthWaveAssert((int)$afterCandidate['is_claimed'] === 0 && $afterCandidate['claimed_at'] === null, 'Rollback did not restore the household claim state.');
$proofCount = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_fourth_wave_claims')->fetch_assoc()['total'];
fourthWaveAssert($proofCount === 0, 'Rollback did not remove the test proof record.');

foreach ($protectedTables as $table) {
    fourthWaveAssert($before[$table] === fourthWaveFingerprint($conn, $table), "Protected table changed during test: {$table}");
}

echo "Fourth-wave tests passed: cohort, exclusions, lock state, duplicate protection, rollback, and protected-table fingerprints.\n";

