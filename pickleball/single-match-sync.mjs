export const SINGLE_MATCH_API_URL = "./single-match-state-api.php";

function normalizeAccessCode(value) {
  return String(value ?? "").trim().toUpperCase();
}

function validateRecord(record) {
  if (record?.version !== 1 || !record.data || !Array.isArray(record.data.history)) {
    throw new Error("The saved database state is incompatible.");
  }
  return record.data;
}

async function responseError(response, raw = null) {
  if (response.status === 401) return new Error("The database access code is incorrect.");
  if (response.status === 429) return new Error("Too many incorrect attempts. Wait 15 minutes and try again.");
  let code = "";
  try { code = JSON.parse(raw ?? await response.text())?.error ?? ""; } catch {}
  const messages = {
    database_config_missing: "The database configuration is missing on the server.",
    database_unavailable: "The database is currently unavailable.",
    database_setup_failed: "The database tables could not be created.",
    database_query_failed: "The database could not be read.",
    database_write_failed: "The match could not be saved to the database.",
    database_transaction_failed: "The database could not complete the update.",
    stored_state_invalid: "The saved database state is invalid.",
  };
  return new Error(messages[code] ?? `Database synchronization failed (${response.status}).`);
}

export async function fetchSingleMatchState(accessCode, fetchImpl = globalThis.fetch) {
  const response = await fetchImpl(SINGLE_MATCH_API_URL, {
    method: "GET",
    cache: "no-store",
    headers: { Accept: "application/json", "X-Pickleball-Access-Code": normalizeAccessCode(accessCode) },
  });
  if (response.status === 204) return null;
  if (!response.ok) throw await responseError(response);
  return validateRecord(await response.json());
}

export async function saveSingleMatchState(data, accessCode, fetchImpl = globalThis.fetch) {
  const response = await fetchImpl(SINGLE_MATCH_API_URL, {
    method: "PUT",
    cache: "no-store",
    headers: { "Content-Type": "application/json", Accept: "application/json", "X-Pickleball-Access-Code": normalizeAccessCode(accessCode) },
    body: JSON.stringify({ version: 1, data }),
  });
  const raw = await response.text();
  if (response.status === 409) return { ok: false, conflict: true, data: validateRecord(JSON.parse(raw)) };
  if (!response.ok) throw await responseError(response, raw);
  return { ok: true, conflict: false, data: validateRecord(JSON.parse(raw)) };
}

