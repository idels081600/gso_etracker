import {
  addTeam,
  adjustLiveScore,
  createTournament,
  deserializeTournament,
  discardLiveMatch,
  getSchedule,
  getStandings,
  liveCourtOrder,
  recordLiveResult,
  removeTeam,
  resetLiveScore,
  serializeTournament,
  setDivisionFinalized,
  setLiveServer,
  setLiveServerNumber,
  servingPlayerName,
  startMatch,
  swapLiveCourt,
  teamPlayerNames,
  undoLastResult,
  undoLive,
} from "./tournament-engine.mjs";
import { fetchTournamentState, isStateNewer, normalizeAccessCode, saveTournamentState } from "./tournament-sync.mjs";

const STORAGE_KEY = "pickleball.tournamentBoard.v2";
const LEGACY_STORAGE_KEY = "pickleball.tournamentBoard.v1";
const SYNC_ACCESS_KEY = "pickleball.tournamentSyncAccess.v1";
const SYNC_POLL_MS = 900;
const DIVISIONS = ["girls", "boys"];

const app = document.querySelector("#tournament-app");
const toastRegion = document.querySelector("#toast-region");
const announcer = document.querySelector("#score-announcer");
const matchDialog = document.querySelector("#match-dialog");
const teamDialog = document.querySelector("#team-dialog");
const confirmDialog = document.querySelector("#confirm-dialog");
const winnerDialog = document.querySelector("#winner-dialog");
const syncDialog = document.querySelector("#sync-dialog");
const standingsDialog = document.querySelector("#standings-dialog");
const matchForm = document.querySelector("#match-form");
const teamForm = document.querySelector("#team-form");
const syncForm = document.querySelector("#sync-form");

let hadStoredState = false;
let state = loadState();
let confirmCallback = null;
let syncAccessCode = normalizeAccessCode(localStorage.getItem(SYNC_ACCESS_KEY));
let syncWriting = false;
let syncQueued = false;

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function loadState() {
  try {
    const currentRecord = localStorage.getItem(STORAGE_KEY);
    const legacyRecord = currentRecord === null ? localStorage.getItem(LEGACY_STORAGE_KEY) : null;
    hadStoredState = currentRecord !== null || legacyRecord !== null;
    const result = deserializeTournament(currentRecord ?? legacyRecord);
    if (!result.ok) {
      localStorage.removeItem(STORAGE_KEY);
      window.setTimeout(() => showToast("Saved tournament data was unreadable and has been reset.", "error"), 0);
    } else if (result.migrated) {
      localStorage.setItem(STORAGE_KEY, serializeTournament(result.data));
      localStorage.removeItem(LEGACY_STORAGE_KEY);
      window.setTimeout(() => showToast("Boys roster added. Existing tournament data was preserved."), 0);
    }
    return result.data;
  } catch {
    window.setTimeout(() => showToast("Browser storage is unavailable; changes may not survive a reload.", "error"), 0);
    return createTournament();
  }
}

function persistLocal() {
  try {
    localStorage.setItem(STORAGE_KEY, serializeTournament(state));
  } catch {
    showToast("Unable to save this change in the browser.", "error");
  }
}

function persist() {
  persistLocal();
  queueRemoteSave();
}

function setSyncStatus(message, connected = false) {
  document.querySelector("#saved-state-label").textContent = message;
  document.querySelector("#sync-settings-button").textContent = connected ? "Phone connected" : "Connect phone";
}

async function flushRemoteSave({ throwOnError = false } = {}) {
  if (!syncAccessCode || syncWriting || !syncQueued) return;
  syncWriting = true;
  syncQueued = false;
  const snapshot = state;
  try {
    const result = await saveTournamentState(snapshot, syncAccessCode);
    if (result.conflict && isStateNewer(result.state, state)) {
      state = result.state;
      persistLocal();
      render();
      showToast("A newer phone score was loaded.", "info");
    }
    setSyncStatus("Saved online · Phone sync active", true);
  } catch (error) {
    setSyncStatus("Saved locally · Phone sync offline", false);
    if (throwOnError) throw error;
  } finally {
    syncWriting = false;
    if (syncQueued) void flushRemoteSave();
  }
}

