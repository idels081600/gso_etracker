import {
  adjustScore,
  buildHistoryRecord,
  cancelPendingWinner,
  confirmGame,
  correctLastGame,
  createMatch,
  deserializeActiveMatch,
  deserializeHistory,
  gameWins,
  getPlayer,
  getScoreCall,
  isTraditionalDoubles,
  resetGame,
  serializeActiveMatch,
  serializeHistory,
  setActiveServer,
  setServerNumber,
  setServingTeam,
  startNextGame,
  switchEnds,
  undo,
  winsNeeded,
} from "./scoring-engine.mjs";

const ACTIVE_KEY = "pickleball.activeMatch.v1";
const HISTORY_KEY = "pickleball.matchHistory.v1";

const app = document.querySelector("#app");
const toastRegion = document.querySelector("#toast-region");
const announcer = document.querySelector("#score-announcer");
const resumeDialog = document.querySelector("#resume-dialog");
const winnerDialog = document.querySelector("#winner-dialog");
const nextGameDialog = document.querySelector("#next-game-dialog");
const confirmDialog = document.querySelector("#confirm-dialog");
const helpDialog = document.querySelector("#help-dialog");

let match = null;
let history = [];
let view = "setup";
let confirmCallback = null;

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function plural(number, singular, pluralForm = `${singular}s`) {
  return `${number} ${number === 1 ? singular : pluralForm}`;
}

function formatScoringMode(settings) {
  return settings.scoringMode === "sideout" ? "Side-out scoring" : "Rally scoring";
}

function formatMatchType(settings) {
  return settings.matchType === "doubles" ? "Doubles" : "Singles";
}

function formatDate(iso) {
  return new Intl.DateTimeFormat(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(iso));
}

function formatDuration(durationMs) {
  const totalMinutes = Math.max(0, Math.round(durationMs / 60000));
  if (totalMinutes < 60) return plural(totalMinutes, "min");
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  return `${hours}h ${minutes}m`;
}

function safeStorage(action) {
  try {
    return action();
  } catch {
    showToast("Browser storage is unavailable. This match will not survive a reload.", "error");
    return null;
  }
}

function persistMatch() {
  if (!match) return;
  safeStorage(() => localStorage.setItem(ACTIVE_KEY, serializeActiveMatch(match)));
}

function clearPersistedMatch() {
  safeStorage(() => localStorage.removeItem(ACTIVE_KEY));
}

function persistHistory() {
  safeStorage(() => localStorage.setItem(HISTORY_KEY, serializeHistory(history)));
  updateNavigation();
}

function showToast(message, type = "info") {
  const toast = document.createElement("div");
  toast.className = `toast ${type === "error" ? "error" : ""}`;
  toast.textContent = message;
  toastRegion.append(toast);
  window.setTimeout(() => toast.remove(), 3600);
}

function announce(message) {
  announcer.textContent = "";
  window.setTimeout(() => {
    announcer.textContent = message;
  }, 20);
}

function updateNavigation() {
  document.querySelector("#history-count").textContent = history.length;
  const currentButton = document.querySelector('[data-nav="current"]');
  currentButton.hidden = !match;
  document.querySelectorAll("[data-nav]").forEach((button) => {
    const destination = button.dataset.nav;
    const active =
      (destination === "setup" && view === "setup") ||
      (destination === "history" && view === "history") ||
      (destination === "current" && ["scorer", "summary"].includes(view));
    if (active) button.setAttribute("aria-current", "page");
    else button.removeAttribute("aria-current");
  });
}

function render() {
  if (view === "scorer" && match) renderScorer();
  else if (view === "summary" && match) renderSummary();
  else if (view === "history") renderHistory();
  else renderSetup();
  updateNavigation();
}

