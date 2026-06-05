<?php
session_start();
require_once 'auth_payables.php';
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'transmit_db.php';
require_once 'payables_workflow.php';

payables_ensure_workflow_table();

$workflowStatuses = PAYABLES_WORKFLOW_STATUSES;
$activeStatus = strtoupper(trim($_GET['status'] ?? 'GSO'));
if (!in_array($activeStatus, $workflowStatuses, true)) {
    $activeStatus = 'GSO';
}
$searchTerm = trim($_GET['search'] ?? '');
$isGlobalSearch = $searchTerm !== '';
$tableStatus = $isGlobalSearch ? 'SEARCH' : $activeStatus;
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($currentPage < 1) {
    $currentPage = 1;
}
$perPage = 25;
$offset = ($currentPage - 1) * $perPage;

$where = ["LOWER(TRIM(COALESCE(tb.status, ''))) <> 'not yet received'"];
$types = '';
$params = [];

if (!$isGlobalSearch) {
    $where[] = "COALESCE(pws.main_status, 'GSO') = ?";
    $types .= 's';
    $params[] = $activeStatus;
}

if ($searchTerm !== '') {
    $searchLike = '%' . $searchTerm . '%';
    $where[] = "(tb.ib_no LIKE ? OR tb.bidder LIKE ? OR tb.project_name LIKE ? OR CAST(COALESCE(NULLIF(tb.final_amount, 0), tb.abc) AS CHAR) LIKE ? OR COALESCE(pws.main_status, 'GSO') LIKE ? OR COALESCE(pws.current_location, 'ACCOUNTING') LIKE ?)";
    $types .= 'ssssss';
    array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
}

$whereSql = implode(' AND ', $where);

$totalRows = 0;
$countSql = "
    SELECT COUNT(*) AS total
    FROM bac_monitoring tb
    LEFT JOIN payables_workflow_status pws
        ON pws.record_type = 'bac_monitoring'
       AND pws.record_id = tb.id
    WHERE {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($countResult && $countRow = $countResult->fetch_assoc()) {
        $totalRows = (int)$countRow['total'];
    }
    $countStmt->close();
}

$metricCounts = [
    'GSO' => 0,
    'BUDGET' => 0,
    'ACCOUNTING' => 0,
    'CTO' => 0,
    'RELEASED' => 0,
];
$metricsSql = "
    SELECT
        SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'GSO' THEN 1 ELSE 0 END) AS gso_count,
        SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'BUDGET' THEN 1 ELSE 0 END) AS budget_count,
        SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'ACCOUNTING' THEN 1 ELSE 0 END) AS accounting_count,
        SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'CTO' THEN 1 ELSE 0 END) AS cto_count,
        SUM(CASE WHEN COALESCE(pws.released, 0) = 1 THEN 1 ELSE 0 END) AS released_count
    FROM bac_monitoring tb
    LEFT JOIN payables_workflow_status pws
        ON pws.record_type = 'bac_monitoring'
       AND pws.record_id = tb.id
    WHERE LOWER(TRIM(COALESCE(tb.status, ''))) <> 'not yet received'
";
$metricsResult = mysqli_query($conn, $metricsSql);
if ($metricsResult && $metricsRow = mysqli_fetch_assoc($metricsResult)) {
    $metricCounts['GSO'] = (int)($metricsRow['gso_count'] ?? 0);
    $metricCounts['BUDGET'] = (int)($metricsRow['budget_count'] ?? 0);
    $metricCounts['ACCOUNTING'] = (int)($metricsRow['accounting_count'] ?? 0);
    $metricCounts['CTO'] = (int)($metricsRow['cto_count'] ?? 0);
    $metricCounts['RELEASED'] = (int)($metricsRow['released_count'] ?? 0);
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}

$buildPageUrl = function (string $status, int $page = 1) use ($searchTerm): string {
    $query = [
        'status' => $status,
        'page' => $page,
    ];
    if ($searchTerm !== '') {
        $query['search'] = $searchTerm;
    }

    return 'bac_dashboard.php?' . http_build_query($query);
};

