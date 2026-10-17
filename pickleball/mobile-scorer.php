<?php

declare(strict_types=1);

$app = ["name" => "Pickleball Remote Scorer", "version" => "5.0.2"];
header("Content-Type: text/html; charset=UTF-8");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: same-origin");
header("Cache-Control: no-cache");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#ffffff">
  <meta name="description" content="Role-protected phone controls for live pickleball scoring.">
  <title><?= htmlspecialchars($app["name"], ENT_QUOTES, "UTF-8") ?></title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='15' fill='%23147a3f'/%3E%3Ccircle cx='11' cy='12' r='2' fill='%23ffffff'/%3E%3Ccircle cx='21' cy='14' r='2' fill='%23ffffff'/%3E%3Ccircle cx='16' cy='22' r='2' fill='%23ffffff'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="styles.css?v=<?= htmlspecialchars($app["version"], ENT_QUOTES, "UTF-8") ?>">
  <link rel="stylesheet" href="mobile-scorer.css?v=<?= htmlspecialchars($app["version"], ENT_QUOTES, "UTF-8") ?>">
</head>
<body class="mobile-scorer-body" data-app-version="<?= htmlspecialchars($app["version"], ENT_QUOTES, "UTF-8") ?>">
  <header class="mobile-topbar">
      <div class="mobile-brand"><span class="brand-mark" aria-hidden="true"><span></span></span><span><strong>Remote Scorer</strong><small id="phone-sync-status">Signed out</small></span></div>
    <a class="mini-button" href="index.php">Tournament board</a>
  </header>

  <main class="mobile-shell">
    <section class="phone-access-panel" id="phone-access-panel" aria-labelledby="phone-access-title">
      <div class="access-mark" aria-hidden="true">●</div>
      <div class="dialog-kicker">Court scoring role</div>
      <h1 id="phone-access-title">Remote Scorer sign-in</h1>
      <p>Enter the Remote Scorer code. The Game Master must start a game before scoring becomes available.</p>
      <form id="phone-access-form">
        <label class="field"><span>Remote Scorer access code</span><input id="phone-access-code" name="accessCode" type="password" autocomplete="current-password" maxlength="64" required></label>
        <button class="button button-primary" type="submit">Enter live scoring</button>
      </form>
      <p class="phone-security-note" id="phone-https-note">Use the HTTPS website address when scoring over the internet.</p>
    </section>

    <section class="phone-scorer-panel" id="phone-scorer-panel" hidden>
      <div class="phone-division-bar">
        <label class="phone-board-picker"><span>Scoreboard</span><select id="phone-board-select" aria-label="Select scoreboard"></select></label>
        <button class="phone-connection-button" id="phone-disconnect-button" type="button">Sign out</button>
      </div>
      <div id="phone-app"></div>
    </section>
  </main>

  <div class="toast-region phone-toast-region" id="phone-toast-region" aria-live="polite" aria-atomic="true"></div>
  <div class="sr-only" id="phone-announcer" aria-live="assertive" aria-atomic="true"></div>

  <dialog class="app-dialog phone-dialog" id="phone-confirm-dialog" aria-labelledby="phone-confirm-title">
    <div class="dialog-kicker">Please confirm</div>
    <h2 id="phone-confirm-title">Confirm action</h2>
    <p id="phone-confirm-message"></p>
    <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-phone-dialog>Cancel</button><button class="button button-danger" id="phone-confirm-action" type="button">Confirm</button></div>
  </dialog>

  <dialog class="app-dialog celebration-dialog phone-dialog" id="phone-winner-dialog" aria-labelledby="phone-winner-title" aria-describedby="phone-winner-message">
    <div class="winner-icon" aria-hidden="true">✓</div>
    <div class="dialog-kicker" id="phone-winner-kicker">Winning point reached</div>
    <h2 id="phone-winner-title">Congratulations!</h2>
    <p id="phone-winner-message"></p>
    <div class="winner-score-summary" id="phone-winner-score"><span id="phone-winner-side-a">Side A</span><strong id="phone-winner-final-score">0–0</strong><span id="phone-winner-side-b">Side B</span></div>
    <p class="dialog-note">Review the final score, then submit it to the Game Master and live rankings.</p>
    <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-phone-dialog>Review score</button><button class="button button-primary" id="phone-record-result" type="button">Submit result</button></div>
  </dialog>

  <script type="module" src="js-module.php?module=mobile-scorer&amp;v=<?= htmlspecialchars($app["version"], ENT_QUOTES, "UTF-8") ?>"></script>
</body>
</html>
