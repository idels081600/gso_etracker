import test from "node:test";
import assert from "node:assert/strict";
import { fetchSingleMatchState, saveSingleMatchState } from "../single-match-sync.mjs";

const state = {
  activeMatch: null,
  history: [],
  updatedAt: "2026-10-17T00:00:00.000Z",
};

test("fetch returns null for an empty single-match database", async () => {
  const fetchImpl = async (_url, options) => {
    assert.equal(options.method, "GET");
    assert.equal(options.headers["X-Pickleball-Access-Code"], "PICKLE123");
    return new Response(null, { status: 204 });
  };
  assert.equal(await fetchSingleMatchState(" pickle123 ", fetchImpl), null);
});

test("fetch validates and returns database state", async () => {
  const fetchImpl = async () => new Response(JSON.stringify({ version: 1, data: state }), { status: 200 });
  assert.deepEqual(await fetchSingleMatchState("PICKLE123", fetchImpl), state);
});

test("save sends versioned state and reports conflicts", async () => {
  const fetchImpl = async (_url, options) => {
    assert.equal(options.method, "PUT");
    assert.deepEqual(JSON.parse(options.body), { version: 1, data: state });
    return new Response(JSON.stringify({ version: 1, data: state }), { status: 409 });
  };
  const result = await saveSingleMatchState(state, "PICKLE123", fetchImpl);
  assert.equal(result.conflict, true);
  assert.deepEqual(result.data, state);
});

test("sync gives a clear error for an invalid access code", async () => {
  const fetchImpl = async () => new Response(JSON.stringify({ error: "access_code_required" }), { status: 401 });
  await assert.rejects(() => fetchSingleMatchState("wrong", fetchImpl), /access code is incorrect/i);
});
