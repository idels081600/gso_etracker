<?php

declare(strict_types=1);

final class TournamentActionException extends RuntimeException
{
    public function __construct(public readonly int $status, string $code)
    {
        parent::__construct($code);
    }
}

function tournamentNowIso(): string
{
    return (new DateTimeImmutable("now", new DateTimeZone("UTC")))->format("Y-m-d\TH:i:s.v\Z");
}

function tournamentDefaultRecord(?string $now = null): array
{
    $timestamp = $now ?? tournamentNowIso();
    $seed = static function (string $division, array $names): array {
        return array_map(
            static fn(string $name, int $index): array => ["id" => $division . "-team-" . ($index + 1), "name" => $name],
            $names,
            array_keys($names)
        );
    };
    return [
        "version" => 2,
        "data" => [
            "schemaVersion" => 2,
            "createdAt" => $timestamp,
            "updatedAt" => $timestamp,
            "scoreboards" => [],
            "divisions" => [
                "girls" => ["id" => "girls", "label" => "Girls", "teams" => $seed("girls", ["Joy & Irah", "Born2x & Jane Yap", "Obe & Kath", "Miriam & Mau", "Gen & Babette", "Anna & Twinkle", "Margie & Thamar"]), "live" => null, "results" => [], "finalized" => false],
                "boys" => ["id" => "boys", "label" => "Boys", "teams" => $seed("boys", ["Ryan & Try", "Ridan & Carl", "Junax & Baba", "Manju & Ian", "Iven & Jeff", "Eping & JG"]), "live" => null, "results" => [], "finalized" => false],
                "mixed" => ["id" => "mixed", "label" => "Mixed Doubles", "teams" => $seed("mixed", ["Marlon Amila & Babette Lim", "Iven Lim & Thamar Olivar", "Manju Lim & Jane Yap", "Baba Yap & Mau Heraldo", "Junax Suarez & Kat Sarte", "Ryan Suarez & Twinkle Lim", "JG Yap & Margie Estavilla", "Eping Estavilla & Obe Yap", "Jeffey Yap & Geri Niepes", "Ian Mendez & Bombom Suarez", "Carl Lim & Janah Yap", "Trygve Olivar & Miriam Sumaylo", "Arar Lim & Anna Lim", "Emil Yap & Ira Lim"]), "live" => null, "results" => [], "finalized" => false],
            ],
        ],
    ];
}

function tournamentActionId(string $prefix): string
{
    return $prefix . "-" . bin2hex(random_bytes(8));
}

function tournamentActionError(string $code, int $status = 422): never
{
    throw new TournamentActionException($status, $code);
}

function tournamentDivision(array &$record, string $divisionId): array
{
    if (!in_array($divisionId, ["girls", "boys", "mixed"], true) || !isset($record["data"]["divisions"][$divisionId])) {
        tournamentActionError("invalid_division");
    }
    return $record["data"]["divisions"][$divisionId];
}

function tournamentFindBoardIndex(array $record, string $boardId): int
{
    foreach (($record["data"]["scoreboards"] ?? []) as $index => $board) {
        if (($board["id"] ?? "") === $boardId) return $index;
    }
    tournamentActionError("scoreboard_not_found", 404);
}

function tournamentPlayerNames(string $name): array
{
    $parts = preg_split('/\s*&\s*/', trim($name)) ?: [];
    $parts = array_values(array_filter(array_map("trim", $parts), static fn(string $part): bool => $part !== ""));
    if (count($parts) >= 2) return [$parts[0], implode(" & ", array_slice($parts, 1))];
    return [$parts[0] ?? "Player", "Partner"];
}

function tournamentPlayerId(string $divisionId, string $name): string
{
    $hash = 2166136261;
    foreach (str_split(strtolower($name)) as $character) $hash = (($hash ^ ord($character)) * 16777619) & 0xffffffff;
    return $divisionId . "-player-" . base_convert((string) $hash, 10, 36);
}

function tournamentParticipants(array $division, string $divisionId, string $matchType): array
{
    if ($matchType !== "singles") return array_values($division["teams"] ?? []);
    $participants = [];
    foreach (($division["teams"] ?? []) as $team) {
        foreach (tournamentPlayerNames((string) ($team["name"] ?? "")) as $name) {
            if ($name === "Partner" || $name === "") continue;
            $participants[tournamentPlayerId($divisionId, $name)] = ["id" => tournamentPlayerId($divisionId, $name), "name" => $name];
        }
    }
    return array_values($participants);
}

function tournamentCandidateWinner(array $live): ?string
{
    $teamA = (string) ($live["teamAId"] ?? "");
    $teamB = (string) ($live["teamBId"] ?? "");
    $scoreA = (int) ($live["scores"][$teamA] ?? 0);
    $scoreB = (int) ($live["scores"][$teamB] ?? 0);
    $target = (int) ($live["targetPoints"] ?? 11);
    $margin = (int) ($live["winBy"] ?? 2);
    if ($scoreA >= $target && $scoreA - $scoreB >= $margin) return $teamA;
    if ($scoreB >= $target && $scoreB - $scoreA >= $margin) return $teamB;
    return null;
}

