<?php

$masterRoot = dirname(__DIR__);
set_include_path($masterRoot . PATH_SEPARATOR . $masterRoot . DIRECTORY_SEPARATOR . 'Payables' . PATH_SEPARATOR . get_include_path());

if (class_exists('mysqli')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

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

function master_safe_number($value): int
{
    return is_numeric($value) ? (int)$value : 0;
}

function master_read_env_file(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $values = parse_ini_file($path, false, INI_SCANNER_RAW);
    return is_array($values) ? $values : [];
}

function master_connect_from_env(array $env, string $databaseKey = 'DB_DATABASE', int $timeout = 2): ?mysqli
{
    if (!class_exists('mysqli')) {
        return null;
    }

    $host = $env['DB_HOST'] ?? 'localhost';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    $db = $env[$databaseKey] ?? ($env['DB_NAME'] ?? '');
    $port = isset($env['DB_PORT']) ? (int)$env['DB_PORT'] : 3306;

    if ($host === '' || $user === '' || $db === '') {
        return null;
    }

    $connection = mysqli_init();
    if (!$connection) {
        return null;
    }

    mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, $timeout);
    if (!@mysqli_real_connect($connection, $host, $user, $pass, $db, $port)) {
        return null;
    }

    $connection->set_charset('utf8mb4');
    return $connection;
}

function master_asset_connect(string $masterRoot): ?mysqli
{
    return master_connect_from_env(
        master_read_env_file($masterRoot . DIRECTORY_SEPARATOR . 'asset_tracker_dashboard' . DIRECTORY_SEPARATOR . '.env'),
        'DB_NAME',
        2
    );
}

$conn = master_asset_connect($masterRoot);

