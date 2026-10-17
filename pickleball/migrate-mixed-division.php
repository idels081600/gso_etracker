<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . "pickleball-config.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "tournament-team-scores.php";

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

$connectionFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . "db.php";
if (!is_file($connectionFile)) {
    fwrite(STDERR, "Database configuration not found." . PHP_EOL);
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
ob_start();
require $connectionFile;
ob_end_clean();
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno || !$conn->select_db(PICKLEBALL_DATABASE_NAME)) {
    fwrite(STDERR, "Tournament database connection failed." . PHP_EOL);
    exit(1);
}
$conn->set_charset("utf8mb4");

$teams = [
    "Marlon Amila & Babette Lim",
    "Iven Lim & Thamar Olivar",
    "Manju Lim & Jane Yap",
    "Baba Yap & Mau Heraldo",
    "Junax Suarez & Kat Sarte",
    "Ryan Suarez & Twinkle Lim",
    "JG Yap & Margie Estavilla",
    "Eping Estavilla & Obe Yap",
    "Jeffey Yap & Geri Niepes",
    "Ian Mendez & Bombom Suarez",
    "Carl Lim & Janah Yap",
    "Trygve Olivar & Miriam Sumaylo",
    "Arar Lim & Anna Lim",
    "Emil Yap & Ira Lim",
];

$conn->begin_transaction();
try {
    $result = $conn->query("SELECT `state_json` FROM `pickleball_tournament_state` WHERE `state_key` = 'live' FOR UPDATE");
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) $result->free();
    if (!is_array($row)) {
        throw new RuntimeException("No live tournament state exists. Connect the tournament board once, then run this migration again.");
    }

    $record = json_decode((string) $row["state_json"], true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($record["data"]["divisions"] ?? null)) {
        throw new RuntimeException("The stored tournament state is invalid.");
    }

    $seededTeams = array_map(
        static fn(string $name, int $index): array => ["id" => "mixed-team-" . ($index + 1), "name" => $name],
        $teams,
        array_keys($teams)
    );
    $mixed = $record["data"]["divisions"]["mixed"] ?? null;
    if (!is_array($mixed)) {
        $record["data"]["divisions"]["mixed"] = [
            "id" => "mixed",
            "label" => "Mixed Doubles",
            "teams" => $seededTeams,
            "live" => null,
            "results" => [],
            "finalized" => false,
        ];
    } else {
        $existingNames = [];
        foreach (($mixed["teams"] ?? []) as $team) $existingNames[strtolower((string) ($team["name"] ?? ""))] = true;
        foreach ($seededTeams as $team) {
            if (!isset($existingNames[strtolower($team["name"])])) $record["data"]["divisions"]["mixed"]["teams"][] = $team;
        }
    }
    $record["data"]["updatedAt"] = (new DateTimeImmutable("now", new DateTimeZone("UTC")))->format("Y-m-d\TH:i:s.v\Z");

    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $statement = $conn->prepare("UPDATE `pickleball_tournament_state` SET `state_json` = ?, `state_updated_at` = ? WHERE `state_key` = 'live'");
    if (!$statement) throw new RuntimeException("The tournament state update could not be prepared.");
    $statement->bind_param("ss", $json, $record["data"]["updatedAt"]);
    if (!$statement->execute()) throw new RuntimeException("The tournament state update failed.");
    $statement->close();

    if (!ensureTournamentTeamScoreTable($conn) || !replaceTournamentTeamScores($conn, $record)) {
        throw new RuntimeException("The relational team score rows could not be rebuilt.");
    }
    $conn->commit();
    echo "Mixed Doubles: 14 teams migrated." . PHP_EOL;
} catch (Throwable $error) {
    $conn->rollback();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
