<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();

require_once __DIR__ . '/master_data.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function n($value): string
{
    return number_format((float)$value);
}

function peso($value): string
{
    return '&#8369;' . number_format((float)$value, 2);
}

function dashboard_short_week($value): string
{
    $time = strtotime((string)$value);
    return $time ? date('M j', $time) : (string)$value;
}

function dashboard_fuel_env(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $values = parse_ini_file($path, false, INI_SCANNER_RAW);
    return is_array($values) ? $values : [];
}

function dashboard_fuel_connect(string $fuelDir): ?mysqli
{
    if (!class_exists('mysqli')) {
        return null;
    }

    $env = dashboard_fuel_env($fuelDir . DIRECTORY_SEPARATOR . '.env');
    $host = $env['FUEL_DB_HOST'] ?? 'localhost';
    $user = $env['FUEL_DB_USER'] ?? 'root';
    $pass = $env['FUEL_DB_PASS'] ?? '';
    $db = $env['FUEL_DB_NAME'] ?? 'fuel_tracker';
    $port = isset($env['FUEL_DB_PORT']) ? (int)$env['FUEL_DB_PORT'] : 3306;
    $charset = $env['FUEL_DB_CHARSET'] ?? 'utf8mb4';

    $connection = mysqli_init();
    if (!$connection) {
        return null;
    }

    mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    if (!@mysqli_real_connect($connection, $host, $user, $pass, $db, $port)) {
        return null;
    }

    $connection->set_charset($charset);
    return $connection;
}

function dashboard_fuel_budget_snapshot(string $fuelDir): array
{
    $empty = [
        'available' => false,
        'summary' => [
            'used_budget' => 0,
            'used_diesel_budget' => 0,
            'used_unleaded_budget' => 0,
            'actual_diesel_liters' => 0,
            'actual_unleaded_liters' => 0,
            'actual_missing_price_count' => 0,
        ],
        'weekly' => [],
    ];

    $fuelBudgetFile = $fuelDir . DIRECTORY_SEPARATOR . 'fuel_budget_data.php';
    if (!is_readable($fuelBudgetFile)) {
        return $empty;
    }

    try {
        require_once $fuelBudgetFile;
        $fuelConn = dashboard_fuel_connect($fuelDir);
        if (!$fuelConn instanceof mysqli) {
            return $empty;
        }

        $summary = fuelBudgetSummary($fuelConn);
        $weekly = fuelBudgetWeeklyActualUsageTrend($fuelConn, 16);
        mysqli_close($fuelConn);

        return [
            'available' => true,
            'summary' => $summary,
            'weekly' => $weekly,
        ];
    } catch (Throwable $e) {
        error_log('Master dashboard fuel snapshot error: ' . $e->getMessage());
        return $empty;
    }
}

