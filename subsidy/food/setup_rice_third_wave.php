<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This setup can only be run from the command line.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = require __DIR__ . '/config/database.php';
$lock = $conn->query("SELECT GET_LOCK('rice_third_wave_setup', 30) AS acquired")->fetch_assoc();
if ((int)$lock['acquired'] !== 1) {
    throw new RuntimeException('Another third-batch setup is already running.');
}

try {
    $conn->query('CREATE TABLE IF NOT EXISTS rice_third_wave_households LIKE rice_claimed_households');
    $conn->query(
        "CREATE TABLE IF NOT EXISTS rice_third_wave_claims (
            id INT NOT NULL AUTO_INCREMENT,
            household_id INT NOT NULL,
            claimant_name VARCHAR(150) DEFAULT NULL,
            e_signature LONGTEXT,
            claim_date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            verifier_name VARCHAR(150) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_rice_third_wave_household_claim (household_id),
            KEY idx_rice_third_wave_claim_date (claim_date),
            KEY idx_rice_third_wave_claimant_name (claimant_name),
            CONSTRAINT rice_third_wave_claims_household_fk
                FOREIGN KEY (household_id) REFERENCES rice_third_wave_households (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );

    $total = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_third_wave_households')->fetch_assoc()['total'];
    $claims = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_third_wave_claims')->fetch_assoc()['total'];
    if ($total === 0 && $claims === 0) {
        $columns = [];
        $values = [];
        $resetValues = ['is_claimed' => '0', 'claimed_at' => 'NULL', 'is_checked' => '0', 'modified' => 'NULL'];
        $schema = $conn->query('SHOW COLUMNS FROM rice_claimed_households');
        while ($column = $schema->fetch_assoc()) {
            if (preg_match('/(?:VIRTUAL|STORED) GENERATED/i', $column['Extra'])) {
                continue;
            }
            $name = $column['Field'];
            $quoted = '`' . str_replace('`', '``', $name) . '`';
            $columns[] = $quoted;
            $values[] = $resetValues[$name] ?? $quoted;
        }

        $conn->begin_transaction();
        try {
            // Copy the complete second-batch list, never just its claimed subset.
            $conn->query('INSERT INTO rice_third_wave_households (' . implode(', ', $columns) . ') '
                . 'SELECT ' . implode(', ', $values) . ' FROM rice_claimed_households');
            $copied = $conn->affected_rows;
            $conn->commit();
            echo "Created third-batch snapshot: {$copied} households.\n";
        } catch (Throwable $error) {
            $conn->rollback();
            throw $error;
        }
    } else {
        echo "Third-batch snapshot already exists. No household or claim records were changed.\n";
    }

    $summary = $conn->query('SELECT COUNT(*) AS total, COALESCE(SUM(is_claimed = 1), 0) AS claimed FROM rice_third_wave_households')->fetch_assoc();
    $claims = (int)$conn->query('SELECT COUNT(*) AS total FROM rice_third_wave_claims')->fetch_assoc()['total'];
    echo "Third-batch households: {$summary['total']}\nThird-batch claimed: {$summary['claimed']}\nThird-batch proof records: {$claims}\n";
    echo "First- and second-batch tables were not modified.\n";
} finally {
    $conn->query("SELECT RELEASE_LOCK('rice_third_wave_setup')");
}
