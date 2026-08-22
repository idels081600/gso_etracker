import test from "node:test";
import assert from "node:assert/strict";

import {
  MAX_UNDO_STEPS,
  adjustScore,
  buildHistoryRecord,
  cancelPendingWinner,
  confirmGame,
  correctLastGame,
  createMatch,
  deserializeActiveMatch,
  deserializeHistory,
  gameWins,
  getScoreCall,
  isTraditionalDoubles,
  resetGame,
  serializeActiveMatch,
  serializeHistory,
  setActiveServer,
  setServerNumber,
  setServingTeam,
  startNextGame,
  switchEnds,
  undo,
} from "../scoring-engine.mjs";

const T0 = "2026-08-22T01:00:00.000Z";

function configured(overrides = {}) {
  return createMatch({
    id: "test-match",
    matchType: "doubles",
    scoringMode: "sideout",
    targetPoints: 11,
    winBy: 2,
    bestOf: 3,
    servingTeamId: "A",
    activeServerPlayerId: "A1",
    teams: {
      A: { players: [{ name: "Ada" }, { name: "Bea" }] },
      B: { players: [{ name: "Cleo" }, { name: "Dani" }] },
    },
    positions: { A: { right: "A1" }, B: { right: "B2" } },
    ...overrides,
  }, T0);
}

function scoreTo(match, teamId, points) {
  let next = match;
  for (let index = 0; index < points; index += 1) next = adjustScore(next, teamId, 1);
  return next;
}

test("creates standard doubles side-out defaults", () => {
  const match = createMatch({ id: "defaults" }, T0);
  assert.equal(match.settings.matchType, "doubles");
  assert.equal(match.settings.scoringMode, "sideout");
  assert.equal(match.settings.targetPoints, 11);
  assert.equal(match.settings.winBy, 2);
  assert.equal(match.settings.bestOf, 3);
  assert.equal(match.live.serverNumber, 2);
  assert.equal(match.teams.A.players.length, 2);
  assert.equal(match.teams.B.players.length, 2);
});

test("creates singles with one player per team and a single server", () => {
  const match = configured({ matchType: "singles", scoringMode: "rally" });
  assert.equal(match.teams.A.players.length, 1);
  assert.equal(match.teams.B.players.length, 1);
  assert.equal(match.live.serverNumber, 1);
  assert.equal(isTraditionalDoubles(match), false);
});

test("formats traditional doubles score in serving-team order with server number", () => {
  let match = configured();
  match = scoreTo(match, "A", 5);
  match = scoreTo(match, "B", 3);
  match = setServerNumber(match, 1);
  assert.equal(getScoreCall(match).label, "5 – 3 – 1");
  match = setServingTeam(match, "B");
  assert.equal(getScoreCall(match).label, "3 – 5 – 1");
});

test("formats rally and singles scores with two numbers", () => {
  let match = configured({ scoringMode: "rally" });
  match = scoreTo(match, "A", 2);
  assert.deepEqual(getScoreCall(match).values, [2, 0]);
  assert.equal(isTraditionalDoubles(match), false);

  match = configured({ matchType: "singles" });
  assert.equal(getScoreCall(match).values.length, 2);
});

test("manual scoring permits either team to score regardless of serve", () => {
  let match = configured({ servingTeamId: "A" });
  match = adjustScore(match, "B", 1);
  assert.equal(match.live.scores.B, 1);
  assert.equal(match.live.servingTeamId, "A");
});

test("score decrement is clamped at zero", () => {
  let match = configured();
  match = adjustScore(match, "A", -1);
  assert.equal(match.live.scores.A, 0);
});

test("serving team, active server, and server number are manually selectable", () => {
  let match = configured();
  match = setActiveServer(match, "A2");
  match = setServerNumber(match, 1);
  assert.equal(match.live.activeServerPlayerId, "A2");
  assert.equal(match.live.serverNumber, 1);

  match = setServingTeam(match, "B");
  assert.equal(match.live.servingTeamId, "B");
  assert.equal(match.live.activeServerPlayerId, "B1");
  assert.throws(() => setActiveServer(match, "A1"), /serving team/);
});

test("detects 11–9 but not 11–10 when win by two is selected", () => {
  let winning = configured();
  winning = scoreTo(winning, "A", 11);
  winning = scoreTo(winning, "B", 9);
  assert.equal(winning.pendingWinner, "A");

  let extended = configured();
  extended = scoreTo(extended, "A", 11);
  extended = scoreTo(extended, "B", 10);
  assert.equal(extended.pendingWinner, null);
  extended = adjustScore(extended, "A", 1);
  assert.equal(extended.pendingWinner, "A");
});

