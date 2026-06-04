<?php
require_once '../functions.php';
require_once '../dbh.php';

if (!isset($_SESSION['username'])) {
    header("location:../../login_v2.php");
    exit();
} else if ($_SESSION['role'] == 'Employee') {
    header("location:../../login_v2.php");
    exit();
} else if ($_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
    header("location:../../login_v2.php");
    exit();
}

$passSlipRoles = [
    'Admin',
    'Admin2',
    'Employee',
    'TCWS Employee',
    'TCWS Scanner',
    'Desk Clerk',
    'Division Head',
    'TCWS Division Head',
    'Department Head',
];

$message = null;
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reveal_password') {
    header('Content-Type: application/json');

    $accountId = (int) ($_POST['account_id'] ?? 0);
    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    if ($accountId <= 0 || $adminPassword === '') {
        echo json_encode(['ok' => false, 'message' => 'Enter your password to view this account password.']);
        exit();
    }

    $adminStmt = $conn->prepare("SELECT `password` FROM `logindb` WHERE BINARY `username` = ? LIMIT 1");
    $adminStmt->bind_param('s', $_SESSION['username']);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    $adminRow = $adminResult->fetch_assoc();

    if (!$adminRow || !password_verify($adminPassword, $adminRow['password'])) {
        echo json_encode(['ok' => false, 'message' => 'Your password is incorrect.']);
        exit();
    }

    $accountStmt = $conn->prepare("SELECT `text_password`, `role` FROM `logindb` WHERE `Id` = ? LIMIT 1");
    $accountStmt->bind_param('i', $accountId);
    $accountStmt->execute();
    $accountResult = $accountStmt->get_result();
    $accountRow = $accountResult->fetch_assoc();

    if (!$accountRow || !in_array($accountRow['role'], $passSlipRoles, true)) {
        echo json_encode(['ok' => false, 'message' => 'Account was not found in the Pass Slip list.']);
        exit();
    }

    echo json_encode([
        'ok' => true,
        'password' => (string) ($accountRow['text_password'] ?? ''),
        'hasPassword' => trim((string) ($accountRow['text_password'] ?? '')) !== '',
    ]);
    exit();
}

