<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This setup can only be run from the command line.');
}

$conn = require __DIR__ . '/config/database.php';

$conn->query(
    "CREATE TABLE IF NOT EXISTS rice_claim_consolidation_audit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        action_type ENUM('copy_signature', 'update_claimant', 'update_household_name', 'swap_household_name', 'keep_household_name', 'restore_signature', 'restore_claimant', 'restore_household_name') NOT NULL,
        direction VARCHAR(32) DEFAULT NULL,
        household_code VARCHAR(50) NOT NULL,
        source_wave ENUM('first_wave', 'second_wave') DEFAULT NULL,
        target_wave ENUM('first_wave', 'second_wave') NOT NULL,
        target_household_id INT DEFAULT NULL,
        source_claim_id INT DEFAULT NULL,
        target_claim_id INT DEFAULT NULL,
        previous_signature LONGTEXT DEFAULT NULL,
        result_signature_hash CHAR(64) DEFAULT NULL,
        previous_claimant_name VARCHAR(150) DEFAULT NULL,
        result_claimant_name VARCHAR(150) DEFAULT NULL,
        previous_first_name VARCHAR(150) DEFAULT NULL,
        previous_last_name VARCHAR(150) DEFAULT NULL,
        result_first_name VARCHAR(150) DEFAULT NULL,
        result_last_name VARCHAR(150) DEFAULT NULL,
        operator_name VARCHAR(150) NOT NULL,
        restored_from_id BIGINT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_rice_consolidation_household (household_code, created_at),
        KEY idx_rice_consolidation_target (target_wave, target_claim_id, created_at),
        KEY idx_rice_consolidation_restore (restored_from_id),
        KEY idx_rice_consolidation_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
);

$conn->query(
    "ALTER TABLE rice_claim_consolidation_audit
     MODIFY COLUMN source_wave ENUM('first_wave', 'second_wave', 'third_wave', 'fourth_wave') DEFAULT NULL,
     MODIFY COLUMN target_wave ENUM('first_wave', 'second_wave', 'third_wave', 'fourth_wave') NOT NULL"
);

$conn->query(
    "ALTER TABLE rice_claim_consolidation_audit
     MODIFY COLUMN action_type ENUM(
        'copy_signature', 'update_claimant', 'update_household_name', 'swap_household_name', 'keep_household_name',
        'restore_signature', 'restore_claimant', 'restore_household_name'
     ) NOT NULL"
);

$conn->query(
    "ALTER TABLE rice_claim_consolidation_audit
     MODIFY COLUMN target_claim_id INT DEFAULT NULL"
);

$auditColumns = [
    'target_household_id' => "INT DEFAULT NULL AFTER target_wave",
    'previous_first_name' => "VARCHAR(150) DEFAULT NULL AFTER result_claimant_name",
    'previous_last_name' => "VARCHAR(150) DEFAULT NULL AFTER previous_first_name",
    'result_first_name' => "VARCHAR(150) DEFAULT NULL AFTER previous_last_name",
    'result_last_name' => "VARCHAR(150) DEFAULT NULL AFTER result_first_name",
];

foreach ($auditColumns as $column => $definition) {
    $stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'rice_claim_consolidation_audit'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        $conn->query("ALTER TABLE rice_claim_consolidation_audit ADD COLUMN `{$column}` {$definition}");
    }
}

echo "rice_claim_consolidation_audit is ready. Existing claim data was not changed.\n";
