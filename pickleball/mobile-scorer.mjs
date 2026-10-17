import {
  adjustScoreboardScore, getScoreboards, liveCourtOrder, participantOptions, resetScoreboard,
  setScoreboardServer, setScoreboardServerNumber, swapScoreboardCourt, teamPlayerNames, undoScoreboard,
} from "./tournament-engine.mjs";

const divisionLabel = (id) => ({ girls: "Girls", boys: "Boys", mixed: "Mixed Doubles" }[id] ?? "Division");
const competitionLabel = (id, type) => id === "mixed" && type === "doubles" ? "Mixed Doubles" : `${divisionLabel(id)} ${type === "singles" ? "Singles" : "Doubles"}`;
import { createActionId, fetchTournamentState, isStateNewer, normalizeAccessCode, performTournamentAction } from "./tournament-sync.mjs";

const ACCESS_KEY = "pickleball.phoneScorerAccess.v1";
const BOARD_KEY = "pickleball.phoneScorerBoard.v1";
const POLL_MS = 900;
const SCORE_BATCH_MS = 90;
const $ = (selector) => document.querySelector(selector);
const accessPanel = $("#phone-access-panel");
const scorerPanel = $("#phone-scorer-panel");
const phoneApp = $("#phone-app");
const confirmDialog = $("#phone-confirm-dialog");
const winnerDialog = $("#phone-winner-dialog");
let state = null;
let synchronizedState = null;
let accessCode = normalizeAccessCode(localStorage.getItem(ACCESS_KEY));
let selectedBoardId = localStorage.getItem(BOARD_KEY) || "";
let boardPickerSignature = "";
let confirmCallback = null;
let actionQueue = Promise.resolve();
let actionInFlight = false;
let pendingActions = 0;
const pendingPreviews = new Map();
const scoreBatches = new Map();

