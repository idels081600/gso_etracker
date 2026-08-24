import test from "node:test";
import assert from "node:assert/strict";

import {
  addScoreboard,
  addTeam,
  adjustScoreboardScore,
  adjustLiveScore,
  createTournament,
  deserializeTournament,
  getSchedule,
  getScoreboards,
  getStandings,
  liveCourtOrder,
  participantOptions,
  recordLiveResult,
  recordScoreboardResult,
  removeTeam,
  serializeTournament,
  setDivisionFinalized,
  setLiveServer,
  startMatch,
  startScoreboardMatch,
  servingPlayerName,
  swapLiveCourt,
  teamPlayerNames,
  undoLastResult,
  undoLive,
} from "../tournament-engine.mjs";

const T0 = "2026-08-22T01:00:00.000Z";

function boysWithTeams() {
  return createTournament(T0);
}

function startBoys(state, teamAId, teamBId, targetPoints = 11) {
  return startMatch(state, "boys", {
    teamAId,
    teamBId,
    targetPoints,
    winBy: 2,
    servingTeamId: teamAId,
    serverNumber: 2,
  }, T0);
}

function score(state, divisionId, teamId, points) {
  let next = state;
  for (let index = 0; index < points; index += 1) next = adjustLiveScore(next, divisionId, teamId, 1);
  return next;
}

test("seeds the girls and boys teams from the standings sheets", () => {
  const state = createTournament(T0);
  assert.deepEqual(state.divisions.girls.teams.map((team) => team.name), [
    "Joy & Irah",
    "Born2x & Jane Yap",
    "Obe & Kath",
    "Miriam & Mau",
    "Gen & Babette",
    "Anna & Twinkle",
    "Margie & Thamar",
  ]);
  assert.deepEqual(state.divisions.boys.teams.map((team) => team.name), [
    "Ryan & Try",
    "Ridan & Carl",
    "Junax & Baba",
    "Manju & Ian",
    "Iven & Jeff",
    "Eping & JG",
  ]);
});

test("derives serving-player labels from doubles team names", () => {
  assert.deepEqual(teamPlayerNames("Joy & Irah"), ["Joy", "Irah"]);
  assert.deepEqual(teamPlayerNames(" Born2x  &  Jane Yap "), ["Born2x", "Jane Yap"]);
  assert.deepEqual(teamPlayerNames("Standalone Team"), ["Standalone Team", "Partner"]);
  assert.equal(servingPlayerName("Joy & Irah", 1), "Joy");
  assert.equal(servingPlayerName("Joy & Irah", 2), "Irah");
});

test("projects the supplied court schedules through pending, live, and complete states", () => {
  let state = createTournament(T0);
  const girls = getSchedule(state, "girls");
  const boys = getSchedule(state, "boys");
  assert.equal(girls.length, 21);
  assert.equal(boys.length, 15);
  assert.deepEqual([girls[0].teamAName, girls[0].teamBName, girls[0].status], ["Joy & Irah", "Born2x & Jane Yap", "pending"]);
  assert.deepEqual([boys[14].teamAName, boys[14].teamBName], ["Ryan & Try", "Manju & Ian"]);

  const first = girls[0];
  state = startMatch(state, "girls", { teamAId: first.teamBId, teamBId: first.teamAId, servingTeamId: first.teamAId });
  state = score(state, "girls", first.teamAId, 11);
  let scheduled = getSchedule(state, "girls")[0];
  assert.deepEqual({ status: scheduled.status, scoreA: scheduled.scoreA, scoreB: scheduled.scoreB, winnerName: scheduled.winnerName }, { status: "live", scoreA: 11, scoreB: 0, winnerName: "Joy & Irah" });

  state = recordLiveResult(state, "girls", "2026-08-22T01:30:00.000Z");
  scheduled = getSchedule(state, "girls")[0];
  assert.deepEqual({ status: scheduled.status, scoreA: scheduled.scoreA, scoreB: scheduled.scoreB, winnerName: scheduled.winnerName }, { status: "complete", scoreA: 11, scoreB: 0, winnerName: "Joy & Irah" });
});

test("adds unique teams and removes only teams without match data", () => {
  let state = createTournament(T0);
  state = addTeam(state, "boys", "Ace & Ben");
  assert.equal(state.divisions.boys.teams.at(-1).name, "Ace & Ben");
  assert.throws(() => addTeam(state, "boys", " ace & ben "), /already exists/);
  const teamId = state.divisions.boys.teams.at(-1).id;
  state = removeTeam(state, "boys", teamId);
  assert.equal(state.divisions.boys.teams.length, 6);
});

