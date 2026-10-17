<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . "pickleball-config.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "pickleball-database.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "tournament-team-scores.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "tournament-actions.php";

const TOURNAMENT_API_VERSION = 2;
const MAX_STATE_BYTES = 1048576;
const MAX_FAILED_ATTEMPTS = 6;
const RATE_LIMIT_SECONDS = 900;
const STATE_TABLE = "pickleball_tournament_state";
const RATE_LIMIT_TABLE = "pickleball_scorer_rate_limit";
const ACTION_TABLE = "pickleball_tournament_actions";
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
        && is_array($record["data"]["divisions"]["mixed"] ?? null)
        && is_array($record["data"]["divisions"]["girls"]["teams"] ?? null)
        && is_array($record["data"]["divisions"]["boys"]["teams"] ?? null)
        && is_array($record["data"]["divisions"]["mixed"]["teams"] ?? null)
        && is_array($record["data"]["divisions"]["girls"]["results"] ?? null)
        && is_array($record["data"]["divisions"]["boys"]["results"] ?? null)
        && is_array($record["data"]["divisions"]["mixed"]["results"] ?? null);
}

function decodeRecord(string $json): ?array
{
    try {
        $record = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!isValidRecord($record)) return null;
        foreach (["girls", "boys", "mixed"] as $divisionId) {
            foreach ($record["data"]["divisions"][$divisionId]["results"] as &$result) {
                $highScore = max((int) ($result["scoreA"] ?? 0), (int) ($result["scoreB"] ?? 0));
                if (!isset($result["targetPoints"])) $result["targetPoints"] = $highScore >= 21 ? 21 : ($highScore >= 15 ? 15 : 11);
                if (!isset($result["winBy"])) $result["winBy"] = abs((int) ($result["scoreA"] ?? 0) - (int) ($result["scoreB"] ?? 0)) >= 2 ? 2 : 1;
            }
            unset($result);
        }
        return $record;
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

    $actionSql = "CREATE TABLE IF NOT EXISTS `" . ACTION_TABLE . "` (
        `action_id` VARCHAR(64) NOT NULL,
        `role_id` VARCHAR(24) NOT NULL,
        `action_name` VARCHAR(40) NOT NULL,
        `payload_json` TEXT NOT NULL,
        `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`action_id`),
        KEY `actions_by_role` (`role_id`, `applied_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$database->query($stateSql) || !$database->query($rateSql) || !$database->query($actionSql) || !ensureTournamentTeamScoreTable($database)) {
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

function readStoredUpdatedAt(mysqli $database): ?string
{
    $statement = $database->prepare("SELECT `state_updated_at` FROM `" . STATE_TABLE . "` WHERE `state_key` = ?");
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $key = LIVE_STATE_KEY;
    $statement->bind_param("s", $key);
    if (!$statement->execute()) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_result($updatedAt);
    $found = $statement->fetch();
    $statement->close();
    return $found ? (string) $updatedAt : null;
}

function writeStoredRecord(mysqli $database, array $record, bool $refreshTeamScores = false): void
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
    if ($refreshTeamScores && !replaceTournamentTeamScores($database, $record)) respond(500, ["error" => "database_write_failed"]);
}

function authenticatedRole(string $providedCode): ?string
{
    if (hash_equals(PICKLEBALL_GAME_MASTER_CODE, $providedCode)) return "game_master";
    if (hash_equals(PICKLEBALL_REMOTE_SCORER_CODE, $providedCode)) return "remote_scorer";
    return null;
}

function actionWasApplied(mysqli $database, string $actionId): bool
{
    $statement = $database->prepare("SELECT 1 FROM `" . ACTION_TABLE . "` WHERE `action_id` = ? LIMIT 1");
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_param("s", $actionId);
    if (!$statement->execute()) respond(500, ["error" => "database_query_failed"]);
    $statement->store_result();
    $found = $statement->num_rows > 0;
    $statement->close();
    return $found;
}

