<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This setup can only be run from the command line.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = require __DIR__ . '/config/database.php';
$lock = $conn->query("SELECT GET_LOCK('rice_fourth_wave_setup', 30) AS acquired")->fetch_assoc();
if ((int)$lock['acquired'] !== 1) {
    throw new RuntimeException('Another fourth-batch setup is already running.');
}

try {
    $conn->query('CREATE TABLE IF NOT EXISTS rice_fourth_wave_households LIKE rice_claimed_households');

    $extraColumns = [
        'cohort_sequence' => 'INT UNSIGNED DEFAULT NULL AFTER `id`',
        'source_claim_id' => 'INT DEFAULT NULL AFTER `cohort_sequence`',
        'source_household_id' => 'INT DEFAULT NULL AFTER `source_claim_id`',
    ];
    foreach ($extraColumns as $column => $definition) {
        $stmt = $conn->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rice_fourth_wave_households' AND COLUMN_NAME = ?"
        );
        $stmt->bind_param('s', $column);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $conn->query("ALTER TABLE rice_fourth_wave_households ADD COLUMN `{$column}` {$definition}");
        }
    }

    $indexCheck = $conn->query(
        "SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rice_fourth_wave_households'
           AND INDEX_NAME = 'uniq_rice_fourth_wave_cohort_sequence'"
    );
    if ($indexCheck->num_rows === 0) {
        $conn->query('ALTER TABLE rice_fourth_wave_households ADD UNIQUE KEY uniq_rice_fourth_wave_cohort_sequence (cohort_sequence)');
        $conn->query('ALTER TABLE rice_fourth_wave_households ADD KEY idx_rice_fourth_wave_source_claim (source_claim_id)');
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS rice_fourth_wave_claims (
            id INT NOT NULL AUTO_INCREMENT,
            household_id INT NOT NULL,
            claimant_name VARCHAR(150) DEFAULT NULL,
            e_signature LONGTEXT,
            claim_date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            verifier_name VARCHAR(150) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_rice_fourth_wave_household_claim (household_id),
            KEY idx_rice_fourth_wave_claim_date (claim_date),
            KEY idx_rice_fourth_wave_claimant_name (claimant_name),
            CONSTRAINT rice_fourth_wave_claims_household_fk
                FOREIGN KEY (household_id) REFERENCES rice_fourth_wave_households (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS rice_release_batch_status (
            wave VARCHAR(32) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            activated_at DATETIME DEFAULT NULL,
            activated_by VARCHAR(150) DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (wave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $conn->query(
        "INSERT INTO rice_release_batch_status (wave, is_active) VALUES
            ('first_wave', 1), ('next_wave', 1), ('third_wave', 1), ('fourth_wave', 0)
         ON DUPLICATE KEY UPDATE wave = VALUES(wave)"
    );

    $total = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_fourth_wave_households')->fetch_assoc()['total'];
    $claimTotal = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_fourth_wave_claims')->fetch_assoc()['total'];

    if ($total === 0 && $claimTotal === 0) {
        $conn->begin_transaction();
        try {
            $conn->query(
                "INSERT INTO rice_fourth_wave_households (
                    cohort_sequence, source_claim_id, source_household_id,
                    household_code, household_code_prefix, household_code_number,
                    household_name, last_name, first_name, middle_name,
                    sex, pwd, age, office, designation, sectoral_representation, contact_number,
                    address, status, is_claimed, claimed_at, is_checked, modified
                )
                SELECT
                    source.cohort_sequence,
                    source.claim_id,
                    source.household_id,
                    source.household_code,
                    source.household_code_prefix,
                    source.household_code_number,
                    COALESCE(reference.household_name, source.household_name),
                    COALESCE(reference.last_name, source.last_name),
                    COALESCE(reference.first_name, source.first_name),
                    reference.middle_name,
                    reference.sex,
                    reference.pwd,
                    reference.age,
                    reference.office,
                    COALESCE(NULLIF(reference.designation, ''), source.address),
                    reference.sectoral_representation,
                    reference.contact_number,
                    COALESCE(NULLIF(reference.address, ''), source.address),
                    'Active', 0, NULL, 0, NULL
                FROM (
                    SELECT ordered.*, ROW_NUMBER() OVER (ORDER BY ordered.claim_date, ordered.household_name, ordered.claim_id) AS cohort_sequence
                    FROM (
                        SELECT
                            claim.id AS claim_id,
                            claim.claim_date,
                            household.id AS household_id,
                            household.household_code,
                            household.household_code_prefix,
                            household.household_code_number,
                            household.household_name,
                            household.last_name,
                            household.first_name,
                            household.address
                        FROM rice_voucher_claims claim
                        INNER JOIN rice_households household ON household.id = claim.household_id
                        WHERE household.is_claimed = 1
                        ORDER BY claim.claim_date ASC, household.household_name ASC, claim.id ASC
                        LIMIT 5404
                    ) ordered
                ) source
                LEFT JOIN rice_claimed_households reference
                    ON reference.household_code = source.household_code
                ORDER BY source.cohort_sequence"
            );
            $copied = $conn->affected_rows;
            if ($copied !== 5404) {
                throw new RuntimeException("Expected 5,404 fourth-batch households, copied {$copied}.");
            }
            $conn->commit();
            echo "Created fourth-batch snapshot: {$copied} households.\n";
        } catch (Throwable $error) {
            $conn->rollback();
            throw $error;
        }
    } elseif ($total !== 5404) {
        throw new RuntimeException("Fourth-batch household table already contains {$total} rows; no records were overwritten.");
    } else {
        echo "Fourth-batch snapshot already exists. No household or claim records were changed.\n";
    }

    $summary = $conn->query(
        "SELECT COUNT(*) AS total,
                COUNT(DISTINCT household_code) AS unique_codes,
                COUNT(DISTINCT cohort_sequence) AS unique_sequences,
                COALESCE(SUM(is_claimed = 1), 0) AS claimed
         FROM rice_fourth_wave_households"
    )->fetch_assoc();
    if ((int)$summary['total'] !== 5404 || (int)$summary['unique_codes'] !== 5404 || (int)$summary['unique_sequences'] !== 5404) {
        throw new RuntimeException('Fourth-batch integrity validation failed.');
    }

    echo "Fourth-batch households: {$summary['total']}\n";
    echo "Fourth-batch claimed: {$summary['claimed']}\n";
    echo "Fourth-batch proof records: {$claimTotal}\n";
    echo "Fourth-batch claiming remains locked until activate_rice_fourth_wave.php --activate is run.\n";
    echo "First-, second-, and third-batch tables were not modified.\n";
} finally {
    $conn->query("SELECT RELEASE_LOCK('rice_fourth_wave_setup')");
}