test("runs independent girls and boys live matches", () => {
  let state = boysWithTeams();
  const [girlA, girlB] = state.divisions.girls.teams;
  const [boyA, boyB] = state.divisions.boys.teams;
  state = startMatch(state, "girls", { teamAId: girlA.id, teamBId: girlB.id, servingTeamId: girlA.id });
  state = startBoys(state, boyA.id, boyB.id);
  state = adjustLiveScore(state, "girls", girlA.id, 1);
  state = adjustLiveScore(state, "boys", boyB.id, 1);
  assert.equal(state.divisions.girls.live.scores[girlA.id], 1);
  assert.equal(state.divisions.boys.live.scores[boyB.id], 1);
  assert.equal(state.divisions.girls.live.scores[girlB.id], 0);
});

test("manual live scoring permits either team and clamps at zero", () => {
  let state = boysWithTeams();
  const [a, b] = state.divisions.boys.teams;
  state = startBoys(state, a.id, b.id);
  state = adjustLiveScore(state, "boys", b.id, 1);
  assert.equal(state.divisions.boys.live.scores[b.id], 1);
  assert.equal(state.divisions.boys.live.servingTeamId, a.id);
  state = adjustLiveScore(state, "boys", a.id, -1);
  assert.equal(state.divisions.boys.live.scores[a.id], 0);
});

test("court swap moves each team with its score and can be undone", () => {
  let state = boysWithTeams();
  const [a, b] = state.divisions.boys.teams;
  state = startBoys(state, a.id, b.id);
  state = adjustLiveScore(state, "boys", a.id, 3);
  state = adjustLiveScore(state, "boys", b.id, 1);
  const scoresBeforeSwap = { ...state.divisions.boys.live.scores };

  assert.deepEqual(liveCourtOrder(state.divisions.boys.live), [a.id, b.id]);
  state = swapLiveCourt(state, "boys");
  assert.deepEqual(liveCourtOrder(state.divisions.boys.live), [b.id, a.id]);
  assert.deepEqual(state.divisions.boys.live.scores, scoresBeforeSwap);
  assert.equal(state.divisions.boys.live.servingTeamId, a.id);

  state = undoLive(state, "boys");
  assert.deepEqual(liveCourtOrder(state.divisions.boys.live), [a.id, b.id]);
  assert.deepEqual(state.divisions.boys.live.scores, scoresBeforeSwap);

  const oldSavedMatch = structuredClone(state.divisions.boys.live);
  delete oldSavedMatch.courtOrder;
  assert.deepEqual(liveCourtOrder(oldSavedMatch), [a.id, b.id]);
});

test("runs multiple dynamic boys and girls singles and doubles scoreboards independently", () => {
  let state = createTournament(T0);
  state = addScoreboard(state, { name: "Court 1", divisionId: "girls", matchType: "doubles" }, T0);
  state = addScoreboard(state, { name: "Court 2", divisionId: "boys", matchType: "singles" }, T0);
  assert.deepEqual(getScoreboards(state).map((board) => [board.name, board.divisionId, board.matchType]), [
    ["Court 1", "girls", "doubles"],
    ["Court 2", "boys", "singles"],
  ]);

  const girls = participantOptions(state, "girls", "doubles");
  const boys = participantOptions(state, "boys", "singles");
  assert.ok(boys.some((player) => player.name === "Ryan"));
  assert.ok(boys.some((player) => player.name === "Try"));
  state = startScoreboardMatch(state, state.scoreboards[0].id, { teamAId: girls[0].id, teamBId: girls[1].id, servingTeamId: girls[0].id }, T0);
  state = startScoreboardMatch(state, state.scoreboards[1].id, { teamAId: boys[0].id, teamBId: boys[1].id, servingTeamId: boys[1].id }, T0);
  state = adjustScoreboardScore(state, state.scoreboards[0].id, girls[0].id, 4);
  state = adjustScoreboardScore(state, state.scoreboards[1].id, boys[1].id, 7);
  assert.equal(state.scoreboards[0].live.scores[girls[0].id], 4);
  assert.equal(state.scoreboards[1].live.scores[boys[1].id], 7);
  assert.equal(getSchedule(state, "girls")[0].status, "live");
});

test("records a dynamic singles result in singles standings", () => {
  let state = addScoreboard(createTournament(T0), { divisionId: "boys", matchType: "singles" }, T0);
  const [a, b] = participantOptions(state, "boys", "singles");
  const boardId = state.scoreboards[0].id;
  state = startScoreboardMatch(state, boardId, { teamAId: a.id, teamBId: b.id, servingTeamId: a.id, targetPoints: 11 }, T0);
  state = adjustScoreboardScore(state, boardId, a.id, 11);
  state = recordScoreboardResult(state, boardId);
  const standings = getStandings(state, "boys", "singles");
  assert.equal(standings[0].name, a.name);
  assert.equal(standings[0].wins, 1);
  assert.equal(state.scoreboards[0].live, null);
});
test("detects a winner only after target and margin are met", () => {
  let state = boysWithTeams();
  const [a, b] = state.divisions.boys.teams;
  state = startBoys(state, a.id, b.id);
  state = score(state, "boys", a.id, 11);
  state = score(state, "boys", b.id, 10);
  assert.equal(state.divisions.boys.live.winnerTeamId, null);
  state = adjustLiveScore(state, "boys", a.id, 1);
  assert.equal(state.divisions.boys.live.winnerTeamId, a.id);
  assert.equal(state.divisions.boys.live.status, "ready");
});