function queueRemoteSave() {
  if (!syncAccessCode) return;
  syncQueued = true;
  void flushRemoteSave();
}

async function reconcileRemoteState({ initial = false } = {}) {
  if (!syncAccessCode || syncWriting || (!initial && document.querySelector("dialog[open]:not(#standings-dialog)"))) return;
  try {
    const remote = await fetchTournamentState(syncAccessCode);
    if (remote === null) {
      syncQueued = true;
      await flushRemoteSave({ throwOnError: initial });
      return;
    }
    if ((initial && !hadStoredState) || isStateNewer(remote, state)) {
      state = remote;
      persistLocal();
      render();
      if (!initial) announce("Score updated from a connected phone.");
    } else if (isStateNewer(state, remote)) {
      syncQueued = true;
      await flushRemoteSave();
    }
    setSyncStatus("Saved online · Phone sync active", true);
  } catch (error) {
    setSyncStatus("Saved locally · Phone sync offline", false);
    if (initial) throw error;
  }
}

async function connectSharedScoring(accessCode) {
  syncAccessCode = normalizeAccessCode(accessCode);
  if (!syncAccessCode) throw new Error("Enter the scorer access code.");
  await reconcileRemoteState({ initial: true });
  localStorage.setItem(SYNC_ACCESS_KEY, syncAccessCode);
  syncDialog.close();
  showToast("Phone scorer connected.");
}

function showToast(message, type = "info") {
  const toast = document.createElement("div");
  toast.className = `toast ${type === "error" ? "error" : ""}`;
  toast.textContent = message;
  toastRegion.append(toast);
  window.setTimeout(() => toast.remove(), 3400);
}

function announce(message) {
  announcer.textContent = "";
  window.setTimeout(() => { announcer.textContent = message; }, 20);
}

function division(divisionId) {
  return state.divisions[divisionId];
}

function team(divisionId, teamId) {
  return division(divisionId).teams.find((item) => item.id === teamId);
}

function apply(next, message) {
  state = next;
  persist();
  render();
  if (message) announce(message);
}

function formatDiff(value) {
  return value > 0 ? `+${value}` : String(value);
}

function liveTeamMarkup(divisionId, teamId, side) {
  const live = division(divisionId).live;
  const currentTeam = team(divisionId, teamId);
  const serving = live.servingTeamId === teamId;
  const activeServer = serving ? servingPlayerName(currentTeam.name, live.serverNumber) : "";
  return `
    <div class="compact-score-side side-${teamId === live.teamAId ? "a" : "b"} ${serving ? "is-serving" : ""}">
      <div class="compact-team-head">
        <div>
          <span>Court ${side}</span>
          <strong title="${escapeHtml(currentTeam.name)}">${escapeHtml(currentTeam.name)}</strong>
        </div>
        <button class="serve-toggle ${serving ? "active" : ""}" type="button" data-action="set-server" data-division="${divisionId}" data-team="${teamId}" aria-pressed="${serving}" aria-label="${serving ? `${escapeHtml(activeServer)} is serving for ${escapeHtml(currentTeam.name)}` : `Set ${escapeHtml(currentTeam.name)} as serving team`}">
          ${serving ? `<span class="serve-player-name">${escapeHtml(activeServer)}</span><span class="serve-player-state">Serving</span>` : "Set serve"}
        </button>
      </div>
      <div class="compact-score-controls">
        <button type="button" data-action="score" data-division="${divisionId}" data-team="${teamId}" data-delta="-1" aria-label="Subtract one from ${escapeHtml(currentTeam.name)}">−</button>
        <strong data-live-score="${divisionId}-${teamId}">${live.scores[teamId]}</strong>
        <button class="add" type="button" data-action="score" data-division="${divisionId}" data-team="${teamId}" data-delta="1" aria-label="Add one to ${escapeHtml(currentTeam.name)}">+</button>
      </div>
    </div>`;
}

