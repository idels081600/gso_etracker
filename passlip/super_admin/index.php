<?php
require_once '../dbh.php';
require_once '../functions.php';

$result = display_data();

if (!isset($_SESSION['username'])) {
    header("location: ../../login_v2.php");
    exit();
}

if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
    header("location: ../../login_v2.php");
    exit();
}

$pendingRows = [];
$stats = [
    'pending' => 0,
    'approved_today' => 0,
    'declined_today' => 0,
    'users' => 0,
];

while ($row = mysqli_fetch_assoc($result)) {
    $pendingRows[] = $row;
    $stats['pending']++;
}

$approvedResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `request` WHERE Status IN ('Approved', 'Done') AND DATE(`date`) = CURDATE()");
if ($approvedResult && $row = mysqli_fetch_assoc($approvedResult)) {
    $stats['approved_today'] = (int) $row['total'];
}

$declinedResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `request` WHERE Status = 'Declined' AND DATE(`date`) = CURDATE()");
if ($declinedResult && $row = mysqli_fetch_assoc($declinedResult)) {
    $stats['declined_today'] = (int) $row['total'];
}

$usersResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `logindb`");
if ($usersResult && $row = mysqli_fetch_assoc($usersResult)) {
    $stats['users'] = (int) $row['total'];
}

