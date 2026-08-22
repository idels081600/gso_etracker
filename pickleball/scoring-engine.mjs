export const ACTIVE_SCHEMA_VERSION = 1;
export const HISTORY_SCHEMA_VERSION = 1;
export const MAX_UNDO_STEPS = 50;

const TEAM_IDS = ["A", "B"];

function clone(value) {
  return JSON.parse(JSON.stringify(value));
}

function nowIso(now) {
  return typeof now === "string" ? now : (now ?? new Date()).toISOString();
}

function makeId(prefix = "match") {
  if (globalThis.crypto?.randomUUID) return `${prefix}-${globalThis.crypto.randomUUID()}`;
  return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function otherTeam(teamId) {
  return teamId === "A" ? "B" : "A";
}

function ensureTeamId(teamId) {
  if (!TEAM_IDS.includes(teamId)) throw new Error(`Unknown team: ${teamId}`);
}

function defaultPlayers(matchType, teamId, supplied = []) {
  const count = matchType === "doubles" ? 2 : 1;
  const offset = teamId === "A" ? 0 : count;
  return Array.from({ length: count }, (_, index) => ({
    id: `${teamId}${index + 1}`,
    name: String(supplied[index]?.name || `Player ${offset + index + 1}`).trim(),
  }));
}

function teamLabel(players) {
  return players.map((player) => player.name).join(" & ");
}

function positionsFor(players, requestedRightPlayerId) {
  if (players.length === 1) {
    return { left: null, right: players[0].id };
  }
  const right = players.some((player) => player.id === requestedRightPlayerId)
    ? requestedRightPlayerId
    : players[0].id;
  const left = players.find((player) => player.id !== right).id;
  return { left, right };
}

function snapshot(match) {
  return clone({
    games: match.games,
    live: match.live,
    status: match.status,
    pendingWinner: match.pendingWinner,
    updatedAt: match.updatedAt,
  });
}

function commit(match, mutator, now, { detectWinner = false } = {}) {
  const next = clone(match);
  const previous = snapshot(match);
  next.undoStack = [...(next.undoStack || []), previous].slice(-MAX_UNDO_STEPS);
  mutator(next);
  if (detectWinner) next.pendingWinner = getCandidateWinner(next);
  next.updatedAt = nowIso(now);
  return next;
}

export function createMatch(config = {}, now = new Date()) {
  const matchType = config.matchType === "singles" ? "singles" : "doubles";
  const scoringMode = config.scoringMode === "rally" ? "rally" : "sideout";
  const targetPoints = [11, 15, 21].includes(Number(config.targetPoints))
    ? Number(config.targetPoints)
    : 11;
  const bestOf = [1, 3, 5].includes(Number(config.bestOf)) ? Number(config.bestOf) : 3;
  const winBy = Number(config.winBy) === 1 ? 1 : 2;
  const createdAt = nowIso(now);

  const aPlayers = defaultPlayers(matchType, "A", config.teams?.A?.players);
  const bPlayers = defaultPlayers(matchType, "B", config.teams?.B?.players);
  const teams = {
    A: { id: "A", label: teamLabel(aPlayers), players: aPlayers },
    B: { id: "B", label: teamLabel(bPlayers), players: bPlayers },
  };

  const servingTeamId = TEAM_IDS.includes(config.servingTeamId) ? config.servingTeamId : "A";
  const servingPlayers = teams[servingTeamId].players;
  const activeServerPlayerId = servingPlayers.some((player) => player.id === config.activeServerPlayerId)
    ? config.activeServerPlayerId
    : servingPlayers[0].id;
  const isTraditionalDoubles = matchType === "doubles" && scoringMode === "sideout";

  return {
    schemaVersion: ACTIVE_SCHEMA_VERSION,
    id: config.id || makeId("match"),
    createdAt,
    updatedAt: createdAt,
    settings: { matchType, scoringMode, targetPoints, winBy, bestOf },
    teams,
    games: [],
    live: {
      number: 1,
      scores: { A: 0, B: 0 },
      servingTeamId,
      activeServerPlayerId,
      serverNumber: isTraditionalDoubles ? Number(config.serverNumber) === 1 ? 1 : 2 : 1,
      displayOrder: config.displayOrder?.[0] === "B" ? ["B", "A"] : ["A", "B"],
      positions: {
        A: positionsFor(aPlayers, config.positions?.A?.right),
        B: positionsFor(bPlayers, config.positions?.B?.right),
      },
    },
    status: "active",
    pendingWinner: null,
    undoStack: [],
  };
}

export function isTraditionalDoubles(match) {
  return match.settings.matchType === "doubles" && match.settings.scoringMode === "sideout";
}

export function adjustScore(match, teamId, delta, now) {
  ensureTeamId(teamId);
  return commit(match, (next) => {
    next.live.scores[teamId] = Math.max(0, next.live.scores[teamId] + Number(delta));
  }, now, { detectWinner: true });
}

export function setServingTeam(match, teamId, now) {
  ensureTeamId(teamId);
  return commit(match, (next) => {
    next.live.servingTeamId = teamId;
    const players = next.teams[teamId].players;
    if (!players.some((player) => player.id === next.live.activeServerPlayerId)) {
      next.live.activeServerPlayerId = players[0].id;
    }
    if (!isTraditionalDoubles(next)) next.live.serverNumber = 1;
  }, now);
}

export function setActiveServer(match, playerId, now) {
  const servingPlayers = match.teams[match.live.servingTeamId].players;
  if (!servingPlayers.some((player) => player.id === playerId)) {
    throw new Error("The active server must belong to the serving team.");
  }
  return commit(match, (next) => {
    next.live.activeServerPlayerId = playerId;
  }, now);
}

export function setServerNumber(match, serverNumber, now) {
  const normalized = Number(serverNumber) === 2 ? 2 : 1;
  return commit(match, (next) => {
    next.live.serverNumber = isTraditionalDoubles(next) ? normalized : 1;
  }, now);
}

export function switchEnds(match, now) {
  return commit(match, (next) => {
    next.live.displayOrder = [...next.live.displayOrder].reverse();
  }, now);
}

export function setTeamRightPlayer(match, teamId, playerId, now) {
  ensureTeamId(teamId);
  const players = match.teams[teamId].players;
  if (!players.some((player) => player.id === playerId)) {
    throw new Error("Court position player must belong to the team.");
  }
  return commit(match, (next) => {
    next.live.positions[teamId] = positionsFor(next.teams[teamId].players, playerId);
  }, now);
}

export function resetGame(match, now) {
  return commit(match, (next) => {
    next.live.scores = { A: 0, B: 0 };
    next.pendingWinner = null;
  }, now);
}

export function getCandidateWinner(match) {
  const { targetPoints, winBy } = match.settings;
  const { A, B } = match.live.scores;
  if (A >= targetPoints && A - B >= winBy) return "A";
  if (B >= targetPoints && B - A >= winBy) return "B";
  return null;
}

export function cancelPendingWinner(match, now) {
  const next = clone(match);
  next.pendingWinner = null;
  next.updatedAt = nowIso(now);
  return next;
}

export function gameWins(match) {
  return match.games.reduce((wins, game) => {
    wins[game.winnerTeamId] += 1;
    return wins;
  }, { A: 0, B: 0 });
}

export function winsNeeded(match) {
  return Math.ceil(match.settings.bestOf / 2);
}

export function confirmGame(match, winnerTeamId = match.pendingWinner, now) {
  ensureTeamId(winnerTeamId);
  const candidate = getCandidateWinner(match);
  if (winnerTeamId !== candidate) throw new Error("The selected team has not met the win condition.");

  const next = commit(match, (draft) => {
    draft.games.push({
      number: draft.live.number,
      scores: clone(draft.live.scores),
      winnerTeamId,
      confirmedAt: nowIso(now),
      finalLive: clone(draft.live),
    });
    draft.pendingWinner = null;
    const wins = gameWins(draft);
    draft.status = wins[winnerTeamId] >= winsNeeded(draft) ? "awaiting-save" : "between-games";
  }, now);
  return next;
}

export function startNextGame(match, options = {}, now) {
  if (match.status !== "between-games") throw new Error("The match is not ready for another game.");
  const servingTeamId = TEAM_IDS.includes(options.servingTeamId) ? options.servingTeamId : "A";
  const servingPlayers = match.teams[servingTeamId].players;
  const activeServerPlayerId = servingPlayers.some((player) => player.id === options.activeServerPlayerId)
    ? options.activeServerPlayerId
    : servingPlayers[0].id;

  return commit(match, (next) => {
    next.live = {
      ...next.live,
      number: next.games.length + 1,
      scores: { A: 0, B: 0 },
      servingTeamId,
      activeServerPlayerId,
      serverNumber: isTraditionalDoubles(next) ? Number(options.serverNumber) === 1 ? 1 : 2 : 1,
      displayOrder: options.swapEnds ? [...next.live.displayOrder].reverse() : [...next.live.displayOrder],
    };
    if (options.rightPlayers) {
      TEAM_IDS.forEach((teamId) => {
        next.live.positions[teamId] = positionsFor(next.teams[teamId].players, options.rightPlayers[teamId]);
      });
    }
    next.status = "active";
    next.pendingWinner = null;
  }, now);
}

export function correctLastGame(match, now) {
  if (!match.games.length) return match;
  const next = clone(match);
  const lastGame = next.games.pop();
  next.live = clone(lastGame.finalLive);
  next.status = "active";
  next.pendingWinner = null;
  next.updatedAt = nowIso(now);
  next.undoStack = [];
  return next;
}

export function undo(match, now) {
  if (!match.undoStack?.length) return match;
  const next = clone(match);
  const previous = next.undoStack.pop();
  next.games = previous.games;
  next.live = previous.live;
  next.status = previous.status;
  next.pendingWinner = previous.pendingWinner;
  next.updatedAt = nowIso(now);
  return next;
}

export function getScoreCall(match) {
  const serving = match.live.servingTeamId;
  const receiving = otherTeam(serving);
  const values = [match.live.scores[serving], match.live.scores[receiving]];
  if (isTraditionalDoubles(match)) values.push(match.live.serverNumber);
  return {
    servingTeamId: serving,
    receivingTeamId: receiving,
    values,
    label: values.join(" – "),
  };
}

export function getPlayer(match, playerId) {
  return [...match.teams.A.players, ...match.teams.B.players].find((player) => player.id === playerId) || null;
}

export function buildHistoryRecord(match, now = new Date()) {
  if (match.status !== "awaiting-save") throw new Error("Only a completed match can be saved.");
  const wins = gameWins(match);
  const winnerTeamId = wins.A > wins.B ? "A" : "B";
  const completedAt = nowIso(now);
  const durationMs = Math.max(0, new Date(completedAt).getTime() - new Date(match.createdAt).getTime());
  return {
    schemaVersion: HISTORY_SCHEMA_VERSION,
    id: match.id,
    createdAt: match.createdAt,
    completedAt,
    durationMs,
    settings: clone(match.settings),
    teams: clone(match.teams),
    games: match.games.map(({ number, scores, winnerTeamId: winner }) => ({
      number,
      scores: clone(scores),
      winnerTeamId: winner,
    })),
    winnerTeamId,
  };
}

function validMatch(value) {
  return Boolean(
    value &&
    value.schemaVersion === ACTIVE_SCHEMA_VERSION &&
    value.id &&
    value.settings &&
    value.teams?.A?.players &&
    value.teams?.B?.players &&
    value.live?.scores &&
    TEAM_IDS.includes(value.live.servingTeamId),
  );
}

export function serializeActiveMatch(match) {
  return JSON.stringify({ version: ACTIVE_SCHEMA_VERSION, data: match });
}

export function deserializeActiveMatch(raw) {
  if (!raw) return { ok: true, data: null, error: null };
  try {
    const parsed = JSON.parse(raw);
    if (parsed.version !== ACTIVE_SCHEMA_VERSION || !validMatch(parsed.data)) {
      return { ok: false, data: null, error: "unsupported" };
    }
    return { ok: true, data: parsed.data, error: null };
  } catch {
    return { ok: false, data: null, error: "corrupt" };
  }
}

export function serializeHistory(records) {
  return JSON.stringify({ version: HISTORY_SCHEMA_VERSION, data: records });
}

export function deserializeHistory(raw) {
  if (!raw) return { ok: true, data: [], error: null };
  try {
    const parsed = JSON.parse(raw);
    if (parsed.version !== HISTORY_SCHEMA_VERSION || !Array.isArray(parsed.data)) {
      return { ok: false, data: [], error: "unsupported" };
    }
    return { ok: true, data: parsed.data, error: null };
  } catch {
    return { ok: false, data: [], error: "corrupt" };
  }
}
