<?php

$masterRoot = dirname(__DIR__);
set_include_path($masterRoot . PATH_SEPARATOR . $masterRoot . DIRECTORY_SEPARATOR . 'Payables' . PATH_SEPARATOR . get_include_path());

require_once $masterRoot . DIRECTORY_SEPARATOR . 'db_asset.php';
require_once $masterRoot . DIRECTORY_SEPARATOR . 'display_data_asset.php';
require_once $masterRoot . DIRECTORY_SEPARATOR . 'asset_tracker_dashboard' . DIRECTORY_SEPARATOR . 'motorpool' . DIRECTORY_SEPARATOR . 'motorpool_data_display.php';

if (!function_exists('master_rows_from_result')) {
    function master_rows_from_result($result, int $limit = 5): array
    {
        $rows = [];
        if (!$result) {
            return $rows;
        }

        while (($row = mysqli_fetch_assoc($result)) && count($rows) < $limit) {
            $rows[] = $row;
        }

        return $rows;
    }
}

function master_asset_query_rows(mysqli $connection, string $sql, int $limit = 5): array
{
    $result = mysqli_query($connection, $sql);
    return master_rows_from_result($result, $limit);
}

function master_safe_number($value): int
{
    return is_numeric($value) ? (int)$value : 0;
}

function master_read_env_file(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }

    return $values;
}

function master_payables_connect(string $payablesDir): ?mysqli
{
    $env = master_read_env_file($payablesDir . DIRECTORY_SEPARATOR . '.env');
    if (!$env) {
        return null;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = mysqli_init();
    if (!$connection) {
        return null;
    }

    mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $host = $env['DB_HOST'] ?? 'localhost';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    $db = $env['DB_DATABASE'] ?? '';
    $port = isset($env['DB_PORT']) ? (int)$env['DB_PORT'] : 3306;

    if (!@mysqli_real_connect($connection, $host, $user, $pass, $db, $port)) {
        return null;
    }

    $connection->set_charset('utf8mb4');
    return $connection;
}

function master_motorpool_status_counts(): array
{
    global $conn;
    $sql = "SELECT
            SUM(status = 'Pending') AS pending,
            SUM(status = 'In Progress') AS in_progress,
            SUM(status = 'Completed') AS completed
        FROM motorpool_repair";
    $result = mysqli_query($conn, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : [];

    return [
        'pending' => master_safe_number($row['pending'] ?? 0),
        'in_progress' => master_safe_number($row['in_progress'] ?? 0),
        'completed' => master_safe_number($row['completed'] ?? 0),
    ];
}
function master_motorpool_daily_repairs(int $limit = 7): array
{
    global $conn;
    $limit = max(1, $limit);
    $sql = "SELECT DATE(repair_date) AS repair_day, COUNT(*) AS repair_count
        FROM motorpool_repair
        GROUP BY DATE(repair_date)
        ORDER BY repair_day DESC
        LIMIT " . (int)$limit;
    $result = mysqli_query($conn, $sql);
    return master_rows_from_result($result, $limit);
}
function master_payables_summary(string $payablesDir): array
{
    $summary = [
        'available' => false,
        'counts' => [
            'GSO' => 0,
            'BUDGET' => 0,
            'ACCOUNTING' => 0,
            'CTO' => 0,
            'RELEASED' => 0,
        ],
        'latest' => [],
    ];

    $payablesConn = master_payables_connect($payablesDir);
    if (!$payablesConn) {
        return $summary;
    }

    $summary['available'] = true;
    $metricsSql = "
        SELECT
            SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'GSO' THEN 1 ELSE 0 END) AS gso_count,
            SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'BUDGET' THEN 1 ELSE 0 END) AS budget_count,
            SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'ACCOUNTING' THEN 1 ELSE 0 END) AS accounting_count,
            SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'CTO' THEN 1 ELSE 0 END) AS cto_count,
            SUM(CASE WHEN COALESCE(pws.released, 0) = 1 THEN 1 ELSE 0 END) AS released_count
        FROM transmittal_bac tb
        LEFT JOIN payables_workflow_status pws
            ON pws.record_type = 'bac'
           AND pws.record_id = tb.id
        WHERE tb.delete_status = 0";
    $metricsResult = mysqli_query($payablesConn, $metricsSql);
    if ($metricsResult && $row = mysqli_fetch_assoc($metricsResult)) {
        $summary['counts']['GSO'] = master_safe_number($row['gso_count'] ?? 0);
        $summary['counts']['BUDGET'] = master_safe_number($row['budget_count'] ?? 0);
        $summary['counts']['ACCOUNTING'] = master_safe_number($row['accounting_count'] ?? 0);
        $summary['counts']['CTO'] = master_safe_number($row['cto_count'] ?? 0);
        $summary['counts']['RELEASED'] = master_safe_number($row['released_count'] ?? 0);
    }

    $latestSql = "
        SELECT 'BAC' AS source, ib_no AS reference_no, project_name AS title, date_received AS transaction_date
        FROM transmittal_bac
        WHERE delete_status = 0
        UNION ALL
        SELECT 'RFQ' AS source, RFQ_no AS reference_no, supplier AS title, date_received AS transaction_date
        FROM PO_sap
        WHERE delete_status = 0
        ORDER BY transaction_date DESC
        LIMIT 6";
    $latestResult = mysqli_query($payablesConn, $latestSql);
    $summary['latest'] = master_rows_from_result($latestResult, 6);

    mysqli_close($payablesConn);
    return $summary;
}