function escapeHtml(value) { return String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;"); }
function toast(message, error = false) { const el = document.createElement("div"); el.className = `toast ${error ? "error" : ""}`; el.textContent = message; $("#phone-toast-region").append(el); setTimeout(() => el.remove(), 3200); }
function announce(message) { $("#phone-announcer").textContent = ""; setTimeout(() => { $("#phone-announcer").textContent = message; }, 20); }
function connection(message, connected = false) { $("#phone-sync-status").textContent = message; $("#phone-sync-status").classList.toggle("connected", connected); }
function boards() { return state ? getScoreboards(state).filter((board) => board.live) : []; }
function currentBoard() { return boards().find((item) => item.id === selectedBoardId) ?? boards()[0] ?? null; }
function participantName(board, id) { return board.live?.participantNames?.[id] ?? participantOptions(state, board.divisionId, board.matchType).find((item) => item.id === id)?.name ?? "Participant"; }
function selectAvailableBoard() { if (!boards().some((item) => item.id === selectedBoardId)) selectedBoardId = boards()[0]?.id ?? ""; if (selectedBoardId) localStorage.setItem(BOARD_KEY, selectedBoardId); }

function picker() {
  selectAvailableBoard();
  const select = $("#phone-board-select");
  const availableBoards = boards();
  const signature = availableBoards.map((board) => `${board.id}:${board.name}:${board.divisionId}:${board.matchType}`).join("|");
  if (signature !== boardPickerSignature) {
    select.innerHTML = availableBoards.length ? availableBoards.map((board) => `<option value="${board.id}">${escapeHtml(board.name)} / ${competitionLabel(board.divisionId, board.matchType)}</option>`).join("") : '<option value="">No scoreboards</option>';
    boardPickerSignature = signature;
  }
  if (select.value !== selectedBoardId) select.value = selectedBoardId;
}
function idleMarkup(board) {
  return `<section class="phone-idle-view division-${board.divisionId}"><div class="phone-section-heading"><div><span>${competitionLabel(board.divisionId, board.matchType)}</span><h1>${escapeHtml(board.name)}</h1></div><strong>0–0</strong></div><div class="phone-waiting-state"><span class="waiting-pulse" aria-hidden="true"></span><h2>Waiting for the Game Master</h2><p>The matchup and winning rules must be started from tournament control. This court will update automatically.</p></div></section>`;
}
function sideMarkup(board, id, position) {
  const live = board.live, name = participantName(board, id), serving = live.servingTeamId === id;
  const server = board.matchType === "singles" ? name : teamPlayerNames(name)[live.serverNumber - 1];
  return `<article class="phone-score-card ${serving ? "is-serving" : ""}" data-score-team="${id}"><div class="phone-team-line"><div><span>Court ${position}</span><h2>${escapeHtml(name)}</h2></div><button class="${serving ? "active" : ""}" data-phone-action="serve" data-team="${id}">${serving ? `<span class="serve-player-name">${escapeHtml(server)}</span><span class="serve-player-state">Serving</span>` : "Set serve"}</button></div><strong class="phone-score-value">${live.scores[id]}</strong><div class="phone-score-buttons"><button data-phone-action="score" data-team="${id}" data-delta="-1">−</button><button class="add" data-phone-action="score" data-team="${id}" data-delta="1">+</button></div></article>`;
}
function liveMarkup(board) {
  const live = board.live, [left, right] = liveCourtOrder(live), receiver = live.servingTeamId === live.teamAId ? live.teamBId : live.teamAId;
  const call = `${live.scores[live.servingTeamId]} – ${live.scores[receiver]}${board.matchType === "doubles" ? ` – ${live.serverNumber}` : ""}`;
  const serverName = participantName(board, live.servingTeamId), servers = board.matchType === "doubles" ? teamPlayerNames(serverName) : [serverName];
  const winner = live.winnerTeamId ? participantName(board, live.winnerTeamId) : "";
  return `<section class="phone-live-view division-${board.divisionId}"><div class="phone-match-meta"><div><span>${competitionLabel(board.divisionId, board.matchType)}</span><strong>${escapeHtml(board.name)} / To ${live.targetPoints}</strong></div><button data-phone-action="undo" ${live.undoStack.length ? "" : "disabled"}>Undo</button></div><div class="phone-score-grid">${sideMarkup(board, left, "Left")}${sideMarkup(board, right, "Right")}</div><div class="phone-score-call"><div><span>Score call</span><strong>${call}</strong></div><fieldset><legend>Serving player</legend>${servers.map((name, index) => `<label><input type="radio" name="phoneServer" value="${index + 1}" data-phone-server ${live.serverNumber === index + 1 ? "checked" : ""} ${board.matchType === "singles" ? "disabled" : ""}><span><strong>${escapeHtml(name)}</strong><small>${live.serverNumber === index + 1 ? "Serving" : "Select"}</small></span></label>`).join("")}</fieldset></div><div class="phone-match-actions"><button data-phone-action="swap">⇄ Swap sides</button><button data-phone-action="reset">Reset score</button></div><button class="button ${winner ? "button-primary" : "button-secondary"} phone-record-button" data-phone-action="record" ${winner ? "" : "disabled"}>${winner ? `Submit ${escapeHtml(winner)} win` : "Awaiting winner"}</button></section>`;
}
function render() {
  if (!state) return; picker(); const board = currentBoard();
  if (!board) { phoneApp.innerHTML = '<section class="phone-no-boards"><h1>No active games</h1><p>When the Game Master starts a game, its court will appear here automatically.</p></section>'; return; }
  phoneApp.innerHTML = board.live ? liveMarkup(board) : idleMarkup(board);
}
function previewMutation(action, payload, boardId) {
  if (action === "adjust_score") return (value) => adjustScoreboardScore(value, boardId, payload.participantId, payload.delta);
  if (action === "set_server") return (value) => setScoreboardServer(value, boardId, payload.participantId);
  if (action === "set_server_number") return (value) => setScoreboardServerNumber(value, boardId, payload.serverNumber);
  if (action === "swap_court") return (value) => swapScoreboardCourt(value, boardId);
  if (action === "undo_live") return (value) => undoScoreboard(value, boardId);
  if (action === "reset_live") return (value) => resetScoreboard(value, boardId);
  return null;
}
function rebuildOptimisticState() {
  state = synchronizedState;
  for (const applyPreview of pendingPreviews.values()) state = applyPreview(state);
  render();
}
function queueScore(participantId, delta) {
  const boardId = currentBoard()?.id;
  if (!boardId) return;
  const key = `${boardId}:${participantId}`;
  const batch = scoreBatches.get(key) ?? { boardId, participantId, delta: 0, previewId: `score-preview:${key}`, timer: null };
  batch.delta += delta;
  clearTimeout(batch.timer);
  scoreBatches.set(key, batch);
  pendingPreviews.set(batch.previewId, (value) => adjustScoreboardScore(value, boardId, participantId, batch.delta));
  rebuildOptimisticState();
  batch.timer = setTimeout(() => {
    scoreBatches.delete(key);
    pendingPreviews.delete(batch.previewId);
    if (batch.delta === 0) { rebuildOptimisticState(); return; }
    mutate("adjust_score", { participantId, delta: batch.delta }, "Score updated.", true);
  }, SCORE_BATCH_MS);
}
async function connect(code) {
  const normalized = normalizeAccessCode(code); if (!normalized) throw new Error("Enter the Remote Scorer access code."); connection("Signing in…");
  const remote = await fetchTournamentState(normalized, globalThis.fetch, "remote_scorer"); if (remote === null) throw new Error("The Game Master must initialize tournament control first.");
  accessCode = normalized; synchronizedState = remote; state = remote; localStorage.setItem(ACCESS_KEY, accessCode); accessPanel.hidden = true; scorerPanel.hidden = false; render(); connection("Remote Scorer / Live sync", true);
}
function mutate(action, payload, message, celebrate = false) {
  const boardId = currentBoard()?.id; if (!boardId) return;
  const actionId = createActionId();
  const preview = previewMutation(action, payload, boardId);
  if (preview) { pendingPreviews.set(actionId, preview); rebuildOptimisticState(); }
  pendingActions += 1;
  actionInFlight = true;
  actionQueue = actionQueue.then(async () => { const before = getScoreboards(synchronizedState).find((board) => board.id === boardId)?.live?.winnerTeamId; connection("Saving score…", true); let result = await performTournamentAction(synchronizedState, accessCode, action, { scoreboardId: boardId, ...payload }, actionId); if (result.conflict) { synchronizedState = result.state; result = await performTournamentAction(synchronizedState, accessCode, action, { scoreboardId: boardId, ...payload }, actionId); } if (result.conflict) throw new Error("The court changed on another device. Try the action again."); synchronizedState = result.state; pendingPreviews.delete(actionId); pendingActions -= 1; rebuildOptimisticState(); connection(pendingActions ? "Saving score…" : "Remote Scorer / Live sync", true); announce(message); const savedBoard = getScoreboards(synchronizedState).find((board) => board.id === boardId); if (celebrate && !before && savedBoard?.live?.winnerTeamId && pendingActions === 0) winnerAlert(); }).catch((error) => { pendingPreviews.delete(actionId); pendingActions = Math.max(0, pendingActions - 1); rebuildOptimisticState(); connection("Sync needs attention"); toast(error.message, true); }).finally(() => { actionInFlight = pendingActions > 0; });
}
function confirmAction(title, message, label, callback) { $("#phone-confirm-title").textContent = title; $("#phone-confirm-message").textContent = message; $("#phone-confirm-action").textContent = label; confirmCallback = callback; confirmDialog.showModal(); }
function winnerAlert() { const board = currentBoard(), live = board.live, winner = participantName(board, live.winnerTeamId); winnerDialog.dataset.board = board.id; $("#phone-winner-kicker").textContent = `${board.name} winning point reached`; $("#phone-winner-title").textContent = `Congratulations, ${winner}!`; $("#phone-winner-message").textContent = "The required winning margin has been reached."; $("#phone-winner-side-a").textContent = participantName(board, live.teamAId); $("#phone-winner-side-b").textContent = participantName(board, live.teamBId); $("#phone-winner-final-score").textContent = `${live.scores[live.teamAId]}–${live.scores[live.teamBId]}`; winnerDialog.showModal(); }

$("#phone-access-form").addEventListener("submit", async (event) => { event.preventDefault(); try { await connect(new FormData(event.target).get("accessCode")); } catch (error) { connection("Signed out"); toast(error.message, true); } });
$("#phone-board-select").addEventListener("change", (event) => { selectedBoardId = event.target.value; localStorage.setItem(BOARD_KEY, selectedBoardId); render(); });
phoneApp.addEventListener("change", (event) => { if (event.target.matches("[data-phone-server]")) mutate("set_server_number", { serverNumber: Number(event.target.value) }, "Serving player updated."); });
phoneApp.addEventListener("click", (event) => { const el = event.target.closest("[data-phone-action]"); if (!el) return; const action = el.dataset.phoneAction; if (action === "score") queueScore(el.dataset.team, Number(el.dataset.delta)); if (action === "serve") { const participantId = el.dataset.team; const message = `${participantName(currentBoard(), participantId)} is serving.`; mutate("set_server", { participantId }, message); } if (action === "undo") mutate("undo_live", {}, "Last change undone."); if (action === "swap") mutate("swap_court", {}, "Court sides swapped."); if (action === "reset") confirmAction("Reset score?", "Both scores return to zero. This can be undone.", "Reset score", () => mutate("reset_live", {}, "Score reset.")); if (action === "record") winnerAlert(); });
$("#phone-confirm-action").addEventListener("click", () => { const callback = confirmCallback; confirmCallback = null; confirmDialog.close(); callback?.(); });
$("#phone-record-result").addEventListener("click", () => { winnerDialog.close(); mutate("submit_result", {}, "Result submitted to the Game Master."); });
document.querySelectorAll("[data-close-phone-dialog]").forEach((el) => el.addEventListener("click", () => el.closest("dialog").close()));
$("#phone-disconnect-button").addEventListener("click", () => { accessCode = ""; state = null; localStorage.removeItem(ACCESS_KEY); scorerPanel.hidden = true; accessPanel.hidden = false; connection("Signed out"); });
async function poll() { if (!accessCode || !state || actionInFlight || document.querySelector("dialog[open]")) return; try { const remote = await fetchTournamentState(accessCode, globalThis.fetch, "remote_scorer"); if (remote && isStateNewer(remote, synchronizedState)) { synchronizedState = remote; state = remote; render(); } connection("Remote Scorer / Live sync", true); } catch { connection("Sync offline"); } }
if (accessCode) connect(accessCode).catch(() => { accessCode = ""; localStorage.removeItem(ACCESS_KEY); connection("Signed out"); });
setInterval(() => { void poll(); }, POLL_MS);