function dashboard_advance_db_value(string $source, string $name): string
{
    if (preg_match('/\$' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1\s*;/', $source, $matches)) {
        return $matches[2];
    }

    return '';
}

function dashboard_advance_cached_totals(string $cacheFile): ?array
{
    if (!is_readable($cacheFile)) {
        return null;
    }

    $payload = json_decode((string)file_get_contents($cacheFile), true);
    return is_array($payload) ? $payload : null;
}

function dashboard_advance_store_totals(string $advanceDir): array
{
    $stores = ['BQ', 'BQ BUILDERWARE', 'NODAL', 'JETS MARKETING', 'JJS SEAFOODS', 'CITY TYRE'];
    $totals = array_fill_keys($stores, 0.0);
    $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '.cache';
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'advance_store_totals.json';
    $cached = dashboard_advance_cached_totals($cacheFile);
    if ($cached !== null && filemtime($cacheFile) >= time() - 300) {
        return $cached;
    }

    $dbFile = $advanceDir . DIRECTORY_SEPARATOR . 'advance_po_db.php';
    if (!is_readable($dbFile)) {
        return $cached ?? [];
    }

    $source = (string)file_get_contents($dbFile);
    $host = dashboard_advance_db_value($source, 'servername');
    $user = dashboard_advance_db_value($source, 'username');
    $pass = dashboard_advance_db_value($source, 'password');
    $db = dashboard_advance_db_value($source, 'dbname');
    if ($host === '' || $user === '' || $db === '') {
        return $cached ?? [];
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $advanceConn = mysqli_init();
    if (!$advanceConn) {
        return $cached ?? [];
    }
    mysqli_options($advanceConn, MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    if (!@mysqli_real_connect($advanceConn, $host, $user, $pass, $db)) {
        return $cached ?? [];
    }
    $advanceConn->set_charset('utf8mb4');

    $sql = "
        SELECT store, COALESCE(SUM(amount), 0) AS total_amount
        FROM advancePo
        WHERE delete_status = 0
          AND status = 'Pending'
          AND store IN ('BQ', 'BQ BUILDERWARE', 'NODAL', 'JETS MARKETING', 'JJS SEAFOODS', 'CITY TYRE')
        GROUP BY store";
    $result = mysqli_query($advanceConn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($totals[$row['store']])) {
                $totals[$row['store']] = (float)$row['total_amount'];
            }
        }
    }
    mysqli_close($advanceConn);

    $filteredTotals = array_filter($totals, function ($total): bool { return $total > 0; });
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }
    if (is_dir($cacheDir) && is_writable($cacheDir)) {
        file_put_contents($cacheFile, json_encode($filteredTotals));
    }

    return $filteredTotals;
}

function short_date($value): string
{
    if (!$value) {
        return 'No date';
    }

    $time = strtotime((string)$value);
    return $time ? date('M d, Y', $time) : (string)$value;
}

$userName = $_SESSION['username'] ?? 'Admin';
$advanceStoreTotals = dashboard_advance_store_totals(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'advance_request');
$dispatchTrend = is_array($masterData['transportation']['dispatch_trend']) ? $masterData['transportation']['dispatch_trend'] : [];
$dispatchChartLabels = array_keys($dispatchTrend);
$dispatchChartValues = array_map('intval', array_values($dispatchTrend));
$repairChartLabels = [];
$repairChartValues = [];
foreach (array_reverse($masterData['motorpool']['daily_repairs']) as $repairDay) {
    $repairChartLabels[] = short_date($repairDay['repair_day'] ?? '');
    $repairChartValues[] = (int)($repairDay['repair_count'] ?? 0);
}
$maxMostRepaired = max(array_map('intval', array_values($masterData['motorpool']['most_repaired'] ?: [0])));
$maxMostRepaired = $maxMostRepaired > 0 ? $maxMostRepaired : 1;
$chairsMetrics = get_deployment_metrics();
$chairsEquipmentTypes = get_equipment_types();
$chairsInventory = [
    'chairs_total' => 0,
    'chairs_available' => 0,
    'tables_total' => 0,
    'tables_available' => 0,
];
foreach ($chairsEquipmentTypes as $type) {
    if (($type['category'] ?? '') === 'Chair') {
        $chairsInventory['chairs_total'] += (int)($type['total_qty'] ?? 0);
        $chairsInventory['chairs_available'] += (int)($type['available_qty'] ?? 0);
    } elseif (($type['category'] ?? '') === 'Table') {
        $chairsInventory['tables_total'] += (int)($type['total_qty'] ?? 0);
        $chairsInventory['tables_available'] += (int)($type['available_qty'] ?? 0);
    }
}