function scoringPanel(divisionId) {
  const current = division(divisionId);
  const live = current.live;
  const enoughTeams = current.teams.length >= 2;
  const colorLabel = divisionId === "girls" ? "Girls division" : "Boys division";
  if (!live) {
    return `
      <section class="dashboard-quadrant scoring-quadrant division-${divisionId}" aria-labelledby="${divisionId}-scoring-title">
        <header class="quadrant-header">
          <div><span>${colorLabel}</span><h2 id="${divisionId}-scoring-title">${current.label} Live Scoring</h2></div>
          <button class="mini-button" type="button" data-action="manage-teams" data-division="${divisionId}">Manage teams</button>
        </header>
        <div class="quadrant-empty scoring-empty">
          <div class="empty-score-mark">0<span>–</span>0</div>
          <strong>No live ${current.label.toLowerCase()} match</strong>
          <p>${enoughTeams ? "Choose two teams to begin courtside scoring." : `Add at least two ${current.label.toLowerCase()} teams before starting.`}</p>
          <div class="empty-actions">
            <button class="button button-secondary" type="button" data-action="manage-teams" data-division="${divisionId}">Add team</button>
            <button class="button button-primary" type="button" data-action="new-match" data-division="${divisionId}" ${enoughTeams ? "" : "disabled"}>Start match</button>
          </div>
        </div>
      </section>`;
  }
  const [leftTeamId, rightTeamId] = liveCourtOrder(live);
  const winner = live.winnerTeamId ? team(divisionId, live.winnerTeamId) : null;
  const servingScore = live.scores[live.servingTeamId];
  const receivingId = live.servingTeamId === live.teamAId ? live.teamBId : live.teamAId;
  const scoreCall = `${servingScore} – ${live.scores[receivingId]} – ${live.serverNumber}`;
  const servingPlayers = teamPlayerNames(team(divisionId, live.servingTeamId).name);
  return `
    <section class="dashboard-quadrant scoring-quadrant division-${divisionId}" aria-labelledby="${divisionId}-scoring-title">
      <header class="quadrant-header">
        <div><span>${colorLabel} · To ${live.targetPoints}, win by ${live.winBy}</span><h2 id="${divisionId}-scoring-title">${current.label} Live Scoring</h2></div>
        <div class="header-actions">
          <button class="mini-button" type="button" data-action="undo-live" data-division="${divisionId}" ${live.undoStack.length ? "" : "disabled"}>Undo</button>
          <button class="mini-button" type="button" data-action="swap-court" data-division="${divisionId}" aria-label="Swap the left and right court sides with their scores">⇄ Swap courts</button>
          <button class="mini-button" type="button" data-action="new-match" data-division="${divisionId}">New matchup</button>
        </div>
      </header>
      <div class="compact-scoreboard">
        ${liveTeamMarkup(divisionId, leftTeamId, "Left")}
        ${liveTeamMarkup(divisionId, rightTeamId, "Right")}
      </div>
      <footer class="compact-score-footer">
        <div class="score-call-compact"><span>Score call</span><strong>${scoreCall}</strong></div>
        <fieldset class="inline-server-choice">
          <legend>Serving player</legend>
          ${servingPlayers.map((playerName, index) => {
            const number = index + 1;
            const selected = live.serverNumber === number;
            return `<label><input type="radio" name="${divisionId}ServerNumber" value="${number}" data-action="server-number" data-division="${divisionId}" aria-label="Set ${escapeHtml(playerName)} as serving player" ${selected ? "checked" : ""}><span><strong>${escapeHtml(playerName)}</strong><small>${selected ? "Serving" : "Select"}</small></span></label>`;
          }).join("")}
        </fieldset>
        <button class="mini-button" type="button" data-action="reset-live" data-division="${divisionId}">Reset</button>
        <button class="mini-button danger-text" type="button" data-action="discard-live" data-division="${divisionId}">End</button>
        <button class="button ${winner ? "button-primary" : "button-secondary"}" type="button" data-action="record-result" data-division="${divisionId}" ${winner ? "" : "disabled"}>
          ${winner ? `Record ${escapeHtml(winner.name)} win` : "Awaiting winner"}
        </button>
      </footer>
    </section>`;
}