$payablesRows = [];
$payablesSql = "
    SELECT
        tb.id,
        tb.ib_no,
        tb.bidder AS winning_bidders,
        tb.project_name,
        COALESCE(NULLIF(tb.final_amount, 0), tb.abc) AS amount,
        tb.remarks,
        COALESCE(pws.main_status, 'GSO') AS main_status,
        COALESCE(pws.inspection, 0) AS inspection,
        COALESCE(pws.obr, 0) AS obr,
        COALESCE(pws.ics, 0) AS ics,
        COALESCE(pws.par, 0) AS par,
        COALESCE(pws.ris, 0) AS ris,
        COALESCE(pws.released, 0) AS released,
        COALESCE(pws.current_location, 'ACCOUNTING') AS current_location
    FROM bac_monitoring tb
    LEFT JOIN payables_workflow_status pws
        ON pws.record_type = 'bac_monitoring'
       AND pws.record_id = tb.id
    WHERE {$whereSql}
    ORDER BY tb.id DESC
    LIMIT ? OFFSET ?";
$pageTypes = $types . 'ii';
$pageParams = array_merge($params, [$perPage, $offset]);
$payablesStmt = $conn->prepare($payablesSql);
if ($payablesStmt) {
    $payablesStmt->bind_param($pageTypes, ...$pageParams);
    $payablesStmt->execute();
    $payablesResult = $payablesStmt->get_result();
    while ($row = mysqli_fetch_assoc($payablesResult)) {
        $payablesRows[] = $row;
    }
    $payablesStmt->close();
}

$recordIds = array_column($payablesRows, 'id');
$locationHistoryMap = payables_get_location_history_map('bac_monitoring', $recordIds);
$remarksHistoryMap = payables_get_remarks_history_map('bac_monitoring', $recordIds);

$latestTransactions = [];
$latestSql = "
    SELECT 'BAC' AS source, ib_no AS reference_no, project_name AS title, date_transmitted_from_bac AS transaction_date
    FROM bac_monitoring
    WHERE LOWER(TRIM(COALESCE(status, ''))) <> 'not yet received'
    UNION ALL
    SELECT 'RFQ' AS source, RFQ_no AS reference_no, supplier AS title, date_received AS transaction_date
    FROM PO_sap
    WHERE delete_status = 0
    ORDER BY transaction_date DESC
    LIMIT 8";
