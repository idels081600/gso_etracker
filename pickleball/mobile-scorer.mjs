import {
  adjustLiveScore,
  discardLiveMatch,
  recordLiveResult,
  resetLiveScore,
  setLiveServer,
  setLiveServerNumber,
  servingPlayerName,
  startMatch,
  teamPlayerNames,
  undoLive,
} from "./tournament-engine.mjs";
import { fetchTournamentState, isStateNewer, normalizeAccessCode, saveTournamentState } from "./tournament-sync.mjs";

const ACCESS_KEY = "pickleball.phoneScorerAccess.v1";
const DIVISION_KEY = "pickleball.phoneScorerDivision.v1";
const POLL_MS = 900;

const accessPanel = document.querySelector("#phone-access-panel");
const scorerPanel = document.querySelector("#phone-scorer-panel");
const accessForm = document.querySelector("#phone-access-form");
const phoneApp = document.querySelector("#phone-app");
const confirmDialog = document.querySelector("#phone-confirm-dialog");
const winnerDialog = document.querySelector("#phone-winner-dialog");
const toastRegion = document.querySelector("#phone-toast-region");
const announcer = document.querySelector("#phone-announcer");

let state = null;
let accessCode = normalizeAccessCode(localStorage.getItem(ACCESS_KEY));
let selectedDivision = ["girls", "boys"].includes(localStorage.getItem(DIVISION_KEY)) ? localStorage.getItem(DIVISION_KEY) : "girls";
let confirmCallback = null;
let actionQueue = Promise.resolve();
let actionInFlight = false;

function escapeHtml(value) {
  return String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
}

function showToast(message, type = "info") {
  const toast = document.createElement("div");
  toast.className = `toast ${type === "error" ? "error" : ""}`;
  toast.textContent = message;
  toastRegion.append(toast);
  window.setTimeout(() => toast.remove(), 3200);
}

function announce(message) {
  announcer.textContent = "";
  window.setTimeout(() => { announcer.textContent = message; }, 20);
}

function setConnectionStatus(message, connected = false) {
  const element = document.querySelector("#phone-sync-status");
  element.textContent = message;
  element.classList.toggle("connected", connected);
}

function division(divisionId = selectedDivision) { return state.divisions[divisionId]; }
function team(teamId, divisionId = selectedDivision) { return division(divisionId).teams.find((item) => item.id === teamId); }

function updateDivisionTabs() {
  document.querySelectorAll("[data-select-division]").forEach((button) => {
    const selected = button.dataset.selectDivision === selectedDivision;
    button.setAttribute("aria-selected", String(selected));
    button.classList.toggle("selected", selected);
  });
}

