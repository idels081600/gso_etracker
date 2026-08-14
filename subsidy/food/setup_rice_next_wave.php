<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This setup can only be run from the command line.');
}

$conn = require(__DIR__ . '/config/database.php');
$should_reset = in_array('--reset', $argv, true);

$conn->query(
    "CREATE TABLE IF NOT EXISTS rice_next_wave_claims (
        id INT NOT NULL AUTO_INCREMENT,
        household_id INT NOT NULL,
        claimant_name VARCHAR(150) DEFAULT NULL,
        e_signature LONGTEXT,
        claim_date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        verifier_name VARCHAR(150) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_rice_next_wave_household_claim (household_id),
        KEY idx_rice_next_wave_claim_date (claim_date),
        CONSTRAINT rice_next_wave_claims_household_fk
            FOREIGN KEY (household_id) REFERENCES rice_claimed_households (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
);

if (!$should_reset) {
    echo "rice_next_wave_claims is ready. No household statuses were reset.\n";
    exit(0);
}

$existing_claims = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM rice_next_wave_claims'
)->fetch_assoc()['total'];

if ($existing_claims > 0) {
    fwrite(STDERR, "Reset refused: rice_next_wave_claims already contains {$existing_claims} claim(s).\n");
    exit(1);
}

$active_list_total = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM rice_claimed_households'
)->fetch_assoc()['total'];
$active_list_claimed_before = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM rice_claimed_households WHERE is_claimed = 1'
)->fetch_assoc()['total'];
$first_wave_claimed = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM rice_households WHERE is_claimed = 1'
)->fetch_assoc()['total'];
$first_wave_proofs = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM rice_voucher_claims'
)->fetch_assoc()['total'];

$conn->begin_transaction();
try {
    $conn->query(
        'UPDATE rice_claimed_households
         SET is_claimed = 0,
             claimed_at = NULL'
    );
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}

$active_list_claimed_after = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM rice_claimed_households WHERE is_claimed = 1'
)->fetch_assoc()['total'];
$first_wave_claimed_after = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM rice_households WHERE is_claimed = 1'
)->fetch_assoc()['total'];
$first_wave_proofs_after = (int)$conn->query(
    'SELECT COUNT(*) AS total FROM rice_voucher_claims'
)->fetch_assoc()['total'];

echo "Next-wave setup complete.\n";
echo "Active next-wave households: {$active_list_total}\n";
echo "Next-wave claimed before reset: {$active_list_claimed_before}\n";
echo "Next-wave claimed after reset: {$active_list_claimed_after}\n";
echo "First-wave claimed preserved: {$first_wave_claimed_after} (before: {$first_wave_claimed})\n";
echo "First-wave proof rows preserved: {$first_wave_proofs_after} (before: {$first_wave_proofs})\n";