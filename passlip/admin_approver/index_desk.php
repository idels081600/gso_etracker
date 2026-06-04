<?php
require_once '../dbh.php';
require_once '../functions.php';

$result = display_request();

if (!isset($_SESSION['username'])) {
    header("location: ../../login_v2.php");
    exit();
}

if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Department Head' || $_SESSION['role'] == 'TCWS Employee') {
    header("location: ../../login_v2.php");
    exit();
}

$stats = [
    'pending' => 0,
    'personal' => 0,
    'official' => 0,
    'outside' => 0,
];

$pendingRows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $pendingRows[] = $row;
    $stats['pending']++;
    if (($row['typeofbusiness'] ?? '') === 'Personal') {
        $stats['personal']++;
    } else {
        $stats['official']++;
    }
}

$outsideResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `request` WHERE Status = 'Approved' AND DATE(`date`) = CURDATE()");
if ($outsideResult && $outsideRow = mysqli_fetch_assoc($outsideResult)) {
    $stats['outside'] = (int) $outsideRow['total'];
}

$approverName = $_SESSION['pay_name'] ?? $_SESSION['username'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E-Pass Slip Approver</title>
    <style>
        :root {
            --bg: #f4f7f4;
            --panel: #ffffff;
            --panel-soft: #f8faf8;
            --text: #17211b;
            --muted: #66746b;
            --line: #dfe7e0;
            --primary: #167344;
            --primary-dark: #0e5c35;
            --primary-soft: #e5f4ea;
            --info: #246b95;
            --warning: #946b13;
            --danger: #b42318;
            --danger-soft: #fee4e2;
            --shadow: 0 12px 28px rgba(21, 43, 30, .08);
            --radius: 8px;
            font-family: Arial, Helvetica, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
        }

        a { color: inherit; text-decoration: none; }
        button, input, select { font: inherit; }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 18px;
            align-items: center;
            min-height: 68px;
            padding: 10px 22px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, .96);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 190px;
        }

        .brand img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
        }

        .brand strong,
        .brand span,
        .user-chip strong,
        .user-chip span {
            display: block;
        }

        .brand span,
        .user-chip span,
        .muted,
        .panel-head p,
        .empty {
            color: var(--muted);
        }

        .nav {
            display: flex;
            justify-content: flex-end;
            gap: 4px;
            overflow-x: auto;
        }

        .nav a {
            padding: 9px 12px;
            border-radius: var(--radius);
            color: var(--muted);
            white-space: nowrap;
        }

        .nav a:hover,
        .nav a.active {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }

        .user-chip {
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel-soft);
            text-align: right;
        }

        .nav-toggle { display: none; }

        .page {
            width: min(1440px, calc(100% - 32px));
            margin: 22px auto 42px;
        }

        .page-header,
        .panel-head,
        .batch-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
        }

        .page-header { margin-bottom: 16px; }
        .page-header h1, .panel h2 { margin: 0; line-height: 1.1; }
        .page-header h1 { font-size: 28px; }

        .eyebrow {
            margin: 0 0 6px;
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
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

        input, select {
            width: 100%;
            min-height: 40px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: #fff;
            color: var(--text);
            padding: 9px 11px;
        }

        input:focus, select:focus, button:focus, a:focus {
            outline: 3px solid rgba(22, 115, 68, .18);
            outline-offset: 1px;
        }

        .btn,
        .btn-sm {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 8px 12px;
            border: 1px solid transparent;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 700;
            line-height: 1;
        }

        .btn-sm {
            min-height: 32px;
            padding: 7px 10px;
            font-size: 12px;
        }

        .btn:disabled {
            cursor: not-allowed;
            opacity: .52;
        }

        .btn-primary,
        .btn-success {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover,
        .btn-success:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            border-color: var(--line);
            background: #fff;
            color: var(--text);
        }

        .btn-info {
            background: #e8f2f7;
            color: var(--info);
        }

        .icon-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: #fff;
            cursor: pointer;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .metric,
        .panel {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            box-shadow: var(--shadow);
        }

        .metric { padding: 16px; }
        .metric span, .metric strong { display: block; }
        .metric span { color: var(--muted); }
        .metric strong { margin-top: 8px; font-size: 30px; }

        .panel { padding: 16px; }

        .panel-head {
            margin-bottom: 14px;
        }

        .batch-bar {
            min-height: 56px;
            margin-bottom: 12px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel-soft);
        }

        .table-wrap {
            max-height: 68vh;
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: var(--radius);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
        }

        th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f7faf7;
            color: #405046;
            font-size: 12px;
            text-transform: uppercase;
        }

        tbody tr:hover {
            background: #fbfdfb;
        }

        td:nth-child(5) {
            font-weight: 700;
            color: var(--warning);
        }

        td:last-child {
            white-space: nowrap;
        }

        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            min-height: 18px;
            vertical-align: middle;
        }

        .select-all-control {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge-info { background: #e8f2f7; color: var(--info); }
        .badge-warning { background: #fff6dd; color: var(--warning); }

        .empty {
            padding: 16px;
            text-align: center;
        }

        dialog {
            width: min(680px, calc(100% - 24px));
            border: 0;
            padding: 0;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        dialog::backdrop {
            background: rgba(13, 25, 17, .45);
        }

        .modal-card {
            padding: 18px;
        }

        .modal-card header,
        .modal-card footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin: 16px 0;
        }

        .form-grid .wide {
            grid-column: 1 / -1;
        }

        .selection-summary {
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel-soft);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin: 16px 0;
        }

        .detail-item {
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel-soft);
        }

        .detail-item.wide {
            grid-column: 1 / -1;
        }

        .detail-item span,
        .detail-item strong {
            display: block;
        }

        .detail-item span {
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
        }

        .toast-stack {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 30;
            display: grid;
            gap: 10px;
        }

        .toast {
            width: min(360px, calc(100vw - 36px));
            padding: 12px 14px;
            border-radius: var(--radius);
            background: #122119;
            color: #fff;
            box-shadow: var(--shadow);
        }

        @media (max-width: 960px) {
            .topbar {
                grid-template-columns: 1fr auto;
            }

            .nav-toggle {
                display: inline-flex;
                justify-content: center;
                padding: 8px 10px;
                border: 1px solid var(--line);
                border-radius: var(--radius);
                background: #fff;
            }

            .nav {
                grid-column: 1 / -1;
                display: none;
                justify-content: flex-start;
                width: 100%;
            }

            .nav.open {
                display: flex;
            }

            .user-chip {
                display: none;
            }

            .metric-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .page {
                width: min(100% - 20px, 1440px);
                margin-top: 14px;
            }

            .page-header,
            .panel-head,
            .batch-bar {
                align-items: stretch;
                flex-direction: column;
            }

            .toolbar,
            .toolbar label,
            .toolbar button,
            .batch-bar button {
                width: 100%;
            }

            .metric-grid,
            .form-grid,
            .detail-grid {
                grid-template-columns: 1fr;
            }

            .modal-actions {
                flex-direction: column;
            }

            .modal-actions .btn {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: no-preference) {
            .btn, .nav a, .toast {
                transition: background-color 160ms ease, color 160ms ease, transform 160ms ease;
            }

            .btn:active {
                transform: translateY(1px);
            }
        }
    </style>

    <link rel="stylesheet" href="../assets/passlip-modern.css?v=20260603">
    <script defer src="../assets/passlip-modern.js?v=20260603"></script>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="index_desk.php">
            <img src="../logo.png" alt="GSO logo">
            <strong>E-Pass Slip</strong>
            <span>Approver workspace</span>
        </a>
        <button class="nav-toggle" type="button" id="navToggle">Menu</button>
        <nav class="nav" id="mainNav" aria-label="Primary navigation">
            <a class="active" href="index_desk.php">Approvals</a>
            <a href="track_emp_desk.php">Track Employees</a>
            <a href="qrcode_scanner_desk.php">Scanner</a>
            <a href="../../logout.php">Logout</a>
        </nav>
        <div class="user-chip">
            <strong><?php echo htmlspecialchars($approverName); ?></strong>
            <span><?php echo htmlspecialchars($_SESSION['role'] ?? 'Approver'); ?></span>
        </div>
    </header>

    <main class="page">
        <section class="page-header">
            <div>
                <p class="eyebrow">Live approval queue</p>
                <h1>Pending Requests</h1>
                <p class="muted">Review, select, and process pass-slip requests without leaving the queue.</p>
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

        <section class="metric-grid" aria-label="Request summary">
            <div class="metric"><span>Pending</span><strong id="pendingCount"><?php echo $stats['pending']; ?></strong></div>
            <div class="metric"><span>Official Business</span><strong><?php echo $stats['official']; ?></strong></div>
            <div class="metric"><span>Personal</span><strong><?php echo $stats['personal']; ?></strong></div>
            <div class="metric"><span>Currently Outside</span><strong><?php echo $stats['outside']; ?></strong></div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Approval Queue</h2>
                    <p id="queueMeta"><?php echo count($pendingRows); ?> request(s) loaded</p>
                </div>
            </div>

            <div class="batch-bar">
                <div>
                    <strong id="selectionCount">No requests selected</strong>
                    <div class="muted">Selected rows stay checked when the queue refreshes.</div>
                </div>
                <button id="acceptAllBtn" class="btn btn-primary" type="button" disabled>Process Selected</button>
            </div>

            <div class="table-wrap">
                <table id="approvalTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Destination</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>
                                <label class="select-all-control">
                                    <input type="checkbox" id="selectAllRequests">
                                    <span>Select all</span>
                                </label>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php foreach ($pendingRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row["name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["position"]); ?></td>
                                <td><?php echo htmlspecialchars($row["destination"]); ?></td>
                                <td><?php echo htmlspecialchars($row["typeofbusiness"]); ?></td>
                                <td><?php echo htmlspecialchars($row["Status"]); ?></td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm" data-view-request="<?php echo (int) $row['id']; ?>">View</button>
                                    <input type="checkbox" name="selected[]" value="<?php echo (int) $row['id']; ?>" aria-label="Select request for <?php echo htmlspecialchars($row["name"]); ?>">
                                </td>
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

    <dialog id="acceptModal">
        <form id="batchApprovalForm" class="modal-card" method="post">
            <header>
                <div>
                    <p class="eyebrow">Batch action</p>
                    <h2>Process selected requests</h2>
                </div>
                <button type="button" class="icon-btn" id="closeModal" aria-label="Close">x</button>
            </header>

            <input type="hidden" name="selected_ids" id="selected_ids_input">

            <div class="selection-summary" id="selectedRequestsList">No selected requests.</div>

            <div class="form-grid">
                <label>
                    <span>Status</span>
                    <select id="sel1" name="status" required>
                        <option value="Partially Approved">Partially Approved</option>
                        <option value="Declined">Declined</option>
                    </select>
                </label>
                <label>
                    <span>Confirmed By</span>
                    <input id="sel2" name="confirmed_by" value="<?php echo htmlspecialchars($approverName); ?>" required>
                </label>
                <label class="time-field">
                    <span>Hours</span>
                    <input type="number" id="fix_hours" name="fix_hours" min="0" max="8" value="1">
                </label>
                <label class="time-field">
                    <span>Minutes</span>
                    <input type="number" id="fix_minutes" name="fix_minutes" min="0" max="59" value="0">
                </label>
            </div>

            <footer>
                <button type="button" class="btn btn-secondary" id="cancelModal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="approveAllBtn">
                    <span id="approveButtonText">Confirm Action</span>
                </button>
            </footer>
        </form>
    </dialog>

    <dialog id="requestDetailModal">
        <div class="modal-card">
            <header>
                <div>
                    <p class="eyebrow">Request details</p>
                    <h2>Review Request</h2>
                </div>
                <button type="button" class="icon-btn" id="closeDetailModal" aria-label="Close">x</button>
            </header>
            <div id="requestDetailBody" class="detail-modal-body">
                <div class="empty">Loading request details...</div>
            </div>
        </div>
    </dialog>

    <div class="toast-stack" id="toastStack"></div>

    <script>
        const selectedStates = {};
        const tableBody = document.getElementById('table-body');
        const acceptAllBtn = document.getElementById('acceptAllBtn');
        const selectionCount = document.getElementById('selectionCount');
        const selectedIdsInput = document.getElementById('selected_ids_input');
        const selectedRequestsList = document.getElementById('selectedRequestsList');
        const selectAllRequests = document.getElementById('selectAllRequests');
        const modal = document.getElementById('acceptModal');
        const detailModal = document.getElementById('requestDetailModal');
        const detailBody = document.getElementById('requestDetailBody');
        const searchInput = document.getElementById('searchInput');
        const typeFilter = document.getElementById('typeFilter');

        document.getElementById('navToggle').addEventListener('click', () => {
            document.getElementById('mainNav').classList.toggle('open');
        });

        function toast(message) {
            const item = document.createElement('div');
            item.className = 'toast';
            item.textContent = message;
            document.getElementById('toastStack').appendChild(item);
            setTimeout(() => item.remove(), 4200);
        }

        function getCheckboxes() {
            return Array.from(document.querySelectorAll('input[name="selected[]"]'));
        }

        function getSelectedCheckboxes() {
            return getCheckboxes().filter(checkbox => checkbox.checked);
        }

        function saveSelection() {
            getCheckboxes().forEach(checkbox => {
                selectedStates[checkbox.value] = checkbox.checked;
            });
        }

        function restoreSelection() {
            getCheckboxes().forEach(checkbox => {
                checkbox.checked = Boolean(selectedStates[checkbox.value]);
            });
            updateSelectionUi();
        }

        function updateSelectionUi() {
            const selected = getSelectedCheckboxes();
            const checkboxes = getCheckboxes();
            acceptAllBtn.disabled = selected.length === 0;
            selectionCount.textContent = selected.length ? `${selected.length} request(s) selected` : 'No requests selected';
            selectedIdsInput.value = JSON.stringify(selected.map(checkbox => checkbox.value));
            if (selectAllRequests) {
                selectAllRequests.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
                selectAllRequests.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
            }
        }

        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const type = typeFilter.value;
            let visibleCount = 0;

            Array.from(tableBody.querySelectorAll('tr')).forEach(row => {
                if (row.querySelector('.empty')) return;
                const cells = Array.from(row.cells).map(cell => cell.textContent.trim());
                const matchesQuery = !query || cells.slice(0, 3).join(' ').toLowerCase().includes(query);
                const matchesType = !type || cells[3] === type;
                const visible = matchesQuery && matchesType;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });

            document.getElementById('queueMeta').textContent = `${visibleCount} visible request(s)`;
        }

        function renderSelectedSummary() {
            const selected = getSelectedCheckboxes();
            if (!selected.length) {
                selectedRequestsList.textContent = 'No selected requests.';
                return;
            }

            selectedRequestsList.innerHTML = selected.map(checkbox => {
                const row = checkbox.closest('tr');
                const name = row.cells[0].textContent.trim();
                const destination = row.cells[2].textContent.trim();
                return `<div><strong>${name}</strong><span class="muted"> · ${destination} · ID ${checkbox.value}</span></div>`;
            }).join('');
        }

        async function loadQueue() {
            if (document.hidden) return;
            saveSelection();
            try {
                const response = await fetch('data_desk.php', { cache: 'no-store' });
                const html = await response.text();
                tableBody.innerHTML = html || '<tr><td colspan="6" class="empty">No pending requests right now.</td></tr>';
                restoreSelection();
                applyFilters();
                document.getElementById('pendingCount').textContent = getCheckboxes().length;
            } catch (error) {
                toast('Unable to refresh queue. Please try again.');
            }
        }

        tableBody.addEventListener('change', event => {
            if (event.target.name === 'selected[]') {
                selectedStates[event.target.value] = event.target.checked;
                updateSelectionUi();
            }
        });

        selectAllRequests?.addEventListener('change', event => {
            getCheckboxes().forEach(checkbox => {
                checkbox.checked = event.target.checked;
                selectedStates[checkbox.value] = event.target.checked;
            });
            updateSelectionUi();
        });

        searchInput.addEventListener('input', applyFilters);
        typeFilter.addEventListener('change', applyFilters);
        document.getElementById('refreshBtn').addEventListener('click', loadQueue);

        acceptAllBtn.addEventListener('click', () => {
            const selected = getSelectedCheckboxes();
            if (!selected.length) {
                toast('Select at least one request first.');
                return;
            }
            renderSelectedSummary();
            updateSelectionUi();
            modal.showModal();
        });

        document.getElementById('closeModal').addEventListener('click', () => modal.close());
        document.getElementById('cancelModal').addEventListener('click', () => modal.close());
        document.getElementById('closeDetailModal').addEventListener('click', () => detailModal.close());

        document.getElementById('sel1').addEventListener('change', event => {
            const declined = event.target.value === 'Declined';
            document.querySelectorAll('.time-field').forEach(field => {
                field.style.display = declined ? 'none' : '';
            });
        });

        document.getElementById('batchApprovalForm').addEventListener('submit', async event => {
            event.preventDefault();
            updateSelectionUi();

            if (!selectedIdsInput.value || selectedIdsInput.value === '[]') {
                toast('No requests selected.');
                return;
            }

            const approveBtn = document.getElementById('approveAllBtn');
            const approveText = document.getElementById('approveButtonText');
            approveBtn.disabled = true;
            approveText.textContent = 'Processing...';

            try {
                const response = await fetch('bulk_accept.php', {
                    method: 'POST',
                    body: new FormData(event.target)
                });
                const data = await response.json();
                toast(data.message || 'Requests processed.');
                if (data.success) {
                    Object.keys(selectedStates).forEach(id => selectedStates[id] = false);
                    modal.close();
                    await loadQueue();
                }
            } catch (error) {
                toast('There was an error processing the selected requests.');
            } finally {
                approveBtn.disabled = false;
                approveText.textContent = 'Confirm Action';
            }
        });

        async function openRequestDetails(id) {
            detailBody.innerHTML = '<div class="empty">Loading request details...</div>';
            detailModal.showModal();

            try {
                const response = await fetch(`request_details_modal.php?id=${encodeURIComponent(id)}`, { cache: 'no-store' });
                const html = await response.text();
                detailBody.innerHTML = html || '<div class="empty">Unable to load request details.</div>';
                if (response.ok) {
                    bindSingleRequestForm();
                }
            } catch (error) {
                detailBody.innerHTML = '<div class="empty">Unable to load request details.</div>';
            }
        }

        function bindSingleRequestForm() {
            const status = detailBody.querySelector('[data-single-status]');
            const reason = detailBody.querySelector('[data-single-decline-reason]');
            const approve = detailBody.querySelector('[data-single-approve]');
            const decline = detailBody.querySelector('[data-single-decline]');

            function syncSingleAction() {
                const isDeclined = status && status.value === 'Declined';
                if (reason) reason.hidden = !isDeclined;
                if (approve) approve.hidden = isDeclined;
                if (decline) decline.hidden = !isDeclined;
            }

            status?.addEventListener('change', syncSingleAction);
            detailBody.querySelectorAll('[data-close-detail]').forEach(button => {
                button.addEventListener('click', () => detailModal.close());
            });
            syncSingleAction();
        }

        tableBody.addEventListener('click', event => {
            const button = event.target.closest('[data-view-request]');
            if (!button) return;
            openRequestDetails(button.dataset.viewRequest);
        });

        applyFilters();
        updateSelectionUi();
        setInterval(loadQueue, 30000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) loadQueue();
        });
    </script>
</body>
</html>
