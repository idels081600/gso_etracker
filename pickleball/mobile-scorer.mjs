import {
  adjustScoreboardScore, discardScoreboardMatch, getScoreboards, liveCourtOrder, participantOptions,
  recordScoreboardResult, resetScoreboard, setScoreboardServer, setScoreboardServerNumber,
  startScoreboardMatch, swapScoreboardCourt, teamPlayerNames, undoScoreboard,
} from "./tournament-engine.mjs";
import { fetchTournamentState, isStateNewer, normalizeAccessCode, saveTournamentState } from "./tournament-sync.mjs";

const ACCESS_KEY = "pickleball.phoneScorerAccess.v1";
const BOARD_KEY = "pickleball.phoneScorerBoard.v1";
const POLL_MS = 900;
const $ = (selector) => document.querySelector(selector);
const accessPanel = $("#phone-access-panel");
const scorerPanel = $("#phone-scorer-panel");
const phoneApp = $("#phone-app");
const confirmDialog = $("#phone-confirm-dialog");
const winnerDialog = $("#phone-winner-dialog");
let state = null;
let accessCode = normalizeAccessCode(localStorage.getItem(ACCESS_KEY));
let selectedBoardId = localStorage.getItem(BOARD_KEY) || "";
let confirmCallback = null;
let actionQueue = Promise.resolve();
let actionInFlight = false;

