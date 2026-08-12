<?php
session_start();
require_once 'auth_payables.php';
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'transmit_db.php';
require_once 'payables_workflow.php';
require_once 'bac_dashboard_table_helpers.php';

payables_ensure_workflow_table();

$workflowStatuses = PAYABLES_WORKFLOW_STATUSES;
$dashboardStatuses = array_merge($workflowStatuses, ['GSO_LOCATION']);
$activeStatus = strtoupper(trim($_GET['status'] ?? 'GSO'));
if (!in_array($activeStatus, $dashboardStatuses, true)) {
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
    if ($activeStatus === 'GSO_LOCATION') {
        $where[] = "COALESCE(pws.main_status, 'GSO') = 'ACCOUNTING'";
        $where[] = "UPPER(TRIM(COALESCE(pws.current_location, 'ACCOUNTING'))) = 'GSO'";
    } else {
        $where[] = "COALESCE(pws.main_status, 'GSO') = ?";
        $types .= 's';
        $params[] = $activeStatus;

        if ($activeStatus === 'ACCOUNTING') {
            $where[] = "UPPER(TRIM(COALESCE(pws.current_location, 'ACCOUNTING'))) <> 'GSO'";
        }
    }
}

if ($searchTerm !== '') {
    $searchLike = '%' . $searchTerm . '%';
    $where[] = "(tb.ib_no LIKE ? OR tb.bidder LIKE ? OR tb.project_name LIKE ? OR CAST(COALESCE(NULLIF(tb.final_amount, 0), tb.abc) AS CHAR) LIKE ? OR COALESCE(pws.main_status, 'GSO') LIKE ? OR COALESCE(pws.current_location, 'ACCOUNTING') LIKE ?)";
    $types .= 'ssssss';
    array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
}

$whereSql = implode(' AND ', $where);
$editedFirstStatuses = ['BUDGET', 'ACCOUNTING', 'GSO_LOCATION', 'CTO'];
$payablesOrderBySql = (!$isGlobalSearch && in_array($activeStatus, $editedFirstStatuses, true))
    ? 'pws.updated_at DESC, tb.id DESC'
    : 'tb.id DESC';

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
    ORDER BY {$payablesOrderBySql}
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
    <link rel="stylesheet" href="payables_dashboard.css?v=<?php echo urlencode((string)filemtime(__DIR__ . '/payables_dashboard.css')); ?>">
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
                            <button type="button" class="<?php echo !$isGlobalSearch && $activeStatus === 'GSO_LOCATION' ? 'active' : ''; ?>" data-status-filter="GSO_LOCATION" data-status-url="<?php echo htmlspecialchars($buildPageUrl('GSO_LOCATION'), ENT_QUOTES, 'UTF-8'); ?>" title="Accounting records currently located at GSO">GSO</button>
                            <button type="button" class="<?php echo !$isGlobalSearch && $activeStatus === 'CTO' ? 'active' : ''; ?>" data-status-filter="CTO" data-status-url="<?php echo htmlspecialchars($buildPageUrl('CTO'), ENT_QUOTES, 'UTF-8'); ?>">CTO</button>
                        </div>
                        <div class="task-search" role="search" data-active-status="<?php echo htmlspecialchars($tableStatus, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="search" id="monitoringSearchInput" placeholder="Search records" aria-label="Search BAC records" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="button" id="monitoringSearchButton" aria-label="Search">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <?php
                    echo payables_dashboard_render_table_block([
                        'active_status' => $activeStatus,
                        'table_status' => $tableStatus,
                        'is_global_search' => $isGlobalSearch,
                        'rows' => $payablesRows,
                        'search_term' => $searchTerm,
                        'current_page' => $currentPage,
                        'total_pages' => $totalPages,
                        'total_rows' => $totalRows,
                        'offset' => $offset,
                        'per_page' => $perPage,
                        'location_history_map' => $locationHistoryMap,
                        'remarks_history_map' => $remarksHistoryMap,
                        'build_page_url' => $buildPageUrl,
                    ]);
                    ?>
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
                                        <span><?php echo htmlspecialchars($item['source'] . ' â€¢ ' . ($item['reference_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
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
    <script src="bac_monitoring.js?v=<?php echo urlencode((string)filemtime(__DIR__ . '/bac_monitoring.js')); ?>"></script>
</body>
</html>

