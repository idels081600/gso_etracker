<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This setup script can only be run from the command line.\n");
}

require_once __DIR__ . '/rice_household_code.php';
$conn = require __DIR__ . '/config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function riceColumnExists(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
}

function riceIndexExists(mysqli $conn, string $table, string $index): bool
{
    $sql = "SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
}

$tables = ['rice_households', 'rice_claimed_households'];
foreach ($tables as $table) {
    $alterClauses = [];
    if (!riceColumnExists($conn, $table, 'household_code_prefix')) {
        $alterClauses[] = "ADD COLUMN household_code_prefix VARCHAR(50) NOT NULL DEFAULT '' AFTER household_code";
    }
    if (!riceColumnExists($conn, $table, 'household_code_number')) {
        $alterClauses[] = "ADD COLUMN household_code_number INT NOT NULL DEFAULT 0 AFTER household_code_prefix";
    }
    if (!riceIndexExists($conn, $table, 'idx_rice_household_code_sort')) {
        $alterClauses[] = 'ADD INDEX idx_rice_household_code_sort (household_code_prefix, household_code_number, household_code)';
    }
    if (!riceIndexExists($conn, $table, 'idx_rice_household_address_sort')) {
        $alterClauses[] = 'ADD INDEX idx_rice_household_address_sort (address, household_code_prefix, household_code_number, household_code)';
    }
    if ($alterClauses) {
        $conn->query("ALTER TABLE {$table}\n" . implode(",\n", $alterClauses));
    }

    $result = $conn->query("SELECT id, household_code, address, household_code_prefix, household_code_number FROM {$table}");
    $updateStmt = $conn->prepare(
        "UPDATE {$table} SET household_code_prefix = ?, household_code_number = ? WHERE id = ?"
    );
    $total = 0;
    $updated = 0;
    while ($row = $result->fetch_assoc()) {
        $total++;
        $parsed = riceParseHouseholdCode((string)$row['household_code'], (string)($row['address'] ?? ''));
        if ($row['household_code_prefix'] === $parsed['prefix'] && (int)$row['household_code_number'] === $parsed['number']) {
            continue;
        }
        $id = (int)$row['id'];
        $prefix = $parsed['prefix'];
        $number = $parsed['number'];
        $updateStmt->bind_param('sii', $prefix, $number, $id);
        $updateStmt->execute();
        $updated++;
    }
    echo "{$table}: scanned {$total}, updated {$updated}.\n";
}

echo "Rice household code sort columns and indexes are ready.\n";