if (!function_exists('get_dashboard_metrics')) {
    function get_dashboard_metrics(): array
    {
        global $conn;
        if (!$conn instanceof mysqli) {
            return [];
        }

        $tentResult = mysqli_query($conn, "
            SELECT
                COUNT(*) AS total,
                SUM(Status = 'Retrieved') AS available,
                SUM(Status = 'Installed') AS installed,
                SUM(Status = 'For Retrieval') AS for_retrieval,
                SUM(Status = 'Long Term') AS long_term
            FROM tent_status
        ");
        $vehicleResult = mysqli_query($conn, "
            SELECT
                COUNT(*) AS total,
                SUM(Status = 'Stand By') AS available,
                SUM(Status = 'Departed') AS departed
            FROM Vehicle
        ");

        if (!$tentResult || !$vehicleResult) {
            return [];
        }

        $tent = mysqli_fetch_assoc($tentResult) ?: [];
        $vehicle = mysqli_fetch_assoc($vehicleResult) ?: [];
        $tentTotal = master_safe_number($tent['total'] ?? 0);
        $vehicleTotal = master_safe_number($vehicle['total'] ?? 0);
        $tentAvailable = master_safe_number($tent['available'] ?? 0);
        $vehicleAvailable = master_safe_number($vehicle['available'] ?? 0);
        $vehicleDeparted = master_safe_number($vehicle['departed'] ?? 0);

        return [
            'tent_total' => $tentTotal,
            'tent_available' => $tentAvailable,
            'tent_installed' => master_safe_number($tent['installed'] ?? 0),
            'tent_for_retrieval' => master_safe_number($tent['for_retrieval'] ?? 0),
            'tent_long_term' => master_safe_number($tent['long_term'] ?? 0),
            'tent_available_percent' => $tentTotal > 0 ? (int)round(($tentAvailable / $tentTotal) * 100) : 0,
            'vehicle_total' => $vehicleTotal,
            'vehicle_available' => $vehicleAvailable,
            'vehicle_departed' => $vehicleDeparted,
            'vehicle_available_percent' => $vehicleTotal > 0 ? (int)round(($vehicleAvailable / $vehicleTotal) * 100) : 0,
            'vehicle_departed_percent' => $vehicleTotal > 0 ? (int)round(($vehicleDeparted / $vehicleTotal) * 100) : 0,
        ];
    }
}

if (!function_exists('get_daily_dispatch_counts')) {
    function get_daily_dispatch_counts(): array
    {
        global $conn;
        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $dayCounts = array_fill_keys($weekdays, 0);
        if (!$conn instanceof mysqli) {
            return $dayCounts;
        }

        $sql = "
            SELECT DATE_FORMAT(Date, '%W') AS day_name, COUNT(*) AS count
            FROM Transportation
            WHERE Date BETWEEN ? AND ?
              AND DATE_FORMAT(Date, '%W') IN ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')
            GROUP BY day_name
            ORDER BY FIELD(day_name, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $dayCounts;
        }

        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $endOfWeek = date('Y-m-d', strtotime('friday this week'));
        $stmt->bind_param('ss', $startOfWeek, $endOfWeek);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && $row = mysqli_fetch_assoc($result)) {
            if (isset($dayCounts[$row['day_name']])) {
                $dayCounts[$row['day_name']] = master_safe_number($row['count'] ?? 0);
            }
        }
        $stmt->close();

        return $dayCounts;
    }
}

if (!function_exists('get_top_5_vehicle_counts')) {
    function get_top_5_vehicle_counts(): array
    {
        global $conn;
        if (!$conn instanceof mysqli) {
            return [];
        }

        $result = mysqli_query($conn, "
            SELECT Vehicle, Plate_no, COUNT(*) AS Count
            FROM Transportation
            GROUP BY Vehicle, Plate_no
            ORDER BY Count DESC
            LIMIT 5
        ");
        $rows = [];
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $rows[] = [
                'vehicle' => $row['Vehicle'] ?? '',
                'plate_no' => $row['Plate_no'] ?? '',
                'count' => master_safe_number($row['Count'] ?? 0),
            ];
        }

        return $rows;
    }
}

if (!function_exists('count_completed_repairs_by_car')) {
    function count_completed_repairs_by_car(): array
    {
        global $conn;
        if (!$conn instanceof mysqli) {
            return [];
        }

        $result = mysqli_query($conn, "
            SELECT plate_no, COUNT(*) AS completed_count
            FROM motorpool_repair
            WHERE status = 'Completed'
            GROUP BY plate_no
            ORDER BY completed_count DESC
            LIMIT 5
        ");
        $counts = [];
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $counts[$row['plate_no']] = master_safe_number($row['completed_count'] ?? 0);
        }

        return $counts;
    }
}

if (!defined('EQUIPMENT_DEPLOYMENT_STATUSES')) {
    define('EQUIPMENT_DEPLOYMENT_STATUSES', ['Pending', 'Deployed', 'For Retrieval', 'Retrieved', 'Long Term']);
}

if (!function_exists('get_equipment_types')) {
    function get_equipment_types(?string $category = null): array
    {
        global $conn;
        if (!$conn instanceof mysqli) {
            return [];
        }

        $sql = 'SELECT *, (total_qty - available_qty) AS reserved_qty FROM equipment_types';
        $types = '';
        $params = [];
        if ($category !== null && in_array($category, ['Chair', 'Table'], true)) {
            $sql .= ' WHERE category = ?';
            $types = 's';
            $params[] = $category;
        }
        $sql .= ' ORDER BY category, subtype_name';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = master_rows_from_result($result, 500);
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('get_deployment_metrics')) {
    function get_deployment_metrics(): array
    {
        global $conn;
        $empty = ['total' => 0, 'deployed' => 0, 'pending' => 0, 'due_today' => 0, 'overdue' => 0, 'chairs_deployed' => 0, 'tables_deployed' => 0];
        if (!$conn instanceof mysqli) {
            return $empty;
        }

        $result = mysqli_query($conn, "
            SELECT COUNT(*) AS total,
                SUM(status = 'Deployed') AS deployed,
                SUM(status = 'Pending') AS pending,
                SUM(status = 'For Retrieval' AND retrieval_date = CURDATE()) AS due_today,
                SUM(status = 'For Retrieval' AND retrieval_date < CURDATE()) AS overdue
            FROM deployments
        ");
        $row = $result ? (mysqli_fetch_assoc($result) ?: []) : [];

        $deployedResult = mysqli_query($conn, "
            SELECT
                COALESCE(SUM(CASE WHEN et.category = 'Chair' THEN di.quantity ELSE 0 END), 0) AS chairs_deployed,
                COALESCE(SUM(CASE WHEN et.category = 'Table' THEN di.quantity ELSE 0 END), 0) AS tables_deployed
            FROM deployment_items di
            JOIN deployments d ON d.id = di.deployment_id
            JOIN equipment_types et ON et.id = di.equipment_type_id
            WHERE d.status <> 'Retrieved'
        ");
        $deployed = $deployedResult ? (mysqli_fetch_assoc($deployedResult) ?: []) : [];

        return [
            'total' => master_safe_number($row['total'] ?? 0),
            'deployed' => master_safe_number($row['deployed'] ?? 0),
            'pending' => master_safe_number($row['pending'] ?? 0),
            'due_today' => master_safe_number($row['due_today'] ?? 0),
            'overdue' => master_safe_number($row['overdue'] ?? 0),
            'chairs_deployed' => master_safe_number($deployed['chairs_deployed'] ?? 0),
            'tables_deployed' => master_safe_number($deployed['tables_deployed'] ?? 0),
        ];
    }
}

if (!function_exists('get_deployment_items')) {
    function get_deployment_items(int $deploymentId): array
    {
        global $conn;
        if (!$conn instanceof mysqli) {
            return [];
        }

        $stmt = $conn->prepare("
            SELECT di.*, et.subtype_name, et.display_name, et.category, et.total_qty, et.available_qty
            FROM deployment_items di
            JOIN equipment_types et ON et.id = di.equipment_type_id
            WHERE di.deployment_id = ?
            ORDER BY et.category, et.subtype_name
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $deploymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = master_rows_from_result($result, 100);
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('get_deployments_with_items')) {
    function get_deployments_with_items(?string $status = null): array
    {
        global $conn;
        if (!$conn instanceof mysqli) {
            return [];
        }

        $sql = 'SELECT * FROM deployments';
        $types = '';
        $params = [];
        if ($status !== null && in_array($status, EQUIPMENT_DEPLOYMENT_STATUSES, true)) {
            $sql .= ' WHERE status = ?';
            $types = 's';
            $params[] = $status;
        }
        $sql .= ' ORDER BY id DESC';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $row['items'] = get_deployment_items((int)($row['id'] ?? 0));
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}
function master_asset_query_rows(mysqli $connection, string $sql, int $limit = 5): array
{
    $result = mysqli_query($connection, $sql);
    return master_rows_from_result($result, $limit);
}

function master_payables_connect(string $payablesDir): ?mysqli
{
    return master_connect_from_env(master_read_env_file($payablesDir . DIRECTORY_SEPARATOR . '.env'), 'DB_DATABASE', 3);
}

function master_motorpool_status_counts(): array
{
    global $conn;
    if (!$conn instanceof mysqli) {
        return ['pending' => 0, 'in_progress' => 0, 'completed' => 0];
    }

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
    if (!$conn instanceof mysqli) {
        return [];
    }

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