<?php

declare(strict_types=1);

const TOURNAMENT_API_VERSION = 2;
const MAX_STATE_BYTES = 1048576;
// Change this private value before deployment; enter the same value on the board and scorer phones.
const SCORER_ACCESS_CODE = "PICKLE123";
const MAX_FAILED_ATTEMPTS = 6;
const RATE_LIMIT_SECONDS = 900;
const STATE_TABLE = "pickleball_tournament_state";
const RATE_LIMIT_TABLE = "pickleball_scorer_rate_limit";
const LIVE_STATE_KEY = "live";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, max-age=0");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: same-origin");

function respond(int $status, mixed $payload = null): never
{
    http_response_code($status);
    if ($payload !== null) echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function isValidRecord(mixed $record): bool
{
    return is_array($record)
        && ($record["version"] ?? null) === TOURNAMENT_API_VERSION
        && is_array($record["data"] ?? null)
        && ($record["data"]["schemaVersion"] ?? null) === TOURNAMENT_API_VERSION
        && is_string($record["data"]["updatedAt"] ?? null)
        && is_array($record["data"]["divisions"]["girls"] ?? null)
        && is_array($record["data"]["divisions"]["boys"] ?? null)
        && is_array($record["data"]["divisions"]["girls"]["teams"] ?? null)
        && is_array($record["data"]["divisions"]["boys"]["teams"] ?? null)
        && is_array($record["data"]["divisions"]["girls"]["results"] ?? null)
        && is_array($record["data"]["divisions"]["boys"]["results"] ?? null);
}

function decodeRecord(string $json): ?array
{
    try {
        $record = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        return isValidRecord($record) ? $record : null;
    } catch (JsonException) {
        return null;
    }
}

function database(): mysqli
{
    $connectionFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . "db.php";
    if (!is_file($connectionFile)) respond(500, ["error" => "database_config_missing"]);

    mysqli_report(MYSQLI_REPORT_OFF);
    ob_start();
    require $connectionFile;
    ob_end_clean();

    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        respond(500, ["error" => "database_unavailable"]);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

function ensureTables(mysqli $database): void
{
    $stateSql = "CREATE TABLE IF NOT EXISTS `" . STATE_TABLE . "` (
        `state_key` VARCHAR(32) NOT NULL,
        `state_json` LONGTEXT NOT NULL,
        `state_updated_at` VARCHAR(40) NOT NULL,
        `saved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`state_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $rateSql = "CREATE TABLE IF NOT EXISTS `" . RATE_LIMIT_TABLE . "` (
        `client_hash` CHAR(64) NOT NULL,
        `window_started` BIGINT UNSIGNED NOT NULL,
        `failures` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`client_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$database->query($stateSql) || !$database->query($rateSql)) {
        respond(500, ["error" => "database_setup_failed"]);
    }
}

function readRateLimit(mysqli $database, string $clientHash): array
{
    $statement = $database->prepare("SELECT `window_started`, `failures` FROM `" . RATE_LIMIT_TABLE . "` WHERE `client_hash` = ?");
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_param("s", $clientHash);
    if (!$statement->execute()) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_result($startedAt, $failures);
    $found = $statement->fetch();
    $statement->close();
    return $found ? ["startedAt" => (int) $startedAt, "failures" => (int) $failures] : ["startedAt" => time(), "failures" => 0];
}

function saveRateLimit(mysqli $database, string $clientHash, int $startedAt, int $failures): void
{
    $sql = "INSERT INTO `" . RATE_LIMIT_TABLE . "` (`client_hash`, `window_started`, `failures`) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE `window_started` = VALUES(`window_started`), `failures` = VALUES(`failures`)";
    $statement = $database->prepare($sql);
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_param("sii", $clientHash, $startedAt, $failures);
    if (!$statement->execute()) respond(500, ["error" => "database_query_failed"]);
    $statement->close();
}

function clearRateLimit(mysqli $database, string $clientHash): void
{
    $statement = $database->prepare("DELETE FROM `" . RATE_LIMIT_TABLE . "` WHERE `client_hash` = ?");
    if (!$statement) return;
    $statement->bind_param("s", $clientHash);
    $statement->execute();
    $statement->close();
}

function readStoredRecord(mysqli $database, bool $forUpdate = false): ?array
{
    $suffix = $forUpdate ? " FOR UPDATE" : "";
    $statement = $database->prepare("SELECT `state_json` FROM `" . STATE_TABLE . "` WHERE `state_key` = ?" . $suffix);
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $key = LIVE_STATE_KEY;
    $statement->bind_param("s", $key);
    if (!$statement->execute()) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_result($json);
    $found = $statement->fetch();
    $statement->close();
    if (!$found) return null;
    $record = decodeRecord((string) $json);
    if ($record === null) respond(500, ["error" => "stored_state_invalid"]);
    return $record;
}

function writeStoredRecord(mysqli $database, array $record): void
{
    $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded)) respond(500, ["error" => "encoding_failed"]);
    $updatedAt = (string) $record["data"]["updatedAt"];
    $key = LIVE_STATE_KEY;
    $sql = "INSERT INTO `" . STATE_TABLE . "` (`state_key`, `state_json`, `state_updated_at`) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE `state_json` = VALUES(`state_json`), `state_updated_at` = VALUES(`state_updated_at`)";
    $statement = $database->prepare($sql);
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_param("sss", $key, $encoded, $updatedAt);
    if (!$statement->execute()) respond(500, ["error" => "database_write_failed"]);
    $statement->close();
}

$database = database();
ensureTables($database);

$clientAddress = (string) ($_SERVER["REMOTE_ADDR"] ?? "unknown");
$clientHash = hash("sha256", $clientAddress);
$rate = readRateLimit($database, $clientHash);
$now = time();
if ($now - $rate["startedAt"] >= RATE_LIMIT_SECONDS) $rate = ["startedAt" => $now, "failures" => 0];
if ($rate["failures"] >= MAX_FAILED_ATTEMPTS) {
    header("Retry-After: " . max(1, RATE_LIMIT_SECONDS - ($now - $rate["startedAt"])));
    respond(429, ["error" => "too_many_attempts"]);
}

$providedCode = strtoupper(trim((string) ($_SERVER["HTTP_X_PICKLEBALL_ACCESS_CODE"] ?? "")));
if (!hash_equals(SCORER_ACCESS_CODE, $providedCode)) {
    saveRateLimit($database, $clientHash, $rate["startedAt"], $rate["failures"] + 1);
    respond(401, ["error" => "access_code_required"]);
}
clearRateLimit($database, $clientHash);

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
if (!in_array($method, ["GET", "PUT"], true)) {
    header("Allow: GET, PUT");
    respond(405, ["error" => "method_not_allowed"]);
}

if ($method === "GET") {
    $record = readStoredRecord($database);
    if ($record === null) respond(204);
    respond(200, $record);
}

$raw = file_get_contents("php://input");
if (!is_string($raw) || $raw === "" || strlen($raw) > MAX_STATE_BYTES) respond(413, ["error" => "invalid_state_size"]);
$incoming = decodeRecord($raw);
if ($incoming === null) respond(422, ["error" => "invalid_tournament_state"]);

if (!$database->begin_transaction()) respond(500, ["error" => "database_transaction_failed"]);
$current = readStoredRecord($database, true);
$currentUpdatedAt = (string) ($current["data"]["updatedAt"] ?? "");
$incomingUpdatedAt = (string) $incoming["data"]["updatedAt"];
if ($current !== null && strcmp($currentUpdatedAt, $incomingUpdatedAt) > 0) {
    $database->rollback();
    respond(409, $current);
}
writeStoredRecord($database, $incoming);
if (!$database->commit()) respond(500, ["error" => "database_transaction_failed"]);
respond(200, $incoming);