$latestResult = mysqli_query($conn, $latestSql);
if ($latestResult) {
    while ($row = mysqli_fetch_assoc($latestResult)) {
        $latestTransactions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="payables-csrf-token" content="<?php echo htmlspecialchars(payables_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="payables_dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Payables - Dashboard</title>
</head>
<body class="payables-dashboard-page">
    <?php $payablesActivePage = 'monitoring'; require 'payables_sidebar.php'; ?>
    <main class="dashboard-content">
        <section class="dashboard-grid" aria-label="Payables dashboard layout">
            <article class="dashboard-card metric-card metric-card-gso">
                <div class="metric-icon"><i class="fas fa-clipboard-check"></i></div>
                <div class="metric-copy">
                    <span>GSO</span>
                    <strong><?php echo number_format($metricCounts['GSO']); ?></strong>
                    <small>Total</small>
                </div>
            </article>
            <article class="dashboard-card metric-card metric-card-budget">
                <div class="metric-icon"><i class="fas fa-wallet"></i></div>
                <div class="metric-copy">
                    <span>Budget</span>
                    <strong><?php echo number_format($metricCounts['BUDGET']); ?></strong>
                    <small>Total</small>
                </div>
            </article>
            <article class="dashboard-card metric-card metric-card-accounting">
                <div class="metric-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="metric-copy">
                    <span>Accounting</span>
                    <strong><?php echo number_format($metricCounts['ACCOUNTING']); ?></strong>
                    <small>Total</small>
                </div>
            </article>
            <article class="dashboard-card metric-card metric-card-cto">
                <div class="metric-icon"><i class="fas fa-building"></i></div>
                <div class="metric-copy">
                    <span>CTO</span>
                    <strong><?php echo number_format($metricCounts['CTO']); ?></strong>
                    <small>Total</small>
                </div>
            </article>
            <article class="dashboard-card metric-card metric-card-released">
                <div class="metric-icon"><i class="fas fa-check-double"></i></div>
                <div class="metric-copy">
                    <span>Checked Releases</span>
                    <strong><?php echo number_format($metricCounts['RELEASED']); ?></strong>
                    <small>Total</small>
                </div>
            </article>
            <section class="dashboard-card dashboard-main-panel">
                <div class="task-panel">
                    <div class="task-header">
                        <h1>Payables List </h1>
                        <div class="task-header-actions">
                            <button type="button" class="task-filter">BAC Records <i class="fas fa-chevron-down"></i></button>
                        </div>
                    </div>
                    <div class="task-control-row">
                        <div class="task-tabs" aria-label="Task category filters">
                            <button type="button" class="<?php echo !$isGlobalSearch && $activeStatus === 'GSO' ? 'active' : ''; ?>" data-status-filter="GSO" data-status-url="<?php echo htmlspecialchars($buildPageUrl('GSO'), ENT_QUOTES, 'UTF-8'); ?>">GSO Checklist</button>
                            <button type="button" class="<?php echo !$isGlobalSearch && $activeStatus === 'BUDGET' ? 'active' : ''; ?>" data-status-filter="BUDGET" data-status-url="<?php echo htmlspecialchars($buildPageUrl('BUDGET'), ENT_QUOTES, 'UTF-8'); ?>">Budget</button>
                            <button type="button" class="<?php echo !$isGlobalSearch && $activeStatus === 'ACCOUNTING' ? 'active' : ''; ?>" data-status-filter="ACCOUNTING" data-status-url="<?php echo htmlspecialchars($buildPageUrl('ACCOUNTING'), ENT_QUOTES, 'UTF-8'); ?>">Accounting</button>
                            <button type="button" class="<?php echo !$isGlobalSearch && $activeStatus === 'CTO' ? 'active' : ''; ?>" data-status-filter="CTO" data-status-url="<?php echo htmlspecialchars($buildPageUrl('CTO'), ENT_QUOTES, 'UTF-8'); ?>">CTO</button>
                        </div>
                        <div class="task-search" role="search" data-active-status="<?php echo htmlspecialchars($tableStatus, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="search" id="monitoringSearchInput" placeholder="Search records" aria-label="Search BAC records" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="button" id="monitoringSearchButton" aria-label="Search">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="task-table" role="table" aria-label="Payables workflow task list" data-active-status="<?php echo htmlspecialchars($tableStatus, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="task-row task-row-head" role="row">
                            <div>IB / RFQ No.</div>
                            <div>Winning Bidder / Project</div>
                            <div>Amount</div>
                            <div class="search-status-head">Status</div>
                            <div id="workflowDetailHeader"><?php echo $isGlobalSearch ? 'Details' : ($activeStatus === 'GSO' ? 'Checklist' : 'Remarks'); ?></div>
                            <div class="accounting-location-head">Location</div>
                            <div id="workflowActionHeader"><?php
                                echo $isGlobalSearch
                                    ? 'Action'
                                    : ($activeStatus === 'BUDGET'
                                    ? 'To Accounting'
                                    : ($activeStatus === 'ACCOUNTING'
                                        ? 'To CTO'
                                        : ($activeStatus === 'CTO' ? 'Completed' : 'Transmit to Budget')));
                            ?></div>
                            <div class="cto-release-head">Check Released</div>
                        </div>
                        <?php if ($payablesRows): ?>
                            <?php foreach ($payablesRows as $row): ?>
                                <?php
                                $checkKeys = ['inspection', 'obr'];
                                $gsoChecklistItems = [
                                    'inspection' => 'Inspection',
                                    'obr' => 'OBR',
                                    'ics' => 'ICS',
                                    'par' => 'PAR',
                                    'ris' => 'RIS',
                                ];
                                $mainStatus = in_array($row['main_status'] ?? '', PAYABLES_WORKFLOW_STATUSES, true) ? $row['main_status'] : 'GSO';
                                $completeCount = 0;
                                foreach ($checkKeys as $key) {
                                    if (!empty($row[$key])) {
                                        $completeCount++;
                                    }
                                }
                                $canTransmitToBudget = $mainStatus === 'GSO';
                                $canTransmitToAccounting = $mainStatus === 'BUDGET';
                                $canTransmitToCto = $mainStatus === 'ACCOUNTING';
                                $actionEnabled = $canTransmitToBudget || $canTransmitToAccounting || $canTransmitToCto;
                                $currentLocation = payables_normalize_location($row['current_location'] ?? '');
                                $locationHistory = $locationHistoryMap[(int)$row['id']] ?? [];
                                if (!$locationHistory) {
                                    $locationHistory[] = [
                                        'location' => $currentLocation,
                                        'changed_by' => '',
                                        'changed_at' => '',
                                    ];
                                }
                                $locationHistoryJson = htmlspecialchars(json_encode($locationHistory), ENT_QUOTES, 'UTF-8');
                                $remarksHistory = $remarksHistoryMap[(int)$row['id']] ?? [];
                                if (!$remarksHistory) {
                                    $remarksHistory[] = [
                                        'remarks' => trim($row['remarks'] ?? '') !== '' ? trim($row['remarks']) : 'No remarks yet.',
                                        'changed_by' => '',
                                        'changed_at' => '',
                                    ];
                                }
                                $remarksHistoryJson = htmlspecialchars(json_encode($remarksHistory), ENT_QUOTES, 'UTF-8');
                                $actionTitle = $canTransmitToBudget
                                    ? 'Transmit to Budget'
                                    : ($canTransmitToAccounting
                                        ? 'Transmit to Accounting'
                                        : ($canTransmitToCto ? 'Transmit to CTO' : 'Complete the previous stage first'));
                                ?>
                                <div class="task-row payables-row" role="row" data-main-status="<?php echo htmlspecialchars($mainStatus, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="task-name"><strong><?php echo htmlspecialchars($row['ib_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><span>BAC transmittal</span></div>
                                    <div class="task-name task-project-stack">
                                        <strong><?php echo htmlspecialchars($row['winning_bidders'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo htmlspecialchars($row['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="amount-cell">&#8369;<?php echo number_format((float)$row['amount'], 2); ?></div>
                                    <div class="search-status-cell">
                                        <span class="search-status-badge status-<?php echo strtolower(htmlspecialchars($mainStatus, ENT_QUOTES, 'UTF-8')); ?>">
                                            <?php echo htmlspecialchars($mainStatus, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                    <div class="checklist-cell">
                                        <div class="gso-checklist-strip" aria-label="GSO checklist">
                                            <?php foreach ($gsoChecklistItems as $key => $label): ?>
                                                <?php $isComplete = !empty($row[$key]); ?>
                                                <button
                                                    type="button"
                                                    class="gso-check-chip <?php echo $isComplete ? 'is-complete' : 'is-missing'; ?>"
                                                    title="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                                                    aria-pressed="<?php echo $isComplete ? 'true' : 'false'; ?>"
                                                    data-check-key="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo in_array($key, $checkKeys, true) ? 'data-required-check="1"' : ''; ?>
                                                >
                                                    <i class="fas <?php echo $isComplete ? 'fa-check' : 'fa-minus'; ?>"></i>
                                                    <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="budget-remarks">
                                            <span data-remark-for="BUDGET" class="workflow-remarks-preview">
                                                <?php echo htmlspecialchars(trim($row['remarks'] ?? '') !== '' ? $row['remarks'] : 'No remarks yet.', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <span data-remark-for="ACCOUNTING" class="workflow-remarks-preview">
                                                <?php echo htmlspecialchars(trim($row['remarks'] ?? '') !== '' ? $row['remarks'] : 'No remarks yet.', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <strong data-remark-for="CTO"></strong>
                                            <button
                                                type="button"
                                                class="workflow-remarks-edit"
                                                data-remark-for="CTO"
                                                data-record-id="<?php echo (int)$row['id']; ?>"
                                                data-reference="<?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-current-remarks="<?php echo htmlspecialchars(trim($row['remarks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-remarks-stage="CTO"
                                            >
                                                <?php echo htmlspecialchars(trim($row['remarks'] ?? '') !== '' ? $row['remarks'] : 'No remarks yet.', ENT_QUOTES, 'UTF-8'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="accounting-location-cell">
                                        <select
                                            class="accounting-location-select"
                                            aria-label="Location for <?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-record-id="<?php echo (int)$row['id']; ?>"
                                        >
                                            <?php foreach (PAYABLES_LOCATION_OPTIONS as $locationOption): ?>
                                                <option value="<?php echo htmlspecialchars($locationOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $currentLocation === $locationOption ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($locationOption, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="transmit-action-cell">
                                        <?php if ($mainStatus === 'CTO'): ?>
                                            <span class="completed-check-icon" title="Completed" aria-label="Completed">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php else: ?>
                                            <div class="transmit-action-group">
                                                <button
                                                    type="button"
                                                    class="transmit-budget-btn"
                                                    title="<?php echo htmlspecialchars($actionTitle, ENT_QUOTES, 'UTF-8'); ?>"
                                                    aria-label="<?php echo htmlspecialchars($actionTitle . ' for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-record-id="<?php echo (int)$row['id']; ?>"
                                                    data-inspection="<?php echo !empty($row['inspection']) ? '1' : '0'; ?>"
                                                    data-obr="<?php echo !empty($row['obr']) ? '1' : '0'; ?>"
                                                    data-ics="<?php echo !empty($row['ics']) ? '1' : '0'; ?>"
                                                    data-par="<?php echo !empty($row['par']) ? '1' : '0'; ?>"
                                                    data-ris="<?php echo !empty($row['ris']) ? '1' : '0'; ?>"
                                                    <?php echo $actionEnabled ? '' : 'disabled'; ?>
                                                >
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                                <?php if (in_array($mainStatus, ['BUDGET', 'ACCOUNTING'], true)): ?>
                                                    <button
                                                        type="button"
                                                        class="workflow-remarks-action"
                                                        title="Edit remarks"
                                                        aria-label="<?php echo htmlspecialchars('Edit remarks for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-record-id="<?php echo (int)$row['id']; ?>"
                                                        data-reference="<?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-current-remarks="<?php echo htmlspecialchars(trim($row['remarks'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-remarks-stage="<?php echo htmlspecialchars(ucfirst(strtolower($mainStatus)), ENT_QUOTES, 'UTF-8'); ?>"
                                                    >
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button
                                                    type="button"
                                                    class="location-history-btn"
                                                    title="View location history"
                                                    aria-label="<?php echo htmlspecialchars('View location history for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-reference="<?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-location-history="<?php echo $locationHistoryJson; ?>"
                                                >
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cto-release-cell">
                                        <div class="cto-release-actions">
                                            <label class="cto-release-check" title="Mark as released">
                                                <input
                                                    type="checkbox"
                                                    class="cto-release-checkbox"
                                                    aria-label="Check released for <?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-record-id="<?php echo (int)$row['id']; ?>"
                                                    <?php echo !empty($row['released']) ? 'checked' : ''; ?>
                                                >
                                                <span></span>
                                            </label>
                                            <button
                                                type="button"
                                                class="remarks-history-btn"
                                                title="View remarks history"
                                                aria-label="<?php echo htmlspecialchars('View remarks history for ' . ($row['ib_no'] ?? 'record'), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-reference="<?php echo htmlspecialchars($row['ib_no'] ?? 'record', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-remarks-history="<?php echo $remarksHistoryJson; ?>"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="task-row payables-row empty-row" role="row">
                                <div class="text-muted"><?php echo $searchTerm !== '' ? 'No matching BAC records found.' : 'No BAC records found.'; ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="task-pagination" aria-label="Payables pagination">
                        <span>
                            Showing <?php echo $totalRows === 0 ? 0 : $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?>
                            of <?php echo $totalRows; ?>
                        </span>
                        <div class="task-page-buttons">
                            <a class="<?php echo $currentPage <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildPageUrl($activeStatus, max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous page">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <strong>Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></strong>
                            <a class="<?php echo $currentPage >= $totalPages ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildPageUrl($activeStatus, min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next page">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </section>
            <aside class="dashboard-card dashboard-side-panel">
                <div class="history-panel">
                    <div class="history-header">
                        <h2>Latest Transaction</h2>
                        <span>History</span>
                    </div>
                    <div class="history-list">
                        <?php if ($latestTransactions): ?>
                            <?php foreach ($latestTransactions as $item): ?>
                                <div class="history-item">
                                    <span class="history-icon"><?php echo htmlspecialchars(substr($item['source'], 0, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div class="history-copy">
                                        <strong><?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo htmlspecialchars($item['source'] . ' • ' . ($item['reference_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <time><?php echo htmlspecialchars(substr((string)$item['transaction_date'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></time>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="history-empty">No recent transactions.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </section>
    </main>
    <div class="modal fade" id="transmitConfirmModal" tabindex="-1" aria-labelledby="transmitConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered transmit-confirm-dialog">
            <div class="modal-content transmit-confirm-modal">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="transmitConfirmModalLabel">Confirm Transmittal</h5>
                        <div class="transmit-confirm-subtitle" id="transmitConfirmSubtitle">Review this routing action before continuing.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="transmitConfirmMessage" class="transmit-confirm-message mb-0">Transmit this record?</p>
                    <div class="transmit-success d-none mt-3" id="transmitSuccessAlert" role="status" aria-live="polite">
                        <span class="transmit-success-mark">
                            <i class="fas fa-check"></i>
                        </span>
                        <span class="transmit-success-text">Record transmitted successfully.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light transmit-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success transmit-confirm-btn" id="confirmTransmitBtn">
                        <span class="transmit-confirm-icon transmit-confirm-icon-idle">
                            <i class="fas fa-paper-plane"></i>
                        </span>
                        <span class="transmit-confirm-icon transmit-confirm-icon-loading" aria-hidden="true"></span>
                        <span class="transmit-confirm-label">Transmit</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="locationHistoryModal" tabindex="-1" aria-labelledby="locationHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered location-history-dialog">
            <div class="modal-content location-history-modal">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="locationHistoryModalLabel">Location History</h5>
                        <div class="location-history-subtitle" id="locationHistorySubtitle">Review location changes for this record.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="location-history-list" id="locationHistoryList" role="list"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="remarksEditModal" tabindex="-1" aria-labelledby="remarksEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered remarks-edit-dialog">
            <form class="modal-content remarks-edit-modal" id="remarksEditForm">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="remarksEditModalLabel">Edit Remarks</h5>
                        <div class="remarks-edit-subtitle" id="remarksEditSubtitle">Update remarks for this record.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="remarksEditRecordId" name="record_id">
                    <label class="remarks-edit-label" for="remarksEditText">Remarks</label>
                    <textarea id="remarksEditText" name="remarks" rows="4" maxlength="1000" placeholder="Enter remarks"></textarea>
                    <div class="remarks-edit-error d-none" id="remarksEditError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light transmit-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success remarks-save-btn" id="remarksSaveBtn">
                        <span class="remarks-save-label">Save Remarks</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="bac_monitoring.js"></script>
</body>
</html>
