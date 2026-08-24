<?php

declare(strict_types=1);

$app = [
    'name' => 'Pickleball Tournament Board',
    'description' => 'Girls and boys live pickleball scoring with court schedules and automatic division standings.',
    'version' => '4.0.0',
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
  <meta name="theme-color" content="#0b0d0c">
  <meta name="description" content="<?= escape($app['description']) ?>">
  <meta name="application-name" content="<?= escape($app['name']) ?>">
  <title><?= escape($app['name']) ?></title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='15' fill='%23e7ee55'/%3E%3Ccircle cx='11' cy='12' r='2' fill='%23171900'/%3E%3Ccircle cx='21' cy='14' r='2' fill='%23171900'/%3E%3Ccircle cx='16' cy='22' r='2' fill='%23171900'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="styles.css?v=<?= escape($app['version']) ?>">
</head>
<body class="tournament-body" data-app-version="<?= escape($app['version']) ?>">
  <header class="tournament-header">
    <div class="brand" aria-label="Pickleball Tournament Board">
      <span class="brand-mark" aria-hidden="true"><span></span></span>
      <span><strong>Pickleball</strong><small>Tournament Board</small></span>
    </div>
    <div class="tournament-title">
      <strong>Live Tournament Control</strong>
      <span id="saved-state-label">Saved locally · Phone sync off</span>
    </div>
    <nav class="tournament-tools" aria-label="Tournament tools">
      <button class="mini-button" id="add-scoreboard-button" type="button">+ Scoreboard</button>
      <button class="mini-button" id="schedules-button" type="button">Schedules</button>
      <a class="mini-button" href="mobile-scorer.php">Phone scorer</a>
      <button class="mini-button" id="sync-settings-button" type="button">Connect phone</button>
      <a class="mini-button" href="single-match.php">Single scorer</a>
      <button class="mini-button" id="fullscreen-button" type="button">Fullscreen</button>
      <button class="mini-button danger-text" id="reset-tournament-button" type="button">Reset board</button>
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
      <fieldset class="setup-choice"><legend>Division</legend><label><input type="radio" name="divisionId" value="girls" checked><span>Girls</span></label><label><input type="radio" name="divisionId" value="boys"><span>Boys</span></label></fieldset>
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

  <dialog id="team-dialog" class="app-dialog app-dialog-wide" aria-labelledby="team-dialog-title">
    <div class="dialog-heading-row">
      <div><div class="dialog-kicker" id="team-dialog-kicker">Division</div><h2 id="team-dialog-title">Manage teams</h2></div>
      <button class="icon-button" type="button" data-close-dialog aria-label="Close team manager">×</button>
    </div>
    <form id="team-form" class="add-team-form">
      <input type="hidden" id="team-division" name="divisionId">
      <label class="field"><span>New team</span><input id="team-name" name="teamName" maxlength="64" autocomplete="off" required></label>
      <button class="button button-primary" type="submit">Add team</button>
    </form>
    <ul class="team-manager-list" id="team-list"></ul>
    <p class="dialog-note">Teams with a live or recorded match are locked to protect the standings.</p>
  </dialog>

  <dialog id="schedule-dialog" class="app-dialog schedule-modal" aria-labelledby="schedule-dialog-title">
    <div class="dialog-heading-row">
      <div><div class="dialog-kicker">Tournament games</div><h2 id="schedule-dialog-title">Court schedules</h2></div>
      <button class="icon-button" type="button" data-close-dialog aria-label="Close schedules">×</button>
    </div>
    <div class="schedule-dialog-tabs" role="tablist" aria-label="Schedule division"><button type="button" role="tab" data-schedule-division="girls" aria-selected="true">Girls</button><button type="button" role="tab" data-schedule-division="boys" aria-selected="false">Boys</button></div>
    <div class="schedule-scroll schedule-modal-scroll"><table class="schedule-table"><thead><tr><th>Game #</th><th>Team A</th><th>Score A</th><th>Team B</th><th>Score B</th><th>Winner</th><th>Status</th><th>Notes</th></tr></thead><tbody id="schedule-modal-body"></tbody></table></div>
    <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Close</button></div>
  </dialog>
  <dialog id="standings-dialog" class="app-dialog standings-modal" aria-labelledby="standings-dialog-title">
    <div class="dialog-heading-row standings-modal-heading">
      <div><div class="dialog-kicker" id="standings-dialog-kicker">Division standings</div><h2 id="standings-dialog-title">Live Standings</h2></div>
      <button class="icon-button" type="button" data-close-dialog aria-label="Close live standings">×</button>
    </div>
    <div class="standings-modal-summary" id="standings-modal-summary"></div>
    <div class="standings-scroll standings-modal-scroll">
      <table class="standings-table">
        <thead><tr>
          <th>Rank</th><th id="standings-participant-heading">Team</th><th>P</th><th>W</th><th>L</th><th>PF</th><th>PA</th><th>Diff</th><th>H2H</th><th>Tie-break <small>W / H2H / Diff / PF</small></th><th>Award</th>
        </tr></thead>
        <tbody id="standings-modal-body"></tbody>
      </table>
    </div>
    <div class="standings-modal-actions">
      <button class="button button-secondary" id="standings-undo-result" type="button">Undo latest result</button>
      <button class="button button-secondary" id="standings-manage-teams" type="button">Manage teams</button>
      <button class="button button-primary" id="standings-toggle-final" type="button">Finalize awards</button>
      <button class="button button-secondary" type="button" data-close-dialog>Close</button>
    </div>
  </dialog>

  <dialog id="confirm-dialog" class="app-dialog" aria-labelledby="confirm-title">
    <div class="dialog-kicker">Please confirm</div>
    <h2 id="confirm-title">Confirm action</h2>
    <p id="confirm-message"></p>
    <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Cancel</button><button class="button button-danger" id="confirm-action" type="button">Confirm</button></div>
  </dialog>

  <dialog id="sync-dialog" class="app-dialog" aria-labelledby="sync-dialog-title">
    <div class="dialog-heading-row">
      <div><div class="dialog-kicker">Shared scoring</div><h2 id="sync-dialog-title">Connect phone scorer</h2></div>
      <button class="icon-button" type="button" data-close-dialog aria-label="Close phone connection">×</button>
    </div>
    <p>Enter the same private access code on this board and on each scorer phone.</p>
    <form id="sync-form">
      <label class="field sync-code-field"><span>Scorer access code</span><input id="sync-access-code" name="accessCode" type="password" autocomplete="current-password" maxlength="64" required></label>
      <div class="dialog-actions">
        <button class="button button-secondary" id="sync-disconnect-button" type="button">Disconnect</button>
        <button class="button button-primary" type="submit">Connect phone</button>
      </div>
    </form>
    <p class="dialog-note">Use HTTPS on a public website so the access code and scores are encrypted in transit.</p>
  </dialog>

  <dialog id="winner-dialog" class="app-dialog celebration-dialog" aria-labelledby="winner-dialog-title" aria-describedby="winner-dialog-message">
    <div class="winner-icon" aria-hidden="true">✓</div>
    <div class="dialog-kicker" id="winner-dialog-kicker">Winning point reached</div>
    <h2 id="winner-dialog-title">Congratulations!</h2>
    <p id="winner-dialog-message"></p>
    <div class="winner-score-summary" id="winner-score-summary">
      <span id="winner-side-a-name">Side A</span>
      <strong id="winner-final-score">0–0</strong>
      <span id="winner-side-b-name">Side B</span>
    </div>
    <p class="dialog-note">The standings have not changed yet. Review the score or record the result.</p>
    <div class="dialog-actions">
      <button class="button button-secondary" type="button" data-close-dialog>Review score</button>
      <button class="button button-primary" id="winner-record-action" type="button">Record result</button>
    </div>
  </dialog>

  <script type="module" src="js-module.php?module=tournament-app&amp;v=<?= escape($app['version']) ?>"></script>
</body>
</html>
