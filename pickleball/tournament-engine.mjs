export const TOURNAMENT_SCHEMA_VERSION = 2;
export const DIVISION_IDS = ["girls", "boys"];
export const MAX_LIVE_UNDO = 40;

const GIRLS_TEAMS = [
  "Joy & Irah",
  "Born2x & Jane Yap",
  "Obe & Kath",
  "Miriam & Mau",
  "Gen & Babette",
  "Anna & Twinkle",
  "Margie & Thamar",
];

const BOYS_TEAMS = [
  "Ryan & Try",
  "Ridan & Carl",
  "Junax & Baba",
  "Manju & Ian",
  "Iven & Jeff",
  "Eping & JG",
];

export const TOURNAMENT_SCHEDULES = {
  girls: [
    ["Joy & Irah", "Born2x & Jane Yap"],
    ["Obe & Kath", "Miriam & Mau"],
    ["Born2x & Jane Yap", "Gen & Babette"],
    ["Gen & Babette", "Anna & Twinkle"],
    ["Margie & Thamar", "Anna & Twinkle"],
    ["Anna & Twinkle", "Obe & Kath"],
    ["Joy & Irah", "Gen & Babette"],
    ["Born2x & Jane Yap", "Margie & Thamar"],
    ["Gen & Babette", "Margie & Thamar"],
    ["Margie & Thamar", "Obe & Kath"],
    ["Anna & Twinkle", "Miriam & Mau"],
    ["Joy & Irah", "Margie & Thamar"],
    ["Born2x & Jane Yap", "Anna & Twinkle"],
    ["Gen & Babette", "Obe & Kath"],
    ["Margie & Thamar", "Miriam & Mau"],
    ["Joy & Irah", "Anna & Twinkle"],
    ["Born2x & Jane Yap", "Obe & Kath"],
    ["Gen & Babette", "Miriam & Mau"],
    ["Joy & Irah", "Obe & Kath"],
    ["Born2x & Jane Yap", "Miriam & Mau"],
    ["Joy & Irah", "Miriam & Mau"],
  ],
  boys: [
    ["Ryan & Try", "Ridan & Carl"],
    ["Junax & Baba", "Manju & Ian"],
    ["Ridan & Carl", "Iven & Jeff"],
    ["Iven & Jeff", "Eping & JG"],
    ["Eping & JG", "Junax & Baba"],
    ["Ryan & Try", "Iven & Jeff"],
    ["Ridan & Carl", "Eping & JG"],
    ["Iven & Jeff", "Junax & Baba"],
    ["Eping & JG", "Manju & Ian"],
    ["Ryan & Try", "Eping & JG"],
    ["Ridan & Carl", "Junax & Baba"],
    ["Iven & Jeff", "Manju & Ian"],
    ["Ryan & Try", "Junax & Baba"],
    ["Ridan & Carl", "Manju & Ian"],
    ["Ryan & Try", "Manju & Ian"],
  ],
};

const clone = (value) => JSON.parse(JSON.stringify(value));
const nowIso = (now = new Date()) => typeof now === "string" ? now : now.toISOString();

