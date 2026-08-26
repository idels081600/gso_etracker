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
        action_type ENUM('copy_signature', 'update_claimant', 'restore_signature', 'restore_claimant') NOT NULL,
        direction VARCHAR(32) DEFAULT NULL,
        household_code VARCHAR(50) NOT NULL,
        source_wave ENUM('first_wave', 'second_wave') DEFAULT NULL,
        target_wave ENUM('first_wave', 'second_wave') NOT NULL,
        source_claim_id INT DEFAULT NULL,
        target_claim_id INT NOT NULL,
        previous_signature LONGTEXT DEFAULT NULL,
        result_signature_hash CHAR(64) DEFAULT NULL,
        previous_claimant_name VARCHAR(150) DEFAULT NULL,
        result_claimant_name VARCHAR(150) DEFAULT NULL,
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

echo "rice_claim_consolidation_audit is ready. Existing claim data was not changed.\n";