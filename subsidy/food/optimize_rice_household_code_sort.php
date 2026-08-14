<?php
require_once __DIR__ . '/rice_household_code.php';
$conn = require(__DIR__ . '/config/database.php');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function riceColumnExists(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    return (bool)($result && $result->fetch_row());
}

function riceIndexExists(mysqli $conn, string $table, string $index): bool
{
    $sql = "SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $result = $stmt->get_result();
    return (bool)($result && $result->fetch_row());
}

$alterClauses = [];
if (!riceColumnExists($conn, 'rice_households', 'household_code_prefix')) {
    $alterClauses[] = "ADD COLUMN household_code_prefix VARCHAR(50) NOT NULL DEFAULT '' AFTER household_code";
}
if (!riceColumnExists($conn, 'rice_households', 'household_code_number')) {
    $alterClauses[] = "ADD COLUMN household_code_number INT NOT NULL DEFAULT 0 AFTER household_code_prefix";
}
if (!riceIndexExists($conn, 'rice_households', 'idx_rice_household_code_sort')) {
    $alterClauses[] = "ADD INDEX idx_rice_household_code_sort (household_code_prefix, household_code_number, household_code)";
}
if (!riceIndexExists($conn, 'rice_households', 'idx_rice_household_address_sort')) {
    $alterClauses[] = "ADD INDEX idx_rice_household_address_sort (address, household_code_prefix, household_code_number, household_code)";
}

if ($alterClauses) {
    $conn->query("ALTER TABLE rice_households\n" . implode(",\n", $alterClauses));
}

$result = $conn->query("SELECT id, household_code, address, household_code_prefix, household_code_number FROM rice_households");
$updateStmt = $conn->prepare(
    "UPDATE rice_households
     SET household_code_prefix = ?,
         household_code_number = ?
     WHERE id = ?"
);

$total = 0;
$updated = 0;
while ($row = $result->fetch_assoc()) {
    $total++;
    $parsed = riceParseHouseholdCode((string)$row['household_code'], (string)($row['address'] ?? ''));
    $prefix = $parsed['prefix'];
    $number = $parsed['number'];

    if ($row['household_code_prefix'] === $prefix && (int)$row['household_code_number'] === $number) {
        continue;
    }

    $id = (int)$row['id'];
    $updateStmt->bind_param('sii', $prefix, $number, $id);
    $updateStmt->execute();
    $updated++;
}

echo "Rice household code sort optimization complete.\n";
echo "Rows scanned: {$total}\n";
echo "Rows updated: {$updated}\n";
echo "Columns/indexes ready.\n";
?>
