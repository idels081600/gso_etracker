import {
  addScoreboard, addTeam, adjustScoreboardScore, createTournament, deserializeTournament,
  discardScoreboardMatch, getSchedule, getScoreboards, getStandings, liveCourtOrder,
  participantOptions, recordScoreboardResult, removeScoreboard, removeTeam, resetScoreboard,
  serializeTournament, setDivisionFinalized, setScoreboardServer, setScoreboardServerNumber,
  startScoreboardMatch, swapScoreboardCourt, teamPlayerNames, undoLastResultByType,
  undoScoreboard,
} from "./tournament-engine.mjs";
import { fetchTournamentState, isStateNewer, normalizeAccessCode, saveTournamentState } from "./tournament-sync.mjs";

const STORAGE_KEY = "pickleball.tournamentBoard.v2";
const LEGACY_STORAGE_KEY = "pickleball.tournamentBoard.v1";
const SYNC_ACCESS_KEY = "pickleball.tournamentSyncAccess.v1";
const SYNC_POLL_MS = 900;
const $ = (selector) => document.querySelector(selector);
const app = $("#tournament-app");
const scoreboardDialog = $("#scoreboard-dialog");
const matchDialog = $("#match-dialog");
const teamDialog = $("#team-dialog");
const scheduleDialog = $("#schedule-dialog");
const standingsDialog = $("#standings-dialog");
const confirmDialog = $("#confirm-dialog");
const winnerDialog = $("#winner-dialog");
const syncDialog = $("#sync-dialog");
let hadStoredState = false;
let state = loadState();
let confirmCallback = null;
let syncAccessCode = normalizeAccessCode(localStorage.getItem(SYNC_ACCESS_KEY));
let syncWriting = false;
let syncQueued = false;

