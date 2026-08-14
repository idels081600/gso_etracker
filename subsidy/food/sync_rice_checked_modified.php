<?php
$conn = require(__DIR__ . '/config/database.php');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function riceColumnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    return (bool)($result && $result->fetch_row());
}

function riceIndexExists(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?
         LIMIT 1"
    );
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $result = $stmt->get_result();
    return (bool)($result && $result->fetch_row());
}

$alterClauses = [];
if (!riceColumnExists($conn, 'rice_households', 'is_checked')) {
    $alterClauses[] = "ADD COLUMN is_checked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_claimed";
}
if (!riceColumnExists($conn, 'rice_households', 'modified')) {
    $alterClauses[] = "ADD COLUMN modified DATETIME NULL AFTER claimed_at";
}
if (!riceIndexExists($conn, 'rice_households', 'idx_rice_checked_modified')) {
    $alterClauses[] = "ADD INDEX idx_rice_checked_modified (is_checked, modified)";
}

if ($alterClauses) {
    $conn->query("ALTER TABLE rice_households\n" . implode(",\n", $alterClauses));
}

$conn->query("UPDATE rice_households SET modified = updated_at WHERE is_checked = 1 AND modified IS NULL");

echo "Rice checked timestamp columns ready.\n";
?>
