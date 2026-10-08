<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require dirname(__DIR__) . '/logi_db.php';

$baseUrl = rtrim($argv[1] ?? '', '/');
$outputPath = $argv[2] ?? dirname(__DIR__) . '/outputs/production-readiness-results.json';
$testPassword = (string) getenv('TEST_ADMIN_PASSWORD');
if ($baseUrl === '') {
    fwrite(STDERR, "Usage: php production_readiness.php <base-url> [output-json]\n");
    exit(2);
}

$results = [];
function check_result(string $area, string $name, bool $passed, string $detail = '', string $severity = 'required'): void
{
    global $results;
    $results[] = compact('area', 'name', 'passed', 'detail', 'severity');
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $area . ' — ' . $name . ($detail !== '' ? ': ' . $detail : '') . PHP_EOL;
}

function request_http(string $url, string $method = 'GET', mixed $body = null, ?string $cookieFile = null, array $headers = []): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($cookieFile !== null) {
        curl_setopt($handle, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($handle, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
    }
    if ($headers) {
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
    }
    $raw = curl_exec($handle);
    if ($raw === false) {
        throw new RuntimeException(curl_error($handle));
    }
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);
    return [
        'status' => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
    ];
}

function post_json(string $baseUrl, array $payload, string $cookieFile): array
{
    $response = request_http(
        $baseUrl . '/Logi_ib_action.php',
        'POST',
        json_encode($payload, JSON_UNESCAPED_SLASHES),
        $cookieFile,
        ['Content-Type: application/json']
    );
    $response['json'] = json_decode($response['body'], true);
    return $response;
}

function scalar(mysqli $db, string $sql): int
{
    return (int) array_values($db->query($sql)->fetch_assoc())[0];
}

$root = dirname(__DIR__);
$phpSyntaxFailures = [];
$jsSyntaxFailures = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (preg_match('#^(?:v2|fpdf|tmp|node_modules|\.git|\.playwright-cli)/#', $relative)) continue;
    if (str_ends_with($relative, '.php')) {
        $output = []; $code = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $output, $code);
        if ($code !== 0) $phpSyntaxFailures[] = $relative;
    } elseif (str_ends_with($relative, '.js') || str_ends_with($relative, '.mjs')) {
        $output = []; $code = 0;
        exec('node --check ' . escapeshellarg($path), $output, $code);
        if ($code !== 0) $jsSyntaxFailures[] = $relative;
    }
}
check_result('Static', 'All Version 1 PHP files pass syntax validation', !$phpSyntaxFailures, implode(', ', $phpSyntaxFailures), 'blocker');
check_result('Static', 'All Version 1 JavaScript files pass syntax validation', !$jsSyntaxFailures, implode(', ', $jsSyntaxFailures), 'blocker');
$htaccess = file_get_contents($root . '/.htaccess') ?: '';
$artifactsDenied = str_contains($htaccess, 'tmp|outputs|tests') && str_contains($htaccess, 'sql|csv|log');
check_result('Deployment', 'Internal artifacts are denied by Apache configuration', $artifactsDenied, '.htaccess protects tmp, outputs, tests, SQL, CSV, logs, and environment files', 'blocker');

$mutatingEndpoints = [
    'Logi_add_item.php', 'Logi_add_office.php', 'Logi_assign_supplies.php',
    'Logi_barcode_add_item.php', 'Logi_barcode_deduct_item.php', 'Logi_delete_item.php',
    'Logi_delete_office.php', 'Logi_process_bulk_transactions.php', 'Logi_qr_add_item.php',
    'Logi_qr_deduct_item.php', 'Logi_save_manage_common_supplies.php', 'Logi_stock_in.php',
    'Logi_stock_out.php', 'Logi_undo_transaction.php', 'Logi_update_balance.php',
    'Logi_update_item.php', 'process_all_items.php', 'update_request_status.php',
];
$withoutAuthentication = [];
$withoutCsrf = [];
foreach ($mutatingEndpoints as $file) {
    $source = file_get_contents($root . '/' . $file) ?: '';
    if (!preg_match('/logged_in|logi_ib_admin_actor|logi_require_admin_csrf|Authentication required|session has expired/i', $source)) {
        $withoutAuthentication[] = $file;
    }
    if (!preg_match('/csrf|hash_equals\s*\(/i', $source)) {
        $withoutCsrf[] = $file;
    }
}
check_result('Security', 'Every write endpoint enforces authentication', !$withoutAuthentication, implode(', ', $withoutAuthentication), 'blocker');
check_result('Security', 'Every write endpoint validates CSRF', !$withoutCsrf, implode(', ', $withoutCsrf), 'blocker');

