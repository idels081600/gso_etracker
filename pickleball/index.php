<?php

declare(strict_types=1);

$app = [
    'name' => 'Pickleball Tournament Board',
    'description' => 'Girls, boys, and mixed doubles live pickleball scoring with court schedules and automatic division standings.',
    'version' => '5.2.6',
];

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-cache');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ffffff">
  <meta name="description" content="<?= escape($app['description']) ?>">
  <meta name="application-name" content="<?= escape($app['name']) ?>">
  <title><?= escape($app['name']) ?></title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='15' fill='%23147a3f'/%3E%3Ccircle cx='11' cy='12' r='2' fill='%23ffffff'/%3E%3Ccircle cx='21' cy='14' r='2' fill='%23ffffff'/%3E%3Ccircle cx='16' cy='22' r='2' fill='%23ffffff'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="styles.css?v=<?= escape($app['version']) ?>">
</head>
<body class="tournament-body" data-app-version="<?= escape($app['version']) ?>">
  <header class="tournament-header" id="tournament-header-content">
    <div class="brand" aria-label="Pickleball Tournament Board">
      <span class="brand-mark" aria-hidden="true"><span></span></span>
      <span><strong>Pickleball</strong><small>Tournament Board</small></span>
    </div>
    <div class="tournament-title">
      <strong>Live Tournament Control</strong>
      <span id="saved-state-label">Game Master sign-in required</span>
    </div>
    <nav class="tournament-tools" aria-label="Tournament tools">
      <button class="mini-button" id="add-scoreboard-button" type="button">+ Scoreboard</button>
      <button class="mini-button" id="schedules-button" type="button">Schedules</button>
      <a class="mini-button" href="mobile-scorer.php">Phone scorer</a>
      <a class="mini-button" href="single-match.php">Single scorer</a>
      <button class="mini-button" id="fullscreen-button" type="button">Fullscreen</button>
      <button class="mini-button danger-text" id="game-master-sign-out" type="button" hidden>Sign out</button>
      <button class="mini-button nav-minimize-button" id="header-toggle" type="button" aria-expanded="true" aria-controls="tournament-header-content">Minimize</button>
    </nav>
  </header>

  <main id="tournament-app" tabindex="-1"></main>
  <div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="true"></div>
  <div class="sr-only" id="score-announcer" aria-live="assertive" aria-atomic="true"></div>

  <dialog id="scoreboard-dialog" class="app-dialog" aria-labelledby="scoreboard-dialog-title">
    <div class="dialog-heading-row">
      <div><div class="dialog-kicker">New live court</div><h2 id="scoreboard-dialog-title">Add scoreboard</h2></div>
      <button class="icon-button" type="button" data-close-dialog aria-label="Close scoreboard setup">×</button>
    </div>
    <form id="scoreboard-form">
      <label class="field"><span>Scoreboard name</span><input id="scoreboard-name" name="name" maxlength="40" placeholder="Court 3" autocomplete="off"></label>
      <fieldset class="setup-choice division-choice"><legend>Division</legend><label><input type="radio" name="divisionId" value="girls" checked><span>Girls</span></label><label><input type="radio" name="divisionId" value="boys"><span>Boys</span></label><label><input type="radio" name="divisionId" value="mixed"><span>Mixed Doubles</span></label></fieldset>
      <fieldset class="setup-choice"><legend>Match format</legend><label><input type="radio" name="matchType" value="doubles" checked><span>Doubles</span></label><label><input type="radio" name="matchType" value="singles"><span>Singles</span></label></fieldset>
      <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Cancel</button><button class="button button-primary" type="submit">Add scoreboard</button></div>
    </form>
  </dialog>
  <dialog id="match-dialog" class="app-dialog app-dialog-wide" aria-labelledby="match-dialog-title">
    <div class="dialog-heading-row">
      <div><div class="dialog-kicker" id="match-dialog-kicker">Division</div><h2 id="match-dialog-title">Start match</h2></div>
      <button class="icon-button" type="button" data-close-dialog aria-label="Close match setup">×</button>
    </div>
    <form id="match-form">
      <input type="hidden" id="match-scoreboard" name="scoreboardId">
      <div class="dialog-form-grid tournament-dialog-grid">
        <label class="field"><span id="match-side-a-label">Side A</span><select id="match-team-a" name="teamAId"></select></label>
        <label class="field"><span id="match-side-b-label">Side B</span><select id="match-team-b" name="teamBId"></select></label>
        <label class="field"><span>Points to win</span><select id="match-target" name="targetPoints"><option value="11">11 points</option><option value="15">15 points</option><option value="21">21 points</option></select></label>
        <label class="field"><span>Winning margin</span><select id="match-win-by" name="winBy"><option value="2">Win by 2</option><option value="1">Win by 1</option></select></label>
        <label class="field dialog-span-two"><span>First serving team</span><select id="match-serving-team" name="servingTeamId"></select></label>
      </div>
      <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Cancel</button><button class="button button-primary" type="submit">Start live match</button></div>
    </form>
  </dialog>

  <dialog id="schedule-dialog" class="app-dialog schedule-modal" aria-labelledby="schedule-dialog-title">
    <div class="dialog-heading-row">
      <div><div class="dialog-kicker">Tournament games</div><h2 id="schedule-dialog-title">Court schedules</h2></div>
      <button class="icon-button" type="button" data-close-dialog aria-label="Close schedules">×</button>
    </div>
    <div class="schedule-dialog-tabs" role="tablist" aria-label="Schedule division"><button type="button" role="tab" data-schedule-division="girls" aria-selected="true">Girls</button><button type="button" role="tab" data-schedule-division="boys" aria-selected="false">Boys</button><button type="button" role="tab" data-schedule-division="mixed" aria-selected="false">Mixed Doubles</button></div>
    <div class="schedule-scroll schedule-modal-scroll"><table class="schedule-table"><thead><tr><th>Game #</th><th>Team A</th><th>Score A</th><th>Team B</th><th>Score B</th><th>Winner</th><th>Status</th><th>Notes</th></tr></thead><tbody id="schedule-modal-body"></tbody></table></div>
    <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Close</button></div>
  </dialog>
  <dialog id="standings-dialog" class="app-dialog standings-modal" aria-labelledby="standings-dialog-title">
    <div class="dialog-heading-row standings-modal-heading">
      <div><div class="dialog-kicker" id="standings-dialog-kicker">Division standings</div><h2 id="standings-dialog-title">Live Standings</h2></div>
      <div class="standings-view-switch" role="group" aria-label="Ranking display controls">
        <button class="standings-fullscreen-button" id="standings-fullscreen-button" type="button">Full screen</button>
        <button type="button" data-standings-view="styled" aria-pressed="true">Display</button>
        <button type="button" data-standings-view="detailed" aria-pressed="false">Details</button>
      </div>
      <button class="icon-button" type="button" data-close-dialog aria-label="Close live standings">×</button>
    </div>
    <div class="standings-modal-summary" id="standings-modal-summary"></div>
    <div class="standings-scroll standings-modal-scroll">
      <table class="standings-table">
        <colgroup id="standings-table-columns"></colgroup>
        <thead><tr id="standings-table-heading">
          <th>Rank</th><th id="standings-participant-heading">Team</th><th>Score</th>
        </tr></thead>
        <tbody id="standings-modal-body"></tbody>
      </table>
    </div>
    <details class="completed-results" aria-labelledby="completed-results-title">
      <summary class="completed-results-heading"><div><span>Game Master tools</span><h3 id="completed-results-title">Results &amp; corrections</h3></div><strong id="completed-results-count">0 results</strong></summary>
      <div class="completed-results-list" id="completed-results-list"></div>
    </details>
    <div class="standings-modal-actions">
      <button class="button button-secondary" type="button" data-close-dialog>Close</button>
    </div>
  </dialog>

  <dialog id="confirm-dialog" class="app-dialog" aria-labelledby="confirm-title">
    <div class="dialog-kicker">Please confirm</div>
    <h2 id="confirm-title">Confirm action</h2>
    <p id="confirm-message"></p>
    <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Cancel</button><button class="button button-danger" id="confirm-action" type="button">Confirm</button></div>
  </dialog>

  <dialog id="role-dialog" class="app-dialog role-dialog" aria-labelledby="role-dialog-title">
    <div class="role-mark" aria-hidden="true">GM</div>
    <div class="dialog-kicker">Tournament authority</div>
    <h2 id="role-dialog-title">Game Master sign-in</h2>
    <p>Sign in to assign courts, start games, review rankings, and correct completed scores.</p>
    <form id="role-form">
      <label class="field"><span>Game Master access code</span><input id="role-access-code" name="accessCode" type="password" autocomplete="current-password" maxlength="64" required></label>
      <button class="button button-primary" type="submit">Enter tournament control</button>
    </form>
    <p class="dialog-note">Live scoring is handled from the Remote Scorer page.</p>
  </dialog>

  <dialog id="edit-result-dialog" class="app-dialog" aria-labelledby="edit-result-title">
    <div class="dialog-heading-row">
      <div><div class="dialog-kicker">Completed game correction</div><h2 id="edit-result-title">Edit final score</h2></div>
      <button class="icon-button" type="button" data-close-dialog aria-label="Close final score editor">×</button>
    </div>
    <p id="edit-result-matchup"></p>
    <form id="edit-result-form">
      <input type="hidden" id="edit-result-division" name="divisionId">
      <input type="hidden" id="edit-result-id" name="resultId">
      <div class="edit-result-score-grid">
        <label class="field"><span id="edit-result-team-a">Team A</span><input id="edit-result-score-a" name="scoreA" type="number" min="0" step="1" required></label>
        <label class="field"><span id="edit-result-team-b">Team B</span><input id="edit-result-score-b" name="scoreB" type="number" min="0" step="1" required></label>
      </div>
      <p class="dialog-note" id="edit-result-rule">The corrected score must satisfy the original winning condition.</p>
      <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Cancel</button><button class="button button-primary" type="submit">Save corrected score</button></div>
    </form>
  </dialog>

  <script type="module" src="js-module.php?module=tournament-app&amp;v=<?= escape($app['version']) ?>"></script>
</body>
</html>
