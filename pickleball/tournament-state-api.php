<?php

declare(strict_types=1);

const TOURNAMENT_API_VERSION = 2;
const MAX_STATE_BYTES = 1048576;
// Change this private value before deployment; enter the same value on the board and scorer phones.
const SCORER_ACCESS_CODE = "PB-84QZ-7M2K-9X6R";
const MAX_FAILED_ATTEMPTS = 6;
const RATE_LIMIT_SECONDS = 900;

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

$storageDirectory = __DIR__ . DIRECTORY_SEPARATOR . "data";
$stateFile = $storageDirectory . DIRECTORY_SEPARATOR . "tournament-state.v2.json";
if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0775, true) && !is_dir($storageDirectory)) {
    respond(500, ["error" => "storage_unavailable"]);
}

$clientAddress = (string) ($_SERVER["REMOTE_ADDR"] ?? "unknown");
$rateFile = $storageDirectory . DIRECTORY_SEPARATOR . "rate-" . hash("sha256", $clientAddress) . ".json";
$rate = ["startedAt" => time(), "failures" => 0];
if (is_file($rateFile)) {
    $storedRate = json_decode((string) file_get_contents($rateFile), true);
    if (is_array($storedRate)) $rate = array_merge($rate, $storedRate);
}
if (time() - (int) $rate["startedAt"] >= RATE_LIMIT_SECONDS) $rate = ["startedAt" => time(), "failures" => 0];
if ((int) $rate["failures"] >= MAX_FAILED_ATTEMPTS) {
    header("Retry-After: " . max(1, RATE_LIMIT_SECONDS - (time() - (int) $rate["startedAt"])));
    respond(429, ["error" => "too_many_attempts"]);
}

$providedCode = strtoupper(trim((string) ($_SERVER["HTTP_X_PICKLEBALL_ACCESS_CODE"] ?? "")));
if (!hash_equals(SCORER_ACCESS_CODE, $providedCode)) {
    $rate["failures"] = (int) $rate["failures"] + 1;
    file_put_contents($rateFile, json_encode($rate), LOCK_EX);
    respond(401, ["error" => "access_code_required"]);
}
if (is_file($rateFile)) unlink($rateFile);

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
if (!in_array($method, ["GET", "PUT"], true)) {
    header("Allow: GET, PUT");
    respond(405, ["error" => "method_not_allowed"]);
}

if ($method === "GET") {
    if (!is_file($stateFile)) respond(204);
    $handle = fopen($stateFile, "rb");
    if ($handle === false || !flock($handle, LOCK_SH)) respond(500, ["error" => "storage_unavailable"]);
    $json = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    $record = is_string($json) ? decodeRecord($json) : null;
    if ($record === null) respond(500, ["error" => "stored_state_invalid"]);
    respond(200, $record);
}

$raw = file_get_contents("php://input");
if (!is_string($raw) || $raw === "" || strlen($raw) > MAX_STATE_BYTES) respond(413, ["error" => "invalid_state_size"]);
$incoming = decodeRecord($raw);
if ($incoming === null) respond(422, ["error" => "invalid_tournament_state"]);

$handle = fopen($stateFile, "c+");
if ($handle === false || !flock($handle, LOCK_EX)) respond(500, ["error" => "storage_unavailable"]);
rewind($handle);
$currentJson = stream_get_contents($handle);
$current = is_string($currentJson) && $currentJson !== "" ? decodeRecord($currentJson) : null;
$currentUpdatedAt = (string) ($current["data"]["updatedAt"] ?? "");
$incomingUpdatedAt = (string) $incoming["data"]["updatedAt"];
if ($current !== null && strcmp($currentUpdatedAt, $incomingUpdatedAt) > 0) {
    flock($handle, LOCK_UN);
    fclose($handle);
    respond(409, $current);
}

$encoded = json_encode($incoming, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (!is_string($encoded)) {
    flock($handle, LOCK_UN);
    fclose($handle);
    respond(500, ["error" => "encoding_failed"]);
}
rewind($handle);
ftruncate($handle, 0);
$written = fwrite($handle, $encoded);
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);
if ($written === false) respond(500, ["error" => "storage_write_failed"]);
respond(200, $incoming);
