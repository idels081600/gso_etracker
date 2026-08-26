<?php
session_start();
require_once __DIR__ . '/rice_claim_consolidation_lib.php';

riceConsolidationRequireVerifier();
$conn = require __DIR__ . '/config/database.php';
mysqli_report(MYSQLI_REPORT_OFF);

$query = trim((string)($_GET['q'] ?? ''));
$filter = trim((string)($_GET['filter'] ?? 'all'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(10, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;
$allowedFilters = ['all', 'both_waves', 'first_wave_only', 'second_wave_only', 'unmatched', 'name_difference'];

if (!in_array($filter, $allowedFilters, true)) {
    riceConsolidationJsonResponse(['success' => false, 'message' => 'Invalid pairing filter.'], 422);
}

$cte = riceConsolidationPairCte();
$where = [];
$params = [];
$types = '';

if ($query !== '') {
    $like = $query . '%';
    $where[] = '(household_code LIKE ? OR first_household_name LIKE ? OR second_household_name LIKE ? OR first_claimant_name LIKE ? OR second_claimant_name LIKE ?)';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}

if ($filter !== 'all') {
    $where[] = 'pair_state = ?';
    $types .= 's';
    $params[] = $filter;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$totalUsesSummary = $query === '';
$total = 0;
if (!$totalUsesSummary) {
    $countStmt = $conn->prepare($cte . " SELECT COUNT(*) AS total FROM claim_pairs{$whereSql}");
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult ? (int)$countResult->fetch_assoc()['total'] : 0;
}

$listSql = $cte . "
    SELECT
        claim_pairs.household_code,
        first_household_name,
        first_address,
        first_claim_id,
        first_claimant_name,
        first_claim_date,
        first_verifier_name,
        second_household_name,
        second_address,
        second_claim_id,
        second_claimant_name,
        second_claim_date,
        second_verifier_name,
        CASE WHEN first_signature.e_signature IS NOT NULL AND OCTET_LENGTH(first_signature.e_signature) > 0 THEN 1 ELSE 0 END AS first_has_signature,
        CASE WHEN second_signature.e_signature IS NOT NULL AND OCTET_LENGTH(second_signature.e_signature) > 0 THEN 1 ELSE 0 END AS second_has_signature,
        pair_state
    FROM claim_pairs
    LEFT JOIN rice_voucher_claims first_signature ON first_signature.id = claim_pairs.first_claim_id
    LEFT JOIN rice_next_wave_claims second_signature ON second_signature.id = claim_pairs.second_claim_id{$whereSql}
    ORDER BY sort_prefix ASC, sort_number ASC, claim_pairs.household_code ASC
    LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
$listTypes = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$listResult = $listStmt->get_result();
$records = [];

while ($row = $listResult->fetch_assoc()) {
    $row['first_claim_id'] = $row['first_claim_id'] !== null ? (int)$row['first_claim_id'] : null;
    $row['second_claim_id'] = $row['second_claim_id'] !== null ? (int)$row['second_claim_id'] : null;
    $row['first_has_signature'] = (int)$row['first_has_signature'];
    $row['second_has_signature'] = (int)$row['second_has_signature'];
    $row['can_consolidate'] = ($row['first_claim_id'] !== null && $row['second_claim_id'] !== null) ? 1 : 0;
    $records[] = $row;
}

$summary = [
    'all' => 0,
    'both_waves' => 0,
    'first_wave_only' => 0,
    'second_wave_only' => 0,
    'unmatched' => 0,
    'name_difference' => 0,
];
$summaryResult = $conn->query($cte . ' SELECT pair_state, COUNT(*) AS total FROM claim_pairs GROUP BY pair_state');
if ($summaryResult) {
    while ($row = $summaryResult->fetch_assoc()) {
        $summary[$row['pair_state']] = (int)$row['total'];
        $summary['all'] += (int)$row['total'];
    }
}

if ($totalUsesSummary) {
    $total = $filter === 'all' ? $summary['all'] : $summary[$filter];
}

riceConsolidationJsonResponse([
    'success' => true,
    'data' => $records,
    'summary' => $summary,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => max(1, (int)ceil($total / $perPage)),
    ],
]);