function setupMarkup() {
  return `
    <section class="page setup-page" aria-labelledby="setup-title">
      <div class="page-heading">
        <div>
          <div class="eyebrow">New match</div>
          <h1 id="setup-title">Set the court. Keep the score.</h1>
          <p>Choose the format and starting positions, then run the whole match from one screen.</p>
        </div>
      </div>

      <form class="setup-form" id="match-setup-form">
        <section class="setup-section" aria-labelledby="format-heading">
          <div class="section-heading">
            <h2 id="format-heading">Match format</h2>
            <p>Defaults follow a standard doubles match.</p>
          </div>
          <div class="setup-grid">
            <fieldset class="field-group">
              <legend>Players</legend>
              <div class="segment segment-two">
                <label><input type="radio" name="matchType" value="singles"><span>Singles</span></label>
                <label><input type="radio" name="matchType" value="doubles" checked><span>Doubles</span></label>
              </div>
            </fieldset>
            <fieldset class="field-group">
              <legend>Scoring method</legend>
              <div class="segment segment-two">
                <label><input type="radio" name="scoringMode" value="sideout" checked><span>Side-out</span></label>
                <label><input type="radio" name="scoringMode" value="rally"><span>Rally</span></label>
              </div>
            </fieldset>
            <fieldset class="field-group">
              <legend>Points per game</legend>
              <div class="segment">
                <label><input type="radio" name="targetPoints" value="11" checked><span>11</span></label>
                <label><input type="radio" name="targetPoints" value="15"><span>15</span></label>
                <label><input type="radio" name="targetPoints" value="21"><span>21</span></label>
              </div>
            </fieldset>
            <fieldset class="field-group">
              <legend>Win condition</legend>
              <div class="segment segment-two">
                <label><input type="radio" name="winBy" value="1"><span>Win by 1</span></label>
                <label><input type="radio" name="winBy" value="2" checked><span>Win by 2</span></label>
              </div>
            </fieldset>
          </div>
          <div class="setup-grid" style="margin-top:16px">
            <fieldset class="field-group">
              <legend>Match length</legend>
              <div class="segment">
                <label><input type="radio" name="bestOf" value="1"><span>1 game</span></label>
                <label><input type="radio" name="bestOf" value="3" checked><span>Best of 3</span></label>
                <label><input type="radio" name="bestOf" value="5"><span>Best of 5</span></label>
              </div>
            </fieldset>
          </div>
        </section>

        <section class="setup-section" aria-labelledby="players-heading">
          <div class="section-heading">
            <h2 id="players-heading">Players and positions</h2>
            <p>Names are optional; clear labels will be supplied automatically.</p>
          </div>
          <div class="setup-grid two-column">
            <div class="team-setup team-a">
              <h3>Team A</h3>
              <div class="player-fields">
                <label class="field">
                  <span>Player 1</span>
                  <input name="aPlayer1" id="a-player-1" autocomplete="off" placeholder="Player 1" maxlength="32">
                </label>
                <label class="field" data-doubles-only>
                  <span>Player 2</span>
                  <input name="aPlayer2" id="a-player-2" autocomplete="off" placeholder="Player 2" maxlength="32">
                </label>
              </div>
              <label class="field position-field" data-doubles-only>
                <span>Player starting on right</span>
                <select name="aRightPlayer" id="a-right-player"></select>
              </label>
            </div>
            <div class="team-setup team-b">
              <h3>Team B</h3>
              <div class="player-fields">
                <label class="field">
                  <span>Player 1</span>
                  <input name="bPlayer1" id="b-player-1" autocomplete="off" placeholder="Player 3" maxlength="32">
                </label>
                <label class="field" data-doubles-only>
                  <span>Player 2</span>
                  <input name="bPlayer2" id="b-player-2" autocomplete="off" placeholder="Player 4" maxlength="32">
                </label>
              </div>
              <label class="field position-field" data-doubles-only>
                <span>Player starting on right</span>
                <select name="bRightPlayer" id="b-right-player"></select>
              </label>
            </div>
          </div>
        </section>

        <section class="setup-section" aria-labelledby="serve-heading">
          <div class="section-heading">
            <h2 id="serve-heading">Opening serve</h2>
            <p>You can change all serve details during the match.</p>
          </div>
          <div class="setup-grid two-column">
            <fieldset class="field-group">
              <legend>Starting serving team</legend>
              <div class="segment segment-two" id="setup-serving-team">
                <label><input type="radio" name="servingTeamId" value="A" checked><span id="setup-team-a-label">Team A</span></label>
                <label><input type="radio" name="servingTeamId" value="B"><span id="setup-team-b-label">Team B</span></label>
              </div>
            </fieldset>
            <label class="field">
              <span>Starting server</span>
              <select name="activeServerPlayerId" id="setup-active-server"></select>
            </label>
          </div>
        </section>

        <div class="setup-footer">
          <div class="setup-summary" id="setup-summary"></div>
          <button class="button button-primary button-large" type="submit">Start match</button>
        </div>
      </form>
    </section>`;
}

function renderSetup() {
  app.innerHTML = setupMarkup();
  bindSetupDynamicEvents();
  updateSetupDynamic();
}

