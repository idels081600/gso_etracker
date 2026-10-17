import {
  createTournament, getSchedule, getScoreboards, getStandings, liveCourtOrder, participantOptions,
} from "./tournament-engine.mjs";
import { fetchTournamentState, normalizeAccessCode, performTournamentAction, waitForTournamentState } from "./tournament-sync.mjs";

const GAME_MASTER_KEY = "pickleball.gameMasterAccess.v1";
const HEADER_MINIMIZED_KEY = "pickleball.headerMinimized.v1";
const $ = (selector) => document.querySelector(selector);
const app = $("#tournament-app");
const scoreboardDialog = $("#scoreboard-dialog");
const matchDialog = $("#match-dialog");
const scheduleDialog = $("#schedule-dialog");
const standingsDialog = $("#standings-dialog");
const confirmDialog = $("#confirm-dialog");
const roleDialog = $("#role-dialog");
const editResultDialog = $("#edit-result-dialog");
let state = createTournament();
let accessCode = normalizeAccessCode(sessionStorage.getItem(GAME_MASTER_KEY));
let authenticated = false;
let actionInFlight = false;
let confirmCallback = null;

function setHeaderMinimized(minimized) {
  document.body.classList.toggle("header-minimized", minimized);
  const toggle = $("#header-toggle");
  toggle.setAttribute("aria-expanded", String(!minimized));
  toggle.textContent = minimized ? "Expand" : "Minimize";
  toggle.title = minimized ? "Expand tournament navigation" : "Minimize tournament navigation";
  localStorage.setItem(HEADER_MINIMIZED_KEY, minimized ? "1" : "0");
}

const divisionLabel = (id) => ({ girls: "Girls", boys: "Boys", mixed: "Mixed Doubles" }[id] ?? "Division");
const formatLabel = (type) => type === "singles" ? "Singles" : "Doubles";
const competitionLabel = (id, type) => id === "mixed" && type === "doubles" ? "Mixed Doubles" : `${divisionLabel(id)} ${formatLabel(type)}`;
const escapeHtml = (value) => String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
const division = (id) => state.divisions[id];
const board = (id) => getScoreboards(state).find((item) => item.id === id);

function toast(message, error = false) { const el = document.createElement("div"); el.className = `toast ${error ? "error" : ""}`; el.textContent = message; $("#toast-region").append(el); setTimeout(() => el.remove(), 3400); }
function announce(message) { $("#score-announcer").textContent = ""; setTimeout(() => { $("#score-announcer").textContent = message; }, 20); }
function roleStatus(message, connected = false) { $("#saved-state-label").textContent = message; $("#saved-state-label").classList.toggle("connected", connected); }

function setAuthenticated(value) {
  authenticated = value;
  $("#game-master-sign-out").hidden = !value;
  $("#add-scoreboard-button").disabled = !value;
  $("#schedules-button").disabled = !value;
  roleStatus(value ? "Game Master / Live tournament" : "Game Master sign-in required", value);
}

async function signIn(code) {
  const normalized = normalizeAccessCode(code);
  if (!normalized) throw new Error("Enter the Game Master access code.");
  roleStatus("Signing in…");
  const remote = await fetchTournamentState(normalized, globalThis.fetch, "game_master");
  accessCode = normalized;
  state = remote ?? createTournament();
  sessionStorage.setItem(GAME_MASTER_KEY, accessCode);
  setAuthenticated(true);
  if (roleDialog.open) roleDialog.close();
  render();
}

async function runAction(action, payload, message = "") {
  if (!authenticated || actionInFlight) return null;
  actionInFlight = true;
  roleStatus("Saving tournament change…", true);
  try {
    const result = await performTournamentAction(state, accessCode, action, payload);
    if (result.conflict) {
      state = result.state;
      render();
      throw new Error("The tournament changed on another device. Review the latest state and try again.");
    }
    state = result.state;
    render();
    roleStatus("Game Master / Live tournament", true);
    if (message) announce(message);
    return state;
  } finally {
    actionInFlight = false;
  }
}

function participantName(currentBoard, id) {
  return currentBoard.live?.participantNames?.[id]
    ?? participantOptions(state, currentBoard.divisionId, currentBoard.matchType).find((item) => item.id === id)?.name
    ?? "Participant";
}