$negativeBalances = scalar($conn, 'SELECT COUNT(*) FROM inventory_items WHERE current_balance < 0');
check_result('Data', 'No negative inventory balances', $negativeBalances === 0, "count={$negativeBalances}", 'blocker');
$duplicateItemNos = scalar($conn, 'SELECT COUNT(*) FROM (SELECT item_no FROM inventory_items GROUP BY item_no HAVING COUNT(*)>1) d');
check_result('Data', 'Inventory stock numbers are unique in current data', $duplicateItemNos === 0, "duplicate_numbers={$duplicateItemNos}", 'required');
$ibOrphans = scalar($conn, 'SELECT COUNT(*) FROM ib_item_lines l LEFT JOIN ib_office_groups g ON g.id=l.group_id WHERE g.id IS NULL')
    + scalar($conn, 'SELECT COUNT(*) FROM ib_delivery_lines dl LEFT JOIN ib_deliveries d ON d.id=dl.delivery_id LEFT JOIN ib_item_lines l ON l.id=dl.ib_item_line_id WHERE d.id IS NULL OR l.id IS NULL');
check_result('Data', 'IB records have no orphan lines', $ibOrphans === 0, "orphans={$ibOrphans}", 'blocker');
$deliveredHeader = scalar($conn, 'SELECT COALESCE(SUM(delivered_quantity),0) FROM ib_item_lines');
$deliveredHistory = scalar($conn, "SELECT COALESCE(SUM(CASE WHEN d.status='POSTED' THEN dl.quantity ELSE -dl.quantity END),0) FROM ib_delivery_lines dl JOIN ib_deliveries d ON d.id=dl.delivery_id");
check_result('Data', 'IB delivered totals reconcile to posted and reversed deliveries', $deliveredHeader === $deliveredHistory, "lines={$deliveredHeader}, history={$deliveredHistory}", 'blocker');
$ledgerMismatchRows = $conn->query("SELECT i.id inventory_id,i.item_no,i.item_name,i.current_balance,t.id last_transaction_id,t.transaction_type,t.quantity,t.new_balance ledger_balance,(i.current_balance-t.new_balance) difference,t.created_at,t.updated_at,t.reason FROM inventory_items i JOIN (SELECT item_no,MAX(id) last_id FROM inventory_transactions GROUP BY item_no) z ON z.item_no=i.item_no JOIN inventory_transactions t ON t.id=z.last_id WHERE i.current_balance<>t.new_balance ORDER BY ABS(i.current_balance-t.new_balance) DESC,i.item_no")->fetch_all(MYSQLI_ASSOC);
$ledgerMismatch = count($ledgerMismatchRows);
$mismatchCsv = dirname($outputPath) . '/inventory-ledger-mismatches.csv';
if (!is_dir(dirname($mismatchCsv))) mkdir(dirname($mismatchCsv), 0777, true);
$csv = fopen($mismatchCsv, 'wb');
if ($csv !== false) {
    if ($ledgerMismatchRows) fputcsv($csv, array_keys($ledgerMismatchRows[0]));
    foreach ($ledgerMismatchRows as $row) fputcsv($csv, $row);
    fclose($csv);
}
check_result('Data', 'Inventory balances match latest ledger entries', $ledgerMismatch === 0, "mismatches={$ledgerMismatch}; evidence=inventory-ledger-mismatches.csv", 'blocker');