function standingsRows(divisionId) {
  const rows = getStandings(state, divisionId);
  if (!rows.length) {
    return `<tr><td colspan="11" class="table-empty">No teams added yet.</td></tr>`;
  }
  return rows.map((row) => `
    <tr class="${row.rank === 1 ? "rank-first" : ""}">
      <td class="rank-cell">${row.rank ?? "—"}</td>
      <td class="team-cell" title="${escapeHtml(row.name)}">${escapeHtml(row.name)}</td>
      <td>${row.played}</td>
      <td>${row.wins}</td>
      <td>${row.losses}</td>
      <td>${row.pointsFor}</td>
      <td>${row.pointsAgainst}</td>
      <td class="diff-${row.pointDiff > 0 ? "positive" : row.pointDiff < 0 ? "negative" : "zero"}">${formatDiff(row.pointDiff)}</td>
      <td>${row.h2hWins}</td>
      <td class="tiebreak-cell">${row.tieBreakScore ?? "—"}</td>
      <td class="award-cell">${row.award || "—"}</td>
    </tr>`).join("");
}

function scheduleStatusMarkup(row, divisionId) {
  if (row.status === "live") return '<span class="schedule-status status-live">Live</span>';
  if (row.status === "complete") return '<span class="schedule-status status-complete">Complete</span>';
  const disabled = row.teamAId && row.teamBId ? "" : "disabled";
  return `<button class="schedule-status status-pending" type="button" data-action="start-scheduled" data-division="${divisionId}" data-team-a="${row.teamAId ?? ""}" data-team-b="${row.teamBId ?? ""}" ${disabled} title="Start game ${row.gameNumber}">Pending</button>`;
}

function scheduleRows(divisionId) {
  return getSchedule(state, divisionId).map((row) => `
    <tr class="schedule-row status-${row.status}">
      <td class="game-number-cell">${row.gameNumber}</td>
      <td class="schedule-team-cell" title="${escapeHtml(row.teamAName)}">${escapeHtml(row.teamAName)}</td>
      <td class="schedule-score-cell">${row.scoreA ?? "—"}</td>
      <td class="schedule-team-cell" title="${escapeHtml(row.teamBName)}">${escapeHtml(row.teamBName)}</td>
      <td class="schedule-score-cell">${row.scoreB ?? "—"}</td>
      <td class="schedule-winner-cell" title="${escapeHtml(row.winnerName)}">${escapeHtml(row.winnerName) || "—"}</td>
      <td>${scheduleStatusMarkup(row, divisionId)}</td>
      <td class="schedule-notes-cell">${escapeHtml(row.notes) || "—"}</td>
    </tr>`).join("");
}

function schedulePanel(divisionId) {
  const current = division(divisionId);
  const schedule = getSchedule(state, divisionId);
  const complete = schedule.filter((row) => row.status === "complete").length;
  const courtNumber = divisionId === "girls" ? 1 : 2;
  return `
    <section class="dashboard-quadrant schedule-quadrant division-${divisionId}" aria-labelledby="${divisionId}-schedule-title">
      <header class="quadrant-header schedule-header">
        <div><span>Avengers 3rd Pickleball Tournament</span><h2 id="${divisionId}-schedule-title">Court ${courtNumber} · ${current.label} Games</h2></div>
        <div class="header-actions">
          <span class="schedule-progress">${complete}/${schedule.length}</span>
          <button class="mini-button standings-button" type="button" data-action="view-standings" data-division="${divisionId}">View Live Standings</button>
        </div>
      </header>
      <div class="schedule-scroll">
        <table class="schedule-table">
          <thead><tr>
            <th>Game #</th><th>Team A</th><th>Score A</th><th>Team B</th><th>Score B</th><th>Winner</th><th>Status</th><th>Notes</th>
          </tr></thead>
          <tbody>${scheduleRows(divisionId)}</tbody>
        </table>
      </div>
    </section>`;
}