$assetConn = $conn;
$assetMetrics = get_dashboard_metrics();
$motorpoolStatusCounts = master_motorpool_status_counts();

$masterData = [
    'tents' => [
        'available' => master_safe_number($assetMetrics['tent_available'] ?? 0),
        'on_field' => master_safe_number($assetMetrics['tent_installed'] ?? 0),
        'for_retrieval' => master_safe_number($assetMetrics['tent_for_retrieval'] ?? 0),
        'long_term' => master_safe_number($assetMetrics['tent_long_term'] ?? 0),
    ],
    'transportation' => [
        'available' => master_safe_number($assetMetrics['vehicle_available'] ?? 0),
        'departed' => master_safe_number($assetMetrics['vehicle_departed'] ?? 0),
        'dispatch_trend' => get_daily_dispatch_counts(),
        'top_vehicles' => get_top_5_vehicle_counts(),
    ],
    'motorpool' => [
        'pending' => $motorpoolStatusCounts['pending'],
        'in_progress' => $motorpoolStatusCounts['in_progress'],
        'completed' => $motorpoolStatusCounts['completed'],
        'daily_repairs' => master_motorpool_daily_repairs(7),
        'most_repaired' => count_completed_repairs_by_car(),
    ],
    'payables' => master_payables_summary($masterRoot . DIRECTORY_SEPARATOR . 'Payables'),
];

$masterData['alerts'] = [
    [
        'label' => 'Tents for retrieval',
        'value' => $masterData['tents']['for_retrieval'],
        'tone' => $masterData['tents']['for_retrieval'] > 0 ? 'warning' : 'quiet',
        'href' => 'assets.php',
    ],
    [
        'label' => 'Vehicles on field',
        'value' => $masterData['transportation']['departed'],
        'tone' => $masterData['transportation']['departed'] > 0 ? 'info' : 'quiet',
        'href' => 'transportation.php',
    ],
    [
        'label' => 'Motorpool active repairs',
        'value' => $masterData['motorpool']['pending'] + $masterData['motorpool']['in_progress'],
        'tone' => ($masterData['motorpool']['pending'] + $masterData['motorpool']['in_progress']) > 0 ? 'danger' : 'quiet',
        'href' => 'motorpool.php',
    ],
    [
        'label' => 'BAC records in workflow',
        'value' => array_sum([
            $masterData['payables']['counts']['GSO'],
            $masterData['payables']['counts']['BUDGET'],
            $masterData['payables']['counts']['ACCOUNTING'],
            $masterData['payables']['counts']['CTO'],
        ]),
        'tone' => $masterData['payables']['available'] ? 'success' : 'warning',
        'href' => 'payables.php',
    ],
];