$anonymous = tempnam(sys_get_temp_dir(), 'logisys-anon-');
$officeCookie = tempnam(sys_get_temp_dir(), 'logisys-office-');
$adminCookie = tempnam(sys_get_temp_dir(), 'logisys-admin-');
try {
    $response = request_http($baseUrl . '/Logi_ib_monitoring.php', 'GET', null, $anonymous);
    check_result('Authorization', 'Anonymous IB page access redirects to login', $response['status'] === 302 && str_contains($response['headers'], 'Logi_login.php'), "status={$response['status']}", 'blocker');

    $response = request_http($baseUrl . '/Logi_ib_action.php', 'POST', '{}', $anonymous, ['Content-Type: application/json']);
    check_result('Authorization', 'Anonymous IB mutations are rejected', $response['status'] === 401, "status={$response['status']}", 'blocker');

    request_http($baseUrl . '/login_process.php', 'POST', http_build_query(['username'=>'ADMIN','password'=>$testPassword]), $officeCookie, ['Content-Type: application/x-www-form-urlencoded']);
    $response = request_http($baseUrl . '/Logi_ib_monitoring.php', 'GET', null, $officeCookie);
    check_result('Authorization', 'Non-ADMIN_SAP account is rejected', $response['status'] === 403, "status={$response['status']}", 'blocker');

    $login = request_http($baseUrl . '/login_process.php', 'POST', http_build_query(['username'=>'ADMIN_SAP','password'=>$testPassword]), $adminCookie, ['Content-Type: application/x-www-form-urlencoded']);
    check_result('Authentication', 'ADMIN_SAP can sign in', $login['status'] === 302 && str_contains($login['headers'], 'Logi_Sys_Dashboard.php'), "status={$login['status']}", 'blocker');
    $page = request_http($baseUrl . '/Logi_ib_monitoring.php', 'GET', null, $adminCookie);
    check_result('UI', 'IB Monitoring renders for ADMIN_SAP', $page['status'] === 200 && str_contains($page['body'], 'IB Monitoring'), "status={$page['status']}");
    preg_match('/window\.ibPageData=(\{.*?\});<\/script>/s', $page['body'], $match);
    $pageData = isset($match[1]) ? json_decode($match[1], true) : null;
    check_result('UI', 'IB page exposes valid application data', is_array($pageData) && !empty($pageData['csrf']) && !empty($pageData['items']), is_array($pageData) ? 'valid JSON' : 'missing or invalid JSON', 'blocker');
    if (!is_array($pageData)) {
        throw new RuntimeException('Cannot continue IB workflow tests without page data.');
    }
    $csrf = (string) $pageData['csrf'];
    $adminOffice = array_values(array_filter($pageData['offices'], static fn(array $o): bool => strtoupper(trim((string)$o['office_name'])) === 'ADMIN'))[0] ?? null;
    $cswdoOffice = array_values(array_filter($pageData['offices'], static fn(array $o): bool => strtoupper(trim((string)$o['office_name'])) === 'CSWDO'))[0] ?? null;
    $catalog = array_values($pageData['items']);
    check_result('Fixtures', 'Required offices and three catalog items exist', $adminOffice !== null && $cswdoOffice !== null && count($catalog) >= 3, 'ADMIN, CSWDO, catalog');

    $badCsrf = post_json($baseUrl, ['action'=>'activate','ib_id'=>1,'csrf_token'=>'invalid'], $adminCookie);
    check_result('Security', 'IB mutations reject invalid CSRF', $badCsrf['status'] === 403, "status={$badCsrf['status']}", 'blocker');

    $legacyAnonymous = request_http($baseUrl . '/Logi_stock_out.php', 'POST', http_build_query([]), $anonymous, ['Content-Type: application/x-www-form-urlencoded']);
    check_result('Security', 'Legacy write endpoint rejects anonymous request', $legacyAnonymous['status'] === 401, "status={$legacyAnonymous['status']}", 'blocker');
    $legacyBadCsrf = request_http($baseUrl . '/Logi_stock_out.php', 'POST', http_build_query([]), $adminCookie, ['Content-Type: application/x-www-form-urlencoded']);
    check_result('Security', 'Legacy write endpoint rejects missing CSRF', $legacyBadCsrf['status'] === 403, "status={$legacyBadCsrf['status']}", 'blocker');

    $ibNo = 'TEST-READY-' . date('YmdHis');
    $payloadItem = static fn(array $item, int $quantity, string $price): array => [
        'is_new_item'=>false, 'add_to_inventory'=>false, 'item_id'=>(int)$item['id'],
        'item_no'=>'', 'item_name'=>'', 'unit'=>'', 'quantity'=>$quantity, 'unit_price'=>$price,
    ];
    $create = post_json($baseUrl, [
        'action'=>'create', 'csrf_token'=>$csrf, 'ib_no'=>$ibNo,
        'groups'=>[
            ['office_id'=>(int)$adminOffice['id'], 'description'=>'Production readiness ADMIN test', 'items'=>[$payloadItem($catalog[0], 5, '10.00')]],
            ['office_id'=>(int)$cswdoOffice['id'], 'description'=>'Production readiness monitoring test', 'items'=>[$payloadItem($catalog[1], 5, '20.00')]],
        ],
    ], $adminCookie);
    $ibId = (int)($create['json']['ib_id'] ?? 0);
    check_result('IB workflow', 'Create multi-office Draft IB', $create['status'] === 200 && $ibId > 0, $create['json']['message'] ?? $create['body'], 'blocker');

    $duplicate = post_json($baseUrl, [
        'action'=>'create', 'csrf_token'=>$csrf, 'ib_no'=>$ibNo,
        'groups'=>[['office_id'=>(int)$adminOffice['id'], 'description'=>'Duplicate', 'items'=>[$payloadItem($catalog[2], 1, '1.00')]]],
    ], $adminCookie);
    check_result('IB workflow', 'Duplicate IB numbers are rejected', in_array($duplicate['status'], [409, 422], true) && scalar($conn, "SELECT COUNT(*) FROM ib_headers WHERE ib_no='".$conn->real_escape_string($ibNo)."'") === 1, "status={$duplicate['status']}", 'blocker');

    $activate = post_json($baseUrl, ['action'=>'activate','csrf_token'=>$csrf,'ib_id'=>$ibId], $adminCookie);
    check_result('IB workflow', 'Activate Draft IB', $activate['status'] === 200 && scalar($conn, "SELECT status='ACTIVE' FROM ib_headers WHERE id={$ibId}") === 1, $activate['json']['message'] ?? '', 'blocker');

    $groupRows = $conn->query("SELECT id,office_name FROM ib_office_groups WHERE ib_id={$ibId}")->fetch_all(MYSQLI_ASSOC);
    $adminGroupId = 0;
    foreach ($groupRows as $groupRow) if (strtoupper(trim((string)$groupRow['office_name'])) === 'ADMIN') $adminGroupId = (int)$groupRow['id'];
    $addActive = post_json($baseUrl, [
        'action'=>'add_active_items','csrf_token'=>$csrf,'ib_id'=>$ibId,'group_id'=>$adminGroupId,
        'items'=>[$payloadItem($catalog[2], 4, '30.00')],
    ], $adminCookie);
    check_result('IB workflow', 'Append an item while IB is Active', $addActive['status'] === 200 && scalar($conn, "SELECT COUNT(*) FROM ib_item_lines l JOIN ib_office_groups g ON g.id=l.group_id WHERE g.ib_id={$ibId}") === 3, $addActive['json']['message'] ?? '', 'required');

    $lineRows = $conn->query("SELECT l.id,l.item_id,l.item_no,l.planned_quantity,g.office_name FROM ib_item_lines l JOIN ib_office_groups g ON g.id=l.group_id WHERE g.ib_id={$ibId} ORDER BY l.id")->fetch_all(MYSQLI_ASSOC);
    $adminLine = null; $cswdoLine = null;
    foreach ($lineRows as $lineRow) {
        if ($lineRow['office_name'] === 'ADMIN' && (int)$lineRow['planned_quantity'] === 5) $adminLine = $lineRow;
        if ($lineRow['office_name'] === 'CSWDO') $cswdoLine = $lineRow;
    }
    $adminBalanceBefore = scalar($conn, 'SELECT current_balance FROM inventory_items WHERE id=' . (int)$adminLine['item_id']);
    $deliveryToken = '11111111-1111-4111-8111-111111111111';
    $delivery = post_json($baseUrl, [
        'action'=>'record_delivery','csrf_token'=>$csrf,'ib_id'=>$ibId,'delivery_date'=>date('Y-m-d'),
        'notes'=>'Over-delivery integration test','idempotency_token'=>$deliveryToken,
        'lines'=>[['line_id'=>(int)$adminLine['id'],'quantity'=>8]],
    ], $adminCookie);
    $adminBalanceAfter = scalar($conn, 'SELECT current_balance FROM inventory_items WHERE id=' . (int)$adminLine['item_id']);
    check_result('Delivery', 'ADMIN delivery may exceed planned quantity and adds stock', $delivery['status'] === 200 && $adminBalanceAfter === $adminBalanceBefore + 8, "status={$delivery['status']}, balance={$adminBalanceBefore}→{$adminBalanceAfter}", 'blocker');

    $duplicateDelivery = post_json($baseUrl, [
        'action'=>'record_delivery','csrf_token'=>$csrf,'ib_id'=>$ibId,'delivery_date'=>date('Y-m-d'),
        'notes'=>'Duplicate token','idempotency_token'=>$deliveryToken,
        'lines'=>[['line_id'=>(int)$adminLine['id'],'quantity'=>1]],
    ], $adminCookie);
    check_result('Delivery', 'Repeated delivery token cannot post twice', $duplicateDelivery['status'] === 409 && scalar($conn, "SELECT COUNT(*) FROM ib_deliveries WHERE idempotency_token='{$deliveryToken}'") === 1, "status={$duplicateDelivery['status']}", 'blocker');

    $cswdoBalanceBefore = scalar($conn, 'SELECT current_balance FROM inventory_items WHERE id=' . (int)$cswdoLine['item_id']);
    $monitorDelivery = post_json($baseUrl, [
        'action'=>'record_delivery','csrf_token'=>$csrf,'ib_id'=>$ibId,'delivery_date'=>date('Y-m-d'),
        'notes'=>'Monitoring-only test','idempotency_token'=>'22222222-2222-4222-8222-222222222222',
        'lines'=>[['line_id'=>(int)$cswdoLine['id'],'quantity'=>7]],
    ], $adminCookie);
    $cswdoBalanceAfter = scalar($conn, 'SELECT current_balance FROM inventory_items WHERE id=' . (int)$cswdoLine['item_id']);
    $monitorLinked = scalar($conn, "SELECT COUNT(*) FROM ib_delivery_lines WHERE ib_item_line_id=".(int)$cswdoLine['id']." AND inventory_transaction_id IS NOT NULL");
    check_result('Delivery', 'Non-ADMIN delivery is monitoring-only', $monitorDelivery['status'] === 200 && $cswdoBalanceAfter === $cswdoBalanceBefore && $monitorLinked === 0, "status={$monitorDelivery['status']}, stock_change=".($cswdoBalanceAfter-$cswdoBalanceBefore), 'blocker');

    $quantityUpdate = post_json($baseUrl, [
        'action'=>'update_item_quantity','csrf_token'=>$csrf,'ib_id'=>$ibId,'line_id'=>(int)$adminLine['id'],
        'planned_quantity'=>10,'reason'=>'Production readiness quantity correction',
    ], $adminCookie);
    check_result('IB workflow', 'Edit planned quantity with audit reason', $quantityUpdate['status'] === 200 && scalar($conn, 'SELECT planned_quantity FROM ib_item_lines WHERE id='.(int)$adminLine['id']) === 10 && scalar($conn, "SELECT COUNT(*) FROM ib_activity_log WHERE ib_id={$ibId} AND action='ITEM_QUANTITY_UPDATED'") === 1, $quantityUpdate['json']['message'] ?? '', 'required');

    $managedStockIn = request_http($baseUrl . '/Logi_stock_in.php', 'POST', json_encode([
        'ib_no'=>$ibNo,'transaction_date'=>date('Y-m-d'),'reason'=>'Bypass attempt','items'=>[['item_no'=>$adminLine['item_no'],'quantity'=>1]],
    ]), $adminCookie, ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]);
    $managedJson = json_decode($managedStockIn['body'], true);
    check_result('Delivery', 'Ordinary Stock In rejects managed IB number', in_array($managedStockIn['status'], [409, 422], true) && str_contains((string)($managedJson['message'] ?? ''), 'IB Monitoring'), "status={$managedStockIn['status']}", 'blocker');

    $deliveryId = scalar($conn, "SELECT id FROM ib_deliveries WHERE idempotency_token='{$deliveryToken}'");
    $reverse = post_json($baseUrl, ['action'=>'reverse_delivery','csrf_token'=>$csrf,'delivery_id'=>$deliveryId,'reason'=>'Production readiness reversal'], $adminCookie);
    $adminBalanceReversed = scalar($conn, 'SELECT current_balance FROM inventory_items WHERE id=' . (int)$adminLine['item_id']);
    check_result('Reversal', 'Whole delivery reversal restores ADMIN stock and preserves original', $reverse['status'] === 200 && $adminBalanceReversed === $adminBalanceBefore && scalar($conn, "SELECT status='REVERSED' FROM ib_deliveries WHERE id={$deliveryId}") === 1 && scalar($conn, "SELECT COUNT(*) FROM ib_delivery_lines WHERE delivery_id={$deliveryId} AND inventory_transaction_id IS NOT NULL AND reversal_transaction_id IS NOT NULL") === 1, "status={$reverse['status']}, balance={$adminBalanceReversed}", 'blocker');
    $reverseAgain = post_json($baseUrl, ['action'=>'reverse_delivery','csrf_token'=>$csrf,'delivery_id'=>$deliveryId,'reason'=>'Duplicate reversal'], $adminCookie);
    check_result('Reversal', 'A delivery cannot be reversed twice', in_array($reverseAgain['status'], [409, 422], true), "status={$reverseAgain['status']}", 'blocker');
} finally {
    @unlink($anonymous); @unlink($officeCookie); @unlink($adminCookie);
}

$blockers = array_values(array_filter($results, static fn(array $r): bool => !$r['passed'] && $r['severity'] === 'blocker'));
$requiredFailures = array_values(array_filter($results, static fn(array $r): bool => !$r['passed'] && $r['severity'] !== 'blocker'));
$summary = [
    'generated_at' => date(DATE_ATOM),
    'database' => getenv('DB_NAME') ?: '',
    'production_ready' => count($blockers) === 0 && count($requiredFailures) === 0,
    'passed' => count(array_filter($results, static fn(array $r): bool => $r['passed'])),
    'failed' => count(array_filter($results, static fn(array $r): bool => !$r['passed'])),
    'blockers' => count($blockers),
    'results' => $results,
];
if (!is_dir(dirname($outputPath))) mkdir(dirname($outputPath), 0777, true);
file_put_contents($outputPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode(array_diff_key($summary, ['results'=>true]), JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($summary['production_ready'] ? 0 : 1);