function idleMatchMarkup(current) {
  const options = current.teams.map((item) => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join("");
  return `
    <section class="phone-idle-view division-${selectedDivision}">
      <div class="phone-section-heading"><div><span>${current.teams.length} teams available</span><h1>${current.label} Live Scoring</h1></div><strong>0–0</strong></div>
      <p>No live ${current.label.toLowerCase()} match. Select the two teams and begin scoring from this phone.</p>
      <form id="phone-match-form" class="phone-match-form">
        <label class="field"><span>Side A team</span><select name="teamAId" id="phone-team-a">${options}</select></label>
        <label class="field"><span>Side B team</span><select name="teamBId" id="phone-team-b">${options}</select></label>
        <div class="phone-form-pair">
          <label class="field"><span>Points</span><select name="targetPoints"><option value="11">11</option><option value="15">15</option><option value="21">21</option></select></label>
          <label class="field"><span>Win by</span><select name="winBy"><option value="2">2</option><option value="1">1</option></select></label>
        </div>
        <label class="field"><span>First serving team</span><select name="servingTeamId" id="phone-serving-team"></select></label>
        <button class="button button-primary phone-start-button" type="submit">Start ${current.label} match</button>
      </form>
    </section>`;
}

function scoreTeamMarkup(teamId, side) {
  const live = division().live;
  const currentTeam = team(teamId);
  const serving = live.servingTeamId === teamId;
  const activeServer = serving ? servingPlayerName(currentTeam.name, live.serverNumber) : "";
  return `
    <article class="phone-score-card ${serving ? "is-serving" : ""}">
      <div class="phone-team-line"><div><span>Side ${side}</span><h2>${escapeHtml(currentTeam.name)}</h2></div><button class="${serving ? "active" : ""}" type="button" data-phone-action="serve" data-team="${teamId}" aria-pressed="${serving}" aria-label="${serving ? `${escapeHtml(activeServer)} is serving for ${escapeHtml(currentTeam.name)}` : `Set ${escapeHtml(currentTeam.name)} as serving team`}">${serving ? `<span class="serve-player-name">${escapeHtml(activeServer)}</span><span class="serve-player-state">Serving</span>` : "Set serve"}</button></div>
      <strong class="phone-score-value">${live.scores[teamId]}</strong>
      <div class="phone-score-buttons">
        <button type="button" data-phone-action="score" data-team="${teamId}" data-delta="-1" aria-label="Subtract one from ${escapeHtml(currentTeam.name)}">−</button>
        <button class="add" type="button" data-phone-action="score" data-team="${teamId}" data-delta="1" aria-label="Add one to ${escapeHtml(currentTeam.name)}">+</button>
      </div>
    </article>`;
}

function liveMatchMarkup(current) {
  const live = current.live;
  const servingScore = live.scores[live.servingTeamId];
  const receivingId = live.servingTeamId === live.teamAId ? live.teamBId : live.teamAId;
  const scoreCall = `${servingScore} – ${live.scores[receivingId]} – ${live.serverNumber}`;
  const winner = live.winnerTeamId ? team(live.winnerTeamId) : null;
  const servingPlayers = teamPlayerNames(team(live.servingTeamId).name);
  return `
    <section class="phone-live-view division-${selectedDivision}">
      <div class="phone-match-meta"><div><span>${current.label} division</span><strong>To ${live.targetPoints} · Win by ${live.winBy}</strong></div><button type="button" data-phone-action="undo" ${live.undoStack.length ? "" : "disabled"}>Undo</button></div>
      <div class="phone-score-grid">${scoreTeamMarkup(live.teamAId, "A")}${scoreTeamMarkup(live.teamBId, "B")}</div>
      <div class="phone-score-call"><div><span>Score call</span><strong>${scoreCall}</strong></div><fieldset><legend>Serving player</legend>${servingPlayers.map((playerName, index) => { const number = index + 1; const selected = live.serverNumber === number; return `<label><input type="radio" name="phoneServer" value="${number}" data-phone-server aria-label="Set ${escapeHtml(playerName)} as serving player" ${selected ? "checked" : ""}><span><strong>${escapeHtml(playerName)}</strong><small>${selected ? "Serving" : "Select"}</small></span></label>`; }).join("")}</fieldset></div>
      <div class="phone-match-actions"><button type="button" data-phone-action="reset">Reset score</button><button class="danger" type="button" data-phone-action="end">End match</button></div>
      <button class="button ${winner ? "button-primary" : "button-secondary"} phone-record-button" type="button" data-phone-action="record" ${winner ? "" : "disabled"}>${winner ? `Record ${escapeHtml(winner.name)} win` : "Awaiting winning score"}</button>
    </section>`;
}

function render() {
  if (!state) return;
  updateDivisionTabs();
  phoneApp.innerHTML = division().live ? liveMatchMarkup(division()) : idleMatchMarkup(division());
  if (!division().live) updateStartServingOptions();
}

function updateStartServingOptions() {
  const teamA = document.querySelector("#phone-team-a");
  const teamB = document.querySelector("#phone-team-b");
  const serving = document.querySelector("#phone-serving-team");
  if (!teamA || !teamB || !serving) return;
  if (teamA.value === teamB.value && teamB.options.length > 1) teamB.selectedIndex = teamA.selectedIndex === 0 ? 1 : 0;
  serving.innerHTML = [teamA.value, teamB.value].map((id) => `<option value="${id}">${escapeHtml(team(id)?.name ?? "Team")}</option>`).join("");
}

async function connect(code) {
  const normalized = normalizeAccessCode(code);
  if (!normalized) throw new Error("Enter the scorer access code.");
  setConnectionStatus("Connecting…");
  const remote = await fetchTournamentState(normalized);
  if (remote === null) throw new Error("Connect the main tournament board first so it can initialize shared scoring.");
  accessCode = normalized;
  state = remote;
  localStorage.setItem(ACCESS_KEY, accessCode);
  accessPanel.hidden = true;
  scorerPanel.hidden = false;
  render();
  setConnectionStatus("Live sync connected", true);
}

function queueMutation(mutator, message, { celebrate = false, divisionId = selectedDivision } = {}) {
  actionQueue = actionQueue.then(async () => {
    actionInFlight = true;
    const previousWinner = division(divisionId).live?.winnerTeamId;
    let next = mutator(state);
    state = next;
    render();
    setConnectionStatus("Saving score…", true);
    let result = await saveTournamentState(state, accessCode);
    if (result.conflict) {
      state = result.state;
      next = mutator(state);
      state = next;
      render();
      result = await saveTournamentState(state, accessCode);
      if (result.conflict) {
        state = result.state;
        render();
        throw new Error("The score changed elsewhere. The latest shared score was loaded.");
      }
    }
    setConnectionStatus("Live sync connected", true);
    if (message) announce(message);
    const newWinner = division(divisionId).live?.winnerTeamId;
    if (celebrate && !previousWinner && newWinner) openWinnerDialog(divisionId);
  }).catch((error) => {
    setConnectionStatus("Sync needs attention");
    showToast(error.message, "error");
  }).finally(() => { actionInFlight = false; });
}

function openConfirm(title, message, label, callback) {
  document.querySelector("#phone-confirm-title").textContent = title;
  document.querySelector("#phone-confirm-message").textContent = message;
  document.querySelector("#phone-confirm-action").textContent = label;
  confirmCallback = callback;
  confirmDialog.showModal();
}

function openWinnerDialog(divisionId = selectedDivision) {
  const current = division(divisionId);
  const live = current.live;
  const winner = team(live.winnerTeamId, divisionId);
  const sideA = team(live.teamAId, divisionId);
  const sideB = team(live.teamBId, divisionId);
  winnerDialog.dataset.division = divisionId;
  document.querySelector("#phone-winner-kicker").textContent = `${current.label} winning point reached`;
  document.querySelector("#phone-winner-title").textContent = `Congratulations, ${winner.name}!`;
  document.querySelector("#phone-winner-message").textContent = `${winner.name} have reached the winning score with the required ${live.winBy}-point margin.`;
  document.querySelector("#phone-winner-side-a").textContent = sideA.name;
  document.querySelector("#phone-winner-side-b").textContent = sideB.name;
  document.querySelector("#phone-winner-final-score").textContent = `${live.scores[live.teamAId]}–${live.scores[live.teamBId]}`;
  winnerDialog.showModal();
  document.querySelector("#phone-record-result").focus();
}

accessForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const button = accessForm.querySelector("button");
  button.disabled = true;
  try { await connect(new FormData(accessForm).get("accessCode")); } catch (error) { setConnectionStatus("Not connected"); showToast(error.message, "error"); } finally { button.disabled = false; }
});

