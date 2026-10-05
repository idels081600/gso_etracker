<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This command can only be run from the command line.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = require __DIR__ . '/config/database.php';
$activate = in_array('--activate', $argv ?? [], true);

$requiredTables = ['rice_fourth_wave_households', 'rice_fourth_wave_claims', 'rice_release_batch_status'];
foreach ($requiredTables as $table) {
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new RuntimeException("{$table} is missing. Run setup_rice_fourth_wave.php first.");
    }
}

$summary = $conn->query(
    "SELECT COUNT(*) AS total,
            COUNT(DISTINCT household_code) AS unique_codes,
            COUNT(DISTINCT cohort_sequence) AS unique_sequences,
            COALESCE(SUM(is_claimed = 1), 0) AS claimed
     FROM rice_fourth_wave_households"
)->fetch_assoc();
$proofs = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_fourth_wave_claims')->fetch_assoc()['total'];
$status = $conn->query("SELECT is_active, activated_at, activated_by FROM rice_release_batch_status WHERE wave = 'fourth_wave'")->fetch_assoc();

if ((int)$summary['total'] !== 5404 || (int)$summary['unique_codes'] !== 5404 || (int)$summary['unique_sequences'] !== 5404) {
    throw new RuntimeException('Fourth-batch activation stopped: the 5,404-recipient snapshot failed validation.');
}
if ((int)$status['is_active'] === 1) {
    echo "Fourth-batch claiming is already active.\n";
    exit(0);
}
if (!$activate) {
    echo "Fourth-batch claiming is locked. Validation passed for 5,404 recipients.\n";
    echo "Run: php activate_rice_fourth_wave.php --activate\n";
    exit(0);
}
if ((int)$summary['claimed'] !== 0 || $proofs !== 0) {
    throw new RuntimeException('Fourth-batch activation stopped: an inactive batch must not contain claims or proof records.');
}

$operator = get_current_user() ?: 'cli';
$stmt = $conn->prepare(
    "UPDATE rice_release_batch_status
     SET is_active = 1, activated_at = NOW(), activated_by = ?
     WHERE wave = 'fourth_wave' AND is_active = 0"
);
$stmt->bind_param('s', $operator);
$stmt->execute();
if ($stmt->affected_rows !== 1) {
    throw new RuntimeException('Fourth-batch activation was not applied.');
}

echo "Fourth-batch claiming is now active for 5,404 recipients.\n";