function recordAppliedAction(mysqli $database, string $actionId, string $role, string $action, array $payload): void
{
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($payloadJson)) respond(500, ["error" => "encoding_failed"]);
    $statement = $database->prepare("INSERT INTO `" . ACTION_TABLE . "` (`action_id`, `role_id`, `action_name`, `payload_json`) VALUES (?, ?, ?, ?)");
    if (!$statement) respond(500, ["error" => "database_query_failed"]);
    $statement->bind_param("ssss", $actionId, $role, $action, $payloadJson);
    if (!$statement->execute()) respond(500, ["error" => "database_write_failed"]);
    $statement->close();
}

$database = database();

$providedCode = strtoupper(trim((string) ($_SERVER["HTTP_X_PICKLEBALL_ACCESS_CODE"] ?? "")));
$role = authenticatedRole($providedCode);
if ($role === null) {
    $clientAddress = (string) ($_SERVER["REMOTE_ADDR"] ?? "unknown");
    $clientHash = hash("sha256", $clientAddress);
    $rate = readRateLimit($database, $clientHash);
    $now = time();
    if ($now - $rate["startedAt"] >= RATE_LIMIT_SECONDS) $rate = ["startedAt" => $now, "failures" => 0];
    if ($rate["failures"] >= MAX_FAILED_ATTEMPTS) {
        header("Retry-After: " . max(1, RATE_LIMIT_SECONDS - ($now - $rate["startedAt"])));
        respond(429, ["error" => "too_many_attempts"]);
    }
    saveRateLimit($database, $clientHash, $rate["startedAt"], $rate["failures"] + 1);
    respond(401, ["error" => "access_code_required"]);
}
header("X-Pickleball-Role: " . $role);

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
if (!in_array($method, ["GET", "POST"], true)) {
    header("Allow: GET, POST");
    respond(405, ["error" => "method_not_allowed"]);
}

if ($method === "GET") {
    $since = trim((string) ($_GET["since"] ?? ""));
    $waitSeconds = min(20, max(0, (int) ($_GET["wait"] ?? 0)));
    if ($since !== "" && $waitSeconds > 0) {
        $deadline = microtime(true) + $waitSeconds;
        do {
            $updatedAt = readStoredUpdatedAt($database);
            if ($updatedAt !== null && strcmp($updatedAt, $since) > 0) break;
            usleep(100000);
        } while (microtime(true) < $deadline && !connection_aborted());
        if (!isset($updatedAt) || $updatedAt === null || strcmp($updatedAt, $since) <= 0) respond(204);
    }
    $record = readStoredRecord($database);
    if ($record === null) respond(204);
    respond(200, $record);
}

$raw = file_get_contents("php://input");
if (!is_string($raw) || $raw === "" || strlen($raw) > MAX_STATE_BYTES) respond(413, ["error" => "invalid_action_size"]);
try {
    $request = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    respond(422, ["error" => "invalid_action"]);
}
if (!is_array($request)) respond(422, ["error" => "invalid_action"]);
$actionId = (string) ($request["actionId"] ?? "");
$expectedUpdatedAt = (string) ($request["expectedUpdatedAt"] ?? "");
$action = (string) ($request["action"] ?? "");
$payload = $request["payload"] ?? [];
if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $actionId) || $action === "" || !is_array($payload)) respond(422, ["error" => "invalid_action"]);

if (!$database->begin_transaction()) respond(500, ["error" => "database_transaction_failed"]);
$current = readStoredRecord($database, true);
if (actionWasApplied($database, $actionId)) {
    $database->commit();
    respond(200, $current ?? tournamentDefaultRecord());
}
if ($current !== null && !hash_equals((string) $current["data"]["updatedAt"], $expectedUpdatedAt)) {
    $database->rollback();
    respond(409, $current);
}
$current ??= tournamentDefaultRecord();
try {
    $updated = tournamentProcessAction($current, $role, $action, $payload);
} catch (TournamentActionException $error) {
    $database->rollback();
    respond($error->status, ["error" => $error->getMessage()]);
}
writeStoredRecord($database, $updated, in_array($action, ["submit_result", "edit_result"], true));
recordAppliedAction($database, $actionId, $role, $action, $payload);
if (!$database->commit()) respond(500, ["error" => "database_transaction_failed"]);
respond(200, $updated);