document.querySelectorAll("[data-select-division]").forEach((button) => {
  button.addEventListener("click", () => {
    selectedDivision = button.dataset.selectDivision;
    localStorage.setItem(DIVISION_KEY, selectedDivision);
    render();
    announce(`${division().label} division selected.`);
  });
});

phoneApp.addEventListener("change", (event) => {
  if (["phone-team-a", "phone-team-b"].includes(event.target.id)) updateStartServingOptions();
  if (event.target.matches("[data-phone-server]")) {
    const divisionId = selectedDivision;
    const currentLive = division(divisionId).live;
    const servingTeam = division(divisionId).teams.find((item) => item.id === currentLive.servingTeamId);
    const playerName = teamPlayerNames(servingTeam.name)[Number(event.target.value) - 1];
    queueMutation((current) => setLiveServerNumber(current, divisionId, Number(event.target.value)), `${playerName} is serving.`, { divisionId });
  }
});

phoneApp.addEventListener("submit", (event) => {
  if (event.target.id !== "phone-match-form") return;
  event.preventDefault();
  const divisionId = selectedDivision;
  const data = new FormData(event.target);
  queueMutation((current) => startMatch(current, divisionId, { teamAId: data.get("teamAId"), teamBId: data.get("teamBId"), targetPoints: Number(data.get("targetPoints")), winBy: Number(data.get("winBy")), servingTeamId: data.get("servingTeamId"), serverNumber: 2 }), `${division(divisionId).label} match started.`, { divisionId });
});

