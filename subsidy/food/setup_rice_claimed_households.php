<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = require(__DIR__ . '/config/database.php');

function riceClaimedColumnExists(mysqli $conn, string $column): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'rice_claimed_households'
           AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)$result['total'] > 0;
}

try {
    $conn->begin_transaction();

    $conn->query('CREATE TABLE IF NOT EXISTS rice_claimed_households LIKE rice_households');

    $columns = [
        'last_name' => "VARCHAR(100) NULL DEFAULT NULL AFTER household_name",
        'first_name' => "VARCHAR(150) NULL DEFAULT NULL AFTER last_name",
        'middle_name' => "VARCHAR(150) NULL DEFAULT NULL AFTER first_name",
        'sex' => "ENUM('M','F') NULL DEFAULT NULL AFTER middle_name",
        'pwd' => "TINYINT(1) NULL DEFAULT NULL AFTER sex",
        'age' => "SMALLINT UNSIGNED NULL DEFAULT NULL AFTER pwd",
        'office' => "VARCHAR(150) NULL DEFAULT NULL AFTER age",
        'designation' => "VARCHAR(150) NULL DEFAULT NULL AFTER office",
        'sectoral_representation' => "VARCHAR(50) NULL DEFAULT NULL AFTER designation",
        'contact_number' => "VARCHAR(30) NULL DEFAULT NULL AFTER sectoral_representation",
    ];

    foreach ($columns as $column => $definition) {
        if (!riceClaimedColumnExists($conn, $column)) {
            $conn->query("ALTER TABLE rice_claimed_households ADD COLUMN `$column` $definition");
        }
    }

    $conn->query(
        "INSERT INTO rice_claimed_households (
            id,
            household_code,
            household_code_prefix,
            household_code_number,
            household_name,
            address,
            status,
            is_claimed,
            is_checked,
            claimed_at,
            modified,
            created_at,
            updated_at
        )
        SELECT
            id,
            household_code,
            household_code_prefix,
            household_code_number,
            household_name,
            address,
            status,
            0,
            is_checked,
            NULL,
            modified,
            created_at,
            updated_at
        FROM rice_households
        WHERE is_claimed = 1
        ON DUPLICATE KEY UPDATE
            household_code = VALUES(household_code),
            household_code_prefix = VALUES(household_code_prefix),
            household_code_number = VALUES(household_code_number),
            household_name = VALUES(household_name),
            address = VALUES(address),
            status = VALUES(status),
            is_checked = VALUES(is_checked),
            modified = VALUES(modified),
            created_at = VALUES(created_at),
            updated_at = VALUES(updated_at)"
    );
    $conn->query(
        "UPDATE rice_claimed_households
         SET last_name = NULLIF(TRIM(SUBSTRING_INDEX(household_name, ',', 1)), ''),
             first_name = CASE
                 WHEN household_name LIKE '%,%'
                 THEN NULLIF(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(household_name, ',', 2), ',', -1)), '')
                 ELSE NULL
             END,
             middle_name = CASE
                 WHEN LENGTH(household_name) - LENGTH(REPLACE(household_name, ',', '')) >= 2
                 THEN NULLIF(
                     TRIM(SUBSTRING(
                         household_name,
                         LOCATE(',', household_name, LOCATE(',', household_name) + 1) + 1
                     )),
                     ''
                 )
                 ELSE NULL
             END"
    );

    $conn->commit();

    $result = $conn->query('SELECT COUNT(*) AS total FROM rice_claimed_households');
    $total = (int)$result->fetch_assoc()['total'];

    echo "rice_claimed_households is ready. Copied claimed rows: {$total}" . PHP_EOL;
} catch (Throwable $error) {
    $conn->rollback();
    fwrite(STDERR, 'Setup failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}