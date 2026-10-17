import test from "node:test";
import assert from "node:assert/strict";

import { createTournament, serializeTournament } from "../tournament-engine.mjs";
import { fetchTournamentState, isStateNewer, normalizeAccessCode, performTournamentAction } from "../tournament-sync.mjs";

const T0 = "2026-08-22T01:00:00.000Z";

test("normalizes scorer access codes and compares timestamps", () => {
  assert.equal(normalizeAccessCode("  pb-code  "), "PB-CODE");
  assert.equal(isStateNewer({ updatedAt: "2026-08-22T02:00:00.000Z" }, { updatedAt: T0 }), true);
  assert.equal(isStateNewer({ updatedAt: T0 }, { updatedAt: T0 }), false);
});

test("fetch sends the access code and handles an uninitialized server", async () => {
  let request;
  const result = await fetchTournamentState(" secret ", async (url, options) => { request = { url, options }; return new Response(null, { status: 204 }); });
  assert.equal(result, null);
  assert.equal(request.options.headers["X-Pickleball-Access-Code"], "SECRET");
  assert.equal(request.options.method, "GET");
});

test("fetch restores a valid shared tournament record", async () => {
  const expected = createTournament(T0);
  const result = await fetchTournamentState("CODE", async () => new Response(serializeTournament(expected), { status: 200 }));
  assert.equal(result.updatedAt, T0);
  assert.equal(result.divisions.girls.teams.length, 7);
  assert.equal(result.divisions.boys.teams.length, 6);
  assert.equal(result.divisions.mixed.teams.length, 14);
});

test("fetch rejects a valid code belonging to the wrong page role", async () => {
  const expected = createTournament(T0);
  const response = new Response(serializeTournament(expected), { status: 200, headers: { "X-Pickleball-Role": "remote_scorer" } });
  await assert.rejects(() => fetchTournamentState("PICKLE123", async () => response, "game_master"), /Game Master access code/);
});

test("actions send an idempotency key, expected timestamp, and return a stale-write conflict", async () => {
  const local = createTournament(T0);
  const remote = createTournament("2026-08-22T02:00:00.000Z");
  let request;
  const result = await performTournamentAction(local, "CODE", "adjust_score", { scoreboardId: "court-1", participantId: "team-a", delta: 1 }, "action-test-123", async (url, options) => { request = { url, options }; return new Response(serializeTournament(remote), { status: 409 }); });
  assert.equal(result.conflict, true);
  assert.equal(result.state.updatedAt, "2026-08-22T02:00:00.000Z");
  assert.deepEqual(JSON.parse(request.options.body), { actionId: "action-test-123", expectedUpdatedAt: T0, action: "adjust_score", payload: { scoreboardId: "court-1", participantId: "team-a", delta: 1 } });
  assert.equal(request.options.method, "POST");
  assert.equal(request.options.headers["X-Pickleball-Access-Code"], "CODE");
});

test("action sync reports forbidden role operations", async () => {
  const state = createTournament(T0);
  await assert.rejects(() => performTournamentAction(state, "SCORER", "create_scoreboard", {}, "action-forbidden", async () => new Response('{"error":"action_forbidden"}', { status: 403 })), /role is not allowed/i);
});

test("sync reports an incorrect access code without exposing server details", async () => {
  await assert.rejects(() => fetchTournamentState("BAD", async () => new Response('{"error":"access_code_required"}', { status: 401 })), /access code is incorrect/i);
});
