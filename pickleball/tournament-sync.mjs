import { deserializeTournament } from "./tournament-engine.mjs";

export const TOURNAMENT_API_URL = "./tournament-state-api.php";

function parseRecord(raw) {
  const restored = deserializeTournament(raw);
  if (!restored.ok) throw new Error("The shared tournament state is incompatible.");
  return restored.data;
}

async function responseError(response, raw = null) {
  if (response.status === 401) return new Error("The access code is incorrect.");
  if (response.status === 403) return new Error("This role is not allowed to perform that action.");
  if (response.status === 429) return new Error("Too many incorrect attempts. Wait 15 minutes and try again.");
  let code = "";
  try { code = JSON.parse(raw ?? await response.text())?.error ?? ""; } catch {}
  const messages = {
    database_config_missing: "The tournament database configuration is missing on the server.",
    database_unavailable: "The tournament database is currently unavailable.",
    database_setup_failed: "The tournament database tables could not be created.",
    database_query_failed: "The tournament database could not be read.",
    database_write_failed: "The tournament score could not be saved to the database.",
    database_transaction_failed: "The tournament database could not complete the score update.",
    invalid_final_score: "The final score does not satisfy the configured win condition.",
    scoreboard_has_live_match: "Cancel the live match before changing this scoreboard.",
    no_live_match: "This court no longer has a live match.",
    nothing_to_undo: "There is no scoring change to undo.",
    storage_unavailable: "The tournament storage directory is not writable on the server.",
  };
  return new Error(messages[code] ?? `Tournament synchronization failed (${response.status}).`);
}

export function normalizeAccessCode(value) {
  return String(value ?? "").trim().toUpperCase();
}

export function isStateNewer(candidate, reference) {
  return String(candidate?.updatedAt ?? "") > String(reference?.updatedAt ?? "");
}

export async function fetchTournamentState(accessCode, fetchImpl = globalThis.fetch, requiredRole = null) {
  const response = await fetchImpl(TOURNAMENT_API_URL, {
    method: "GET",
    cache: "no-store",
    headers: { Accept: "application/json", "X-Pickleball-Access-Code": normalizeAccessCode(accessCode) },
  });
  if (!response.ok) throw await responseError(response);
  if (requiredRole && response.headers.get("X-Pickleball-Role") !== requiredRole) {
    throw new Error(requiredRole === "game_master" ? "Use the Game Master access code on this page." : "Use the Remote Scorer access code on this page.");
  }
  if (response.status === 204) return null;
  return parseRecord(await response.text());
}

export async function waitForTournamentState(accessCode, updatedAt, fetchImpl = globalThis.fetch, requiredRole = null) {
  const query = new URLSearchParams({ since: String(updatedAt ?? ""), wait: "20" });
  const response = await fetchImpl(`${TOURNAMENT_API_URL}?${query}`, {
    method: "GET",
    cache: "no-store",
    headers: { Accept: "application/json", "X-Pickleball-Access-Code": normalizeAccessCode(accessCode) },
  });
  if (!response.ok) throw await responseError(response);
  if (requiredRole && response.headers.get("X-Pickleball-Role") !== requiredRole) {
    throw new Error(requiredRole === "game_master" ? "Use the Game Master access code on this page." : "Use the Remote Scorer access code on this page.");
  }
  if (response.status === 204) return null;
  return parseRecord(await response.text());
}

export function createActionId() {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
  return `action-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export async function performTournamentAction(state, accessCode, action, payload = {}, actionId = createActionId(), fetchImpl = globalThis.fetch) {
  const response = await fetchImpl(TOURNAMENT_API_URL, {
    method: "POST",
    cache: "no-store",
    headers: { "Content-Type": "application/json", Accept: "application/json", "X-Pickleball-Access-Code": normalizeAccessCode(accessCode) },
    body: JSON.stringify({ actionId, expectedUpdatedAt: state?.updatedAt ?? "", action, payload }),
  });
  const raw = await response.text();
  if (response.status === 409) return { ok: false, conflict: true, state: parseRecord(raw), actionId };
  if (!response.ok) throw await responseError(response, raw);
  return { ok: true, conflict: false, state: parseRecord(raw), actionId };
}