function escapeHtml(value) { return String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;"); }
function toast(message, error = false) { const el = document.createElement("div"); el.className = `toast ${error ? "error" : ""}`; el.textContent = message; $("#phone-toast-region").append(el); setTimeout(() => el.remove(), 3200); }
function announce(message) { $("#phone-announcer").textContent = ""; setTimeout(() => { $("#phone-announcer").textContent = message; }, 20); }
function connection(message, connected = false) { $("#phone-sync-status").textContent = message; $("#phone-sync-status").classList.toggle("connected", connected); }
function boards() { return state ? getScoreboards(state) : []; }
function currentBoard() { return boards().find((item) => item.id === selectedBoardId) ?? boards()[0] ?? null; }
function participantName(board, id) { return board.live?.participantNames?.[id] ?? participantOptions(state, board.divisionId, board.matchType).find((item) => item.id === id)?.name ?? "Participant"; }
function selectAvailableBoard() { if (!boards().some((item) => item.id === selectedBoardId)) selectedBoardId = boards()[0]?.id ?? ""; if (selectedBoardId) localStorage.setItem(BOARD_KEY, selectedBoardId); }

function picker() {
  selectAvailableBoard();
  $("#phone-board-select").innerHTML = boards().length ? boards().map((board) => `<option value="${board.id}">${escapeHtml(board.name)} · ${board.divisionId === "girls" ? "Girls" : "Boys"} ${board.matchType === "singles" ? "Singles" : "Doubles"}</option>`).join("") : '<option value="">No scoreboards</option>';
  $("#phone-board-select").value = selectedBoardId;
}
function idleMarkup(board) {
  const participants = participantOptions(state, board.divisionId, board.matchType);
  const options = participants.map((item) => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join("");
  return `<section class="phone-idle-view division-${board.divisionId}"><div class="phone-section-heading"><div><span>${board.divisionId === "girls" ? "Girls" : "Boys"} · ${board.matchType}</span><h1>${escapeHtml(board.name)}</h1></div><strong>0–0</strong></div><p>Select two ${board.matchType === "singles" ? "players" : "teams"} and begin scoring.</p><form id="phone-match-form" class="phone-match-form"><label class="field"><span>Left ${board.matchType === "singles" ? "player" : "team"}</span><select name="teamAId" id="phone-team-a">${options}</select></label><label class="field"><span>Right ${board.matchType === "singles" ? "player" : "team"}</span><select name="teamBId" id="phone-team-b">${options}</select></label><div class="phone-form-pair"><label class="field"><span>Points</span><select name="targetPoints"><option>11</option><option>15</option><option>21</option></select></label><label class="field"><span>Win by</span><select name="winBy"><option>2</option><option>1</option></select></label></div><label class="field"><span>First server</span><select name="servingTeamId" id="phone-serving-team"></select></label><button class="button button-primary phone-start-button">Start match</button></form></section>`;
}
function sideMarkup(board, id, position) {
  const live = board.live, name = participantName(board, id), serving = live.servingTeamId === id;
  const server = board.matchType === "singles" ? name : teamPlayerNames(name)[live.serverNumber - 1];
  return `<article class="phone-score-card ${serving ? "is-serving" : ""}"><div class="phone-team-line"><div><span>Court ${position}</span><h2>${escapeHtml(name)}</h2></div><button class="${serving ? "active" : ""}" data-phone-action="serve" data-team="${id}">${serving ? `<span class="serve-player-name">${escapeHtml(server)}</span><span class="serve-player-state">Serving</span>` : "Set serve"}</button></div><strong class="phone-score-value">${live.scores[id]}</strong><div class="phone-score-buttons"><button data-phone-action="score" data-team="${id}" data-delta="-1">−</button><button class="add" data-phone-action="score" data-team="${id}" data-delta="1">+</button></div></article>`;
}
function liveMarkup(board) {
  const live = board.live, [left, right] = liveCourtOrder(live), receiver = live.servingTeamId === live.teamAId ? live.teamBId : live.teamAId;
  const call = `${live.scores[live.servingTeamId]} – ${live.scores[receiver]}${board.matchType === "doubles" ? ` – ${live.serverNumber}` : ""}`;
  const serverName = participantName(board, live.servingTeamId), servers = board.matchType === "doubles" ? teamPlayerNames(serverName) : [serverName];
  const winner = live.winnerTeamId ? participantName(board, live.winnerTeamId) : "";
  return `<section class="phone-live-view division-${board.divisionId}"><div class="phone-match-meta"><div><span>${board.divisionId === "girls" ? "Girls" : "Boys"} · ${board.matchType}</span><strong>${escapeHtml(board.name)} · To ${live.targetPoints}</strong></div><button data-phone-action="undo" ${live.undoStack.length ? "" : "disabled"}>Undo</button></div><div class="phone-score-grid">${sideMarkup(board, left, "Left")}${sideMarkup(board, right, "Right")}</div><div class="phone-score-call"><div><span>Score call</span><strong>${call}</strong></div><fieldset><legend>Serving player</legend>${servers.map((name, index) => `<label><input type="radio" name="phoneServer" value="${index + 1}" data-phone-server ${live.serverNumber === index + 1 ? "checked" : ""} ${board.matchType === "singles" ? "disabled" : ""}><span><strong>${escapeHtml(name)}</strong><small>${live.serverNumber === index + 1 ? "Serving" : "Select"}</small></span></label>`).join("")}</fieldset></div><div class="phone-match-actions"><button data-phone-action="swap">⇄ Swap</button><button data-phone-action="reset">Reset</button><button class="danger" data-phone-action="end">End</button></div><button class="button ${winner ? "button-primary" : "button-secondary"} phone-record-button" data-phone-action="record" ${winner ? "" : "disabled"}>${winner ? `Record ${escapeHtml(winner)} win` : "Awaiting winner"}</button></section>`;
}
function render() {
  if (!state) return; picker(); const board = currentBoard();
  if (!board) { phoneApp.innerHTML = '<section class="phone-no-boards"><h1>No scoreboards available</h1><p>Add a scoreboard from the tournament controller, then it will appear here automatically.</p></section>'; return; }
  phoneApp.innerHTML = board.live ? liveMarkup(board) : idleMarkup(board);
  if (!board.live) updateServingOptions();
}
function updateServingOptions() {
  const board = currentBoard(), a = $("#phone-team-a"), b = $("#phone-team-b"), serving = $("#phone-serving-team"); if (!a || !b || !serving) return;
  if (a.value === b.value && b.options.length > 1) b.selectedIndex = a.selectedIndex === 0 ? 1 : 0;
  const names = new Map(participantOptions(state, board.divisionId, board.matchType).map((item) => [item.id, item.name]));
  serving.innerHTML = [a.value, b.value].map((id) => `<option value="${id}">${escapeHtml(names.get(id))}</option>`).join("");
}
async function connect(code) {
  const normalized = normalizeAccessCode(code); if (!normalized) throw new Error("Enter the scorer access code."); connection("Connecting…");
  const remote = await fetchTournamentState(normalized); if (remote === null) throw new Error("Connect the tournament controller first.");
  accessCode = normalized; state = remote; localStorage.setItem(ACCESS_KEY, accessCode); accessPanel.hidden = true; scorerPanel.hidden = false; render(); connection("Live sync connected", true);
}
function mutate(mutator, message, celebrate = false) {
  const boardId = currentBoard()?.id; if (!boardId) return;
  actionQueue = actionQueue.then(async () => { actionInFlight = true; const before = currentBoard().live?.winnerTeamId; state = mutator(state, boardId); render(); connection("Saving score…", true); let result = await saveTournamentState(state, accessCode); if (result.conflict) { state = result.state; state = mutator(state, boardId); result = await saveTournamentState(state, accessCode); } state = result.state; render(); connection("Live sync connected", true); announce(message); if (celebrate && !before && currentBoard()?.live?.winnerTeamId) winnerAlert(); }).catch((error) => { connection("Sync needs attention"); toast(error.message, true); }).finally(() => { actionInFlight = false; });
}
function confirmAction(title, message, label, callback) { $("#phone-confirm-title").textContent = title; $("#phone-confirm-message").textContent = message; $("#phone-confirm-action").textContent = label; confirmCallback = callback; confirmDialog.showModal(); }
function winnerAlert() { const board = currentBoard(), live = board.live, winner = participantName(board, live.winnerTeamId); winnerDialog.dataset.board = board.id; $("#phone-winner-kicker").textContent = `${board.name} winning point reached`; $("#phone-winner-title").textContent = `Congratulations, ${winner}!`; $("#phone-winner-message").textContent = "The required winning margin has been reached."; $("#phone-winner-side-a").textContent = participantName(board, live.teamAId); $("#phone-winner-side-b").textContent = participantName(board, live.teamBId); $("#phone-winner-final-score").textContent = `${live.scores[live.teamAId]}–${live.scores[live.teamBId]}`; winnerDialog.showModal(); }

$("#phone-access-form").addEventListener("submit", async (event) => { event.preventDefault(); try { await connect(new FormData(event.target).get("accessCode")); } catch (error) { connection("Not connected"); toast(error.message, true); } });
$("#phone-board-select").addEventListener("change", (event) => { selectedBoardId = event.target.value; localStorage.setItem(BOARD_KEY, selectedBoardId); render(); });
phoneApp.addEventListener("change", (event) => { if (["phone-team-a", "phone-team-b"].includes(event.target.id)) updateServingOptions(); if (event.target.matches("[data-phone-server]")) mutate((state, id) => setScoreboardServerNumber(state, id, Number(event.target.value)), "Serving player updated."); });
phoneApp.addEventListener("submit", (event) => { if (event.target.id !== "phone-match-form") return; event.preventDefault(); const data = new FormData(event.target); mutate((state, id) => startScoreboardMatch(state, id, { teamAId: data.get("teamAId"), teamBId: data.get("teamBId"), targetPoints: Number(data.get("targetPoints")), winBy: Number(data.get("winBy")), servingTeamId: data.get("servingTeamId"), serverNumber: 2 }), "Match started."); });
phoneApp.addEventListener("click", (event) => { const el = event.target.closest("[data-phone-action]"); if (!el) return; const action = el.dataset.phoneAction; if (action === "score") mutate((state, id) => adjustScoreboardScore(state, id, el.dataset.team, Number(el.dataset.delta)), "Score updated.", true); if (action === "serve") mutate((state, id) => setScoreboardServer(state, id, el.dataset.team), `${participantName(currentBoard(), el.dataset.team)} is serving.`); if (action === "undo") mutate(undoScoreboard, "Last change undone."); if (action === "swap") mutate(swapScoreboardCourt, "Court sides swapped."); if (action === "reset") confirmAction("Reset score?", "Both scores return to zero.", "Reset", () => mutate(resetScoreboard, "Score reset.")); if (action === "end") confirmAction("End match?", "The unrecorded score will be discarded.", "End", () => mutate(discardScoreboardMatch, "Match ended.")); if (action === "record") winnerAlert(); });
$("#phone-confirm-action").addEventListener("click", () => { const callback = confirmCallback; confirmCallback = null; confirmDialog.close(); callback?.(); });
$("#phone-record-result").addEventListener("click", () => { winnerDialog.close(); mutate(recordScoreboardResult, "Result recorded."); });
document.querySelectorAll("[data-close-phone-dialog]").forEach((el) => el.addEventListener("click", () => el.closest("dialog").close()));
$("#phone-disconnect-button").addEventListener("click", () => { accessCode = ""; state = null; localStorage.removeItem(ACCESS_KEY); scorerPanel.hidden = true; accessPanel.hidden = false; connection("Not connected"); });
async function poll() { if (!accessCode || !state || actionInFlight || document.querySelector("dialog[open]")) return; try { const remote = await fetchTournamentState(accessCode); if (remote && isStateNewer(remote, state)) { state = remote; render(); } connection("Live sync connected", true); } catch { connection("Sync offline"); } }
if (accessCode) connect(accessCode).catch(() => { accessCode = ""; localStorage.removeItem(ACCESS_KEY); connection("Not connected"); });
setInterval(() => { void poll(); }, POLL_MS);
