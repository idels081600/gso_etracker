<?php

declare(strict_types=1);

const TOURNAMENT_TEAM_SCORE_TABLE = "pickleball_tournament_teams";

function ensureTournamentTeamScoreTable(mysqli $database): bool
{
    $sql = "CREATE TABLE IF NOT EXISTS `" . TOURNAMENT_TEAM_SCORE_TABLE . "` (
        `team_id` VARCHAR(100) NOT NULL,
        `division_id` VARCHAR(20) NOT NULL,
        `team_name` VARCHAR(160) NOT NULL,
        `games_played` INT UNSIGNED NOT NULL DEFAULT 0,
        `wins` INT UNSIGNED NOT NULL DEFAULT 0,
        `losses` INT UNSIGNED NOT NULL DEFAULT 0,
        `points_for` INT UNSIGNED NOT NULL DEFAULT 0,
        `points_against` INT UNSIGNED NOT NULL DEFAULT 0,
        `point_difference` INT NOT NULL DEFAULT 0,
        `updated_at` VARCHAR(40) NOT NULL,
        PRIMARY KEY (`team_id`),
        KEY `division_standings` (`division_id`, `wins`, `point_difference`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    return (bool) $database->query($sql);
}

function tournamentTeamScoreRows(array $record): array
{
    $rows = [];
    $updatedAt = (string) ($record["data"]["updatedAt"] ?? gmdate("c"));
    foreach (["girls", "boys", "mixed"] as $divisionId) {
        $division = $record["data"]["divisions"][$divisionId] ?? [];
        foreach (($division["teams"] ?? []) as $team) {
            $teamId = (string) ($team["id"] ?? "");
            if ($teamId === "") continue;
            $rows[$teamId] = [
                "teamId" => $teamId,
                "divisionId" => $divisionId,
                "teamName" => (string) ($team["name"] ?? "Team"),
                "gamesPlayed" => 0,
                "wins" => 0,
                "losses" => 0,
                "pointsFor" => 0,
                "pointsAgainst" => 0,
                "pointDifference" => 0,
                "updatedAt" => $updatedAt,
            ];
        }
        foreach (($division["results"] ?? []) as $result) {
            if (($result["matchType"] ?? "doubles") !== "doubles") continue;
            $teamAId = (string) ($result["teamAId"] ?? "");
            $teamBId = (string) ($result["teamBId"] ?? "");
            if (!isset($rows[$teamAId], $rows[$teamBId])) continue;
            $scoreA = max(0, (int) ($result["scoreA"] ?? 0));
            $scoreB = max(0, (int) ($result["scoreB"] ?? 0));
            $rows[$teamAId]["gamesPlayed"]++;
            $rows[$teamBId]["gamesPlayed"]++;
            $rows[$teamAId]["pointsFor"] += $scoreA;
            $rows[$teamAId]["pointsAgainst"] += $scoreB;
            $rows[$teamBId]["pointsFor"] += $scoreB;
            $rows[$teamBId]["pointsAgainst"] += $scoreA;
            $winnerId = (string) ($result["winnerTeamId"] ?? "");
            if ($winnerId === $teamAId) {
                $rows[$teamAId]["wins"]++;
                $rows[$teamBId]["losses"]++;
            } elseif ($winnerId === $teamBId) {
                $rows[$teamBId]["wins"]++;
                $rows[$teamAId]["losses"]++;
            }
        }
    }
    foreach ($rows as &$row) $row["pointDifference"] = $row["pointsFor"] - $row["pointsAgainst"];
    unset($row);
    return array_values($rows);
}

function replaceTournamentTeamScores(mysqli $database, array $record): bool
{
    if (!$database->query("DELETE FROM `" . TOURNAMENT_TEAM_SCORE_TABLE . "`")) return false;
    $sql = "INSERT INTO `" . TOURNAMENT_TEAM_SCORE_TABLE . "`
        (`team_id`, `division_id`, `team_name`, `games_played`, `wins`, `losses`, `points_for`, `points_against`, `point_difference`, `updated_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $statement = $database->prepare($sql);
    if (!$statement) return false;
    foreach (tournamentTeamScoreRows($record) as $row) {
        $statement->bind_param(
            "sssiiiiiis",
            $row["teamId"],
            $row["divisionId"],
            $row["teamName"],
            $row["gamesPlayed"],
            $row["wins"],
            $row["losses"],
            $row["pointsFor"],
            $row["pointsAgainst"],
            $row["pointDifference"],
            $row["updatedAt"]
        );
        if (!$statement->execute()) {
            $statement->close();
            return false;
        }
    }
    $statement->close();
    return true;
}