function renderStandingsDialog(divisionId) {
  const current = division(divisionId);
  const latest = current.results.at(-1);
  const latestWinner = latest ? team(divisionId, latest.winnerTeamId) : null;
  standingsDialog.dataset.division = divisionId;
  standingsDialog.classList.toggle("division-girls", divisionId === "girls");
  standingsDialog.classList.toggle("division-boys", divisionId === "boys");
  document.querySelector("#standings-dialog-kicker").textContent = `${current.teams.length} teams · ${current.results.length} results`;
  document.querySelector("#standings-dialog-title").textContent = `${current.label} Live Standings`;
  document.querySelector("#standings-modal-summary").innerHTML = latest
    ? `<span>Latest result</span><strong>${escapeHtml(latestWinner?.name ?? "Winner")}</strong><b>${latest.scoreA}–${latest.scoreB}</b>`
    : `<span>Live table</span><strong>No results recorded yet</strong><b>0 games</b>`;
  document.querySelector("#standings-modal-body").innerHTML = standingsRows(divisionId);
  document.querySelector("#standings-undo-result").disabled = !current.results.length;
  document.querySelector("#standings-toggle-final").disabled = !current.results.length;
  document.querySelector("#standings-toggle-final").textContent = current.finalized ? "Reopen awards" : "Finalize awards";
}

function openStandingsDialog(divisionId) {
  renderStandingsDialog(divisionId);
  standingsDialog.showModal();
  standingsDialog.querySelector("[data-close-dialog]").focus();
}

function render() {
  app.innerHTML = `
    <div class="tournament-grid">
      ${scoringPanel("girls")}
      ${scoringPanel("boys")}
      ${schedulePanel("girls")}
      ${schedulePanel("boys")}
    </div>`;
  if (standingsDialog.open && standingsDialog.dataset.division) renderStandingsDialog(standingsDialog.dataset.division);
}