$adminName = $_SESSION['pay_name'] ?? $_SESSION['username'];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin | E-Pass Slip</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.14.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.4.0/firebase-messaging-compat.js"></script>
    <link rel="stylesheet" href="../assets/passlip-modern.css?v=20260603">
    <script defer src="../assets/passlip-modern.js?v=20260603"></script>
    <style>
        :root {
            --admin-bg: #f4f7f4;
            --admin-panel: #fff;
            --admin-soft: #f8faf8;
            --admin-text: #17211b;
            --admin-muted: #66746b;
            --admin-line: #dfe7e0;
            --admin-primary: #167344;
            --admin-primary-dark: #0e5c35;
            --admin-primary-soft: #e5f4ea;
            --admin-warning: #946b13;
            --admin-danger: #b42318;
            --admin-info: #246b95;
            --admin-shadow: 0 12px 28px rgba(21, 43, 30, .08);
            --admin-radius: 8px;
        }

        body {
            background: var(--admin-bg) !important;
            color: var(--admin-text);
        }

        .admin-shell {
            width: min(1440px, calc(100% - 32px));
            margin: 22px auto 42px;
        }

        .admin-topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            min-height: 68px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 18px;
            padding: 10px 22px;
            border-bottom: 1px solid var(--admin-line);
            background: rgba(255, 255, 255, .96);
        }

        .admin-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 190px;
        }

        .admin-brand img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
        }

        .admin-brand strong,
        .admin-brand span,
        .admin-user strong,
        .admin-user span {
            display: block;
        }

        .admin-brand span,
        .admin-user span,
        .muted {
            color: var(--admin-muted);
        }

        .admin-nav {
            display: flex;
            justify-content: flex-end;
            gap: 4px;
            overflow-x: auto;
        }

        .admin-nav a {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            padding: 9px 12px;
            border-radius: var(--admin-radius);
            color: var(--admin-muted);
            white-space: nowrap;
        }

        .admin-nav a:hover,
        .admin-nav a.active {
            background: var(--admin-primary-soft);
            color: var(--admin-primary-dark);
        }

        .admin-user {
            padding: 8px 12px;
            border: 1px solid var(--admin-line);
            border-radius: var(--admin-radius);
            background: var(--admin-soft);
            text-align: right;
        }

        .page-header,
        .panel-head,
        .queue-tools {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
        }

        .page-header {
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
            color: var(--admin-primary);
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
            border: 1px solid var(--admin-line);
            border-radius: var(--admin-radius);
            background: var(--admin-panel);
            box-shadow: var(--admin-shadow);
        }

        .metric {
            padding: 16px;
        }

        .metric span,
        .metric strong {
            display: block;
        }

        .metric span {
            color: var(--admin-muted);
        }

        .metric strong {
            margin-top: 8px;
            font-size: 30px;
        }

        .panel {
            padding: 16px;
        }

        .queue-tools {
            margin: 14px 0 12px;
            padding: 10px 12px;
            border: 1px solid var(--admin-line);
            border-radius: var(--admin-radius);
            background: var(--admin-soft);
        }

        .toolbar {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        label span {
            display: block;
            margin-bottom: 5px;
            color: #3f4b43;
            font-size: 12px;
            font-weight: 700;
        }

        input,
        select,
        textarea {
            min-height: 40px;
            border: 1px solid var(--admin-line);
            border-radius: var(--admin-radius);
            padding: 9px 11px;
        }

        .table-wrap {
            max-height: 68vh;
            overflow: auto;
            border: 1px solid var(--admin-line);
            border-radius: var(--admin-radius);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th,
        #table tr:first-child th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f7faf7 !important;
            color: #405046;
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .empty {
            padding: 18px;
            color: var(--admin-muted);
            text-align: center;
        }

        .super-detail-grid,
        .super-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin: 16px 0;
        }

        .super-detail-item {
            padding: 12px;
            border: 1px solid var(--admin-line);
            border-radius: var(--admin-radius);
            background: var(--admin-soft);
        }

        .super-detail-item.wide,
        .super-form-grid .wide {
            grid-column: 1 / -1;
        }

        .super-detail-item span,
        .super-detail-item strong {
            display: block;
        }

        .super-detail-item span {
            margin-bottom: 4px;
            color: var(--admin-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .super-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
        }

        .toast-stack {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 40;
            display: grid;
            gap: 10px;
        }

        .toast-item {
            width: min(360px, calc(100vw - 36px));
            padding: 12px 14px;
            border-radius: var(--admin-radius);
            background: #122119;
            color: #fff;
            box-shadow: var(--admin-shadow);
        }

        @media (max-width: 960px) {
            .admin-topbar {
                grid-template-columns: 1fr;
            }

            .admin-nav {
                justify-content: flex-start;
            }

            .admin-user {
                display: none;
            }

            .metric-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .admin-shell {
                width: min(100% - 20px, 1440px);
                margin-top: 14px;
            }

            .page-header,
            .panel-head,
            .queue-tools {
                align-items: stretch;
                flex-direction: column;
            }

            .toolbar,
            .toolbar label,
            .toolbar button {
                width: 100%;
            }

            .metric-grid,
            .super-detail-grid,
            .super-form-grid {
                grid-template-columns: 1fr;
            }

            .super-modal-actions {
                flex-direction: column;
            }

            .super-modal-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <header class="admin-topbar">
        <a class="admin-brand" href="index.php">
            <img src="../logo.png" alt="GSO logo">
            <strong>E-Pass Slip</strong>
            <span>Super Admin workspace</span>
        </a>
        <nav class="admin-nav" aria-label="Super admin navigation">
            <a class="active" href="index.php">Pending</a>
            <a href="add_req.php">Add Request</a>
            <a href="approved.php">Approved</a>
            <a href="decline.php">Declined</a>
            <a href="track_emp.php">Track Employees</a>
            <a href="register.php">Register</a>
            <a href="../../logout.php">Logout</a>
        </nav>
        <div class="admin-user">
            <strong><?= htmlspecialchars($adminName); ?></strong>
            <span><?= htmlspecialchars($_SESSION['role'] ?? 'Super Admin'); ?></span>
        </div>
    </header>

    <main class="admin-shell">
        <section class="page-header">
            <div>
                <p class="eyebrow">System control</p>
                <h1>Pending Requests</h1>
                <p class="muted">Review requests, monitor today’s outcomes, and jump to user or tracking tools.</p>
            </div>
            <form class="toolbar" id="filterForm">
                <label>
                    <span>Search</span>
                    <input type="search" id="searchInput" placeholder="Name, position, destination">
                </label>
                <label>
                    <span>Type</span>
                    <select id="typeFilter">
                        <option value="">All types</option>
                        <option value="Official Business">Official Business</option>
                        <option value="Personal">Personal</option>
                    </select>
                </label>
                <button class="btn btn-secondary" type="button" id="refreshBtn">Refresh</button>
            </form>
        </section>

        <section class="metric-grid" aria-label="Super admin summary">
            <div class="metric"><span>Pending</span><strong id="pendingCount"><?= $stats['pending']; ?></strong></div>
            <div class="metric"><span>Approved Today</span><strong><?= $stats['approved_today']; ?></strong></div>
            <div class="metric"><span>Declined Today</span><strong><?= $stats['declined_today']; ?></strong></div>
            <div class="metric"><span>Registered Users</span><strong><?= $stats['users']; ?></strong></div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Approval Queue</h2>
                    <p class="muted" id="queueMeta"><?= count($pendingRows); ?> request(s) loaded</p>
                </div>
            </div>

            <div class="queue-tools">
                <div>
                    <strong>Live queue</strong>
                    <div class="muted">Refreshes every 30 seconds while the tab is visible.</div>
                </div>
                <a class="btn btn-primary" href="register.php">Manage Users</a>
            </div>

            <div class="table-wrap">
                <table class="table table-hover" id="table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Position</th>
                            <th scope="col">Destination</th>
                            <th scope="col">Type</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pendingRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row["name"]); ?></td>
                            <td><?= htmlspecialchars($row["position"]); ?></td>
                            <td><?= htmlspecialchars($row["destination"]); ?></td>
                            <td><?= htmlspecialchars($row["typeofbusiness"]); ?></td>
                            <td><?= htmlspecialchars($row["Status"]); ?></td>
                            <td><button type="button" class="btn btn-info btn-sm" data-super-view="<?= (int) $row['id']; ?>">View</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pendingRows): ?>
                        <tr><td colspan="6" class="empty">No pending requests right now.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div class="modal fade" id="requestDetailModal" tabindex="-1" role="dialog" aria-labelledby="requestDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requestDetailModalLabel">Request Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="requestDetailBody">
                    <div class="empty">Loading request details...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-stack" id="toastStack"></div>

    <script>
        const table = document.getElementById('table');
        const searchInput = document.getElementById('searchInput');
        const typeFilter = document.getElementById('typeFilter');

        function toast(message) {
            const item = document.createElement('div');
            item.className = 'toast-item';
            item.textContent = message;
            document.getElementById('toastStack').appendChild(item);
            setTimeout(() => item.remove(), 4200);
        }

        function tableRows() {
            return Array.from(table.querySelectorAll('tr')).filter(row => row.cells.length && !row.querySelector('th'));
        }

        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const type = typeFilter.value;
            let visible = 0;

            tableRows().forEach(row => {
                if (row.querySelector('.empty')) return;
                const cells = Array.from(row.cells).map(cell => cell.textContent.trim());
                const matchesQuery = !query || cells.slice(0, 3).join(' ').toLowerCase().includes(query);
                const matchesType = !type || cells[3] === type;
                const show = matchesQuery && matchesType;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            document.getElementById('queueMeta').textContent = `${visible} visible request(s)`;
        }

        async function loadDoc() {
            if (document.hidden) return;

            try {
                const response = await fetch('../data.php', { cache: 'no-store' });
                const html = await response.text();
                table.innerHTML = html;
                applyFilters();
                document.getElementById('pendingCount').textContent = tableRows().filter(row => !row.querySelector('.empty')).length;
            } catch (error) {
                toast('Unable to refresh pending requests.');
            }
        }

        async function openRequestDetails(id) {
            const body = document.getElementById('requestDetailBody');
            body.innerHTML = '<div class="empty">Loading request details...</div>';
            $('#requestDetailModal').modal('show');

            try {
                const response = await fetch(`request_details_modal.php?id=${encodeURIComponent(id)}`, { cache: 'no-store' });
                const html = await response.text();
                body.innerHTML = html || '<div class="empty">Unable to load request details.</div>';
                if (response.ok) bindDetailForm();
            } catch (error) {
                body.innerHTML = '<div class="empty">Unable to load request details.</div>';
            }
        }

        function bindDetailForm() {
            const body = document.getElementById('requestDetailBody');
            const status = body.querySelector('[data-super-status]');
            const reason = body.querySelector('[data-super-decline-reason]');
            const approve = body.querySelector('[data-super-approve]');
            const decline = body.querySelector('[data-super-decline]');

            function sync() {
                const isDeclined = status && status.value === 'Declined';
                if (reason) reason.hidden = !isDeclined;
                if (approve) approve.hidden = isDeclined;
                if (decline) decline.hidden = !isDeclined;
            }

            status?.addEventListener('change', sync);
            body.querySelectorAll('[data-close-super-detail]').forEach(button => {
                button.addEventListener('click', () => $('#requestDetailModal').modal('hide'));
            });
            sync();
        }

        table.addEventListener('click', event => {
            const button = event.target.closest('[data-super-view]');
            if (!button) return;
            openRequestDetails(button.dataset.superView);
        });

        searchInput.addEventListener('input', applyFilters);
        typeFilter.addEventListener('change', applyFilters);
        document.getElementById('refreshBtn').addEventListener('click', loadDoc);
        setInterval(loadDoc, 30000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) loadDoc();
        });
        applyFilters();
    </script>

    <script>
        var firebaseConfig = {
            apiKey: "AIzaSyBdJEBddNuHGPyYW_NQ3D8VFpeQdfXOS2M",
            authDomain: "push-notification-4469d.firebaseapp.com",
            databaseURL: "https://push-notification-4469d-default-rtdb.asia-southeast1.firebasedatabase.app",
            projectId: "push-notification-4469d",
            storageBucket: "push-notification-4469d.appspot.com",
            messagingSenderId: "3251430231",
            appId: "1:3251430231:web:aea52a61992765cf511412",
            measurementId: "G-V236DTMQ4E"
        };
        firebase.initializeApp(firebaseConfig);

        firebase.messaging().getToken().then((token) => {
            $.ajax({
                url: '../store_token.php',
                type: 'POST',
                data: { token: token }
            });
        }).catch(() => {});
    </script>
</body>

</html>
