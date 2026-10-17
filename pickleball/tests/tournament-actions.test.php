<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "tournament-actions.php";

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function expectActionError(callable $callback, string $code, int $status): void
{
    try {
        $callback();
    } catch (TournamentActionException $error) {
        expect($error->getMessage() === $code, "Expected error " . $code . ", got " . $error->getMessage());
        expect($error->status === $status, "Expected status " . $status);
        return;
    }
    throw new RuntimeException("Expected action error " . $code);
}

$time = "2026-10-17T01:00:00.000Z";
$record = tournamentDefaultRecord($time);
expect(count($record["data"]["divisions"]["mixed"]["teams"]) === 14, "Mixed Doubles seed is missing.");

$record = tournamentProcessAction($record, "game_master", "create_scoreboard", ["name" => "Court 1", "divisionId" => "mixed", "matchType" => "singles"], "2026-10-17T01:01:00.000Z");
$board = $record["data"]["scoreboards"][0];
expect($board["matchType"] === "doubles", "Mixed Doubles court must reject singles.");

expectActionError(
    static fn(): array => tournamentProcessAction($record, "remote_scorer", "create_scoreboard", ["divisionId" => "girls"]),
    "action_forbidden",
    403
);

$teams = $record["data"]["divisions"]["mixed"]["teams"];
$record = tournamentProcessAction($record, "game_master", "start_match", [
    "scoreboardId" => $board["id"], "teamAId" => $teams[0]["id"], "teamBId" => $teams[1]["id"],
    "targetPoints" => 11, "winBy" => 2, "servingTeamId" => $teams[0]["id"], "serverNumber" => 2,
], "2026-10-17T01:02:00.000Z");

$record = tournamentProcessAction($record, "game_master", "adjust_score", ["scoreboardId" => $board["id"], "participantId" => $teams[0]["id"], "delta" => 1], "2026-10-17T01:02:30.000Z");
expect($record["data"]["scoreboards"][0]["live"]["scores"][$teams[0]["id"]] === 1, "Game Master score adjustment was not applied.");
$record = tournamentProcessAction($record, "game_master", "adjust_score", ["scoreboardId" => $board["id"], "participantId" => $teams[0]["id"], "delta" => -1], "2026-10-17T01:02:31.000Z");
$record = tournamentProcessAction($record, "game_master", "set_server", ["scoreboardId" => $board["id"], "participantId" => $teams[1]["id"]], "2026-10-17T01:02:32.000Z");
expect($record["data"]["scoreboards"][0]["live"]["servingTeamId"] === $teams[1]["id"], "Game Master serving-team change was not applied.");
$record = tournamentProcessAction($record, "game_master", "swap_court", ["scoreboardId" => $board["id"]], "2026-10-17T01:02:33.000Z");
expect($record["data"]["scoreboards"][0]["live"]["courtOrder"] === [$teams[1]["id"], $teams[0]["id"]], "Game Master court swap was not applied.");

for ($point = 0; $point < 11; $point++) {
    $record = tournamentProcessAction($record, "remote_scorer", "adjust_score", ["scoreboardId" => $board["id"], "participantId" => $teams[0]["id"], "delta" => 1], "2026-10-17T01:03:" . str_pad((string) $point, 2, "0", STR_PAD_LEFT) . ".000Z");
}
$record = tournamentProcessAction($record, "remote_scorer", "submit_result", ["scoreboardId" => $board["id"]], "2026-10-17T01:04:00.000Z");
$result = $record["data"]["divisions"]["mixed"]["results"][0];
expect($result["targetPoints"] === 11 && $result["winBy"] === 2, "Winning rules were not stored.");

expectActionError(
    static fn(): array => tournamentProcessAction($record, "game_master", "edit_result", ["divisionId" => "mixed", "resultId" => $result["id"], "scoreA" => 11, "scoreB" => 10]),
    "invalid_final_score",
    422
);

$record = tournamentProcessAction($record, "game_master", "edit_result", ["divisionId" => "mixed", "resultId" => $result["id"], "scoreA" => 10, "scoreB" => 12], "2026-10-17T01:05:00.000Z");
$corrected = $record["data"]["divisions"]["mixed"]["results"][0];
expect($corrected["winnerTeamId"] === $teams[1]["id"], "Corrected winner was not recalculated.");
expect($corrected["correctedBy"] === "game_master", "Correction role was not recorded.");

echo "Tournament role actions: passed" . PHP_EOL;