test("records a result and calculates standings totals", () => {
  let state = boysWithTeams();
  const [a, b, c] = state.divisions.boys.teams;
  state = startBoys(state, a.id, b.id);
  state = score(state, "boys", a.id, 11);
  state = score(state, "boys", b.id, 7);
  state = recordLiveResult(state, "boys", "2026-08-22T01:15:00.000Z");

  const rows = getStandings(state, "boys");
  assert.equal(rows[0].id, a.id);
  assert.deepEqual({ played: rows[0].played, wins: rows[0].wins, pf: rows[0].pointsFor, pa: rows[0].pointsAgainst, diff: rows[0].pointDiff }, { played: 1, wins: 1, pf: 11, pa: 7, diff: 4 });
  assert.equal(rows[1].id, b.id);
  assert.equal(rows[1].losses, 1);
  assert.equal(rows.find((row) => row.id === c.id).rank, null);
});

test("uses head-to-head wins within equal-win groups", () => {
  let state = boysWithTeams();
  const [a, b, c] = state.divisions.boys.teams;

  state = startBoys(state, a.id, b.id);
  state = score(state, "boys", a.id, 11);
  state = recordLiveResult(state, "boys");

  state = startBoys(state, b.id, c.id);
  state = score(state, "boys", b.id, 11);
  state = recordLiveResult(state, "boys");

  state = startBoys(state, c.id, a.id);
  state = score(state, "boys", c.id, 11);
  state = recordLiveResult(state, "boys");

  const rows = getStandings(state, "boys");
  const playedRows = rows.filter((row) => row.played > 0);
  assert.equal(playedRows.length, 3);
  assert.ok(playedRows.every((row) => row.wins === 1));
  assert.ok(playedRows.every((row) => row.h2hWins === 1));
});

test("live score and server changes can be undone", () => {
  let state = boysWithTeams();
  const [a, b] = state.divisions.boys.teams;
  state = startBoys(state, a.id, b.id);
  state = adjustLiveScore(state, "boys", a.id, 1);
  state = setLiveServer(state, "boys", b.id);
  state = undoLive(state, "boys");
  assert.equal(state.divisions.boys.live.servingTeamId, a.id);
  state = undoLive(state, "boys");
  assert.equal(state.divisions.boys.live.scores[a.id], 0);
});

test("undoing the latest result recalculates standings", () => {
  let state = boysWithTeams();
  const [a, b] = state.divisions.boys.teams;
  state = startBoys(state, a.id, b.id);
  state = score(state, "boys", a.id, 11);
  state = recordLiveResult(state, "boys");
  state = undoLastResult(state, "boys");
  assert.equal(state.divisions.boys.results.length, 0);
  assert.ok(getStandings(state, "boys").every((row) => row.played === 0));
});

test("awards require results and a unique first place", () => {
  let state = boysWithTeams();
  assert.throws(() => setDivisionFinalized(state, "boys", true), /at least one result/);
  const [a, b] = state.divisions.boys.teams;
  state = startBoys(state, a.id, b.id);
  state = score(state, "boys", a.id, 11);
  state = recordLiveResult(state, "boys");
  state = setDivisionFinalized(state, "boys", true);
  assert.equal(getStandings(state, "boys")[0].award, "CHAMPION");
});

test("versioned tournament storage round-trips and rejects corrupt data", () => {
  const state = boysWithTeams();
  const restored = deserializeTournament(serializeTournament(state));
  assert.equal(restored.ok, true);
  assert.equal(restored.data.divisions.boys.teams.length, 6);
  assert.equal(deserializeTournament("not json").error, "corrupt");
  assert.equal(deserializeTournament(JSON.stringify({ version: 99, data: state })).error, "unsupported");
});

test("migrates a version-one board by adding missing boys teams without losing existing data", () => {
  const legacy = createTournament(T0);
  legacy.schemaVersion = 1;
  legacy.divisions.boys.teams = [{ id: "legacy-boys-team", name: "Existing Team" }];
  legacy.divisions.boys.results = [{ id: "saved-result" }];
  const restored = deserializeTournament(JSON.stringify({ version: 1, data: legacy }));
  assert.equal(restored.ok, true);
  assert.equal(restored.migrated, true);
  assert.equal(restored.data.schemaVersion, 2);
  assert.equal(restored.data.divisions.boys.teams.length, 7);
  assert.equal(restored.data.divisions.boys.teams[0].name, "Existing Team");
  assert.equal(restored.data.divisions.boys.results[0].id, "saved-result");
});
