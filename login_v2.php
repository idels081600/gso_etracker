<?php
require_once 'auth_security.php';
require_once 'passlip/dbh.php';
start_secure_session();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>E-CGSO Login</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

  <style>
    :root {
      --cgso-ink: #17212b;
      --cgso-muted: #65717d;
      --cgso-line: #dbe3e8;
      --cgso-teal: #064f59;
      --cgso-teal-dark: #043f46;
      --cgso-gold: #f0b84c;
      --cgso-surface: #ffffff;
      --cgso-soft: #f4f7f8;
    }

    * {
      box-sizing: border-box;
    }

    body {
      min-height: 100vh;
      margin: 0;
      color: var(--cgso-ink);
      background: #dde6e7;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    }

    a {
      color: var(--cgso-teal);
    }

    .login-shell {
      min-height: 100vh;
      padding: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-frame {
      width: min(1180px, 100%);
      min-height: 720px;
      display: grid;
      grid-template-columns: minmax(380px, 0.92fr) minmax(460px, 1.08fr);
      background: var(--cgso-surface);
      border: 1px solid rgba(15, 77, 86, 0.14);
      box-shadow: 0 26px 70px rgba(17, 38, 45, 0.18);
      overflow: hidden;
    }

    .login-panel {
      position: relative;
      padding: 54px clamp(32px, 6vw, 98px);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .brand {
      position: absolute;
      top: 54px;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      color: var(--cgso-teal-dark);
      font-weight: 700;
      letter-spacing: 0;
      text-decoration: none;
    }

    .brand:hover {
      color: var(--cgso-teal-dark);
      text-decoration: none;
    }

    .brand-mark {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: 0 10px 22px rgba(4, 79, 89, 0.16);
    }

    .form-wrap {
      width: 100%;
      max-width: 430px;
      margin-top: 72px;
    }

    .eyebrow {
      margin-bottom: 12px;
      color: var(--cgso-teal);
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    h1 {
      margin: 0 0 10px;
      color: var(--cgso-ink);
      font-size: clamp(2rem, 4vw, 2.65rem);
      font-weight: 750;
      line-height: 1.1;
      letter-spacing: 0;
    }

    .intro {
      margin: 0 0 32px;
      color: var(--cgso-muted);
      line-height: 1.65;
    }

    .alert {
      border-radius: 8px;
      font-size: 0.92rem;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-label {
      margin-bottom: 8px;
      color: #28333d;
      font-size: 0.86rem;
      font-weight: 700;
    }

    .input-shell {
      position: relative;
    }

    .input-shell > i {
      position: absolute;
      top: 50%;
      left: 16px;
      color: #7c8b94;
      transform: translateY(-50%);
      pointer-events: none;
    }

    .form-control {
      height: 52px;
      padding: 13px 44px;
      color: var(--cgso-ink);
      border: 1px solid var(--cgso-line);
      border-radius: 8px;
      background: #fbfdfe;
      font-size: 0.95rem;
      transition: border-color 160ms ease, box-shadow 160ms ease, background-color 160ms ease;
    }

    .form-control:focus {
      border-color: var(--cgso-teal);
      background: #fff;
      box-shadow: 0 0 0 4px rgba(6, 79, 89, 0.12);
    }

    .password-toggle {
      position: absolute;
      top: 50%;
      right: 12px;
      width: 32px;
      height: 32px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #7c8b94;
      border: 0;
      border-radius: 6px;
      background: transparent;
      transform: translateY(-50%);
      cursor: pointer;
    }

    .password-toggle:hover,
    .password-toggle:focus {
      color: var(--cgso-teal);
      background: #edf4f5;
      outline: none;
    }

    .password-toggle i {
      position: static;
      color: inherit;
      transform: none;
      pointer-events: auto;
    }

    .login-actions {
      margin-top: 28px;
    }

    .btn-login {
      width: 100%;
      height: 52px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      border: 0;
      border-radius: 8px;
      background: var(--cgso-teal);
      color: #fff;
      font-weight: 700;
      transition: transform 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
    }

    .btn-login:hover,
    .btn-login:focus {
      background: var(--cgso-teal-dark);
      color: #fff;
      box-shadow: 0 14px 28px rgba(4, 79, 89, 0.22);
      transform: translateY(-1px);
    }

    .support-row {
      margin-top: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 18px;
      color: var(--cgso-muted);
      font-size: 0.92rem;
    }

    .support-row a {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #33414a;
      font-weight: 650;
      text-decoration: none;
    }

    .support-row a:hover {
      color: var(--cgso-teal);
      text-decoration: none;
    }

    .login-footer {
      margin-top: 28px;
      color: #7b8790;
      font-size: 0.78rem;
      font-weight: 650;
      letter-spacing: 0.02em;
      text-align: center;
    }

    .showcase-panel {
      position: relative;
      min-height: 720px;
      padding: 70px clamp(44px, 6vw, 78px);
      display: flex;
      flex-direction: column;
      justify-content: center;
      overflow: hidden;
      color: #fff;
      background:
        linear-gradient(135deg, rgba(4, 63, 70, 0.96), rgba(6, 79, 89, 0.87)),
        url('bg_image.jpg') center / cover no-repeat;
    }

    .showcase-panel::before {
      content: "";
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 78% 10%, rgba(240, 184, 76, 0.22), transparent 32%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.08), transparent 45%);
      pointer-events: none;
    }

    .showcase-content {
      position: relative;
      z-index: 1;
      max-width: 540px;
    }

    .showcase-kicker {
      margin-bottom: 18px;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: rgba(255, 255, 255, 0.86);
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .showcase-kicker::before {
      content: "";
      width: 34px;
      height: 2px;
      background: var(--cgso-gold);
    }

    .showcase-panel h2 {
      margin: 0 0 22px;
      font-size: clamp(2rem, 3.2vw, 3rem);
      font-weight: 740;
      line-height: 1.16;
      letter-spacing: 0;
    }

    .quote {
      margin: 0;
      padding-left: 20px;
      border-left: 4px solid var(--cgso-gold);
      color: rgba(255, 255, 255, 0.84);
      font-size: 1.03rem;
      line-height: 1.75;
    }

    .module-list {
      position: relative;
      z-index: 1;
      margin-top: auto;
      padding-top: 64px;
    }

    .module-heading {
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 18px;
      color: rgba(255, 255, 255, 0.72);
      font-size: 0.74rem;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .module-heading::after {
      content: "";
      flex: 1;
      height: 1px;
      background: rgba(255, 255, 255, 0.22);
    }

    .module-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px 24px;
    }

    .module-item {
      display: flex;
      align-items: center;
      gap: 10px;
      color: rgba(255, 255, 255, 0.82);
      font-size: 0.93rem;
      font-weight: 700;
      white-space: nowrap;
    }

    .module-item i {
      width: 24px;
      color: var(--cgso-gold);
      text-align: center;
    }

    @media (max-width: 991px) {
      .login-shell {
        padding: 0;
        align-items: stretch;
      }

      .login-frame {
        min-height: 100vh;
        grid-template-columns: 1fr;
        border: 0;
        box-shadow: none;
      }

      .login-panel {
        min-height: auto;
        padding: 36px 24px 40px;
      }

      .brand {
        position: static;
        margin-bottom: 46px;
      }

      .form-wrap {
        max-width: 520px;
        margin: 0 auto;
      }

      .showcase-panel {
        min-height: auto;
        padding: 42px 24px;
      }

      .module-list {
        padding-top: 34px;
      }
    }

    @media (max-width: 575px) {
      .login-panel {
        padding: 28px 18px 34px;
      }

      .brand {
        margin-bottom: 36px;
      }

      .brand-mark {
        width: 34px;
        height: 34px;
      }

      h1 {
        font-size: 2rem;
      }

      .intro {
        margin-bottom: 26px;
      }

      .support-row {
        flex-direction: column;
        gap: 12px;
      }

      .showcase-panel {
        padding: 34px 18px;
      }

      .showcase-panel h2 {
        font-size: 1.85rem;
      }

      .module-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
  <link rel="canonicalize" href="https://gso.etracker.tagbilaran.gov.ph">
</head>

<body>
  <main class="login-shell">
    <section class="login-frame" aria-label="E-CGSO login">
      <div class="login-panel">
        <a class="brand" href="login_v2.php" aria-label="E-CGSO home">
          <img src="logo.png" alt="" class="brand-mark">
          <span>E-CGSO</span>
        </a>

        <div class="form-wrap">
          <h1>Welcome Back!</h1>
          <p class="intro">Open your dashboard and continue managing CGSO requests, records, and operations.</p>

          <?php if (isset($_SESSION['LoginMessage'])) : ?>
            <div class="alert alert-danger" id="error-alert" role="alert">
              <?php echo htmlspecialchars($_SESSION['LoginMessage'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['LoginMessage']); ?>
          <?php endif; ?>

          <form action="check_login.php" method="POST">
            <div class="form-group">
              <label class="form-label" for="usr">Username or ID number</label>
              <div class="input-shell">
                <i class="fas fa-user" aria-hidden="true"></i>
                <input type="text" class="form-control" id="usr" name="username" autocomplete="username" placeholder="Enter your username or ID" required autofocus>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="pwd">Password</label>
              <div class="input-shell">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <input type="password" class="form-control" id="pwd" name="password" autocomplete="current-password" placeholder="Enter your password" required>
                <button class="password-toggle" type="button" aria-label="Show password" title="Show password">
                  <i class="fas fa-eye" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <div class="login-actions">
              <button type="submit" name="login" id="login_btn" class="btn-login">
                <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                <span>Login</span>
              </button>
            </div>
          </form>

          <div class="support-row" aria-label="CGSO links">
            <a href="https://www.facebook.com/tagbilarancitygso" target="_blank" rel="noopener noreferrer">
              <i class="fab fa-facebook" aria-hidden="true"></i>
              <span>Facebook</span>
            </a>
            <a href="https://cgsotagbilaran.com" target="_blank" rel="noopener noreferrer">
              <i class="fas fa-globe" aria-hidden="true"></i>
              <span>Website</span>
            </a>
          </div>

          <footer class="login-footer">
            Powered by CGSO-IT
          </footer>
        </div>
      </div>

      <aside class="showcase-panel" aria-label="CGSO operations overview">
        <div class="showcase-content">
          <div class="showcase-kicker">City General Services Office</div>
          <h2>City General Services Office where service is our passion and performance is our commitment.</h2>
          <p class="quote">A unified access point for requests, assets, documents, fuel monitoring, and field operations across CGSO systems.</p>
        </div>


      </aside>
    </section>
  </main>

  <script>
    var errorAlert = document.getElementById('error-alert');
    if (errorAlert) {
      setTimeout(function() {
        errorAlert.style.display = 'none';
      }, 3500);
    }

    var passwordToggle = document.querySelector('.password-toggle');
    var passwordInput = document.getElementById('pwd');

    if (passwordToggle && passwordInput) {
      passwordToggle.addEventListener('click', function() {
        var isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        passwordToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
        passwordToggle.querySelector('i').className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
      });
    }
  </script>
</body>

</html>