function tournamentPushUndo(array &$live): void
{
    $snapshot = $live;
    unset($snapshot["undoStack"]);
    $stack = is_array($live["undoStack"] ?? null) ? $live["undoStack"] : [];
    $stack[] = $snapshot;
    $live["undoStack"] = array_slice($stack, -40);
}

function tournamentRefreshLive(array &$live, string $timestamp): void
{
    $live["winnerTeamId"] = tournamentCandidateWinner($live);
    $live["status"] = $live["winnerTeamId"] ? "ready" : "live";
    $live["updatedAt"] = $timestamp;
}

function tournamentNonnegativeInt(mixed $value, string $code): int
{
    if (is_int($value) && $value >= 0) return $value;
    if (is_string($value) && ctype_digit($value)) return (int) $value;
    tournamentActionError($code);
}

function tournamentProcessAction(array $record, string $role, string $action, array $payload, ?string $now = null): array
{
    $allowed = [
        "game_master" => ["create_scoreboard", "remove_scoreboard", "start_match", "cancel_match", "edit_result", "adjust_score", "set_server", "swap_court"],
        "remote_scorer" => ["adjust_score", "set_server", "set_server_number", "swap_court", "undo_live", "reset_live", "submit_result"],
    ];
    if (!in_array($action, $allowed[$role] ?? [], true)) tournamentActionError("action_forbidden", 403);

    $timestamp = $now ?? tournamentNowIso();
    $data =& $record["data"];
    if (!is_array($data["scoreboards"] ?? null)) $data["scoreboards"] = [];

    if ($action === "create_scoreboard") {
        $divisionId = (string) ($payload["divisionId"] ?? "");
        $division = tournamentDivision($record, $divisionId);
        $matchType = $divisionId !== "mixed" && ($payload["matchType"] ?? "") === "singles" ? "singles" : "doubles";
        $name = trim(preg_replace('/\s+/', ' ', (string) ($payload["name"] ?? "")) ?? "");
        if ($name === "") {
            $used = array_map(static fn(array $board): string => strtolower((string) ($board["name"] ?? "")), $data["scoreboards"]);
            $number = 1;
            while (in_array("court " . $number, $used, true)) $number++;
            $name = "Court " . $number;
        }
        if (strlen($name) > 40) tournamentActionError("invalid_scoreboard_name");
        foreach ($data["scoreboards"] as $board) if (strcasecmp((string) ($board["name"] ?? ""), $name) === 0) tournamentActionError("duplicate_scoreboard_name");
        $data["scoreboards"][] = ["id" => tournamentActionId("scoreboard"), "name" => $name, "divisionId" => $divisionId, "matchType" => $matchType, "live" => null, "createdAt" => $timestamp];
    } elseif ($action === "remove_scoreboard") {
        $index = tournamentFindBoardIndex($record, (string) ($payload["scoreboardId"] ?? ""));
        if (is_array($data["scoreboards"][$index]["live"] ?? null)) tournamentActionError("scoreboard_has_live_match");
        array_splice($data["scoreboards"], $index, 1);
    } elseif ($action === "start_match") {
        $index = tournamentFindBoardIndex($record, (string) ($payload["scoreboardId"] ?? ""));
        $board =& $data["scoreboards"][$index];
        if (is_array($board["live"] ?? null)) tournamentActionError("scoreboard_has_live_match");
        $divisionId = (string) $board["divisionId"];
        $division = tournamentDivision($record, $divisionId);
        $participants = tournamentParticipants($division, $divisionId, (string) $board["matchType"]);
        $participantMap = [];
        foreach ($participants as $participant) $participantMap[(string) $participant["id"]] = (string) $participant["name"];
        $teamA = (string) ($payload["teamAId"] ?? "");
        $teamB = (string) ($payload["teamBId"] ?? "");
        if ($teamA === $teamB || !isset($participantMap[$teamA], $participantMap[$teamB])) tournamentActionError("invalid_participants");
        $target = in_array((int) ($payload["targetPoints"] ?? 11), [11, 15, 21], true) ? (int) $payload["targetPoints"] : 11;
        $winBy = (int) ($payload["winBy"] ?? 2) === 1 ? 1 : 2;
        $serving = in_array((string) ($payload["servingTeamId"] ?? ""), [$teamA, $teamB], true) ? (string) $payload["servingTeamId"] : $teamA;
        $board["live"] = [
            "id" => tournamentActionId($divisionId . "-" . $board["matchType"] . "-match"),
            "teamAId" => $teamA,
            "teamBId" => $teamB,
            "participantNames" => [$teamA => $participantMap[$teamA], $teamB => $participantMap[$teamB]],
            "courtOrder" => [$teamA, $teamB],
            "scores" => [$teamA => 0, $teamB => 0],
            "targetPoints" => $target,
            "winBy" => $winBy,
            "servingTeamId" => $serving,
            "serverNumber" => $board["matchType"] === "singles" ? 1 : ((int) ($payload["serverNumber"] ?? 2) === 1 ? 1 : 2),
            "status" => "live",
            "winnerTeamId" => null,
            "startedAt" => $timestamp,
            "updatedAt" => $timestamp,
            "undoStack" => [],
        ];
    } elseif ($action === "cancel_match") {
        $index = tournamentFindBoardIndex($record, (string) ($payload["scoreboardId"] ?? ""));
        if (!is_array($data["scoreboards"][$index]["live"] ?? null)) tournamentActionError("no_live_match");
        $data["scoreboards"][$index]["live"] = null;
    } elseif ($action === "edit_result") {
        $divisionId = (string) ($payload["divisionId"] ?? "");
        tournamentDivision($record, $divisionId);
        $results =& $data["divisions"][$divisionId]["results"];
        $resultIndex = null;
        foreach ($results as $index => $result) if (($result["id"] ?? "") === ($payload["resultId"] ?? "")) $resultIndex = $index;
        if ($resultIndex === null) tournamentActionError("result_not_found", 404);
        $scoreA = tournamentNonnegativeInt($payload["scoreA"] ?? null, "invalid_score_a");
        $scoreB = tournamentNonnegativeInt($payload["scoreB"] ?? null, "invalid_score_b");
        $result =& $results[$resultIndex];
        $target = (int) ($result["targetPoints"] ?? 11);
        $winBy = (int) ($result["winBy"] ?? 2);
        $winner = tournamentCandidateWinner(["teamAId" => $result["teamAId"], "teamBId" => $result["teamBId"], "scores" => [$result["teamAId"] => $scoreA, $result["teamBId"] => $scoreB], "targetPoints" => $target, "winBy" => $winBy]);
        if ($winner === null) tournamentActionError("invalid_final_score");
        $result["scoreA"] = $scoreA;
        $result["scoreB"] = $scoreB;
        $result["winnerTeamId"] = $winner;
        $result["correctedAt"] = $timestamp;
        $result["correctedBy"] = "game_master";
        $data["divisions"][$divisionId]["finalized"] = false;
    } else {
        $index = tournamentFindBoardIndex($record, (string) ($payload["scoreboardId"] ?? ""));
        $board =& $data["scoreboards"][$index];
        if (!is_array($board["live"] ?? null)) tournamentActionError("no_live_match");
        $live =& $board["live"];
        if ($action === "undo_live") {
            if (!is_array($live["undoStack"] ?? null) || count($live["undoStack"]) === 0) tournamentActionError("nothing_to_undo");
            $stack = $live["undoStack"];
            $previous = array_pop($stack);
            $live = $previous;
            $live["undoStack"] = $stack;
            tournamentRefreshLive($live, $timestamp);
        } elseif ($action === "submit_result") {
            $winner = tournamentCandidateWinner($live);
            if ($winner === null) tournamentActionError("invalid_final_score");
            $divisionId = (string) $board["divisionId"];
            $data["divisions"][$divisionId]["results"][] = [
                "id" => $live["id"], "matchType" => $board["matchType"], "teamAId" => $live["teamAId"], "teamBId" => $live["teamBId"],
                "participantNames" => $live["participantNames"], "scoreA" => $live["scores"][$live["teamAId"]], "scoreB" => $live["scores"][$live["teamBId"]],
                "winnerTeamId" => $winner, "scoreboardName" => $board["name"], "targetPoints" => $live["targetPoints"], "winBy" => $live["winBy"], "completedAt" => $timestamp,
            ];
            $board["live"] = null;
            $data["divisions"][$divisionId]["finalized"] = false;
        } else {
            tournamentPushUndo($live);
            if ($action === "adjust_score") {
                $participant = (string) ($payload["participantId"] ?? "");
                $delta = (int) ($payload["delta"] ?? 0);
                if (!in_array($participant, [$live["teamAId"], $live["teamBId"]], true) || $delta === 0 || abs($delta) > 100) tournamentActionError("invalid_score_adjustment");
                $live["scores"][$participant] = max(0, (int) $live["scores"][$participant] + $delta);
            } elseif ($action === "set_server") {
                $participant = (string) ($payload["participantId"] ?? "");
                if (!in_array($participant, [$live["teamAId"], $live["teamBId"]], true)) tournamentActionError("invalid_server");
                $live["servingTeamId"] = $participant;
            } elseif ($action === "set_server_number") {
                $live["serverNumber"] = $board["matchType"] === "singles" ? 1 : ((int) ($payload["serverNumber"] ?? 2) === 1 ? 1 : 2);
            } elseif ($action === "swap_court") {
                $live["courtOrder"] = array_reverse(is_array($live["courtOrder"] ?? null) ? $live["courtOrder"] : [$live["teamAId"], $live["teamBId"]]);
            } elseif ($action === "reset_live") {
                $live["scores"][$live["teamAId"]] = 0;
                $live["scores"][$live["teamBId"]] = 0;
            }
            tournamentRefreshLive($live, $timestamp);
        }
    }

    $data["updatedAt"] = $timestamp;
    return $record;
}
