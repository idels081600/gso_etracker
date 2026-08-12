<?php
session_start();
require_once 'auth_payables.php';
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

$tableHtml = payables_dashboard_render_table_block([
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

payables_json_response([
    'success' => true,
    'html' => $tableHtml,
    'status' => $activeStatus,
    'tableStatus' => $tableStatus,
    'page' => $currentPage,
    'totalPages' => $totalPages,
    'totalRows' => $totalRows,
    'search' => $searchTerm,
]);