test("win by one ends at the target", () => {
  let match = configured({ winBy: 1 });
  match = scoreTo(match, "A", 11);
  match = scoreTo(match, "B", 10);
  assert.equal(match.pendingWinner, "A");
});

test("cancelling a candidate winner preserves the score", () => {
  let match = scoreTo(configured(), "A", 11);
  const cancelled = cancelPendingWinner(match);
  assert.deepEqual(cancelled.live.scores, { A: 11, B: 0 });
  assert.equal(cancelled.pendingWinner, null);
});

test("confirms games and completes a best-of-three match", () => {
  let match = scoreTo(configured(), "A", 11);
  match = confirmGame(match);
  assert.equal(match.status, "between-games");
  assert.deepEqual(gameWins(match), { A: 1, B: 0 });

  match = startNextGame(match, { servingTeamId: "B", activeServerPlayerId: "B2", serverNumber: 2, swapEnds: true });
  assert.equal(match.live.number, 2);
  assert.deepEqual(match.live.scores, { A: 0, B: 0 });
  assert.deepEqual(match.live.displayOrder, ["B", "A"]);
  match = scoreTo(match, "A", 11);
  match = confirmGame(match);
  assert.equal(match.status, "awaiting-save");
  assert.deepEqual(gameWins(match), { A: 2, B: 0 });
});

test("best-of-one and best-of-five require the correct game totals", () => {
  let single = scoreTo(configured({ bestOf: 1 }), "B", 11);
  single = confirmGame(single);
  assert.equal(single.status, "awaiting-save");

  let long = configured({ bestOf: 5 });
  for (let game = 1; game <= 3; game += 1) {
    long = scoreTo(long, "B", 11);
    long = confirmGame(long);
    if (game < 3) long = startNextGame(long, { servingTeamId: "A" });
  }
  assert.equal(long.status, "awaiting-save");
  assert.deepEqual(gameWins(long), { A: 0, B: 3 });
});

test("correcting the last confirmed game restores its final live state", () => {
  let match = scoreTo(configured({ bestOf: 1 }), "A", 11);
  match = confirmGame(match);
  const corrected = correctLastGame(match);
  assert.equal(corrected.status, "active");
  assert.deepEqual(corrected.live.scores, { A: 11, B: 0 });
  assert.equal(corrected.games.length, 0);
  assert.equal(corrected.pendingWinner, null);
});

test("undo restores score, serve, court ends, and reset changes", () => {
  const original = configured();
  let match = adjustScore(original, "A", 1);
  match = undo(match);
  assert.deepEqual(match.live.scores, original.live.scores);

  match = setServingTeam(match, "B");
  match = undo(match);
  assert.equal(match.live.servingTeamId, "A");

  match = switchEnds(match);
  match = undo(match);
  assert.deepEqual(match.live.displayOrder, ["A", "B"]);

  match = scoreTo(match, "A", 3);
  match = resetGame(match);
  assert.deepEqual(match.live.scores, { A: 0, B: 0 });
  match = undo(match);
  assert.deepEqual(match.live.scores, { A: 3, B: 0 });
});

test("undo history is bounded", () => {
  let match = configured();
  for (let index = 0; index < MAX_UNDO_STEPS + 8; index += 1) match = adjustScore(match, "A", 1);
  assert.equal(match.undoStack.length, MAX_UNDO_STEPS);
});

test("active match serialization round-trips and rejects bad data", () => {
  const match = adjustScore(configured(), "B", 4);
  const restored = deserializeActiveMatch(serializeActiveMatch(match));
  assert.equal(restored.ok, true);
  assert.deepEqual(restored.data.live.scores, { A: 0, B: 4 });
  assert.equal(deserializeActiveMatch("not json").error, "corrupt");
  assert.equal(deserializeActiveMatch(JSON.stringify({ version: 99, data: match })).error, "unsupported");
  assert.deepEqual(deserializeActiveMatch(null), { ok: true, data: null, error: null });
});

test("completed history serialization contains result summaries without undo state", () => {
  let match = scoreTo(configured({ bestOf: 1 }), "B", 11);
  match = confirmGame(match, "B", "2026-08-22T01:20:00.000Z");
  const record = buildHistoryRecord(match, "2026-08-22T01:30:00.000Z");
  assert.equal(record.winnerTeamId, "B");
  assert.equal(record.durationMs, 30 * 60 * 1000);
  assert.equal("undoStack" in record, false);
  assert.equal("finalLive" in record.games[0], false);

  const restored = deserializeHistory(serializeHistory([record]));
  assert.equal(restored.ok, true);
  assert.equal(restored.data[0].id, "test-match");
  assert.equal(deserializeHistory("{").error, "corrupt");
  assert.equal(deserializeHistory(JSON.stringify({ version: 99, data: [] })).error, "unsupported");
});
