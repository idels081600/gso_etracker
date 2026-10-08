<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require dirname(__DIR__) . '/logi_db.php';

$testDatabase = $argv[1] ?? '';
if (!preg_match('/^logisys_test_[a-z0-9_]+$/', $testDatabase)) {
    fwrite(STDERR, "Test database name must start with logisys_test_.\n");
    exit(2);
}

$sourceDatabase = (string) $dbname;
$server = new mysqli((string) $servername, (string) $username, (string) $password);
$server->set_charset('utf8mb4');
$server->query("CREATE DATABASE `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$server->query('SET FOREIGN_KEY_CHECKS=0');

$tables = [];
$result = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
while ($row = $result->fetch_row()) {
    $tables[] = (string) $row[0];
}

foreach ($tables as $table) {
    $safe = str_replace('`', '``', $table);
    $server->query("CREATE TABLE `{$testDatabase}`.`{$safe}` LIKE `{$sourceDatabase}`.`{$safe}`");
    $server->query("INSERT INTO `{$testDatabase}`.`{$safe}` SELECT * FROM `{$sourceDatabase}`.`{$safe}`");
}
$server->query('SET FOREIGN_KEY_CHECKS=1');

$testPassword = (string) getenv('TEST_ADMIN_PASSWORD');
if (strlen($testPassword) < 20) {
    throw new RuntimeException('TEST_ADMIN_PASSWORD must be a generated test-only password of at least 20 characters.');
}
$hash = password_hash($testPassword, PASSWORD_DEFAULT);
$stmt = $server->prepare("UPDATE `{$testDatabase}`.users SET password=? WHERE username IN ('ADMIN_SAP','ADMIN')");
$stmt->bind_param('s', $hash);
$stmt->execute();
$stmt->close();

echo json_encode([
    'database' => $testDatabase,
    'tables_copied' => count($tables),
    'test_username' => 'ADMIN_SAP',
], JSON_UNESCAPED_SLASHES), PHP_EOL;