function makeId(prefix) {
  if (globalThis.crypto?.randomUUID) return `${prefix}-${globalThis.crypto.randomUUID()}`;
  return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function divisionOf(state, divisionId) {
  if (!DIVISION_IDS.includes(divisionId)) throw new Error(`Unknown division: ${divisionId}`);
  return state.divisions[divisionId];
}

function teamName(name) {
  return String(name ?? "").trim().replace(/\s+/g, " ");
}

export function teamPlayerNames(name) {
  const normalized = teamName(name);
  const players = normalized.split(/\s*&\s*/).map((player) => player.trim()).filter(Boolean);
  if (players.length >= 2) return [players[0], players.slice(1).join(" & ")];
  return [normalized || "Player", "Partner"];
}

export function servingPlayerName(name, serverNumber) {
  const players = teamPlayerNames(name);
  return players[Number(serverNumber) === 2 ? 1 : 0];
}

export function liveCourtOrder(live) {
  const teams = [live?.teamAId, live?.teamBId];
  const order = Array.isArray(live?.courtOrder) ? live.courtOrder : [];
  const valid = order.length === 2 && new Set(order).size === 2 && teams.every((teamId) => order.includes(teamId));
  return valid ? [...order] : teams;
}

function seedTeams(names, divisionId) {
  return names.map((name, index) => ({ id: `${divisionId}-team-${index + 1}`, name }));
}

function snapshotLive(live) {
  const copy = clone(live);
  delete copy.undoStack;
  return copy;
}

function mutateLive(state, divisionId, mutator, now) {
  const next = clone(state);
  const division = divisionOf(next, divisionId);
  if (!division.live) throw new Error("There is no active match in this division.");
  division.live.undoStack = [...division.live.undoStack, snapshotLive(division.live)].slice(-MAX_LIVE_UNDO);
  mutator(division.live, division);
  division.live.winnerTeamId = candidateWinner(division.live);
  division.live.status = division.live.winnerTeamId ? "ready" : "live";
  next.updatedAt = nowIso(now);
  return next;
}

export function createTournament(now = new Date()) {
  const createdAt = nowIso(now);
  return {
    schemaVersion: TOURNAMENT_SCHEMA_VERSION,
    createdAt,
    updatedAt: createdAt,
    divisions: {
      girls: {
        id: "girls",
        label: "Girls",
        teams: seedTeams(GIRLS_TEAMS, "girls"),
        live: null,
        results: [],
        finalized: false,
      },
      boys: {
        id: "boys",
        label: "Boys",
        teams: seedTeams(BOYS_TEAMS, "boys"),
        live: null,
        results: [],
        finalized: false,
      },
    },
  };
}

export function addTeam(state, divisionId, name, now) {
  const normalized = teamName(name);
  if (!normalized) throw new Error("Enter a team name.");
  const next = clone(state);
  const division = divisionOf(next, divisionId);
  if (division.teams.some((team) => team.name.toLocaleLowerCase() === normalized.toLocaleLowerCase())) {
    throw new Error("That team already exists in this division.");
  }
  division.teams.push({ id: makeId(`${divisionId}-team`), name: normalized });
  division.finalized = false;
  next.updatedAt = nowIso(now);
  return next;
}

export function removeTeam(state, divisionId, teamId, now) {
  const next = clone(state);
  const division = divisionOf(next, divisionId);
  if (!division.teams.some((team) => team.id === teamId)) throw new Error("Team not found.");
  const isLive = division.live && [division.live.teamAId, division.live.teamBId].includes(teamId);
  const hasResults = division.results.some((result) => [result.teamAId, result.teamBId].includes(teamId));
  if (isLive || hasResults) throw new Error("A team with a live or recorded match cannot be removed.");
  division.teams = division.teams.filter((team) => team.id !== teamId);
  next.updatedAt = nowIso(now);
  return next;
}

export function startMatch(state, divisionId, options, now = new Date()) {
  const next = clone(state);
  const division = divisionOf(next, divisionId);
  const teamAId = options.teamAId;
  const teamBId = options.teamBId;
  if (teamAId === teamBId) throw new Error("Select two different teams.");
  if (!division.teams.some((team) => team.id === teamAId) || !division.teams.some((team) => team.id === teamBId)) {
    throw new Error("Both teams must belong to this division.");
  }
  const targetPoints = [11, 15, 21].includes(Number(options.targetPoints)) ? Number(options.targetPoints) : 11;
  const winBy = Number(options.winBy) === 1 ? 1 : 2;
  const servingTeamId = [teamAId, teamBId].includes(options.servingTeamId) ? options.servingTeamId : teamAId;
  const startedAt = nowIso(now);
  division.live = {
    id: makeId(`${divisionId}-match`),
    teamAId,
    teamBId,
    courtOrder: [teamAId, teamBId],
    scores: { [teamAId]: 0, [teamBId]: 0 },
    targetPoints,
    winBy,
    servingTeamId,
    serverNumber: Number(options.serverNumber) === 1 ? 1 : 2,
    status: "live",
    winnerTeamId: null,
    startedAt,
    updatedAt: startedAt,
    undoStack: [],
  };
  division.finalized = false;
  next.updatedAt = startedAt;
  return next;
}

export function candidateWinner(live) {
  const a = live.scores[live.teamAId];
  const b = live.scores[live.teamBId];
  if (a >= live.targetPoints && a - b >= live.winBy) return live.teamAId;
  if (b >= live.targetPoints && b - a >= live.winBy) return live.teamBId;
  return null;
}

export function adjustLiveScore(state, divisionId, teamId, delta, now) {
  return mutateLive(state, divisionId, (live) => {
    if (![live.teamAId, live.teamBId].includes(teamId)) throw new Error("Team is not in the live match.");
    live.scores[teamId] = Math.max(0, live.scores[teamId] + Number(delta));
    live.updatedAt = nowIso(now);
  }, now);
}

export function setLiveServer(state, divisionId, teamId, now) {
  return mutateLive(state, divisionId, (live) => {
    if (![live.teamAId, live.teamBId].includes(teamId)) throw new Error("Serving team is not in the live match.");
    live.servingTeamId = teamId;
    live.updatedAt = nowIso(now);
  }, now);
}

export function setLiveServerNumber(state, divisionId, number, now) {
  return mutateLive(state, divisionId, (live) => {
    live.serverNumber = Number(number) === 1 ? 1 : 2;
    live.updatedAt = nowIso(now);
  }, now);
}

export function swapLiveCourt(state, divisionId, now) {
  return mutateLive(state, divisionId, (live) => {
    live.courtOrder = liveCourtOrder(live).reverse();
    live.updatedAt = nowIso(now);
  }, now);
}

export function resetLiveScore(state, divisionId, now) {
  return mutateLive(state, divisionId, (live) => {
    live.scores[live.teamAId] = 0;
    live.scores[live.teamBId] = 0;
    live.updatedAt = nowIso(now);
  }, now);
}

export function undoLive(state, divisionId, now) {
  const next = clone(state);
  const division = divisionOf(next, divisionId);
  if (!division.live?.undoStack.length) return state;
  const previous = division.live.undoStack.pop();
  const remainingUndo = division.live.undoStack;
  division.live = { ...previous, undoStack: remainingUndo };
  division.live.updatedAt = nowIso(now);
  next.updatedAt = nowIso(now);
  return next;
}

export function discardLiveMatch(state, divisionId, now) {
  const next = clone(state);
  divisionOf(next, divisionId).live = null;
  next.updatedAt = nowIso(now);
  return next;
}

export function recordLiveResult(state, divisionId, now = new Date()) {
  const next = clone(state);
  const division = divisionOf(next, divisionId);
  const live = division.live;
  if (!live) throw new Error("There is no live match to record.");
  const winnerTeamId = candidateWinner(live);
  if (!winnerTeamId) throw new Error("Neither team has met the configured win condition.");
  const completedAt = nowIso(now);
  division.results.push({
    id: live.id,
    teamAId: live.teamAId,
    teamBId: live.teamBId,
    scoreA: live.scores[live.teamAId],
    scoreB: live.scores[live.teamBId],
    winnerTeamId,
    completedAt,
  });
  division.live = null;
  division.finalized = false;
  next.updatedAt = completedAt;
  return next;
}

export function undoLastResult(state, divisionId, now) {
  const next = clone(state);
  const division = divisionOf(next, divisionId);
  if (!division.results.length) return state;
  division.results.pop();
  division.finalized = false;
  next.updatedAt = nowIso(now);
  return next;
}

function isSameMatch(match, teamAId, teamBId) {
  return Boolean(match) && (
    (match.teamAId === teamAId && match.teamBId === teamBId)
    || (match.teamAId === teamBId && match.teamBId === teamAId)
  );
}

function resultScoreFor(result, teamId) {
  return result.teamAId === teamId ? result.scoreA : result.scoreB;
}

export function getSchedule(state, divisionId) {
  const division = divisionOf(state, divisionId);
  const teamByName = new Map(division.teams.map((team) => [team.name.toLocaleLowerCase(), team]));
  return TOURNAMENT_SCHEDULES[divisionId].map(([teamAName, teamBName], index) => {
    const teamA = teamByName.get(teamAName.toLocaleLowerCase());
    const teamB = teamByName.get(teamBName.toLocaleLowerCase());
    const base = {
      gameNumber: index + 1,
      teamAId: teamA?.id ?? null,
      teamBId: teamB?.id ?? null,
      teamAName,
      teamBName,
      scoreA: null,
      scoreB: null,
      winnerTeamId: null,
      winnerName: "",
      status: "pending",
      notes: teamA && teamB ? "" : "Team unavailable",
    };
    if (!teamA || !teamB) return base;

    if (isSameMatch(division.live, teamA.id, teamB.id)) {
      return {
        ...base,
        scoreA: division.live.scores[teamA.id],
        scoreB: division.live.scores[teamB.id],
        winnerTeamId: division.live.winnerTeamId,
        winnerName: division.live.winnerTeamId ? division.teams.find((team) => team.id === division.live.winnerTeamId)?.name ?? "" : "",
        status: "live",
        notes: "Scoring now",
      };
    }

    const result = [...division.results].reverse().find((item) => isSameMatch(item, teamA.id, teamB.id));
    if (!result) return base;
    return {
      ...base,
      scoreA: resultScoreFor(result, teamA.id),
      scoreB: resultScoreFor(result, teamB.id),
      winnerTeamId: result.winnerTeamId,
      winnerName: division.teams.find((team) => team.id === result.winnerTeamId)?.name ?? "",
      status: "complete",
    };
  });
}

function rawRows(division) {
  const rows = new Map(division.teams.map((team) => [team.id, {
    id: team.id,
    name: team.name,
    played: 0,
    wins: 0,
    losses: 0,
    pointsFor: 0,
    pointsAgainst: 0,
    pointDiff: 0,
    h2hWins: 0,
    rank: null,
    tieBreakScore: null,
    award: "",
  }]));

  division.results.forEach((result) => {
    const a = rows.get(result.teamAId);
    const b = rows.get(result.teamBId);
    if (!a || !b) return;
    a.played += 1;
    b.played += 1;
    a.pointsFor += result.scoreA;
    a.pointsAgainst += result.scoreB;
    b.pointsFor += result.scoreB;
    b.pointsAgainst += result.scoreA;
    if (result.winnerTeamId === result.teamAId) {
      a.wins += 1;
      b.losses += 1;
    } else {
      b.wins += 1;
      a.losses += 1;
    }
  });
  rows.forEach((row) => { row.pointDiff = row.pointsFor - row.pointsAgainst; });
  return rows;
}

export function getStandings(state, divisionId) {
  const division = divisionOf(state, divisionId);
  const rows = rawRows(division);
  const tiedWinGroups = new Map();
  rows.forEach((row) => {
    if (!tiedWinGroups.has(row.wins)) tiedWinGroups.set(row.wins, new Set());
    tiedWinGroups.get(row.wins).add(row.id);
  });
  division.results.forEach((result) => {
    const winner = rows.get(result.winnerTeamId);
    const loserId = result.winnerTeamId === result.teamAId ? result.teamBId : result.teamAId;
    if (winner && tiedWinGroups.get(winner.wins)?.has(loserId)) winner.h2hWins += 1;
  });

  const sorted = [...rows.values()].sort((a, b) => {
    if (a.played === 0 && b.played > 0) return 1;
    if (b.played === 0 && a.played > 0) return -1;
    return b.wins - a.wins || b.h2hWins - a.h2hWins || b.pointDiff - a.pointDiff || b.pointsFor - a.pointsFor || a.name.localeCompare(b.name);
  });

  let previousKey = null;
  let previousRank = 0;
  sorted.forEach((row, index) => {
    if (!row.played) return;
    const key = `${row.wins}|${row.h2hWins}|${row.pointDiff}|${row.pointsFor}`;
    row.rank = key === previousKey ? previousRank : index + 1;
    previousKey = key;
    previousRank = row.rank;
    row.tieBreakScore = `${row.wins} / ${row.h2hWins} / ${row.pointDiff >= 0 ? "+" : ""}${row.pointDiff} / ${row.pointsFor}`;
  });

  if (division.finalized) {
    sorted.forEach((row) => {
      if (row.rank === 1) row.award = "CHAMPION";
      if (row.rank === 2) row.award = "RUNNER-UP";
      if (row.rank === 3) row.award = "3RD PLACE";
    });
  }
  return sorted;
}

export function setDivisionFinalized(state, divisionId, finalized, now) {
  const next = clone(state);
  const division = divisionOf(next, divisionId);
  if (finalized) {
    const standings = getStandings(next, divisionId);
    if (!division.results.length) throw new Error("Record at least one result before finalizing awards.");
    if (standings.filter((row) => row.rank === 1).length !== 1) {
      throw new Error("First place is still tied. Record a deciding result before finalizing awards.");
    }
  }
  division.finalized = Boolean(finalized);
  next.updatedAt = nowIso(now);
  return next;
}

export function serializeTournament(state) {
  return JSON.stringify({ version: TOURNAMENT_SCHEMA_VERSION, data: state });
}

function migrateLegacyTournament(data) {
  const migrated = clone(data);
  const existingNames = new Set(migrated.divisions.boys.teams.map((team) => team.name.toLocaleLowerCase()));
  seedTeams(BOYS_TEAMS, "boys").forEach((team) => {
    if (!existingNames.has(team.name.toLocaleLowerCase())) migrated.divisions.boys.teams.push(team);
  });
  migrated.schemaVersion = TOURNAMENT_SCHEMA_VERSION;
  return migrated;
}

export function deserializeTournament(raw) {
  if (!raw) return { ok: true, data: createTournament(), error: null, migrated: false };
  try {
    const parsed = JSON.parse(raw);
    const hasDivisions = parsed.data?.divisions?.girls && parsed.data?.divisions?.boys;
    const valid = parsed.version === TOURNAMENT_SCHEMA_VERSION
      && parsed.data?.schemaVersion === TOURNAMENT_SCHEMA_VERSION
      && hasDivisions;
    if (valid) return { ok: true, data: parsed.data, error: null, migrated: false };
    const legacy = parsed.version === 1 && parsed.data?.schemaVersion === 1 && hasDivisions;
    if (legacy) return { ok: true, data: migrateLegacyTournament(parsed.data), error: null, migrated: true };
    return { ok: false, data: createTournament(), error: "unsupported", migrated: false };
  } catch {
    return { ok: false, data: createTournament(), error: "corrupt", migrated: false };
  }
}