function readOnlySide(currentBoard, id, position) {
  const live = currentBoard.live;
  const serving = live.servingTeamId === id;
  const name = participantName(currentBoard, id);
  return `<div class="compact-score-side read-only-score-side ${serving ? "is-serving" : ""}"><div class="compact-team-head"><div><span>Court ${position}</span><strong>${escapeHtml(name)}</strong></div><button class="live-serving-badge ${serving ? "active" : ""}" data-action="master-serve" data-board="${currentBoard.id}" data-team="${id}" aria-pressed="${serving}">${serving ? "Serving" : "Set serve"}</button></div><div class="game-master-score-controls"><button data-action="master-score" data-board="${currentBoard.id}" data-team="${id}" data-delta="-1" aria-label="Subtract one point from ${escapeHtml(name)}">−</button><div class="game-master-score">${live.scores[id]}</div><button class="add" data-action="master-score" data-board="${currentBoard.id}" data-team="${id}" data-delta="1" aria-label="Add one point to ${escapeHtml(name)}">+</button></div></div>`;
}

function boardMarkup(currentBoard) {
  const meta = competitionLabel(currentBoard.divisionId, currentBoard.matchType);
  if (!currentBoard.live) return `<section class="scoreboard-panel division-${currentBoard.divisionId}"><header class="quadrant-header"><div><span>${meta}</span><h2>${escapeHtml(currentBoard.name)}</h2></div><div class="header-actions"><button class="mini-button" data-action="view-standings" data-board="${currentBoard.id}">Rankings</button><button class="mini-button danger-text" data-action="remove-board" data-board="${currentBoard.id}">Remove court</button></div></header><div class="quadrant-empty scoring-empty"><div class="empty-score-mark">0<span>–</span>0</div><strong>Ready for the Game Master</strong><p>Select the matchup and hand live scoring to a Remote Scorer.</p><button class="button button-primary" data-action="new-match" data-board="${currentBoard.id}">Start game</button></div></section>`;
  const [leftId, rightId] = liveCourtOrder(currentBoard.live);
  const live = currentBoard.live;
  return `<section class="scoreboard-panel division-${currentBoard.divisionId}"><header class="quadrant-header"><div><span>${meta} / To ${live.targetPoints}, win by ${live.winBy}</span><h2>${escapeHtml(currentBoard.name)}</h2></div><div class="header-actions"><button class="mini-button" data-action="view-standings" data-board="${currentBoard.id}">Rankings</button><button class="mini-button danger-text" data-action="cancel-match" data-board="${currentBoard.id}">Cancel game</button></div></header><div class="compact-scoreboard game-master-live">${readOnlySide(currentBoard, leftId, "Left")}${readOnlySide(currentBoard, rightId, "Right")}</div><footer class="game-master-live-footer"><span class="live-dot" aria-hidden="true"></span><strong>Shared live controls</strong><small>Changes sync across devices.</small><button class="mini-button" data-action="master-swap" data-board="${currentBoard.id}">⇄ Swap sides</button></footer></section>`;
}

function render() {
  const boards = getScoreboards(state);
  const live = boards.filter((item) => item.live).length;
  app.innerHTML = `<div class="scoreboard-workspace-bar"><div><span>${boards.length} courts</span><strong>${live} live now</strong></div><div><button class="mini-button" data-action="show-schedules">Schedules</button><button class="button button-primary" data-action="add-board">+ Add court</button></div></div>${boards.length ? `<div class="dynamic-scoreboard-grid">${boards.map(boardMarkup).join("")}</div>` : `<section class="scoreboards-empty"><div class="empty-score-mark">0<span>–</span>0</div><h1>No courts configured</h1><p>Add a court, select its division, then start the first game.</p><button class="button button-primary" data-action="add-board">+ Add first court</button></section>`}`;
  if (scheduleDialog.open) renderSchedule(scheduleDialog.dataset.division || "girls");
  if (standingsDialog.open) renderStandings(standingsDialog.dataset.division, standingsDialog.dataset.matchType);
}

function syncScoreboardMatchTypes() {
  const isMixed = scoreboardDialog.querySelector('input[name="divisionId"]:checked')?.value === "mixed";
  const singles = scoreboardDialog.querySelector('input[name="matchType"][value="singles"]');
  singles.disabled = isMixed;
  singles.closest("label").classList.toggle("is-disabled", isMixed);
  if (isMixed) scoreboardDialog.querySelector('input[name="matchType"][value="doubles"]').checked = true;
}