function setupPlayerName(teamId, index) {
  const input = document.querySelector(`#${teamId.toLowerCase()}-player-${index + 1}`);
  const matchType = document.querySelector('input[name="matchType"]:checked')?.value || "doubles";
  const count = matchType === "doubles" ? 2 : 1;
  const fallbackIndex = teamId === "A" ? index + 1 : count + index + 1;
  return input?.value.trim() || `Player ${fallbackIndex}`;
}

function setupTeamLabel(teamId) {
  const matchType = document.querySelector('input[name="matchType"]:checked')?.value || "doubles";
  const names = [setupPlayerName(teamId, 0)];
  if (matchType === "doubles") names.push(setupPlayerName(teamId, 1));
  return names.join(" & ");
}

function replaceOptions(select, options, preferredValue) {
  if (!select) return;
  const previous = preferredValue || select.value;
  select.innerHTML = options
    .map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`)
    .join("");
  if (options.some((option) => option.value === previous)) select.value = previous;
}

function updateSetupDynamic() {
  const form = document.querySelector("#match-setup-form");
  if (!form) return;
  const data = new FormData(form);
  const isDoubles = data.get("matchType") === "doubles";
  document.querySelectorAll("[data-doubles-only]").forEach((element) => {
    element.hidden = !isDoubles;
  });

  const teamAPlayers = [{ value: "A1", label: setupPlayerName("A", 0) }];
  const teamBPlayers = [{ value: "B1", label: setupPlayerName("B", 0) }];
  if (isDoubles) {
    teamAPlayers.push({ value: "A2", label: setupPlayerName("A", 1) });
    teamBPlayers.push({ value: "B2", label: setupPlayerName("B", 1) });
  }
  replaceOptions(document.querySelector("#a-right-player"), teamAPlayers);
  replaceOptions(document.querySelector("#b-right-player"), teamBPlayers);

  document.querySelector("#setup-team-a-label").textContent = setupTeamLabel("A");
  document.querySelector("#setup-team-b-label").textContent = setupTeamLabel("B");
  const servingTeamId = data.get("servingTeamId") || "A";
  replaceOptions(
    document.querySelector("#setup-active-server"),
    servingTeamId === "A" ? teamAPlayers : teamBPlayers,
  );

  const target = data.get("targetPoints");
  const bestOf = data.get("bestOf");
  const winBy = data.get("winBy");
  const type = isDoubles ? "Doubles" : "Singles";
  const scoring = data.get("scoringMode") === "sideout" ? "side-out" : "rally";
  document.querySelector("#setup-summary").innerHTML =
    `<strong>${type}</strong> · ${scoring} · best of ${bestOf} · to ${target}, win by ${winBy}`;
}

function bindSetupDynamicEvents() {
  const form = document.querySelector("#match-setup-form");
  form.addEventListener("input", updateSetupDynamic);
  form.addEventListener("change", updateSetupDynamic);
  form.addEventListener("submit", handleSetupSubmit);
}

function handleSetupSubmit(event) {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const matchType = data.get("matchType");
  const teams = {
    A: { players: [{ name: data.get("aPlayer1") }] },
    B: { players: [{ name: data.get("bPlayer1") }] },
  };
  if (matchType === "doubles") {
    teams.A.players.push({ name: data.get("aPlayer2") });
    teams.B.players.push({ name: data.get("bPlayer2") });
  }

  match = createMatch({
    matchType,
    scoringMode: data.get("scoringMode"),
    targetPoints: Number(data.get("targetPoints")),
    winBy: Number(data.get("winBy")),
    bestOf: Number(data.get("bestOf")),
    teams,
    servingTeamId: data.get("servingTeamId"),
    activeServerPlayerId: data.get("activeServerPlayerId"),
    serverNumber: 2,
    positions: {
      A: { right: data.get("aRightPlayer") || "A1" },
      B: { right: data.get("bRightPlayer") || "B1" },
    },
  });
  persistMatch();
  view = "scorer";
  render();
  app.focus();
  announce(`Match started. ${match.teams[match.live.servingTeamId].label} serving.`);
}

function playerName(playerId) {
  return getPlayer(match, playerId)?.name || "—";
}

function gameMarkers() {
  const wonByGame = new Map(match.games.map((game) => [game.number, game.winnerTeamId]));
  return Array.from({ length: match.settings.bestOf }, (_, index) => {
    const number = index + 1;
    const winner = wonByGame.get(number);
    const classes = [winner ? `won-${winner.toLowerCase()}` : "", number === match.live.number ? "current" : ""]
      .filter(Boolean)
      .join(" ");
    return `<span class="${classes}" title="Game ${number}${winner ? ` won by Team ${winner}` : ""}"></span>`;
  }).join("");
}

function teamPanel(teamId, controlsDisabled) {
  const team = match.teams[teamId];
  const score = match.live.scores[teamId];
  const serving = match.live.servingTeamId === teamId;
  const position = match.live.positions[teamId];
  return `
    <section class="team-score-panel team-${teamId.toLowerCase()} ${serving ? "is-serving" : ""}" aria-labelledby="team-${teamId}-name">
      <div class="team-panel-head">
        <div class="team-identity">
          <span class="team-letter">Team ${teamId}</span>
          <h2 class="team-name" id="team-${teamId}-name" title="${escapeHtml(team.label)}">${escapeHtml(team.label)}</h2>
        </div>
        ${serving ? '<span class="serve-badge">Serving</span>' : ""}
      </div>
      <div class="score-control">
        <button class="score-button subtract" type="button" data-action="adjust-score" data-team="${teamId}" data-delta="-1" aria-label="Subtract one point from ${escapeHtml(team.label)}" ${controlsDisabled ? "disabled" : ""}>−</button>
        <div class="score-value-wrap"><strong class="score-value" data-score-value="${teamId}">${score}</strong></div>
        <button class="score-button add" type="button" data-action="adjust-score" data-team="${teamId}" data-delta="1" aria-label="Add one point to ${escapeHtml(team.label)}" ${controlsDisabled ? "disabled" : ""}>+</button>
      </div>
      <div class="court-positions" aria-label="${escapeHtml(team.label)} court positions">
        <div class="court-position"><small>Left court</small><strong>${escapeHtml(playerName(position.left))}</strong></div>
        <div class="court-position"><small>Right court</small><strong>${escapeHtml(playerName(position.right))}</strong></div>
      </div>
    </section>`;
}

function renderScorer() {
  const scoreCall = getScoreCall(match);
  const servingPlayers = match.teams[match.live.servingTeamId].players;
  const controlsDisabled = match.status !== "active";
  const servingTeamOptions = ["A", "B"].map((teamId) => `
    <label>
      <input type="radio" name="liveServingTeam" value="${teamId}" ${match.live.servingTeamId === teamId ? "checked" : ""} ${controlsDisabled ? "disabled" : ""}>
      <span>${escapeHtml(match.teams[teamId].label)}</span>
    </label>`).join("");
  const serverOptions = servingPlayers.map((player) =>
    `<option value="${player.id}" ${match.live.activeServerPlayerId === player.id ? "selected" : ""}>${escapeHtml(player.name)}</option>`,
  ).join("");
  const wins = gameWins(match);

  app.innerHTML = `
    <section class="scoreboard-page" aria-label="Active match scoreboard">
      <div class="scoreboard-shell">
        <header class="match-strip">
          <div class="match-meta">
            <span class="status-chip">Game ${match.live.number}</span>
            <span>${formatMatchType(match.settings)} · ${formatScoringMode(match.settings)}</span>
          </div>
          <div class="game-marker" aria-label="Game progress">${gameMarkers()}</div>
          <div class="match-status">
            <span>First to ${winsNeeded(match)} game${winsNeeded(match) === 1 ? "" : "s"}</span>
            <span class="status-chip">${wins.A}–${wins.B}</span>
          </div>
        </header>

        <div class="score-grid">
          ${match.live.displayOrder.map((teamId) => teamPanel(teamId, controlsDisabled)).join("")}
        </div>

        ${match.status === "between-games" ? `
          <div class="between-game-notice">
            <span>Game ${match.games.length} is recorded. Choose the opening serve for game ${match.games.length + 1}.</span>
            <button class="button button-primary" type="button" data-action="open-next-game">Set up next game</button>
          </div>` : `
          <div class="serve-console">
            <div class="score-call-box">
              <div><span>Score call</span><strong>${scoreCall.label}</strong></div>
              <span>Serving side first</span>
            </div>
            <div class="serve-controls">
              <fieldset class="field-group">
                <legend class="control-title">Serving team</legend>
                <div class="segment segment-two">${servingTeamOptions}</div>
              </fieldset>
              <label class="field-group">
                <span class="control-title">Active server</span>
                <select class="control-select" id="live-active-server" ${controlsDisabled ? "disabled" : ""}>${serverOptions}</select>
              </label>
            </div>
            ${isTraditionalDoubles(match) ? `
              <fieldset class="server-control field-group">
                <legend class="control-title">Server number</legend>
                <div class="segment segment-two">
                  <label><input type="radio" name="liveServerNumber" value="1" ${match.live.serverNumber === 1 ? "checked" : ""} ${controlsDisabled ? "disabled" : ""}><span>Server 1</span></label>
                  <label><input type="radio" name="liveServerNumber" value="2" ${match.live.serverNumber === 2 ? "checked" : ""} ${controlsDisabled ? "disabled" : ""}><span>Server 2</span></label>
                </div>
              </fieldset>` : `
              <div class="server-control">
                <span class="control-title">Serve format</span>
                <strong>Single server</strong>
              </div>`}
          </div>`}

        <footer class="utility-bar">
          <div class="utility-group">
            <button class="button button-secondary" type="button" data-action="undo" ${!match.undoStack.length || controlsDisabled ? "disabled" : ""}>↶ <span class="button-label-optional">Undo</span></button>
            <button class="button button-secondary" type="button" data-action="switch-ends" ${controlsDisabled ? "disabled" : ""}>⇄ <span class="button-label-optional">Switch ends</span></button>
            <button class="button button-quiet" type="button" data-action="reset-game" ${controlsDisabled ? "disabled" : ""}>Reset game</button>
          </div>
          <div class="utility-group">
            <button class="button button-quiet" type="button" data-action="show-help">? <span class="button-label-optional">Shortcuts</span></button>
            <button class="button button-quiet" type="button" data-action="fullscreen">⛶ <span class="button-label-optional">Fullscreen</span></button>
            <button class="button button-quiet" type="button" data-action="end-match">End match</button>
          </div>
        </footer>
      </div>
    </section>`;
  updateNavigation();
}

function renderSummary() {
  const wins = gameWins(match);
  const winnerTeamId = wins.A > wins.B ? "A" : "B";
  app.innerHTML = `
    <section class="summary-page" aria-labelledby="summary-title">
      <div class="summary-panel">
        <header class="summary-head">
          <div class="panel-kicker">Match complete</div>
          <h1 id="summary-title">${escapeHtml(match.teams[winnerTeamId].label)} won the match</h1>
          <p class="summary-lead">Review the final scores before saving this result to the device.</p>
        </header>
        <div class="summary-winner">
          <div class="summary-team">
            <span>Team A</span><strong>${escapeHtml(match.teams.A.label)}</strong>
            <div class="summary-team-wins">${wins.A}</div>
          </div>
          <div class="summary-vs">GAMES</div>
          <div class="summary-team">
            <span>Team B</span><strong>${escapeHtml(match.teams.B.label)}</strong>
            <div class="summary-team-wins">${wins.B}</div>
          </div>
        </div>
        <div class="summary-games">
          ${match.games.map((game) => `
            <div class="summary-game-row">
              <span>${game.scores.A}</span><small>Game ${game.number}</small><span>${game.scores.B}</span>
            </div>`).join("")}
        </div>
        <footer class="summary-actions">
          <button class="button button-secondary" type="button" data-action="correct-last-game">Correct last game</button>
          <button class="button button-primary" type="button" data-action="save-result">Save result</button>
        </footer>
      </div>
    </section>`;
  updateNavigation();
}

function historyItem(record) {
  const winner = record.teams[record.winnerTeamId];
  const scores = record.games.map((game) => `<span class="game-score">${game.scores.A}–${game.scores.B}</span>`).join("");
  return `
    <article class="history-item winner-${record.winnerTeamId.toLowerCase()}">
      <div class="history-matchup">
        <strong>${escapeHtml(record.teams.A.label)} vs ${escapeHtml(record.teams.B.label)}</strong>
        <small>${formatMatchType(record.settings)} · ${formatScoringMode(record.settings)} · to ${record.settings.targetPoints}</small>
      </div>
      <div class="history-result">
        <strong>${escapeHtml(winner.label)} won</strong>
        <small>${plural(record.games.length, "game")} played</small>
      </div>
      <div>
        <div class="game-score-list" aria-label="Game scores">${scores}</div>
        <div class="history-meta" style="margin-top:6px">${formatDate(record.completedAt)} · ${formatDuration(record.durationMs)}</div>
      </div>
      <button class="icon-button" type="button" data-action="delete-history" data-id="${escapeHtml(record.id)}" aria-label="Delete match between ${escapeHtml(record.teams.A.label)} and ${escapeHtml(record.teams.B.label)}">×</button>
    </article>`;
}

function renderHistory() {
  app.innerHTML = `
    <section class="page history-page" aria-labelledby="history-title">
      <div class="page-heading">
        <div>
          <div class="eyebrow">This device</div>
          <h1 id="history-title">Match history</h1>
          <p>Completed results are stored only in this browser.</p>
        </div>
        ${history.length ? '<button class="button button-danger" type="button" data-action="clear-history">Clear history</button>' : ""}
      </div>
      ${history.length ? `<div class="history-list">${history.map(historyItem).join("")}</div>` : `
        <div class="empty-state">
          <div>
            <div class="empty-state-mark" aria-hidden="true">0</div>
            <h2>No completed matches yet</h2>
            <p>Saved match results will appear here with game scores and winners.</p>
            <button class="button button-primary" type="button" data-nav="setup">Set up a match</button>
          </div>
        </div>`}
    </section>`;
  updateNavigation();
}

function announceScore(teamId) {
  const team = match.teams[teamId];
  announce(`${team.label}: ${match.live.scores[teamId]}. Score ${match.live.scores.A} to ${match.live.scores.B}.`);
}

function animateScore(teamId) {
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  document.querySelector(`[data-score-value="${teamId}"]`)?.animate(
    [
      { opacity: 0.55, transform: "translateY(7px) scale(0.98)" },
      { opacity: 1, transform: "translateY(0) scale(1)" },
    ],
    { duration: 170, easing: "ease-out" },
  );
}

function applyMatchChange(nextMatch, { announcement, changedScoreTeam } = {}) {
  match = nextMatch;
  persistMatch();
  view = match.status === "awaiting-save" ? "summary" : "scorer";
  render();
  if (announcement) announce(announcement);
  if (changedScoreTeam) {
    announceScore(changedScoreTeam);
    animateScore(changedScoreTeam);
  }
  if (match.pendingWinner && view === "scorer") openWinnerDialog();
}

function openWinnerDialog() {
  if (!match?.pendingWinner || winnerDialog.open) return;
  const team = match.teams[match.pendingWinner];
  document.querySelector("#winner-title").textContent = `${team.label} reached the win condition`;
  document.querySelector("#winner-description").textContent =
    `The score is ${match.live.scores.A}–${match.live.scores.B}. Confirm Game ${match.live.number}, or keep editing the scoreboard.`;
  winnerDialog.showModal();
}

function updateNextServerOptions() {
  const teamId = document.querySelector('input[name="nextServingTeamId"]:checked')?.value || "A";
  const options = match.teams[teamId].players.map((player) => ({ value: player.id, label: player.name }));
  replaceOptions(document.querySelector("#next-active-server"), options);
}

function openNextGameDialog() {
  if (!match || match.status !== "between-games") return;
  const container = document.querySelector("#next-serving-team");
  container.innerHTML = ["A", "B"].map((teamId) => `
    <label>
      <input type="radio" name="nextServingTeamId" value="${teamId}" ${match.live.servingTeamId === teamId ? "checked" : ""}>
      <span>${escapeHtml(match.teams[teamId].label)}</span>
    </label>`).join("");
  document.querySelector("#next-server-number-group").hidden = !isTraditionalDoubles(match);
  document.querySelector("#next-swap-ends").checked = true;
  updateNextServerOptions();
  if (!nextGameDialog.open) nextGameDialog.showModal();
}

function openConfirm({ kicker = "Please confirm", title, message, confirmLabel, onConfirm }) {
  document.querySelector("#confirm-kicker").textContent = kicker;
  document.querySelector("#confirm-title").textContent = title;
  document.querySelector("#confirm-message").textContent = message;
  document.querySelector("#confirm-action-button").textContent = confirmLabel;
  confirmCallback = onConfirm;
  confirmDialog.showModal();
}

async function toggleFullscreen() {
  try {
    if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
    else await document.exitFullscreen();
  } catch {
    showToast("Fullscreen mode is not available in this browser.", "error");
  }
}

function abandonActiveMatch(destination = "setup") {
  if (!match) {
    view = destination;
    render();
    return;
  }
  openConfirm({
    kicker: "Unsaved match",
    title: "End the current match?",
    message: "The active match and its undo history will be removed. Completed history will not be affected.",
    confirmLabel: "End match",
    onConfirm: () => {
      match = null;
      clearPersistedMatch();
      view = destination;
      render();
      showToast("The active match was removed.");
    },
  });
}

function navigate(destination) {
  if (destination === "current") {
    if (!match) return;
    view = match.status === "awaiting-save" ? "summary" : "scorer";
    render();
    if (match.status === "between-games") window.setTimeout(openNextGameDialog, 0);
    return;
  }
  if (destination === "history") {
    view = "history";
    render();
    return;
  }
  if (destination === "setup") {
    if (match) abandonActiveMatch("setup");
    else {
      view = "setup";
      render();
    }
  }
}

app.addEventListener("click", (event) => {
  const button = event.target.closest("button");
  if (!button) return;
  const { action } = button.dataset;
  if (!action) return;

  if (action === "adjust-score") {
    const teamId = button.dataset.team;
    applyMatchChange(adjustScore(match, teamId, Number(button.dataset.delta)), { changedScoreTeam: teamId });
  }
  if (action === "undo") {
    const previous = match;
    const next = undo(match);
    if (next === previous) showToast("There is nothing to undo.");
    else applyMatchChange(next, { announcement: "Last change undone." });
  }
  if (action === "switch-ends") {
    applyMatchChange(switchEnds(match), { announcement: "Displayed court ends switched." });
  }
  if (action === "reset-game") {
    openConfirm({
      kicker: `Game ${match.live.number}`,
      title: "Reset this game?",
      message: "Both scores will return to zero. You can undo the reset immediately afterward.",
      confirmLabel: "Reset game",
      onConfirm: () => applyMatchChange(resetGame(match), { announcement: "Game scores reset to zero." }),
    });
  }
  if (action === "end-match") abandonActiveMatch("setup");
  if (action === "show-help") helpDialog.showModal();
  if (action === "fullscreen") toggleFullscreen();
  if (action === "open-next-game") openNextGameDialog();
  if (action === "correct-last-game") {
    applyMatchChange(correctLastGame(match), { announcement: "Last game reopened for correction." });
  }
  if (action === "save-result") {
    const record = buildHistoryRecord(match);
    history = [record, ...history.filter((item) => item.id !== record.id)];
    persistHistory();
    clearPersistedMatch();
    match = null;
    view = "history";
    render();
    showToast("Match result saved to this device.");
  }
  if (action === "delete-history") {
    const record = history.find((item) => item.id === button.dataset.id);
    if (!record) return;
    openConfirm({
      kicker: "History",
      title: "Delete this match result?",
      message: `${record.teams.A.label} vs ${record.teams.B.label} will be permanently removed from this browser.`,
      confirmLabel: "Delete result",
      onConfirm: () => {
        history = history.filter((item) => item.id !== record.id);
        persistHistory();
        renderHistory();
        showToast("Match result deleted.");
      },
    });
  }
  if (action === "clear-history") {
    openConfirm({
      kicker: "History",
      title: "Clear all match history?",
      message: `${plural(history.length, "saved result")} will be permanently removed from this browser.`,
      confirmLabel: "Clear history",
      onConfirm: () => {
        history = [];
        persistHistory();
        renderHistory();
        showToast("Match history cleared.");
      },
    });
  }
});

app.addEventListener("change", (event) => {
  if (!match || view !== "scorer") return;
  if (event.target.matches('input[name="liveServingTeam"]')) {
    const teamId = event.target.value;
    applyMatchChange(setServingTeam(match, teamId), {
      announcement: `${match.teams[teamId].label} is now serving.`,
    });
  }
  if (event.target.matches("#live-active-server")) {
    const player = getPlayer(match, event.target.value);
    applyMatchChange(setActiveServer(match, event.target.value), {
      announcement: `${player?.name || "Selected player"} is now serving.`,
    });
  }
  if (event.target.matches('input[name="liveServerNumber"]')) {
    applyMatchChange(setServerNumber(match, Number(event.target.value)), {
      announcement: `Server ${event.target.value} selected.`,
    });
  }
});

document.addEventListener("click", (event) => {
  const nav = event.target.closest("[data-nav]");
  if (nav) {
    event.preventDefault();
    navigate(nav.dataset.nav);
    return;
  }

  const actionButton = event.target.closest("[data-dialog-action]");
  if (!actionButton) return;
  const action = actionButton.dataset.dialogAction;
  if (action === "resume") {
    resumeDialog.close();
    view = match.status === "awaiting-save" ? "summary" : "scorer";
    render();
    if (match.status === "between-games") window.setTimeout(openNextGameDialog, 0);
  }
  if (action === "discard-resume") {
    resumeDialog.close();
    match = null;
    clearPersistedMatch();
    view = "setup";
    render();
    showToast("Recovered match discarded.");
  }
  if (action === "continue-editing") {
    winnerDialog.close();
    match = cancelPendingWinner(match);
    persistMatch();
    renderScorer();
  }
  if (action === "confirm-winner") {
    const winnerTeamId = match.pendingWinner;
    match = confirmGame(match, winnerTeamId);
    winnerDialog.close();
    persistMatch();
    if (match.status === "awaiting-save") {
      view = "summary";
      render();
      announce(`${match.teams[winnerTeamId].label} won the match.`);
    } else {
      view = "scorer";
      render();
      announce(`${match.teams[winnerTeamId].label} won game ${match.games.length}.`);
      window.setTimeout(openNextGameDialog, 0);
    }
  }
  if (action === "correct-recorded-game") {
    nextGameDialog.close();
    applyMatchChange(correctLastGame(match), { announcement: "Last game reopened for correction." });
  }
  if (action === "cancel-confirm") {
    confirmCallback = null;
    confirmDialog.close();
  }
  if (action === "close-help") helpDialog.close();
});

document.querySelector("#confirm-action-button").addEventListener("click", () => {
  const callback = confirmCallback;
  confirmCallback = null;
  confirmDialog.close();
  callback?.();
});

document.querySelector("#next-serving-team").addEventListener("change", updateNextServerOptions);

document.querySelector("#next-game-form").addEventListener("submit", (event) => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  match = startNextGame(match, {
    servingTeamId: data.get("nextServingTeamId"),
    activeServerPlayerId: data.get("nextActiveServer"),
    serverNumber: Number(data.get("nextServerNumber") || 1),
    swapEnds: document.querySelector("#next-swap-ends").checked,
  });
  nextGameDialog.close();
  persistMatch();
  view = "scorer";
  render();
  announce(`Game ${match.live.number} started. ${match.teams[match.live.servingTeamId].label} serving.`);
});

[resumeDialog, winnerDialog].forEach((dialog) => {
  dialog.addEventListener("cancel", (event) => event.preventDefault());
});

nextGameDialog.addEventListener("cancel", () => {
  window.setTimeout(() => showToast("Game setup is waiting. Use “Set up next game” when ready."), 0);
});

function shortcutBlocked(event) {
  if (event.ctrlKey || event.metaKey || event.altKey) return true;
  if (document.querySelector("dialog[open]")) return true;
  const target = event.target;
  return target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement || target.isContentEditable;
}

document.addEventListener("keydown", (event) => {
  if (shortcutBlocked(event) || !match || view !== "scorer") return;
  const key = event.key.toLowerCase();
  if (key === "f") {
    event.preventDefault();
    toggleFullscreen();
    return;
  }
  if (match.status !== "active") return;

  if (key === "1" || key === "2") {
    event.preventDefault();
    const teamId = match.live.displayOrder[key === "1" ? 0 : 1];
    applyMatchChange(adjustScore(match, teamId, event.shiftKey ? -1 : 1), { changedScoreTeam: teamId });
  }
  if (key === "s") {
    event.preventDefault();
    const teamId = match.live.servingTeamId === "A" ? "B" : "A";
    applyMatchChange(setServingTeam(match, teamId), {
      announcement: `${match.teams[teamId].label} is now serving.`,
    });
  }
  if (key === "u") {
    event.preventDefault();
    if (!match.undoStack.length) showToast("There is nothing to undo.");
    else applyMatchChange(undo(match), { announcement: "Last change undone." });
  }
  if (key === "e") {
    event.preventDefault();
    applyMatchChange(switchEnds(match), { announcement: "Displayed court ends switched." });
  }
});

function initialize() {
  const historyRaw = safeStorage(() => localStorage.getItem(HISTORY_KEY));
  const historyResult = deserializeHistory(historyRaw);
  if (historyResult.ok) history = historyResult.data;
  else {
    history = [];
    safeStorage(() => localStorage.removeItem(HISTORY_KEY));
    window.setTimeout(() => showToast("Saved history could not be read and was ignored.", "error"), 0);
  }

  const activeRaw = safeStorage(() => localStorage.getItem(ACTIVE_KEY));
  const activeResult = deserializeActiveMatch(activeRaw);
  if (activeResult.ok) match = activeResult.data;
  else {
    match = null;
    safeStorage(() => localStorage.removeItem(ACTIVE_KEY));
    window.setTimeout(() => showToast("The saved match could not be read and was ignored.", "error"), 0);
  }

  view = "setup";
  render();
  if (match) {
    document.querySelector("#resume-description").textContent =
      `${match.teams.A.label} vs ${match.teams.B.label} · Game ${match.live.number} · ${match.live.scores.A}–${match.live.scores.B}`;
    resumeDialog.showModal();
  }
}

initialize();
