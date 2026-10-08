<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require dirname(__DIR__) . '/logi_db.php';

$testDatabase = $argv[1] ?? '';
if (!preg_match('/^logisys_test_[a-z0-9_]+$/', $testDatabase)) {
    fwrite(STDERR, "Refusing to drop a database outside the logisys_test_ namespace.\n");
    exit(2);
}

$server = new mysqli((string) $servername, (string) $username, (string) $password);
$server->query("DROP DATABASE IF EXISTS `{$testDatabase}`");
echo "Dropped isolated test database {$testDatabase}.\n";