if (isset($_POST['register_user'])) {
    $usernameInput = trim($_POST['username'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if ($usernameInput === '' || $position === '' || $password === '' || $name === '' || !in_array($role, $passSlipRoles, true)) {
        $message = 'Please complete all fields and choose a valid Pass Slip role.';
        $messageType = 'danger';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO `logindb` (`username`, `password`, `text_password`, `name`, `role`, `Position`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $usernameInput, $hashedPassword, $password, $name, $role, $position);

        if ($stmt->execute()) {
            $message = 'Pass Slip account added.';
        } else {
            $message = 'Unable to add account. The username may already exist.';
            $messageType = 'danger';
        }
    }
}

$placeholders = implode(',', array_fill(0, count($passSlipRoles), '?'));
$types = str_repeat('s', count($passSlipRoles));
$stmt = $conn->prepare("SELECT `Id`, `username`, `name`, `role`, `Position` FROM `logindb` WHERE `role` IN ($placeholders) ORDER BY `role`, `name`, `username`");
$stmt->bind_param($types, ...$passSlipRoles);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
$roleCounts = array_fill_keys($passSlipRoles, 0);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
    if (isset($roleCounts[$row['role']])) {
        $roleCounts[$row['role']]++;
    }
}

$adminName = $_SESSION['pay_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pass Slip Accounts</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <link rel="stylesheet" href="../assets/passlip-modern.css?v=20260603">
    <script defer src="../assets/passlip-modern.js?v=20260603"></script>
    <style>
        :root {
            --reg-bg: #f4f7f4;
            --reg-panel: #fff;
            --reg-soft: #f8faf8;
            --reg-text: #17211b;
            --reg-muted: #66746b;
            --reg-line: #dfe7e0;
            --reg-primary: #167344;
            --reg-primary-dark: #0e5c35;
            --reg-primary-soft: #e5f4ea;
            --reg-danger: #b42318;
            --reg-shadow: 0 12px 28px rgba(21, 43, 30, .08);
            --reg-radius: 8px;
        }

        body {
            background: var(--reg-bg) !important;
            color: var(--reg-text);
        }

        .reg-topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            min-height: 68px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 18px;
            padding: 10px 22px;
            border-bottom: 1px solid var(--reg-line);
            background: rgba(255,255,255,.96);
        }

        .reg-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 190px;
        }

        .reg-brand img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
        }

        .reg-brand strong,
        .reg-brand span,
        .reg-user strong,
        .reg-user span {
            display: block;
        }

        .reg-brand span,
        .reg-user span,
        .muted {
            color: var(--reg-muted);
        }

        .reg-nav {
            display: flex;
            justify-content: flex-end;
            gap: 4px;
            overflow-x: auto;
        }

        .reg-nav a {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            padding: 9px 12px;
            border-radius: var(--reg-radius);
            color: var(--reg-muted);
            white-space: nowrap;
            text-decoration: none;
        }

        .reg-nav a:hover,
        .reg-nav a.active {
            background: var(--reg-primary-soft);
            color: var(--reg-primary-dark);
        }

        .reg-user {
            padding: 8px 12px;
            border: 1px solid var(--reg-line);
            border-radius: var(--reg-radius);
            background: var(--reg-soft);
            text-align: right;
        }

        .reg-shell {
            width: min(1440px, calc(100% - 32px));
            margin: 22px auto 42px;
        }

        .page-header,
        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            margin-bottom: 16px;
        }

        .page-header h1,
        .panel h2 {
            margin: 0;
            line-height: 1.1;
        }

        .page-header h1 {
            font-size: 28px;
        }

        .eyebrow {
            margin: 0 0 6px;
            color: var(--reg-primary);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .metric,
        .panel {
            border: 1px solid var(--reg-line);
            border-radius: var(--reg-radius);
            background: var(--reg-panel);
            box-shadow: var(--reg-shadow);
        }

        .metric {
            padding: 16px;
        }

        .metric span,
        .metric strong {
            display: block;
        }

        .metric span {
            color: var(--reg-muted);
        }

        .metric strong {
            margin-top: 8px;
            font-size: 30px;
        }

        .register-layout {
            display: grid;
            grid-template-columns: 380px minmax(0, 1fr);
            gap: 16px;
        }

        .panel {
            padding: 16px;
        }

        .form-grid {
            display: grid;
            gap: 14px;
        }

        label span {
            display: block;
            margin-bottom: 5px;
            color: #3f4b43;
            font-size: 12px;
            font-weight: 700;
        }

        input,
        select {
            width: 100%;
            min-height: 40px;
            border: 1px solid var(--reg-line);
            border-radius: var(--reg-radius);
            padding: 9px 11px;
        }

        .toolbar {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .table-wrap {
            max-height: 68vh;
            overflow: auto;
            border: 1px solid var(--reg-line);
            border-radius: var(--reg-radius);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f7faf7 !important;
            color: #405046;
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .role-badge {
            display: inline-flex;
            min-height: 24px;
            align-items: center;
            padding: 3px 8px;
            border-radius: 999px;
            background: var(--reg-primary-soft);
            color: var(--reg-primary-dark);
            font-size: 12px;
            font-weight: 700;
        }

        .password-cell {
            min-width: 190px;
        }

        .password-mask {
            display: inline-flex;
            align-items: center;
            min-width: 88px;
            min-height: 28px;
            padding: 4px 8px;
            border: 1px solid var(--reg-line);
            border-radius: 6px;
            background: var(--reg-soft);
            color: var(--reg-muted);
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            vertical-align: middle;
        }

        .password-cell .btn {
            margin-left: 8px;
            min-height: 28px;
            padding: 3px 9px;
            border-radius: 6px;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .actions form {
            margin: 0;
        }

        .password-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(14, 25, 18, .48);
        }

        .password-modal.is-open {
            display: flex;
        }

        .password-dialog {
            width: min(100%, 420px);
            border: 1px solid var(--reg-line);
            border-radius: var(--reg-radius);
            background: #fff;
            box-shadow: 0 20px 60px rgba(12, 32, 20, .22);
        }

        .password-dialog header,
        .password-dialog form {
            padding: 18px;
        }

        .password-dialog header {
            border-bottom: 1px solid var(--reg-line);
        }

        .password-dialog h2 {
            margin: 0 0 5px;
            font-size: 19px;
        }

        .password-dialog label {
            display: grid;
            gap: 7px;
            color: var(--reg-muted);
            font-size: 13px;
            font-weight: 700;
        }

        .password-dialog input {
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--reg-line);
            border-radius: 7px;
            padding: 8px 10px;
            color: var(--reg-text);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 16px;
        }

        .modal-error {
            display: none;
            margin-top: 10px;
            color: var(--reg-danger);
            font-size: 13px;
            font-weight: 700;
        }

        .modal-error.is-visible {
            display: block;
        }

        .empty {
            padding: 18px;
            color: var(--reg-muted);
            text-align: center;
        }

        .alert {
            border-radius: var(--reg-radius);
        }

        @media (max-width: 1100px) {
            .register-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 960px) {
            .reg-topbar {
                grid-template-columns: 1fr;
            }

            .reg-nav {
                justify-content: flex-start;
            }

            .reg-user {
                display: none;
            }

            .metric-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .reg-shell {
                width: min(100% - 20px, 1440px);
                margin-top: 14px;
            }

            .page-header,
            .panel-head {
                align-items: stretch;
                flex-direction: column;
            }

            .toolbar,
            .toolbar label {
                width: 100%;
            }

            .metric-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header class="reg-topbar">
        <a class="reg-brand" href="index.php">
            <img src="../logo.png" alt="GSO logo">
            <strong>E-Pass Slip</strong>
            <span>Account management</span>
        </a>
        <nav class="reg-nav" aria-label="Super admin navigation">
            <a href="index.php">Pending</a>
            <a href="approved.php">Approved</a>
            <a href="decline.php">Declined</a>
            <a href="track_emp.php">Track Employees</a>
            <a class="active" href="register.php">Register</a>
            <a href="../../logout.php">Logout</a>
        </nav>
        <div class="reg-user">
            <strong><?= htmlspecialchars($adminName); ?></strong>
            <span><?= htmlspecialchars($_SESSION['role'] ?? 'Super Admin'); ?></span>
        </div>
    </header>

    <main class="reg-shell">
        <section class="page-header">
            <div>
                <p class="eyebrow">Pass Slip accounts</p>
                <h1>Register Users</h1>
                <p class="muted">Only accounts connected to the Pass Slip workflow are shown here.</p>
            </div>
            <form class="toolbar" id="filterForm">
                <label>
                    <span>Search</span>
                    <input type="search" id="searchInput" placeholder="Name, username, position">
                </label>
                <label>
                    <span>Role</span>
                    <select id="roleFilter">
                        <option value="">All Pass Slip roles</option>
                        <?php foreach ($passSlipRoles as $roleOption): ?>
                            <option value="<?= htmlspecialchars($roleOption); ?>"><?= htmlspecialchars($roleOption); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        </section>

        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType); ?>" role="status">
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <section class="metric-grid" aria-label="Pass Slip account summary">
            <div class="metric"><span>Total Pass Slip Accounts</span><strong id="visibleCount"><?= count($users); ?></strong></div>
            <div class="metric"><span>Employees</span><strong><?= (int) ($roleCounts['Employee'] ?? 0); ?></strong></div>
            <div class="metric"><span>Approvers</span><strong><?= (int) (($roleCounts['Admin'] ?? 0) + ($roleCounts['Admin2'] ?? 0) + ($roleCounts['Division Head'] ?? 0) + ($roleCounts['Department Head'] ?? 0)); ?></strong></div>
            <div class="metric"><span>Scanner/Desk</span><strong><?= (int) (($roleCounts['Desk Clerk'] ?? 0) + ($roleCounts['TCWS Scanner'] ?? 0)); ?></strong></div>
        </section>

        <section class="register-layout">
            <aside class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Add Account</h2>
                        <p class="muted">Create a Pass Slip user only.</p>
                    </div>
                </div>
                <form action="register.php" method="POST" class="form-grid">
                    <label>
                        <span>Name</span>
                        <input type="text" name="name" autocomplete="name" required>
                    </label>
                    <label>
                        <span>Username</span>
                        <input type="text" name="username" autocomplete="username" required>
                    </label>
                    <label>
                        <span>Position</span>
                        <input type="text" name="position" required>
                    </label>
                    <label>
                        <span>Temporary Password</span>
                        <input type="text" name="password" autocomplete="new-password" required>
                    </label>
                    <label>
                        <span>Pass Slip Role</span>
                        <select name="role" required>
                            <?php foreach ($passSlipRoles as $roleOption): ?>
                                <option value="<?= htmlspecialchars($roleOption); ?>"><?= htmlspecialchars($roleOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-success" name="register_user">Create Account</button>
                </form>
            </aside>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Pass Slip Accounts</h2>
                        <p class="muted" id="tableMeta"><?= count($users); ?> account(s) loaded</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="table table-hover" id="accountsTable">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Username</th>
                                <th scope="col">Name</th>
                                <th scope="col">Position</th>
                                <th scope="col">Role</th>
                                <th scope="col">Password</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $row): ?>
                                <tr>
                                    <td><?= (int) $row["Id"]; ?></td>
                                    <td><?= htmlspecialchars($row["username"]); ?></td>
                                    <td><?= htmlspecialchars($row["name"]); ?></td>
                                    <td><?= htmlspecialchars($row["Position"] ?? $row["position"] ?? ''); ?></td>
                                    <td><span class="role-badge"><?= htmlspecialchars($row["role"]); ?></span></td>
                                    <td class="password-cell">
                                        <span class="password-mask" id="password-<?= (int) $row['Id']; ?>">Hidden</span>
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            data-password-view
                                            data-account-id="<?= (int) $row['Id']; ?>"
                                            data-account-name="<?= htmlspecialchars($row["name"]); ?>">
                                            View
                                        </button>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <form action="../code.php" method="post" onsubmit="return confirm('Delete this Pass Slip account?');">
                                                <input type="hidden" name="id" value="<?= (int) $row['Id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$users): ?>
                                <tr><td colspan="7" class="empty">No Pass Slip accounts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>

    <div class="password-modal" id="passwordModal" aria-hidden="true">
        <div class="password-dialog" role="dialog" aria-modal="true" aria-labelledby="passwordModalTitle">
            <header>
                <h2 id="passwordModalTitle">Confirm Password</h2>
                <p class="muted" id="passwordModalSubtitle">Enter your password before viewing this account password.</p>
            </header>
            <form id="passwordRevealForm">
                <input type="hidden" id="revealAccountId" name="account_id">
                <label>
                    <span>Your Login Password</span>
                    <input type="password" id="adminPasswordInput" name="admin_password" autocomplete="current-password" required>
                </label>
                <p class="modal-error" id="passwordError"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline-secondary" id="cancelReveal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="confirmReveal">Show Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const table = document.getElementById('accountsTable');
        const passwordModal = document.getElementById('passwordModal');
        const passwordRevealForm = document.getElementById('passwordRevealForm');
        const revealAccountId = document.getElementById('revealAccountId');
        const adminPasswordInput = document.getElementById('adminPasswordInput');
        const passwordError = document.getElementById('passwordError');
        const confirmReveal = document.getElementById('confirmReveal');
        const cancelReveal = document.getElementById('cancelReveal');
        const passwordModalSubtitle = document.getElementById('passwordModalSubtitle');
        let activePasswordTarget = null;
        let activePasswordButton = null;

        function rows() {
            return Array.from(table.querySelectorAll('tbody tr')).filter(row => !row.querySelector('.empty'));
        }

        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const role = roleFilter.value;
            let visible = 0;

            rows().forEach(row => {
                const cells = Array.from(row.cells).map(cell => cell.textContent.trim());
                const matchesQuery = !query || cells.slice(1, 4).join(' ').toLowerCase().includes(query);
                const matchesRole = !role || cells[4] === role;
                const show = matchesQuery && matchesRole;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            document.getElementById('tableMeta').textContent = visible + ' visible account(s)';
            document.getElementById('visibleCount').textContent = visible;
        }

        searchInput.addEventListener('input', applyFilters);
        roleFilter.addEventListener('change', applyFilters);

        function setPasswordError(message) {
            passwordError.textContent = message || '';
            passwordError.classList.toggle('is-visible', Boolean(message));
        }

        function openPasswordModal(button) {
            if (button.dataset.revealed === 'true') {
                const target = document.getElementById('password-' + button.dataset.accountId);
                if (target) {
                    target.textContent = 'Hidden';
                    target.style.color = 'var(--reg-muted)';
                }
                button.dataset.revealed = 'false';
                button.textContent = 'View';
                return;
            }

            activePasswordTarget = document.getElementById('password-' + button.dataset.accountId);
            activePasswordButton = button;
            revealAccountId.value = button.dataset.accountId;
            adminPasswordInput.value = '';
            setPasswordError('');
            passwordModalSubtitle.textContent = 'Enter your password before viewing ' + button.dataset.accountName + '\'s account password.';
            passwordModal.classList.add('is-open');
            passwordModal.setAttribute('aria-hidden', 'false');
            setTimeout(() => adminPasswordInput.focus(), 40);
        }

        function closePasswordModal() {
            passwordModal.classList.remove('is-open');
            passwordModal.setAttribute('aria-hidden', 'true');
            activePasswordTarget = null;
            activePasswordButton = null;
            adminPasswordInput.value = '';
            setPasswordError('');
        }

        table.addEventListener('click', event => {
            const button = event.target.closest('[data-password-view]');
            if (!button) return;
            openPasswordModal(button);
        });

        cancelReveal.addEventListener('click', closePasswordModal);

        passwordModal.addEventListener('click', event => {
            if (event.target === passwordModal) {
                closePasswordModal();
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && passwordModal.classList.contains('is-open')) {
                closePasswordModal();
            }
        });

        passwordRevealForm.addEventListener('submit', async event => {
            event.preventDefault();
            setPasswordError('');
            confirmReveal.disabled = true;
            confirmReveal.textContent = 'Checking...';

            const formData = new FormData(passwordRevealForm);
            formData.append('action', 'reveal_password');

            try {
                const response = await fetch('register.php', {
                    method: 'POST',
                    body: formData
                });
                const payload = await response.json();

                if (!payload.ok) {
                    setPasswordError(payload.message || 'Unable to view password.');
                    return;
                }

                if (activePasswordTarget) {
                    activePasswordTarget.textContent = payload.hasPassword ? payload.password : 'Not stored';
                    activePasswordTarget.style.color = 'var(--reg-text)';
                }
                if (activePasswordButton) {
                    activePasswordButton.dataset.revealed = 'true';
                    activePasswordButton.textContent = 'Hide';
                }
                closePasswordModal();
            } catch (error) {
                setPasswordError('Unable to verify your password right now.');
            } finally {
                confirmReveal.disabled = false;
                confirmReveal.textContent = 'Show Password';
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/xy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>

</html>