function openMatchDialog(divisionId, selectedTeamAId = null, selectedTeamBId = null) {
  const current = division(divisionId);
  document.querySelector("#match-division").value = divisionId;
  document.querySelector("#match-dialog-kicker").textContent = `${current.label} division`;
  document.querySelector("#match-dialog-title").textContent = `Start ${current.label.toLowerCase()} match`;
  const options = current.teams.map((item) => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join("");
  document.querySelector("#match-team-a").innerHTML = options;
  document.querySelector("#match-team-b").innerHTML = options;
  if (selectedTeamAId && current.teams.some((item) => item.id === selectedTeamAId)) document.querySelector("#match-team-a").value = selectedTeamAId;
  if (selectedTeamBId && current.teams.some((item) => item.id === selectedTeamBId)) document.querySelector("#match-team-b").value = selectedTeamBId;
  else document.querySelector("#match-team-b").selectedIndex = Math.min(1, current.teams.length - 1);
  document.querySelector("#match-target").value = "11";
  document.querySelector("#match-win-by").value = "2";
  updateServingOptions();
  matchDialog.showModal();
}

function updateServingOptions() {
  const divisionId = document.querySelector("#match-division").value;
  const teamAId = document.querySelector("#match-team-a").value;
  const teamBId = document.querySelector("#match-team-b").value;
  const serving = document.querySelector("#match-serving-team");
  serving.innerHTML = [teamAId, teamBId]
    .filter((id, index, ids) => id && ids.indexOf(id) === index)
    .map((id) => `<option value="${id}">${escapeHtml(team(divisionId, id)?.name || "Team")}</option>`)
    .join("");
}

function renderTeamManager(divisionId) {
  const current = division(divisionId);
  document.querySelector("#team-division").value = divisionId;
  document.querySelector("#team-dialog-kicker").textContent = `${current.label} division`;
  document.querySelector("#team-dialog-title").textContent = `Manage ${current.label.toLowerCase()} teams`;
  document.querySelector("#team-name").placeholder = current.label === "Girls" ? "Player 1 & Player 2" : "Boys team name";
  document.querySelector("#team-list").innerHTML = current.teams.length
    ? current.teams.map((item) => {
        const locked = Boolean(current.live && [current.live.teamAId, current.live.teamBId].includes(item.id))
          || current.results.some((result) => [result.teamAId, result.teamBId].includes(item.id));
        return `<li><span>${escapeHtml(item.name)}</span><button type="button" data-action="remove-team" data-division="${divisionId}" data-team="${item.id}" ${locked ? "disabled title=\"Team has match data\"" : ""} aria-label="Remove ${escapeHtml(item.name)}">×</button></li>`;
      }).join("")
    : '<li class="team-list-empty">No teams added.</li>';
}

function openTeamDialog(divisionId) {
  renderTeamManager(divisionId);
  document.querySelector("#team-name").value = "";
  teamDialog.showModal();
  document.querySelector("#team-name").focus();
}

function openConfirm({ title, message, label, onConfirm }) {
  document.querySelector("#confirm-title").textContent = title;
  document.querySelector("#confirm-message").textContent = message;
  document.querySelector("#confirm-action").textContent = label;
  confirmCallback = onConfirm;
  confirmDialog.showModal();
}

function openWinnerAlert(divisionId) {
  const current = division(divisionId);
  const live = current.live;
  if (!live?.winnerTeamId) return;
  const winner = team(divisionId, live.winnerTeamId);
  const sideA = team(divisionId, live.teamAId);
  const sideB = team(divisionId, live.teamBId);
  const scoreA = live.scores[live.teamAId];
  const scoreB = live.scores[live.teamBId];

  winnerDialog.dataset.division = divisionId;
  document.querySelector("#winner-dialog-kicker").textContent = `${current.label} winning point reached`;
  document.querySelector("#winner-dialog-title").textContent = `Congratulations, ${winner.name}!`;
  document.querySelector("#winner-dialog-message").textContent = `${winner.name} have reached the winning score with the required ${live.winBy}-point margin.`;
  document.querySelector("#winner-side-a-name").textContent = sideA.name;
  document.querySelector("#winner-side-b-name").textContent = sideB.name;
  document.querySelector("#winner-final-score").textContent = `${scoreA}–${scoreB}`;
  document.querySelector("#winner-score-summary").setAttribute("aria-label", `${sideA.name} ${scoreA}, ${sideB.name} ${scoreB}`);
  winnerDialog.showModal();
  document.querySelector("#winner-record-action").focus();
}

app.addEventListener("click", (event) => {
  const button = event.target.closest("button[data-action]");
  if (!button) return;
  const action = button.dataset.action;
  const divisionId = button.dataset.division;

  try {
    if (action === "score") {
      const previousWinner = division(divisionId).live?.winnerTeamId;
      const next = adjustLiveScore(state, divisionId, button.dataset.team, Number(button.dataset.delta));
      const newWinner = next.divisions[divisionId].live?.winnerTeamId;
      apply(next, `${team(divisionId, button.dataset.team).name}: score updated.`);
      if (!previousWinner && newWinner) openWinnerAlert(divisionId);
    }
    if (action === "set-server") {
      apply(setLiveServer(state, divisionId, button.dataset.team), `${team(divisionId, button.dataset.team).name} is serving.`);
    }
    if (action === "swap-court") {
      apply(swapLiveCourt(state, divisionId), `${division(divisionId).label} teams and scores swapped court sides.`);
    }
    if (action === "undo-live") {
      const next = undoLive(state, divisionId);
      if (next === state) showToast("Nothing to undo."); else apply(next, `${division(divisionId).label} score change undone.`);
    }
    if (action === "reset-live") {
      openConfirm({ title: `Reset ${division(divisionId).label.toLowerCase()} score?`, message: "Both scores will return to zero. This can be undone from the scoring panel.", label: "Reset score", onConfirm: () => apply(resetLiveScore(state, divisionId), "Score reset to zero.") });
    }
    if (action === "discard-live") {
      openConfirm({ title: `End ${division(divisionId).label.toLowerCase()} match?`, message: "The current score will be discarded and will not affect the standings.", label: "End match", onConfirm: () => apply(discardLiveMatch(state, divisionId), "Live match discarded.") });
    }
    if (action === "record-result") {
      const live = division(divisionId).live;
      const winner = team(divisionId, live.winnerTeamId);
      openConfirm({ title: `Record ${winner.name} as winner?`, message: "This result will immediately update the game schedule and live standings.", label: "Record result", onConfirm: () => apply(recordLiveResult(state, divisionId), `${winner.name} win recorded.`) });
    }
    if (action === "new-match") {
      if (division(divisionId).live) {
        openConfirm({ title: "Replace the live matchup?", message: "The current unrecorded score will be discarded.", label: "Replace matchup", onConfirm: () => openMatchDialog(divisionId) });
      } else openMatchDialog(divisionId);
    }
    if (action === "start-scheduled") {
      const openScheduledMatch = () => openMatchDialog(divisionId, button.dataset.teamA, button.dataset.teamB);
      if (division(divisionId).live) {
        openConfirm({ title: "Replace the live matchup?", message: `Start game ${button.closest("tr")?.querySelector(".game-number-cell")?.textContent ?? ""} from the court schedule instead. The current unrecorded score will be discarded.`, label: "Replace matchup", onConfirm: openScheduledMatch });
      } else openScheduledMatch();
    }
    if (action === "view-standings") openStandingsDialog(divisionId);
    if (action === "manage-teams") openTeamDialog(divisionId);
    if (action === "undo-result") {
      openConfirm({ title: `Undo latest ${division(divisionId).label.toLowerCase()} result?`, message: "The latest recorded result will be removed and the standings recalculated.", label: "Undo result", onConfirm: () => apply(undoLastResult(state, divisionId), "Latest result removed.") });
    }
    if (action === "toggle-final") {
      const current = division(divisionId);
      apply(setDivisionFinalized(state, divisionId, !current.finalized), current.finalized ? "Awards reopened." : `${current.label} awards finalized.`);
    }
  } catch (error) {
    showToast(error.message, "error");
  }
});

document.querySelector("#standings-undo-result").addEventListener("click", () => {
  const divisionId = standingsDialog.dataset.division;
  const current = division(divisionId);
  if (!current.results.length) return;
  openConfirm({ title: `Undo latest ${current.label.toLowerCase()} result?`, message: "The latest recorded result will be removed, the schedule will return to pending, and the standings will be recalculated.", label: "Undo result", onConfirm: () => apply(undoLastResult(state, divisionId), "Latest result removed.") });
});

document.querySelector("#standings-toggle-final").addEventListener("click", () => {
  const divisionId = standingsDialog.dataset.division;
  try {
    const current = division(divisionId);
    apply(setDivisionFinalized(state, divisionId, !current.finalized), current.finalized ? "Awards reopened." : `${current.label} awards finalized.`);
  } catch (error) {
    showToast(error.message, "error");
  }
});

document.querySelector("#standings-manage-teams").addEventListener("click", () => {
  const divisionId = standingsDialog.dataset.division;
  standingsDialog.close();
  openTeamDialog(divisionId);
});

standingsDialog.addEventListener("close", () => {
  delete standingsDialog.dataset.division;
});

app.addEventListener("change", (event) => {
  if (event.target.matches('input[data-action="server-number"]')) {
    try {
      const divisionId = event.target.dataset.division;
      const currentLive = division(divisionId).live;
      const playerName = teamPlayerNames(team(divisionId, currentLive.servingTeamId).name)[Number(event.target.value) - 1];
      apply(setLiveServerNumber(state, divisionId, Number(event.target.value)), `${playerName} is serving.`);
    } catch (error) {
      showToast(error.message, "error");
    }
  }
});

matchForm.addEventListener("change", (event) => {
  if (["match-team-a", "match-team-b"].includes(event.target.id)) updateServingOptions();
});

matchForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const data = new FormData(matchForm);
  const divisionId = data.get("divisionId");
  try {
    state = startMatch(state, divisionId, {
      teamAId: data.get("teamAId"),
      teamBId: data.get("teamBId"),
      targetPoints: Number(data.get("targetPoints")),
      winBy: Number(data.get("winBy")),
      servingTeamId: data.get("servingTeamId"),
      serverNumber: 2,
    });
    persist();
    matchDialog.close();
    render();
    announce(`${division(divisionId).label} match started.`);
  } catch (error) {
    showToast(error.message, "error");
  }
});

teamForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const data = new FormData(teamForm);
  const divisionId = data.get("divisionId");
  try {
    state = addTeam(state, divisionId, data.get("teamName"));
    persist();
    render();
    renderTeamManager(divisionId);
    document.querySelector("#team-name").value = "";
    document.querySelector("#team-name").focus();
    showToast("Team added.");
  } catch (error) {
    showToast(error.message, "error");
  }
});

document.querySelector("#team-list").addEventListener("click", (event) => {
  const button = event.target.closest('button[data-action="remove-team"]');
  if (!button) return;
  try {
    state = removeTeam(state, button.dataset.division, button.dataset.team);
    persist();
    render();
    renderTeamManager(button.dataset.division);
    showToast("Team removed.");
  } catch (error) {
    showToast(error.message, "error");
  }
});

document.querySelectorAll("[data-close-dialog]").forEach((button) => {
  button.addEventListener("click", () => button.closest("dialog").close());
});

document.querySelector("#confirm-action").addEventListener("click", () => {
  const callback = confirmCallback;
  confirmCallback = null;
  confirmDialog.close();
  try { callback?.(); } catch (error) { showToast(error.message, "error"); }
});

document.querySelector("#sync-settings-button").addEventListener("click", () => {
  document.querySelector("#sync-access-code").value = syncAccessCode;
  document.querySelector("#sync-disconnect-button").hidden = !syncAccessCode;
  syncDialog.showModal();
  document.querySelector("#sync-access-code").focus();
});

syncForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const submitButton = syncForm.querySelector('button[type="submit"]');
  submitButton.disabled = true;
  try {
    await connectSharedScoring(new FormData(syncForm).get("accessCode"));
  } catch (error) {
    showToast(error.message, "error");
  } finally {
    submitButton.disabled = false;
  }
});

document.querySelector("#sync-disconnect-button").addEventListener("click", () => {
  syncAccessCode = "";
  syncQueued = false;
  localStorage.removeItem(SYNC_ACCESS_KEY);
  syncDialog.close();
  setSyncStatus("Saved locally · Phone sync off", false);
  showToast("Phone scorer disconnected.");
});

document.querySelector("#winner-record-action").addEventListener("click", () => {
  const divisionId = winnerDialog.dataset.division;
  try {
    const live = division(divisionId).live;
    const winner = team(divisionId, live.winnerTeamId);
    state = recordLiveResult(state, divisionId);
    persist();
    winnerDialog.close();
    render();
    announce(`${winner.name} win recorded. ${division(divisionId).label} standings updated.`);
    showToast(`${winner.name} win recorded.`);
  } catch (error) {
    showToast(error.message, "error");
  }
});

winnerDialog.addEventListener("close", () => {
  delete winnerDialog.dataset.division;
});

document.querySelector("#fullscreen-button").addEventListener("click", async () => {
  try {
    if (document.fullscreenElement) await document.exitFullscreen();
    else await document.documentElement.requestFullscreen();
  } catch {
    showToast("Fullscreen is unavailable in this browser.", "error");
  }
});

document.querySelector("#reset-tournament-button").addEventListener("click", () => {
  openConfirm({
    title: "Reset the entire tournament board?",
    message: "All live scores, recorded results, and awards will be removed. The original girls and boys team lists will be restored.",
    label: "Reset tournament",
    onConfirm: () => {
      state = createTournament();
      persist();
      render();
      showToast("Tournament board reset.");
    },
  });
});

render();
if (syncAccessCode) {
  void reconcileRemoteState({ initial: true }).catch((error) => showToast(error.message, "error"));
}
window.setInterval(() => { void reconcileRemoteState(); }, SYNC_POLL_MS);
