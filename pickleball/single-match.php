<?php

declare(strict_types=1);

$app = [
    'name' => 'Pickleball Scorekeeper',
    'description' => 'An offline, manual pickleball match scorekeeper.',
    'version' => '1.0.0',
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
  <link rel="stylesheet" href="styles.css">
</head>
<body data-app-version="<?= escape($app['version']) ?>">
  <header class="app-header">
    <a class="brand" href="#setup" data-nav="setup" aria-label="Pickleball Scorekeeper home">
      <span class="brand-mark" aria-hidden="true"><span></span></span>
      <span>
        <strong>Pickleball</strong>
        <small>Scorekeeper</small>
      </span>
    </a>
    <nav class="app-nav" aria-label="Main navigation">
      <button class="nav-button" type="button" data-nav="current" hidden>Current match</button>
      <button class="nav-button" type="button" data-nav="setup">New match</button>
      <button class="nav-button" type="button" data-nav="history">
        History <span class="nav-count" id="history-count">0</span>
      </button>
    </nav>
  </header>

  <main id="app" tabindex="-1"></main>

  <div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="true"></div>
  <div class="sr-only" id="score-announcer" aria-live="assertive" aria-atomic="true"></div>

  <dialog id="resume-dialog" class="app-dialog" aria-labelledby="resume-title">
    <div class="dialog-kicker">Match recovered</div>
    <h2 id="resume-title">Continue where you left off?</h2>
    <p id="resume-description">An unfinished match is saved on this device.</p>
    <div class="dialog-actions">
      <button class="button button-secondary" type="button" data-dialog-action="discard-resume">Discard match</button>
      <button class="button button-primary" type="button" data-dialog-action="resume">Resume match</button>
    </div>
  </dialog>

  <dialog id="winner-dialog" class="app-dialog winner-dialog" aria-labelledby="winner-title">
    <div class="winner-icon" aria-hidden="true">✓</div>
    <div class="dialog-kicker">Win condition reached</div>
    <h2 id="winner-title">Confirm game winner</h2>
    <p id="winner-description"></p>
    <div class="dialog-actions">
      <button class="button button-secondary" type="button" data-dialog-action="continue-editing">Keep editing</button>
      <button class="button button-primary" type="button" data-dialog-action="confirm-winner">Confirm game</button>
    </div>
  </dialog>

  <dialog id="next-game-dialog" class="app-dialog app-dialog-wide" aria-labelledby="next-game-title">
    <div class="dialog-kicker">Game recorded</div>
    <h2 id="next-game-title">Set up the next game</h2>
    <form id="next-game-form">
      <div class="dialog-form-grid">
        <fieldset class="field-group compact-group">
          <legend>Starting serving team</legend>
          <div class="segment segment-two" id="next-serving-team"></div>
        </fieldset>
        <label class="field">
          <span>Starting server</span>
          <select id="next-active-server" name="nextActiveServer"></select>
        </label>
        <fieldset class="field-group compact-group" id="next-server-number-group">
          <legend>Starting server number</legend>
          <div class="segment segment-two">
            <label><input type="radio" name="nextServerNumber" value="1"><span>Server 1</span></label>
            <label><input type="radio" name="nextServerNumber" value="2" checked><span>Server 2</span></label>
          </div>
        </fieldset>
        <label class="check-row">
          <input type="checkbox" id="next-swap-ends" checked>
          <span><strong>Switch displayed ends</strong><small>Swap the teams on screen for the next game.</small></span>
        </label>
      </div>
      <div class="dialog-actions">
        <button class="button button-secondary" type="button" data-dialog-action="correct-recorded-game">Correct last game</button>
        <button class="button button-primary" type="submit">Start next game</button>
      </div>
    </form>
  </dialog>

  <dialog id="confirm-dialog" class="app-dialog" aria-labelledby="confirm-title">
    <div class="dialog-kicker" id="confirm-kicker">Please confirm</div>
    <h2 id="confirm-title">Confirm action</h2>
    <p id="confirm-message"></p>
    <div class="dialog-actions">
      <button class="button button-secondary" type="button" data-dialog-action="cancel-confirm">Cancel</button>
      <button class="button button-danger" id="confirm-action-button" type="button">Confirm</button>
    </div>
  </dialog>

  <dialog id="help-dialog" class="app-dialog app-dialog-wide" aria-labelledby="help-title">
    <div class="dialog-heading-row">
      <div>
        <div class="dialog-kicker">Laptop controls</div>
        <h2 id="help-title">Keyboard shortcuts</h2>
      </div>
      <button class="icon-button" type="button" data-dialog-action="close-help" aria-label="Close keyboard shortcuts">×</button>
    </div>
    <div class="shortcut-grid">
      <div><kbd>1</kbd><span>Add point to left team</span></div>
      <div><kbd>2</kbd><span>Add point to right team</span></div>
      <div><kbd>Shift</kbd> + <kbd>1</kbd><span>Subtract from left team</span></div>
      <div><kbd>Shift</kbd> + <kbd>2</kbd><span>Subtract from right team</span></div>
      <div><kbd>S</kbd><span>Switch serving team</span></div>
      <div><kbd>U</kbd><span>Undo last change</span></div>
      <div><kbd>E</kbd><span>Switch displayed ends</span></div>
      <div><kbd>F</kbd><span>Toggle fullscreen</span></div>
    </div>
    <p class="dialog-note">Shortcuts pause while you type in a field or use a dialog.</p>
  </dialog>

  <script type="module" src="app.mjs"></script>
</body>
</html>
