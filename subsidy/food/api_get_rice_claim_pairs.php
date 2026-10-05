<?php
session_start();
require_once __DIR__ . '/rice_claim_consolidation_lib.php';

riceConsolidationRequireVerifier();
session_write_close();
$conn = require __DIR__ . '/config/database.php';
mysqli_report(MYSQLI_REPORT_OFF);

$query = trim((string)($_GET['q'] ?? ''));
$filter = trim((string)($_GET['filter'] ?? 'all'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(10, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;
$allowedFilters = ['all', 'all_four', 'partial_claims', 'name_difference'];

if (!in_array($filter, $allowedFilters, true)) {
    riceConsolidationJsonResponse(['success' => false, 'message' => 'Invalid pairing filter.'], 422);
}

$searching = $query !== '';
$cte = riceConsolidationPairCte($searching);
$where = [];
$params = [];
$types = '';

if ($searching) {
    $like = $query . '%';
    $types .= str_repeat('s', 12);
    for ($index = 0; $index < 12; $index++) {
        $params[] = $like;
    }
}

if ($filter !== 'all') {
    $where[] = 'pair_state = ?';
    $types .= 's';
    $params[] = $filter;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = 0;

$totalExpression = $searching ? 'COUNT(*) OVER()' : '0';
$listSql = $cte . "
    SELECT
        paged_pairs.household_code,
        paged_pairs.first_household_name,
        paged_pairs.first_address,
        paged_pairs.first_claim_id,
        paged_pairs.first_claimant_name,
        paged_pairs.first_claim_date,
        paged_pairs.first_verifier_name,
        paged_pairs.second_household_name,
        paged_pairs.second_address,
        paged_pairs.second_claim_id,
        paged_pairs.second_claimant_name,
        paged_pairs.second_claim_date,
        paged_pairs.second_verifier_name,
        paged_pairs.third_household_name,
        paged_pairs.third_address,
        paged_pairs.third_claim_id,
        paged_pairs.third_claimant_name,
        paged_pairs.third_claim_date,
        paged_pairs.third_verifier_name,
        paged_pairs.fourth_household_name,
        paged_pairs.fourth_address,
        paged_pairs.fourth_claim_id,
        paged_pairs.fourth_claimant_name,
        paged_pairs.fourth_claim_date,
        paged_pairs.fourth_verifier_name,
        CASE WHEN first_signature.e_signature IS NOT NULL AND OCTET_LENGTH(first_signature.e_signature) > 0 THEN 1 ELSE 0 END AS first_has_signature,
        CASE WHEN second_signature.e_signature IS NOT NULL AND OCTET_LENGTH(second_signature.e_signature) > 0 THEN 1 ELSE 0 END AS second_has_signature,
        CASE WHEN third_signature.e_signature IS NOT NULL AND OCTET_LENGTH(third_signature.e_signature) > 0 THEN 1 ELSE 0 END AS third_has_signature,
        CASE WHEN fourth_signature.e_signature IS NOT NULL AND OCTET_LENGTH(fourth_signature.e_signature) > 0 THEN 1 ELSE 0 END AS fourth_has_signature,
        paged_pairs.pair_state,
        paged_pairs.filtered_total
    FROM (
        SELECT claim_pairs.*, {$totalExpression} AS filtered_total
        FROM claim_pairs{$whereSql}
        ORDER BY sort_prefix ASC, sort_number ASC, household_code ASC
        LIMIT ? OFFSET ?
    ) paged_pairs
    LEFT JOIN rice_voucher_claims first_signature ON first_signature.id = paged_pairs.first_claim_id
    LEFT JOIN rice_next_wave_claims second_signature ON second_signature.id = paged_pairs.second_claim_id
    LEFT JOIN rice_third_wave_claims third_signature ON third_signature.id = paged_pairs.third_claim_id
    LEFT JOIN rice_fourth_wave_claims fourth_signature ON fourth_signature.id = paged_pairs.fourth_claim_id
    ORDER BY paged_pairs.sort_prefix ASC, paged_pairs.sort_number ASC, paged_pairs.household_code ASC
";$listStmt = $conn->prepare($listSql);
$listTypes = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$listResult = $listStmt->get_result();
$records = [];

while ($row = $listResult->fetch_assoc()) {
    $total = (int)$row['filtered_total'];
    unset($row['filtered_total']);
    $row['first_claim_id'] = $row['first_claim_id'] !== null ? (int)$row['first_claim_id'] : null;
    $row['second_claim_id'] = $row['second_claim_id'] !== null ? (int)$row['second_claim_id'] : null;
    $row['third_claim_id'] = $row['third_claim_id'] !== null ? (int)$row['third_claim_id'] : null;
    $row['fourth_claim_id'] = $row['fourth_claim_id'] !== null ? (int)$row['fourth_claim_id'] : null;
    $row['first_has_signature'] = (int)$row['first_has_signature'];
    $row['second_has_signature'] = (int)$row['second_has_signature'];
    $row['third_has_signature'] = (int)$row['third_has_signature'];
    $row['fourth_has_signature'] = (int)$row['fourth_has_signature'];
    $claimCount = count(array_filter([
        $row['first_claim_id'], $row['second_claim_id'], $row['third_claim_id'], $row['fourth_claim_id'],
    ], static fn($claimId) => $claimId !== null));
    $row['can_consolidate'] = $claimCount >= 2 ? 1 : 0;
    $records[] = $row;
}

$summary = [
    'all' => 0,
    'all_four' => 0,
    'partial_claims' => 0,
    'name_difference' => 0,
];
$includeSummary = !$searching;
if ($includeSummary) {
    $summaryResult = $conn->query(riceConsolidationPairCte() . ' SELECT pair_state, COUNT(*) AS total FROM claim_pairs GROUP BY pair_state');
    if ($summaryResult) {
        while ($row = $summaryResult->fetch_assoc()) {
            $summary[$row['pair_state']] = (int)$row['total'];
            $summary['all'] += (int)$row['total'];
        }
    }

    $total = $filter === 'all' ? $summary['all'] : $summary[$filter];
}

riceConsolidationJsonResponse([
    'success' => true,
    'data' => $records,
    'summary' => $includeSummary ? $summary : null,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => max(1, (int)ceil($total / $perPage)),
    ],
]);