function escapeHtml(value) { return String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;"); }
function loadState() {
  try {
    const current = localStorage.getItem(STORAGE_KEY);
    const legacy = current === null ? localStorage.getItem(LEGACY_STORAGE_KEY) : null;
    hadStoredState = current !== null || legacy !== null;
    const result = deserializeTournament(current ?? legacy);
    if (!result.ok) localStorage.removeItem(STORAGE_KEY);
    if (result.migrated) {
      localStorage.setItem(STORAGE_KEY, serializeTournament(result.data));
      localStorage.removeItem(LEGACY_STORAGE_KEY);
      setTimeout(() => toast("Existing live matches were moved into court scoreboards."), 0);
    }
    return result.data;
  } catch { return createTournament(); }
}
function persistLocal() { try { localStorage.setItem(STORAGE_KEY, serializeTournament(state)); } catch { toast("Unable to save in this browser.", true); } }
function persist() { persistLocal(); queueRemoteSave(); }
function apply(next, message = "") { state = next; persist(); render(); if (message) announce(message); }
function toast(message, error = false) { const el = document.createElement("div"); el.className = `toast ${error ? "error" : ""}`; el.textContent = message; $("#toast-region").append(el); setTimeout(() => el.remove(), 3400); }
function announce(message) { $("#score-announcer").textContent = ""; setTimeout(() => { $("#score-announcer").textContent = message; }, 20); }
function syncStatus(message, connected = false) { $("#saved-state-label").textContent = message; $("#sync-settings-button").textContent = connected ? "Phone connected" : "Connect phone"; }
async function flushRemoteSave({ throwOnError = false } = {}) {
  if (!syncAccessCode || syncWriting || !syncQueued) return;
  syncWriting = true; syncQueued = false;
  try {
    const result = await saveTournamentState(state, syncAccessCode);
    if (result.conflict && isStateNewer(result.state, state)) { state = result.state; persistLocal(); render(); }
    syncStatus("Saved online · Phone sync active", true);
  } catch (error) { syncStatus("Saved locally · Phone sync offline"); if (throwOnError) throw error; }
  finally { syncWriting = false; if (syncQueued) void flushRemoteSave(); }
}
function queueRemoteSave() { if (syncAccessCode) { syncQueued = true; void flushRemoteSave(); } }
async function reconcileRemote({ initial = false } = {}) {
  if (!syncAccessCode || syncWriting || (!initial && document.querySelector("dialog[open]:not(#standings-dialog):not(#schedule-dialog)"))) return;
  try {
    const remote = await fetchTournamentState(syncAccessCode);
    if (remote === null) { syncQueued = true; await flushRemoteSave({ throwOnError: initial }); return; }
    if ((initial && !hadStoredState) || isStateNewer(remote, state)) { state = remote; persistLocal(); render(); }
    else if (isStateNewer(state, remote)) { syncQueued = true; await flushRemoteSave(); }
    syncStatus("Saved online · Phone sync active", true);
  } catch (error) { syncStatus("Saved locally · Phone sync offline"); if (initial) throw error; }
}

const divisionLabel = (id) => id === "girls" ? "Girls" : "Boys";
const formatLabel = (type) => type === "singles" ? "Singles" : "Doubles";
const division = (id) => state.divisions[id];
const board = (id) => getScoreboards(state).find((item) => item.id === id);
function participantName(currentBoard, id) {
  return currentBoard.live?.participantNames?.[id] ?? participantOptions(state, currentBoard.divisionId, currentBoard.matchType).find((item) => item.id === id)?.name ?? "Participant";
}
function sideMarkup(currentBoard, id, position) {
  const live = currentBoard.live;
  const name = participantName(currentBoard, id);
  const serving = live.servingTeamId === id;
  const serverName = currentBoard.matchType === "singles" ? name : teamPlayerNames(name)[live.serverNumber - 1];
  return `<div class="compact-score-side side-${id === live.teamAId ? "a" : "b"} ${serving ? "is-serving" : ""}">
    <div class="compact-team-head"><div><span>Court ${position}</span><strong>${escapeHtml(name)}</strong></div><button class="serve-toggle ${serving ? "active" : ""}" type="button" data-action="set-server" data-board="${currentBoard.id}" data-team="${id}">${serving ? `<span class="serve-player-name">${escapeHtml(serverName)}</span><span class="serve-player-state">Serving</span>` : "Set serve"}</button></div>
    <div class="compact-score-controls"><button type="button" data-action="score" data-board="${currentBoard.id}" data-team="${id}" data-delta="-1">−</button><strong>${live.scores[id]}</strong><button class="add" type="button" data-action="score" data-board="${currentBoard.id}" data-team="${id}" data-delta="1">+</button></div></div>`;
}
function boardMarkup(currentBoard) {
  const meta = `${divisionLabel(currentBoard.divisionId)} · ${formatLabel(currentBoard.matchType)}`;
  if (!currentBoard.live) return `<section class="scoreboard-panel division-${currentBoard.divisionId}"><header class="quadrant-header"><div><span>${meta}</span><h2>${escapeHtml(currentBoard.name)}</h2></div><div class="header-actions"><button class="mini-button" data-action="view-standings" data-board="${currentBoard.id}">Standings</button><button class="mini-button danger-text" data-action="remove-board" data-board="${currentBoard.id}">Remove</button></div></header><div class="quadrant-empty scoring-empty"><div class="empty-score-mark">0<span>–</span>0</div><strong>Ready for a ${currentBoard.matchType} match</strong><p>Select two ${currentBoard.matchType === "singles" ? "players" : "teams"} to begin.</p><button class="button button-primary" data-action="new-match" data-board="${currentBoard.id}">Start match</button></div></section>`;
  const live = currentBoard.live;
  const [leftId, rightId] = liveCourtOrder(live);
  const receiver = live.servingTeamId === live.teamAId ? live.teamBId : live.teamAId;
  const call = `${live.scores[live.servingTeamId]} – ${live.scores[receiver]}${currentBoard.matchType === "doubles" ? ` – ${live.serverNumber}` : ""}`;
  const servingName = participantName(currentBoard, live.servingTeamId);
  const servers = currentBoard.matchType === "doubles" ? teamPlayerNames(servingName) : [servingName];
  const winner = live.winnerTeamId ? participantName(currentBoard, live.winnerTeamId) : "";
  return `<section class="scoreboard-panel division-${currentBoard.divisionId}"><header class="quadrant-header"><div><span>${meta} · To ${live.targetPoints}, win by ${live.winBy}</span><h2>${escapeHtml(currentBoard.name)}</h2></div><div class="header-actions"><button class="mini-button" data-action="undo-board" data-board="${currentBoard.id}" ${live.undoStack.length ? "" : "disabled"}>Undo</button><button class="mini-button" data-action="swap-board" data-board="${currentBoard.id}">⇄ Swap</button><button class="mini-button" data-action="new-match" data-board="${currentBoard.id}">New</button></div></header>
    <div class="compact-scoreboard">${sideMarkup(currentBoard, leftId, "Left")}${sideMarkup(currentBoard, rightId, "Right")}</div>
    <footer class="compact-score-footer dynamic-score-footer"><div class="score-call-compact"><span>Score call</span><strong>${call}</strong></div><fieldset class="inline-server-choice"><legend>Serving player</legend>${servers.map((name, index) => `<label><input type="radio" name="${currentBoard.id}Server" value="${index + 1}" data-action="server-number" data-board="${currentBoard.id}" ${live.serverNumber === index + 1 ? "checked" : ""} ${currentBoard.matchType === "singles" ? "disabled" : ""}><span><strong>${escapeHtml(name)}</strong><small>${live.serverNumber === index + 1 ? "Serving" : "Select"}</small></span></label>`).join("")}</fieldset><button class="mini-button" data-action="reset-board" data-board="${currentBoard.id}">Reset</button><button class="mini-button danger-text" data-action="end-board" data-board="${currentBoard.id}">End</button><button class="button ${winner ? "button-primary" : "button-secondary"}" data-action="record-board" data-board="${currentBoard.id}" ${winner ? "" : "disabled"}>${winner ? `Record ${escapeHtml(winner)} win` : "Awaiting winner"}</button></footer></section>`;
}
function render() {
  const boards = getScoreboards(state);
  const live = boards.filter((item) => item.live).length;
  app.innerHTML = `<div class="scoreboard-workspace-bar"><div><span>${boards.length} scoreboards</span><strong>${live} live now</strong></div><div><button class="mini-button" data-action="show-schedules">Schedules</button><button class="button button-primary" data-action="add-board">+ Add scoreboard</button></div></div>${boards.length ? `<div class="dynamic-scoreboard-grid">${boards.map(boardMarkup).join("")}</div>` : `<section class="scoreboards-empty"><div class="empty-score-mark">0<span>–</span>0</div><h1>No scoreboards yet</h1><p>Add a court, choose Boys or Girls and Singles or Doubles, then start scoring.</p><button class="button button-primary" data-action="add-board">+ Add first scoreboard</button></section>`}`;
  if (scheduleDialog.open) renderSchedule(scheduleDialog.dataset.division || "girls");
  if (standingsDialog.open) renderStandings(standingsDialog.dataset.division, standingsDialog.dataset.matchType);
}

function openBoardSetup() { $("#scoreboard-name").value = ""; scoreboardDialog.showModal(); $("#scoreboard-name").focus(); }
function openMatch(boardId, selectedA = null, selectedB = null) {
  const currentBoard = board(boardId);
  const participants = participantOptions(state, currentBoard.divisionId, currentBoard.matchType);
  if (participants.length < 2) { toast("Add at least two participants first.", true); return; }
  $("#match-scoreboard").value = boardId;
  $("#match-dialog-kicker").textContent = `${currentBoard.name} · ${divisionLabel(currentBoard.divisionId)} ${formatLabel(currentBoard.matchType)}`;
  $("#match-dialog-title").textContent = "Start live match";
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
  $("#match-serving-team").innerHTML = ids.filter((id, i) => id && ids.indexOf(id) === i).map((id) => `<option value="${id}">${escapeHtml(names.get(id))}</option>`).join("");
}

function scheduleRows(id) { return getSchedule(state, id).map((row) => `<tr class="schedule-row status-${row.status}"><td>${row.gameNumber}</td><td>${escapeHtml(row.teamAName)}</td><td>${row.scoreA ?? "—"}</td><td>${escapeHtml(row.teamBName)}</td><td>${row.scoreB ?? "—"}</td><td>${escapeHtml(row.winnerName) || "—"}</td><td>${row.status === "pending" ? `<button class="schedule-status status-pending" data-action="start-scheduled" data-division="${id}" data-team-a="${row.teamAId ?? ""}" data-team-b="${row.teamBId ?? ""}" ${row.teamAId && row.teamBId ? "" : "disabled"}>Pending</button>` : `<span class="schedule-status status-${row.status}">${row.status === "live" ? "Live" : "Complete"}</span>`}</td><td>${escapeHtml(row.notes) || "—"}</td></tr>`).join(""); }
function renderSchedule(id) { scheduleDialog.dataset.division = id; $("#schedule-dialog-title").textContent = `${divisionLabel(id)} doubles schedule`; $("#schedule-modal-body").innerHTML = scheduleRows(id); document.querySelectorAll("[data-schedule-division]").forEach((el) => el.setAttribute("aria-selected", String(el.dataset.scheduleDivision === id))); }
function openSchedule(id = "girls") { renderSchedule(id); scheduleDialog.showModal(); }
function standingsRows(id, type) { return getStandings(state, id, type).map((row) => `<tr><td>${row.rank ?? "—"}</td><td>${escapeHtml(row.name)}</td><td>${row.played}</td><td>${row.wins}</td><td>${row.losses}</td><td>${row.pointsFor}</td><td>${row.pointsAgainst}</td><td>${row.pointDiff > 0 ? "+" : ""}${row.pointDiff}</td><td>${row.h2hWins}</td><td>${row.tieBreakScore ?? "—"}</td><td>${row.award || "—"}</td></tr>`).join(""); }
function renderStandings(id, type = "doubles") {
  const results = division(id).results.filter((result) => (result.matchType ?? "doubles") === type);
  standingsDialog.dataset.division = id; standingsDialog.dataset.matchType = type;
  $("#standings-dialog-kicker").textContent = `${divisionLabel(id)} · ${formatLabel(type)} · ${results.length} results`;
  $("#standings-dialog-title").textContent = `${divisionLabel(id)} ${formatLabel(type)} Standings`;
  $("#standings-participant-heading").textContent = type === "singles" ? "Player" : "Team";
  $("#standings-modal-body").innerHTML = standingsRows(id, type);
  $("#standings-modal-summary").innerHTML = `<span>Recorded results</span><strong>${results.length ? `${results.length} completed` : "No results yet"}</strong><b>${formatLabel(type)}</b>`;
  $("#standings-undo-result").hidden = false; $("#standings-undo-result").disabled = !results.length; $("#standings-toggle-final").hidden = type === "singles";
}
function openStandings(id, type) { renderStandings(id, type); standingsDialog.showModal(); }
function renderTeams(id) {
  const current = division(id); $("#team-division").value = id; $("#team-dialog-title").textContent = `Manage ${divisionLabel(id).toLowerCase()} teams`;
  $("#team-list").innerHTML = current.teams.map((item) => `<li><span>${escapeHtml(item.name)}</span><button data-action="remove-team" data-division="${id}" data-team="${item.id}">×</button></li>`).join("");
}
function confirmAction(title, message, label, callback) { $("#confirm-title").textContent = title; $("#confirm-message").textContent = message; $("#confirm-action").textContent = label; confirmCallback = callback; confirmDialog.showModal(); }
function winnerAlert(boardId) {
  const currentBoard = board(boardId), live = currentBoard?.live; if (!live?.winnerTeamId) return;
  winnerDialog.dataset.board = boardId; $("#winner-dialog-kicker").textContent = `${currentBoard.name} winning point reached`; $("#winner-dialog-title").textContent = `Congratulations, ${participantName(currentBoard, live.winnerTeamId)}!`; $("#winner-dialog-message").textContent = "The required winning margin has been reached."; $("#winner-side-a-name").textContent = participantName(currentBoard, live.teamAId); $("#winner-side-b-name").textContent = participantName(currentBoard, live.teamBId); $("#winner-final-score").textContent = `${live.scores[live.teamAId]}–${live.scores[live.teamBId]}`; winnerDialog.showModal();
}

app.addEventListener("click", (event) => {
  const el = event.target.closest("button[data-action]"); if (!el) return;
  const action = el.dataset.action, id = el.dataset.board;
  try {
    if (action === "add-board") openBoardSetup();
    if (action === "show-schedules") openSchedule();
    if (action === "score") { const before = board(id).live.winnerTeamId; const next = adjustScoreboardScore(state, id, el.dataset.team, Number(el.dataset.delta)); const after = next.scoreboards.find((item) => item.id === id).live.winnerTeamId; apply(next, "Score updated."); if (!before && after) winnerAlert(id); }
    if (action === "set-server") apply(setScoreboardServer(state, id, el.dataset.team), `${participantName(board(id), el.dataset.team)} is serving.`);
    if (action === "undo-board") apply(undoScoreboard(state, id), "Last change undone.");
    if (action === "swap-board") apply(swapScoreboardCourt(state, id), "Court sides swapped.");
    if (action === "new-match") board(id).live ? confirmAction("Replace this match?", "The current score will be replaced when the new match starts.", "Choose match", () => openMatch(id)) : openMatch(id);
    if (action === "reset-board") confirmAction("Reset this score?", "Both scores return to zero and the reset can be undone.", "Reset score", () => apply(resetScoreboard(state, id), "Score reset."));
    if (action === "end-board") confirmAction("End this match?", "The unrecorded score will be discarded.", "End match", () => apply(discardScoreboardMatch(state, id), "Match ended."));
    if (action === "remove-board") confirmAction(`Remove ${board(id).name}?`, "Any unrecorded score on this board will be discarded.", "Remove scoreboard", () => apply(removeScoreboard(state, id), "Scoreboard removed."));
    if (action === "record-board") winnerAlert(id);
    if (action === "view-standings") openStandings(board(id).divisionId, board(id).matchType);
  } catch (error) { toast(error.message, true); }
});
app.addEventListener("change", (event) => { if (event.target.matches('input[data-action="server-number"]')) apply(setScoreboardServerNumber(state, event.target.dataset.board, Number(event.target.value)), "Serving player updated."); });
$("#add-scoreboard-button").addEventListener("click", openBoardSetup); $("#schedules-button").addEventListener("click", () => openSchedule());
$("#scoreboard-form").addEventListener("submit", (event) => { event.preventDefault(); const data = new FormData(event.target); try { const next = addScoreboard(state, { name: data.get("name"), divisionId: data.get("divisionId"), matchType: data.get("matchType") }); const added = next.scoreboards.at(-1); state = next; persist(); render(); scoreboardDialog.close(); openMatch(added.id); } catch (error) { toast(error.message, true); } });
$("#match-form").addEventListener("change", (event) => { if (["match-team-a", "match-team-b"].includes(event.target.id)) updateServingOptions(); });
$("#match-form").addEventListener("submit", (event) => { event.preventDefault(); const data = new FormData(event.target); try { apply(startScoreboardMatch(state, data.get("scoreboardId"), { teamAId: data.get("teamAId"), teamBId: data.get("teamBId"), targetPoints: Number(data.get("targetPoints")), winBy: Number(data.get("winBy")), servingTeamId: data.get("servingTeamId"), serverNumber: 2 }), "Match started."); matchDialog.close(); } catch (error) { toast(error.message, true); } });
document.querySelectorAll("[data-schedule-division]").forEach((el) => el.addEventListener("click", () => renderSchedule(el.dataset.scheduleDivision)));
$("#schedule-modal-body").addEventListener("click", (event) => { const el = event.target.closest('[data-action="start-scheduled"]'); if (!el) return; let target = getScoreboards(state).find((item) => item.divisionId === el.dataset.division && item.matchType === "doubles" && !item.live); if (!target) { state = addScoreboard(state, { divisionId: el.dataset.division, matchType: "doubles" }); target = state.scoreboards.at(-1); persist(); render(); } scheduleDialog.close(); openMatch(target.id, el.dataset.teamA, el.dataset.teamB); });
$("#team-form").addEventListener("submit", (event) => { event.preventDefault(); const data = new FormData(event.target); try { state = addTeam(state, data.get("divisionId"), data.get("teamName")); persist(); renderTeams(data.get("divisionId")); } catch (error) { toast(error.message, true); } });
$("#team-list").addEventListener("click", (event) => { const el = event.target.closest('[data-action="remove-team"]'); if (!el) return; try { state = removeTeam(state, el.dataset.division, el.dataset.team); persist(); renderTeams(el.dataset.division); } catch (error) { toast(error.message, true); } });
$("#standings-manage-teams").addEventListener("click", () => { const id = standingsDialog.dataset.division; standingsDialog.close(); renderTeams(id); teamDialog.showModal(); });
$("#standings-toggle-final").addEventListener("click", () => { const id = standingsDialog.dataset.division; try { apply(setDivisionFinalized(state, id, !division(id).finalized), "Awards updated."); } catch (error) { toast(error.message, true); } });
$("#standings-undo-result").addEventListener("click", () => {
  const id = standingsDialog.dataset.division, type = standingsDialog.dataset.matchType || "doubles";
  confirmAction("Undo the latest result?", `The most recent ${formatLabel(type).toLowerCase()} result will be removed from the standings.`, "Undo result", () => apply(undoLastResultByType(state, id, type), "Latest result removed."));
});
document.querySelectorAll("[data-close-dialog]").forEach((el) => el.addEventListener("click", () => el.closest("dialog").close()));
$("#confirm-action").addEventListener("click", () => { const callback = confirmCallback; confirmCallback = null; confirmDialog.close(); callback?.(); });
$("#winner-record-action").addEventListener("click", () => { const id = winnerDialog.dataset.board; try { apply(recordScoreboardResult(state, id), "Result recorded."); winnerDialog.close(); } catch (error) { toast(error.message, true); } });
$("#sync-settings-button").addEventListener("click", () => { $("#sync-access-code").value = syncAccessCode; $("#sync-disconnect-button").hidden = !syncAccessCode; syncDialog.showModal(); });
$("#sync-form").addEventListener("submit", async (event) => { event.preventDefault(); try { syncAccessCode = normalizeAccessCode(new FormData(event.target).get("accessCode")); if (!syncAccessCode) throw new Error("Enter the private access code."); await reconcileRemote({ initial: true }); localStorage.setItem(SYNC_ACCESS_KEY, syncAccessCode); syncDialog.close(); } catch (error) { toast(error.message, true); } });
$("#sync-disconnect-button").addEventListener("click", () => { syncAccessCode = ""; localStorage.removeItem(SYNC_ACCESS_KEY); syncDialog.close(); syncStatus("Saved locally · Phone sync off"); });
$("#fullscreen-button").addEventListener("click", async () => { if (!document.fullscreenElement) await document.documentElement.requestFullscreen(); else await document.exitFullscreen(); });
$("#reset-tournament-button").addEventListener("click", () => confirmAction("Reset the entire tournament?", "All scoreboards, results, and standings will be cleared.", "Reset tournament", () => apply(createTournament(), "Tournament reset.")));

render();
if (syncAccessCode) reconcileRemote({ initial: true }).catch((error) => toast(error.message, true));
setInterval(() => { void reconcileRemote(); }, SYNC_POLL_MS);