phoneApp.addEventListener("click", (event) => {
  const button = event.target.closest("[data-phone-action]");
  if (!button) return;
  const divisionId = selectedDivision;
  const action = button.dataset.phoneAction;
  if (action === "score") queueMutation((current) => adjustLiveScore(current, divisionId, button.dataset.team, Number(button.dataset.delta)), `${team(button.dataset.team, divisionId).name}: score updated.`, { celebrate: true, divisionId });
  if (action === "serve") queueMutation((current) => setLiveServer(current, divisionId, button.dataset.team), `${team(button.dataset.team, divisionId).name} is serving.`, { divisionId });
  if (action === "undo") queueMutation((current) => undoLive(current, divisionId), "Last live-score change undone.", { divisionId });
  if (action === "reset") openConfirm("Reset this score?", "Both teams return to zero. You can undo the reset afterward.", "Reset score", () => queueMutation((current) => resetLiveScore(current, divisionId), "Score reset.", { divisionId }));
  if (action === "end") openConfirm("End this match?", "The live score will be discarded and will not affect standings.", "End match", () => queueMutation((current) => discardLiveMatch(current, divisionId), "Live match ended.", { divisionId }));
  if (action === "record") openWinnerDialog(divisionId);
});

document.querySelectorAll("[data-close-phone-dialog]").forEach((button) => button.addEventListener("click", () => button.closest("dialog").close()));
document.querySelector("#phone-confirm-action").addEventListener("click", () => { const callback = confirmCallback; confirmCallback = null; confirmDialog.close(); callback?.(); });
document.querySelector("#phone-record-result").addEventListener("click", () => {
  const divisionId = winnerDialog.dataset.division;
  winnerDialog.close();
  queueMutation((current) => recordLiveResult(current, divisionId), `${division(divisionId).label} result recorded and standings updated.`, { divisionId });
});

document.querySelector("#phone-disconnect-button").addEventListener("click", () => {
  accessCode = ""; state = null; localStorage.removeItem(ACCESS_KEY); scorerPanel.hidden = true; accessPanel.hidden = false; document.querySelector("#phone-access-code").value = ""; setConnectionStatus("Not connected");
});

async function pollRemote() {
  if (!accessCode || !state || actionInFlight || document.querySelector("dialog[open]")) return;
  try {
    const remote = await fetchTournamentState(accessCode);
    if (remote && isStateNewer(remote, state)) { state = remote; render(); announce("Score updated from the tournament board."); }
    setConnectionStatus("Live sync connected", true);
  } catch { setConnectionStatus("Sync offline"); }
}

if (location.protocol !== "https:" && location.hostname !== "localhost" && location.hostname !== "127.0.0.1") document.querySelector("#phone-https-note").classList.add("warning");
if (accessCode) connect(accessCode).catch(() => { accessCode = ""; localStorage.removeItem(ACCESS_KEY); setConnectionStatus("Not connected"); });
window.setInterval(() => { void pollRemote(); }, POLL_MS);
