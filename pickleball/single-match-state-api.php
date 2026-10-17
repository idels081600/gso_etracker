<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . "pickleball-config.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "pickleball-database.php";

const SINGLE_MATCH_API_VERSION = 1;
const SINGLE_MATCH_MAX_BYTES = 2097152;
const SINGLE_MATCH_TABLE = "pickleball_single_match_state";
const SINGLE_MATCH_STATE_KEY = "default";
const SINGLE_MATCH_RATE_LIMIT_TABLE = "pickleball_scorer_rate_limit";
const SINGLE_MATCH_MAX_FAILED_ATTEMPTS = 6;
const SINGLE_MATCH_RATE_LIMIT_SECONDS = 900;

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

function isValidState(mixed $record): bool
{
    return is_array($record)
        && ($record["version"] ?? null) === SINGLE_MATCH_API_VERSION
        && is_array($record["data"] ?? null)
        && array_key_exists("activeMatch", $record["data"])
        && (($record["data"]["activeMatch"] ?? null) === null || is_array($record["data"]["activeMatch"]))
        && is_array($record["data"]["history"] ?? null)
        && is_string($record["data"]["updatedAt"] ?? null);
}

function decodeState(string $json): ?array
{
    try {
        $record = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        return isValidState($record) ? $record : null;
    } catch (JsonException) {
        return null;
    }
}

function database(): mysqli
{
    if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . ".env")) respond(500, ["error" => "database_config_missing"]);
    $connection = pickleballDatabase();
    if (!($connection instanceof mysqli)) respond(500, ["error" => "database_unavailable"]);
    return $connection;
}

function ensureTables(mysqli $database): void
{
    $stateSql = "CREATE TABLE IF NOT EXISTS `" . SINGLE_MATCH_TABLE . "` (
        `state_key` VARCHAR(32) NOT NULL,
        `state_json` LONGTEXT NOT NULL,
        `state_updated_at` VARCHAR(40) NOT NULL,
        `saved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`state_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $rateSql = "CREATE TABLE IF NOT EXISTS `" . SINGLE_MATCH_RATE_LIMIT_TABLE . "` (
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
    $statement = $database->prepare("SELECT `window_started`, `failures` FROM `" . SINGLE_MATCH_RATE_LIMIT_TABLE . "` WHERE `client_hash` = ?");
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
    $sql = "INSERT INTO `" . SINGLE_MATCH_RATE_LIMIT_TABLE . "` (`client_hash`, `window_started`, `failures`) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE `window_started` = VALUES(`window_started`), `failures` = VALUES(`failures`)";
    $statement = $database->prepare($sql);
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_param("sii", $clientHash, $startedAt, $failures);
    if (!$statement->execute()) respond(500, ["error" => "database_query_failed"]);
    $statement->close();
}

function clearRateLimit(mysqli $database, string $clientHash): void
{
    $statement = $database->prepare("DELETE FROM `" . SINGLE_MATCH_RATE_LIMIT_TABLE . "` WHERE `client_hash` = ?");
    if (!$statement) return;
    $statement->bind_param("s", $clientHash);
    $statement->execute();
    $statement->close();
}

function readStoredState(mysqli $database, bool $forUpdate = false): ?array
{
    $suffix = $forUpdate ? " FOR UPDATE" : "";
    $statement = $database->prepare("SELECT `state_json` FROM `" . SINGLE_MATCH_TABLE . "` WHERE `state_key` = ?" . $suffix);
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $key = SINGLE_MATCH_STATE_KEY;
    $statement->bind_param("s", $key);
    if (!$statement->execute()) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_result($json);
    $found = $statement->fetch();
    $statement->close();
    if (!$found) return null;
    $record = decodeState((string) $json);
    if ($record === null) respond(500, ["error" => "stored_state_invalid"]);
    return $record;
}

function writeStoredState(mysqli $database, array $record): void
{
    $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded)) respond(500, ["error" => "encoding_failed"]);
    $updatedAt = (string) $record["data"]["updatedAt"];
    $key = SINGLE_MATCH_STATE_KEY;
    $sql = "INSERT INTO `" . SINGLE_MATCH_TABLE . "` (`state_key`, `state_json`, `state_updated_at`) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE `state_json` = VALUES(`state_json`), `state_updated_at` = VALUES(`state_updated_at`)";
    $statement = $database->prepare($sql);
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_param("sss", $key, $encoded, $updatedAt);
    if (!$statement->execute()) respond(500, ["error" => "database_write_failed"]);
    $statement->close();
}

$database = database();

$clientHash = hash("sha256", (string) ($_SERVER["REMOTE_ADDR"] ?? "unknown"));
$rate = readRateLimit($database, $clientHash);
$now = time();
if ($now - $rate["startedAt"] >= SINGLE_MATCH_RATE_LIMIT_SECONDS) $rate = ["startedAt" => $now, "failures" => 0];
if ($rate["failures"] >= SINGLE_MATCH_MAX_FAILED_ATTEMPTS) {
    header("Retry-After: " . max(1, SINGLE_MATCH_RATE_LIMIT_SECONDS - ($now - $rate["startedAt"])));
    respond(429, ["error" => "too_many_attempts"]);
}

$providedCode = strtoupper(trim((string) ($_SERVER["HTTP_X_PICKLEBALL_ACCESS_CODE"] ?? "")));
if (!hash_equals(PICKLEBALL_REMOTE_SCORER_CODE, $providedCode)) {
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
    $record = readStoredState($database);
    if ($record === null) respond(204);
    respond(200, $record);
}

$raw = file_get_contents("php://input");
if (!is_string($raw) || $raw === "" || strlen($raw) > SINGLE_MATCH_MAX_BYTES) respond(413, ["error" => "invalid_state_size"]);
$incoming = decodeState($raw);
if ($incoming === null) respond(422, ["error" => "invalid_single_match_state"]);

if (!$database->begin_transaction()) respond(500, ["error" => "database_transaction_failed"]);
$current = readStoredState($database, true);
$currentUpdatedAt = (string) ($current["data"]["updatedAt"] ?? "");
$incomingUpdatedAt = (string) $incoming["data"]["updatedAt"];
if ($current !== null && strcmp($currentUpdatedAt, $incomingUpdatedAt) > 0) {
    $database->rollback();
    respond(409, $current);
}
writeStoredState($database, $incoming);
if (!$database->commit()) respond(500, ["error" => "database_transaction_failed"]);
respond(200, $incoming);

