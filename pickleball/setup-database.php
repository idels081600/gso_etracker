<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . "pickleball-config.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "pickleball-database.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "tournament-team-scores.php";

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

$environmentFile = __DIR__ . DIRECTORY_SEPARATOR . ".env";
if (!is_file($environmentFile)) {
    fwrite(STDERR, "Database configuration not found." . PHP_EOL);
    exit(1);
}
$conn = pickleballDatabase(false);
if (!($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection failed." . PHP_EOL);
    exit(1);
}

$sourceDatabase = (string) ($conn->query("SELECT DATABASE() AS name")?->fetch_assoc()["name"] ?? "");
$configuredDatabase = pickleballDatabaseName();
$databaseName = str_replace("`", "``", $configuredDatabase);
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . $databaseName . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    fwrite(STDERR, "Dedicated database could not be created: " . $conn->error . PHP_EOL);
    exit(1);
}
if (!$conn->select_db($configuredDatabase)) {
    fwrite(STDERR, "Dedicated database could not be selected." . PHP_EOL);
    exit(1);
}

$schema = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "database-schema.sql");
if (!is_string($schema) || !$conn->multi_query($schema)) {
    fwrite(STDERR, "Database schema could not be applied: " . $conn->error . PHP_EOL);
    exit(1);
}
do {
    $result = $conn->store_result();
    if ($result instanceof mysqli_result) $result->free();
} while ($conn->more_results() && $conn->next_result());

if ($sourceDatabase !== "" && $sourceDatabase !== $configuredDatabase) {
    $sourceName = str_replace("`", "``", $sourceDatabase);
    $stateTables = ["pickleball_tournament_state", "pickleball_single_match_state"];
    foreach ($stateTables as $table) {
        $escapedSource = $conn->real_escape_string($sourceDatabase);
        $escapedTable = $conn->real_escape_string($table);
        $exists = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = '" . $escapedSource . "' AND table_name = '" . $escapedTable . "' LIMIT 1");
        if (!($exists instanceof mysqli_result) || $exists->num_rows === 0) continue;
        $exists->free();
        $sql = "INSERT IGNORE INTO `" . $table . "` (`state_key`, `state_json`, `state_updated_at`, `saved_at`)
            SELECT `state_key`, `state_json`, `state_updated_at`, `saved_at` FROM `" . $sourceName . "`.`" . $table . "`";
        if (!$conn->query($sql)) {
            fwrite(STDERR, "Existing data could not be copied from " . $sourceDatabase . "." . $table . ": " . $conn->error . PHP_EOL);
            exit(1);
        }
    }
}

$tables = [
    "pickleball_tournament_state",
    "pickleball_single_match_state",
    "pickleball_tournament_teams",
    "pickleball_scorer_rate_limit",
    "pickleball_tournament_actions",
];
foreach ($tables as $table) {
    $escaped = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '" . $escaped . "'");
    $ready = $result instanceof mysqli_result && $result->num_rows === 1;
    if ($result instanceof mysqli_result) $result->free();
    echo $table . ": " . ($ready ? "ready" : "missing") . PHP_EOL;
    if (!$ready) exit(1);
}

$stateResult = $conn->query("SELECT `state_json` FROM `pickleball_tournament_state` WHERE `state_key` = 'live'");
$stateRow = $stateResult instanceof mysqli_result ? $stateResult->fetch_assoc() : null;
if ($stateResult instanceof mysqli_result) $stateResult->free();
if (is_array($stateRow)) {
    $record = json_decode((string) $stateRow["state_json"], true);
    if (!is_array($record) || !replaceTournamentTeamScores($conn, $record)) {
        fwrite(STDERR, "Tournament team score rows could not be rebuilt." . PHP_EOL);
        exit(1);
    }
}
echo "database: " . $configuredDatabase . PHP_EOL;

