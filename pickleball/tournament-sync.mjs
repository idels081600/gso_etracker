import { deserializeTournament, serializeTournament } from "./tournament-engine.mjs";

export const TOURNAMENT_API_URL = "./tournament-state-api.php";

function parseRecord(raw) {
  const restored = deserializeTournament(raw);
  if (!restored.ok) throw new Error("The shared tournament state is incompatible.");
  return restored.data;
}

async function responseError(response, raw = null) {
  if (response.status === 401) return new Error("The scorer access code is incorrect.");
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

export async function fetchTournamentState(accessCode, fetchImpl = globalThis.fetch) {
  const response = await fetchImpl(TOURNAMENT_API_URL, {
    method: "GET",
    cache: "no-store",
    headers: { Accept: "application/json", "X-Pickleball-Access-Code": normalizeAccessCode(accessCode) },
  });
  if (response.status === 204) return null;
  if (!response.ok) throw await responseError(response);
  return parseRecord(await response.text());
}

export async function saveTournamentState(state, accessCode, fetchImpl = globalThis.fetch) {
  const response = await fetchImpl(TOURNAMENT_API_URL, {
    method: "PUT",
    cache: "no-store",
    headers: { "Content-Type": "application/json", Accept: "application/json", "X-Pickleball-Access-Code": normalizeAccessCode(accessCode) },
    body: serializeTournament(state),
  });
  const raw = await response.text();
  if (response.status === 409) return { ok: false, conflict: true, state: parseRecord(raw) };
  if (!response.ok) throw await responseError(response, raw);
  return { ok: true, conflict: false, state: parseRecord(raw) };
}