function openBoardSetup() { $("#scoreboard-name").value = ""; syncScoreboardMatchTypes(); scoreboardDialog.showModal(); $("#scoreboard-name").focus(); }

function openMatch(boardId, selectedA = null, selectedB = null) {
  const currentBoard = board(boardId);
  if (!currentBoard || currentBoard.live) return;
  const participants = participantOptions(state, currentBoard.divisionId, currentBoard.matchType);
  if (participants.length < 2) { toast("This division needs at least two participants.", true); return; }
  $("#match-scoreboard").value = boardId;
  $("#match-dialog-kicker").textContent = `${currentBoard.name} / ${competitionLabel(currentBoard.divisionId, currentBoard.matchType)}`;
  $("#match-side-a-label").textContent = currentBoard.matchType === "singles" ? "Left player" : "Left team";
  $("#match-side-b-label").textContent = currentBoard.matchType === "singles" ? "Right player" : "Right team";
  const options = participants.map((item) => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join("");
  $("#match-team-a").innerHTML = options; $("#match-team-b").innerHTML = options;
  if (selectedA) $("#match-team-a").value = selectedA;
  if (selectedB) $("#match-team-b").value = selectedB; else $("#match-team-b").selectedIndex = 1;
  updateServingOptions(); matchDialog.showModal();
}

function updateServingOptions() {
  const currentBoard = board($("#match-scoreboard").value); if (!currentBoard) return;
  const names = new Map(participantOptions(state, currentBoard.divisionId, currentBoard.matchType).map((item) => [item.id, item.name]));
  const ids = [$("#match-team-a").value, $("#match-team-b").value];
  $("#match-serving-team").innerHTML = ids.filter((id, index) => id && ids.indexOf(id) === index).map((id) => `<option value="${id}">${escapeHtml(names.get(id))}</option>`).join("");
}

function scheduleRows(id) {
  return getSchedule(state, id).map((row) => `<tr class="schedule-row status-${row.status}"><td>${row.gameNumber}</td><td>${escapeHtml(row.teamAName)}</td><td>${row.scoreA ?? "—"}</td><td>${escapeHtml(row.teamBName)}</td><td>${row.scoreB ?? "—"}</td><td>${escapeHtml(row.winnerName) || "—"}</td><td>${row.status === "pending" ? `<button class="schedule-status status-pending" data-action="start-scheduled" data-division="${id}" data-team-a="${row.teamAId ?? ""}" data-team-b="${row.teamBId ?? ""}" ${row.teamAId && row.teamBId ? "" : "disabled"}>Start game</button>` : `<span class="schedule-status status-${row.status}">${row.status === "live" ? "Live" : "Complete"}</span>`}</td><td>${escapeHtml(row.notes) || "—"}</td></tr>`).join("");
}

function renderSchedule(id) { scheduleDialog.dataset.division = id; $("#schedule-dialog-title").textContent = `${competitionLabel(id, "doubles")} schedule`; $("#schedule-modal-body").innerHTML = scheduleRows(id); document.querySelectorAll("[data-schedule-division]").forEach((el) => el.setAttribute("aria-selected", String(el.dataset.scheduleDivision === id))); }
function openSchedule(id = "girls") { renderSchedule(id); scheduleDialog.showModal(); }

function resultName(id, result, teamId) { return result.participantNames?.[teamId] ?? division(id).teams.find((team) => team.id === teamId)?.name ?? "Participant"; }
function standingsRows(id, type) {
  const standings = getStandings(state, id, type);
  return standings.map((row, index) => {
    const rank = row.rank ?? index + 1;
    const rankClass = rank === 1 ? " rank-first" : rank === 2 ? " rank-second" : rank === 3 ? " rank-third" : "";
    return `<tr class="leaderboard-row${rankClass}"><td class="rank-cell"><strong>${String(rank).padStart(2, "0")}</strong></td><td class="team-cell"><strong>${escapeHtml(row.name)}</strong></td><td class="score-cell"><strong>${row.pointsFor}</strong><small>points</small></td></tr>`;
  }).join("");
}
function detailedStandingsRows(id, type) {
  return getStandings(state, id, type).map((row) => {
    const diff = `${row.pointDiff > 0 ? "+" : ""}${row.pointDiff}`;
    return `<tr><td class="rank-cell">${row.rank ?? "—"}</td><td class="team-cell"><strong>${escapeHtml(row.name)}</strong><small>${row.pointsFor} PF · ${row.pointsAgainst} PA · ${row.h2hWins} H2H</small></td><td>${row.played}</td><td>${row.wins}</td><td>${row.losses}</td><td class="${row.pointDiff > 0 ? "diff-positive" : row.pointDiff < 0 ? "diff-negative" : "diff-zero"}">${diff}</td><td class="tiebreak-cell">${row.tieBreakScore ?? "—"}</td></tr>`;
  }).join("");
}
function completedResultMarkup(id, result) {
  const nameA = resultName(id, result, result.teamAId), nameB = resultName(id, result, result.teamBId);
  const corrected = result.correctedAt ? '<span class="corrected-badge">Corrected</span>' : "";
  return `<article class="completed-result-row"><div><span>${escapeHtml(result.scoreboardName || "Recorded game")} ${corrected}</span><strong>${escapeHtml(nameA)} <b>${result.scoreA}–${result.scoreB}</b> ${escapeHtml(nameB)}</strong><small>To ${result.targetPoints ?? 11}, win by ${result.winBy ?? 2}</small></div><button class="mini-button" data-action="edit-result" data-division="${id}" data-result="${result.id}">Edit score</button></article>`;
}
function renderStandings(id, type = "doubles", view = standingsDialog.dataset.view || "styled") {
  const results = division(id).results.filter((result) => (result.matchType ?? "doubles") === type).slice().reverse();
  const rankedCount = getStandings(state, id, type).length;
  const detailed = view === "detailed";
  standingsDialog.dataset.division = id; standingsDialog.dataset.matchType = type; standingsDialog.dataset.view = view;
  standingsDialog.dataset.rankedCount = rankedCount;
  standingsDialog.className = `app-dialog standings-modal division-${id} ${detailed ? "is-detailed" : "is-styled"}`;
  $("#standings-dialog-kicker").textContent = detailed ? "Detailed tournament statistics" : `${competitionLabel(id, type)} Tournament Rankings`;
  if (detailed) {
    $("#standings-dialog-title").textContent = `${competitionLabel(id, type)} Rankings`;
    $("#standings-dialog-title").removeAttribute("aria-label");
  } else {
    $("#standings-dialog-title").innerHTML = '<span class="ranking-title-official">Official</span><span class="ranking-title-main">Ranking</span>';
    $("#standings-dialog-title").setAttribute("aria-label", "Official ranking");
  }
  document.querySelectorAll("[data-standings-view]").forEach((button) => button.setAttribute("aria-pressed", String(button.dataset.standingsView === view)));
  $("#standings-table-columns").innerHTML = detailed
    ? '<col class="detail-rank"><col class="detail-team"><col class="detail-stat"><col class="detail-stat"><col class="detail-stat"><col class="detail-diff"><col class="detail-tiebreak">'
    : '<col class="poster-rank"><col class="poster-team"><col class="poster-points">';
  $("#standings-table-heading").innerHTML = detailed
    ? `<th>Rank</th><th id="standings-participant-heading">${type === "singles" ? "Player" : "Team"}</th><th>Played</th><th>Wins</th><th>Losses</th><th>Point diff.</th><th>Tie-break <small>Wins / H2H / Diff / PF</small></th>`
    : `<th>Rank</th><th id="standings-participant-heading">${type === "singles" ? "Player" : "Team"}</th><th>Points</th>`;
  $("#standings-modal-body").innerHTML = detailed ? detailedStandingsRows(id, type) : standingsRows(id, type);
  $("#standings-modal-summary").innerHTML = detailed
    ? `<span>${formatLabel(type)}</span><strong>Standings details</strong><b>${results.length ? `${results.length} completed` : "No results yet"}</b>`
    : `<span>${formatLabel(type)}</span><strong>Live standings</strong><b>${results.length ? `${results.length} completed` : "No results yet"}</b>`;
  $("#completed-results-count").textContent = `${results.length} ${results.length === 1 ? "result" : "results"}`;
  $("#completed-results-list").innerHTML = results.length ? results.map((result) => completedResultMarkup(id, result)).join("") : '<p class="completed-results-empty">Completed games will appear here after a Remote Scorer submits them.</p>';
}
function openStandings(id, type) { renderStandings(id, type, "styled"); standingsDialog.showModal(); }

function openResultEditor(id, resultId) {
  const result = division(id).results.find((item) => item.id === resultId); if (!result) return;
  const nameA = resultName(id, result, result.teamAId), nameB = resultName(id, result, result.teamBId);
  $("#edit-result-division").value = id; $("#edit-result-id").value = resultId;
  $("#edit-result-team-a").textContent = nameA; $("#edit-result-team-b").textContent = nameB;
  $("#edit-result-score-a").value = result.scoreA; $("#edit-result-score-b").value = result.scoreB;
  $("#edit-result-matchup").textContent = `${nameA} against ${nameB}`;
  $("#edit-result-rule").textContent = `Original rule: first to ${result.targetPoints ?? 11}, win by ${result.winBy ?? 2}.`;
  editResultDialog.showModal(); $("#edit-result-score-a").focus();
}

function confirmAction(title, message, label, callback) { $("#confirm-title").textContent = title; $("#confirm-message").textContent = message; $("#confirm-action").textContent = label; confirmCallback = callback; confirmDialog.showModal(); }

app.addEventListener("click", (event) => {
  const el = event.target.closest("button[data-action]"); if (!el || !authenticated) return;
  const action = el.dataset.action, id = el.dataset.board;
  try {
    if (action === "add-board") openBoardSetup();
    if (action === "show-schedules") openSchedule();
    if (action === "new-match") openMatch(id);
    if (action === "view-standings") openStandings(board(id).divisionId, board(id).matchType);
    if (action === "master-score") void runAction("adjust_score", { scoreboardId: id, participantId: el.dataset.team, delta: Number(el.dataset.delta) }, "Score updated.").catch((error) => toast(error.message, true));
    if (action === "master-serve") void runAction("set_server", { scoreboardId: id, participantId: el.dataset.team }, `${participantName(board(id), el.dataset.team)} is serving.`).catch((error) => toast(error.message, true));
    if (action === "master-swap") void runAction("swap_court", { scoreboardId: id }, "Court sides swapped.").catch((error) => toast(error.message, true));
    if (action === "cancel-match") confirmAction("Cancel this live game?", "The unsubmitted score will be discarded. Only the Game Master can restart it.", "Cancel game", () => runAction("cancel_match", { scoreboardId: id }, "Live game cancelled."));
    if (action === "remove-board") confirmAction(`Remove ${board(id).name}?`, "This removes the idle court scoreboard. Recorded results stay in the rankings.", "Remove court", () => runAction("remove_scoreboard", { scoreboardId: id }, "Court removed."));
  } catch (error) { toast(error.message, true); }
});

$("#add-scoreboard-button").addEventListener("click", openBoardSetup);
$("#schedules-button").addEventListener("click", () => openSchedule());
scoreboardDialog.querySelectorAll('input[name="divisionId"]').forEach((input) => input.addEventListener("change", syncScoreboardMatchTypes));
$("#scoreboard-form").addEventListener("submit", async (event) => { event.preventDefault(); const data = new FormData(event.target); try { await runAction("create_scoreboard", { name: data.get("name"), divisionId: data.get("divisionId"), matchType: data.get("matchType") }, "Court added."); scoreboardDialog.close(); const added = getScoreboards(state).at(-1); if (added) openMatch(added.id); } catch (error) { toast(error.message, true); } });
$("#match-form").addEventListener("change", (event) => { if (["match-team-a", "match-team-b"].includes(event.target.id)) updateServingOptions(); });
$("#match-form").addEventListener("submit", async (event) => { event.preventDefault(); const data = new FormData(event.target); try { await runAction("start_match", { scoreboardId: data.get("scoreboardId"), teamAId: data.get("teamAId"), teamBId: data.get("teamBId"), targetPoints: Number(data.get("targetPoints")), winBy: Number(data.get("winBy")), servingTeamId: data.get("servingTeamId"), serverNumber: 2 }, "Game started for the Remote Scorer."); matchDialog.close(); } catch (error) { toast(error.message, true); } });
document.querySelectorAll("[data-schedule-division]").forEach((el) => el.addEventListener("click", () => renderSchedule(el.dataset.scheduleDivision)));
$("#schedule-modal-body").addEventListener("click", async (event) => { const el = event.target.closest('[data-action="start-scheduled"]'); if (!el) return; try { let target = getScoreboards(state).find((item) => item.divisionId === el.dataset.division && item.matchType === "doubles" && !item.live); if (!target) { await runAction("create_scoreboard", { divisionId: el.dataset.division, matchType: "doubles" }, "Court added."); target = getScoreboards(state).at(-1); } scheduleDialog.close(); openMatch(target.id, el.dataset.teamA, el.dataset.teamB); } catch (error) { toast(error.message, true); } });
$("#completed-results-list").addEventListener("click", (event) => { const el = event.target.closest('[data-action="edit-result"]'); if (el) openResultEditor(el.dataset.division, el.dataset.result); });
document.querySelectorAll("[data-standings-view]").forEach((button) => button.addEventListener("click", () => renderStandings(standingsDialog.dataset.division, standingsDialog.dataset.matchType, button.dataset.standingsView)));
$("#edit-result-form").addEventListener("submit", async (event) => { event.preventDefault(); const data = new FormData(event.target); try { await runAction("edit_result", { divisionId: data.get("divisionId"), resultId: data.get("resultId"), scoreA: Number(data.get("scoreA")), scoreB: Number(data.get("scoreB")) }, "Final score corrected and rankings recalculated."); editResultDialog.close(); renderStandings(data.get("divisionId"), standingsDialog.dataset.matchType || "doubles"); } catch (error) { toast(error.message, true); } });
document.querySelectorAll("[data-close-dialog]").forEach((el) => el.addEventListener("click", () => el.closest("dialog").close()));
$("#confirm-action").addEventListener("click", async () => { const callback = confirmCallback; confirmCallback = null; confirmDialog.close(); try { await callback?.(); } catch (error) { toast(error.message, true); } });
$("#role-form").addEventListener("submit", async (event) => { event.preventDefault(); try { await signIn(new FormData(event.target).get("accessCode")); } catch (error) { roleStatus("Game Master sign-in required"); toast(error.message, true); } });
roleDialog.addEventListener("cancel", (event) => event.preventDefault());
$("#game-master-sign-out").addEventListener("click", () => { accessCode = ""; sessionStorage.removeItem(GAME_MASTER_KEY); setAuthenticated(false); if (!roleDialog.open) roleDialog.showModal(); $("#role-access-code").value = ""; $("#role-access-code").focus(); });
async function toggleFullscreen() {
  if (!document.fullscreenElement) await document.documentElement.requestFullscreen(); else await document.exitFullscreen();
  syncFullscreenLabels();
  setTimeout(syncFullscreenLabels, 250);
}
function syncFullscreenLabels() {
  const label = document.fullscreenElement ? "Exit full screen" : "Full screen";
  $("#fullscreen-button").textContent = label;
  $("#standings-fullscreen-button").textContent = label;
}
$("#fullscreen-button").addEventListener("click", toggleFullscreen);
$("#standings-fullscreen-button").addEventListener("click", toggleFullscreen);
document.addEventListener("fullscreenchange", syncFullscreenLabels);
$("#header-toggle").addEventListener("click", () => setHeaderMinimized(!document.body.classList.contains("header-minimized")));

const shortPause = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
async function watchTournament() {
  while (true) {
    if (!authenticated || actionInFlight || document.querySelector("dialog[open]:not(#standings-dialog):not(#schedule-dialog)")) { await shortPause(100); continue; }
    try {
      const remote = await waitForTournamentState(accessCode, state.updatedAt, globalThis.fetch, "game_master");
      if (authenticated && remote && remote.updatedAt > state.updatedAt) { state = remote; render(); }
      if (authenticated) roleStatus("Game Master / Live tournament", true);
    } catch {
      if (authenticated) roleStatus("Tournament connection offline");
      await shortPause(600);
    }
  }
}

setHeaderMinimized(localStorage.getItem(HEADER_MINIMIZED_KEY) === "1");
setAuthenticated(false); render();
if (accessCode) signIn(accessCode).catch(() => { accessCode = ""; sessionStorage.removeItem(GAME_MASTER_KEY); setAuthenticated(false); roleDialog.showModal(); });
else roleDialog.showModal();
void watchTournament();