$fuelBudgetSnapshot = dashboard_fuel_budget_snapshot(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fuel_tracker');
$fuelBudgetSummary = $fuelBudgetSnapshot['summary'];
$fuelWeeklyRows = $fuelBudgetSnapshot['weekly'];
$fuelWeeklyLabels = array_map(static fn(array $row): string => dashboard_short_week((string)($row['week_start'] ?? '')), $fuelWeeklyRows);
$fuelWeeklyDieselAmounts = array_map(static fn(array $row): float => (float)($row['diesel_amount'] ?? 0), $fuelWeeklyRows);
$fuelWeeklyUnleadedAmounts = array_map(static fn(array $row): float => (float)($row['unleaded_amount'] ?? 0), $fuelWeeklyRows);
$fuelWeeklyDieselLiters = array_map(static fn(array $row): float => (float)($row['diesel_liters'] ?? 0), $fuelWeeklyRows);
$fuelWeeklyUnleadedLiters = array_map(static fn(array $row): float => (float)($row['unleaded_liters'] ?? 0), $fuelWeeklyRows);
$fuelWeeklyDieselPrices = array_map(static fn(array $row): float => (float)($row['diesel_price'] ?? 0), $fuelWeeklyRows);
$fuelWeeklyUnleadedPrices = array_map(static fn(array $row): float => (float)($row['unleaded_price'] ?? 0), $fuelWeeklyRows);
$fuelWeeklyTotal = array_sum(array_map(static fn(array $row): float => (float)($row['total_amount'] ?? 0), $fuelWeeklyRows));
$fuelWeeklyMissingPrices = array_sum(array_map(static fn(array $row): int => (int)($row['missing_price_count'] ?? 0), $fuelWeeklyRows));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GSO Master Dashboard</title>
    <link rel="stylesheet" href="master_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="true">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="masterSidebar">
        <div class="logo">
            <img src="../logo.png" alt="Tagbilaran seal">
            <div class="sidebar-user">
                <span class="role">Admin</span>
                <span class="user-name"><?php echo h($userName); ?></span>
            </div>
        </div>
        <hr class="divider">
        <nav aria-label="Master dashboard navigation">
            <ul>
                <li><a class="active" href="dashboard.php"><i class="fas fa-th-large icon-size"></i><span class="nav-label">Master Dashboard</span></a></li>
                <li><a href="assets.php"><i class="fas fa-campground icon-size"></i><span class="nav-label">Tents</span></a></li>
                <li><a href="chairs_table.php"><i class="fas fa-chair icon-size"></i><span class="nav-label">Chairs & Tables</span></a></li>
                <li><a href="advance_request.php"><i class="fas fa-receipt icon-size"></i><span class="nav-label">Advance Request</span></a></li>
                <li><a href="transportation.php"><i class="fas fa-truck icon-size"></i><span class="nav-label">Transportation</span></a></li>
                <li><a href="motorpool.php"><i class="fas fa-wrench icon-size"></i><span class="nav-label">Motorpool</span></a></li>
                <li><a href="payables.php"><i class="fas fa-file-invoice-dollar icon-size"></i><span class="nav-label">BAC Payables</span></a></li>
            </ul>
        </nav>
        <a href="../logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i><span class="nav-label">Logout</span></a>
    </aside>

    <main class="dashboard-content">
        <header class="page-header reveal-on-load">
            <div>
                <p class="eyebrow">Executive overview</p>
                <h1>GSO Master Dashboard</h1>
                <span class="page-subtitle">Read-only summary across tents, chairs and tables, transportation, motorpool, and BAC payables.</span>
            </div>
            <div class="header-actions">
                <span class="last-updated"><i class="fas fa-clock"></i> Updated <?php echo date('M d, Y g:i A'); ?></span>
            </div>
        </header>

        <section class="dashboard-layout">
            <div class="module-stack">
                <section class="dashboard-card module-card reveal-on-load">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">Tents</span>
                            <h2>Tent Inventory</h2>
                        </div>
                        <a class="module-link" href="assets.php">Open workspace <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="mini-metrics four">
                        <div><span>Available</span><strong><?php echo n($masterData['tents']['available']); ?></strong></div>
                        <div><span>On Field</span><strong><?php echo n($masterData['tents']['on_field']); ?></strong></div>
                        <div><span>Retrieval</span><strong><?php echo n($masterData['tents']['for_retrieval']); ?></strong></div>
                        <div><span>Long Term</span><strong><?php echo n($masterData['tents']['long_term']); ?></strong></div>
                    </div>
</section>

                <section class="dashboard-card module-card reveal-on-load">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">Asset Tracker Dashboard</span>
                            <h2>Chairs & Tables</h2>
                        </div>
                        <a class="module-link" href="chairs_table.php">Open workspace <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="mini-metrics four">
                        <div><span>Available Chairs</span><strong><?php echo n($chairsInventory['chairs_available']); ?></strong></div>
                        <div><span>Available Tables</span><strong><?php echo n($chairsInventory['tables_available']); ?></strong></div>
                        <div><span>Pending</span><strong><?php echo n($chairsMetrics['pending']); ?></strong></div>
                        <div><span>Overdue</span><strong><?php echo n($chairsMetrics['overdue']); ?></strong></div>
                    </div>
</section>

                <section class="dashboard-card module-card reveal-on-load">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">Advance Request</span>
                            <h2>Pending Expenses</h2>
                        </div>
                        <a class="module-link" href="advance_request.php">Open workspace <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if ($advanceStoreTotals): ?>
                        <div class="mini-metrics advance-summary-metrics">
                            <?php foreach ($advanceStoreTotals as $store => $total): ?>
                                <div><span><?php echo h($store); ?></span><strong><?php echo peso($total); ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No pending advance request expenses.</div>
                    <?php endif; ?>
                </section>

                <section class="dashboard-card module-card reveal-on-load">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">Fuel Tracker</span>
                            <h2>Fuel Budget Actual Usage</h2>
                        </div>
                        <a class="module-link" href="../fuel_tracker/fuel_dashboard.php">Open workspace <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if ($fuelBudgetSnapshot['available']): ?>
                        <div class="fuel-master-layout">
                            <div class="fuel-master-overview">
                                <div class="fuel-total-panel">
                                    <span class="fuel-panel-label">Actual Used</span>
                                    <strong><?php echo peso($fuelBudgetSummary['used_budget'] ?? 0); ?></strong>
                                    <em>Based on used gas issuances, actual liters, and saved weekly pump prices.</em>
                                </div>
                                <div class="fuel-breakdown-list">
                                    <div class="fuel-breakdown-row diesel">
                                        <div>
                                            <span>Diesel</span>
                                            <strong><?php echo peso($fuelBudgetSummary['used_diesel_budget'] ?? 0); ?></strong>
                                        </div>
                                        <em><?php echo h(number_format((float)($fuelBudgetSummary['actual_diesel_liters'] ?? 0), 2)); ?> L</em>
                                    </div>
                                    <div class="fuel-breakdown-row unleaded">
                                        <div>
                                            <span>Unleaded</span>
                                            <strong><?php echo peso($fuelBudgetSummary['used_unleaded_budget'] ?? 0); ?></strong>
                                        </div>
                                        <em><?php echo h(number_format((float)($fuelBudgetSummary['actual_unleaded_liters'] ?? 0), 2)); ?> L</em>
                                    </div>
                                    <?php if ((int)($fuelBudgetSummary['actual_missing_price_count'] ?? 0) > 0): ?>
                                        <div class="fuel-breakdown-alert"><?php echo n($fuelBudgetSummary['actual_missing_price_count']); ?> used record(s) need a weekly pump price.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="fuel-master-chart-panel">
                                <div class="fuel-chart-heading">
                                    <div>
                                        <span class="fuel-panel-label">Deductions per Week</span>
                                        <strong><?php echo h(count($fuelWeeklyRows)); ?> weeks shown</strong>
                                    </div>
                                    <div class="fuel-chart-total">
                                        <span>Total</span>
                                        <strong><?php echo peso($fuelWeeklyTotal); ?></strong>
                                    </div>
                                </div>
                                <?php if ($fuelWeeklyRows): ?>
                                    <canvas
                                        class="master-fuel-chart"
                                        width="760"
                                        height="260"
                                        aria-label="Weekly fuel budget deductions and pump prices"
                                        role="img"
                                        data-labels="<?php echo h(json_encode($fuelWeeklyLabels)); ?>"
                                        data-diesel-amounts="<?php echo h(json_encode($fuelWeeklyDieselAmounts)); ?>"
                                        data-unleaded-amounts="<?php echo h(json_encode($fuelWeeklyUnleadedAmounts)); ?>"
                                        data-diesel-liters="<?php echo h(json_encode($fuelWeeklyDieselLiters)); ?>"
                                        data-unleaded-liters="<?php echo h(json_encode($fuelWeeklyUnleadedLiters)); ?>"
                                        data-diesel-prices="<?php echo h(json_encode($fuelWeeklyDieselPrices)); ?>"
                                        data-unleaded-prices="<?php echo h(json_encode($fuelWeeklyUnleadedPrices)); ?>"
                                    ></canvas>
                                    <div class="fuel-chart-legend" aria-hidden="true">
                                        <span><i class="diesel-fill"></i> Diesel deduction</span>
                                        <span><i class="unleaded-fill"></i> Unleaded deduction</span>
                                        <span><i class="diesel-line"></i> Diesel price/L</span>
                                        <span><i class="unleaded-line"></i> Unleaded price/L</span>
                                    </div>
                                    <?php if ($fuelWeeklyMissingPrices > 0): ?>
                                        <div class="fuel-master-note"><?php echo n($fuelWeeklyMissingPrices); ?> used record(s) in the shown weeks need a matching weekly pump price.</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="empty-state">No weekly used gas issuance deductions yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state prominent">Fuel Tracker budget data is unavailable right now.</div>
                    <?php endif; ?>
                </section>

                <section class="dashboard-card module-card reveal-on-load">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">Transportation</span>
                            <h2>Dispatch Activity</h2>
                        </div>
                        <a class="module-link" href="transportation.php">Open workspace <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="split-grid">
                        <div>
                            <div class="mini-metrics two">
                                <div><span>Available</span><strong><?php echo n($masterData['transportation']['available']); ?></strong></div>
                                <div><span>On Field</span><strong><?php echo n($masterData['transportation']['departed']); ?></strong></div>
                            </div>
                            <div class="line-chart-card">
                                <div class="chart-title">
                                    <span>Dispatch per Day</span>
                                    <strong><?php echo n(array_sum($dispatchChartValues)); ?></strong>
                                </div>
                                <canvas
                                    class="master-line-chart"
                                    width="640"
                                    height="220"
                                    aria-label="Dispatch per day line graph"
                                    role="img"
                                    data-labels="<?php echo h(json_encode($dispatchChartLabels)); ?>"
                                    data-values="<?php echo h(json_encode($dispatchChartValues)); ?>"
                                ></canvas>
                            </div>
                        </div>
                        <div class="rank-list">
                            <h3>Top Used Vehicles</h3>
                            <?php if ($masterData['transportation']['top_vehicles']): ?>
                                <?php foreach ($masterData['transportation']['top_vehicles'] as $vehicle): ?>
                                    <div class="rank-item">
                                        <span><?php echo h($vehicle['plate_no'] ?? ''); ?></span>
                                        <strong><?php echo h($vehicle['vehicle'] ?? 'Vehicle'); ?></strong>
                                        <em><?php echo n($vehicle['count'] ?? 0); ?> trips</em>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">No vehicle usage data.</div>
                            <?php endif; ?>
                        </div>
                    </div>
</section>

                <section class="dashboard-card module-card reveal-on-load">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">Motorpool</span>
                            <h2>Repair Monitoring</h2>
                        </div>
                        <a class="module-link" href="motorpool.php">Open workspace <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="split-grid">
                        <div>
                            <div class="mini-metrics three">
                                <div><span>Pending</span><strong><?php echo n($masterData['motorpool']['pending']); ?></strong></div>
                                <div><span>In Progress</span><strong><?php echo n($masterData['motorpool']['in_progress']); ?></strong></div>
                                <div><span>Completed</span><strong><?php echo n($masterData['motorpool']['completed']); ?></strong></div>
                            </div>
                            <div class="line-chart-card">
                                <div class="chart-title">
                                    <span>Repairs per Day</span>
                                    <strong><?php echo n(array_sum($repairChartValues)); ?></strong>
                                </div>
                                <canvas
                                    class="master-line-chart"
                                    width="640"
                                    height="220"
                                    aria-label="Repairs per day line graph"
                                    role="img"
                                    data-labels="<?php echo h(json_encode($repairChartLabels)); ?>"
                                    data-values="<?php echo h(json_encode($repairChartValues)); ?>"
                                ></canvas>
                            </div>
                        </div>
                        <div class="rank-list">
                            <h3>Most Repaired</h3>
                            <?php if ($masterData['motorpool']['most_repaired']): ?>
                                <?php foreach ($masterData['motorpool']['most_repaired'] as $plate => $count): ?>
                                    <?php $width = max(6, ((int)$count / $maxMostRepaired) * 100); ?>
                                    <div class="repair-rank">
                                        <div><strong><?php echo h($plate); ?></strong><span><?php echo n($count); ?> completed</span></div>
                                        <i style="width: <?php echo h($width); ?>%;"></i>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">No completed repair data.</div>
                            <?php endif; ?>
                        </div>
                    </div>
</section>

                <section class="dashboard-card module-card reveal-on-load">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">BAC Payables</span>
                            <h2>Workflow Status</h2>
                        </div>
                        <a class="module-link" href="payables.php">Open workspace <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if ($masterData['payables']['available']): ?>
                        <div class="mini-metrics five">
                            <div><span>GSO</span><strong><?php echo n($masterData['payables']['counts']['GSO']); ?></strong></div>
                            <div><span>Budget</span><strong><?php echo n($masterData['payables']['counts']['BUDGET']); ?></strong></div>
                            <div><span>Accounting</span><strong><?php echo n($masterData['payables']['counts']['ACCOUNTING']); ?></strong></div>
                            <div><span>CTO</span><strong><?php echo n($masterData['payables']['counts']['CTO']); ?></strong></div>
                            <div><span>Check Released</span><strong><?php echo n($masterData['payables']['counts']['RELEASED']); ?></strong></div>
                        </div>

                    <?php else: ?>
                        <div class="empty-state prominent">Payables data is unavailable right now. The master dashboard is still showing asset, transportation, and motorpool summaries.</div>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="side-rail">
                <section class="dashboard-card reveal-on-load">
                    <div class="card-header compact">
                        <div>
                            <span class="section-kicker">Attention</span>
                            <h2>Alerts</h2>
                        </div>
                    </div>
                    <div class="alert-list">
                        <?php foreach ($masterData['alerts'] as $alert): ?>
                            <a class="alert-item <?php echo h($alert['tone']); ?>" href="<?php echo h($alert['href']); ?>">
                                <span><?php echo h($alert['label']); ?></span>
                                <strong><?php echo n($alert['value']); ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="dashboard-card reveal-on-load">
                    <div class="card-header compact">
                        <div>
                            <span class="section-kicker">Activity</span>
                            <h2>Latest Payables Transactions</h2>
                        </div>
                    </div>
                    <div class="history-list">
                        <?php if ($masterData['payables']['latest']): ?>
                            <?php foreach (array_slice($masterData['payables']['latest'], 0, 5) as $item): ?>
                                <div class="history-item">
                                    <span class="history-icon"><?php echo h(substr((string)($item['source'] ?? '?'), 0, 1)); ?></span>
                                    <div>
                                        <strong><?php echo h($item['title'] ?? ''); ?></strong>
                                        <span><?php echo h(($item['source'] ?? '') . ' / ' . ($item['reference_no'] ?? '')); ?></span>
                                    </div>
                                    <time><?php echo h(short_date($item['transaction_date'] ?? '')); ?></time>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">No transaction history available.</div>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </section>
    </main>

    <script src="master_dashboard.js"></script>
</body>

</html>
