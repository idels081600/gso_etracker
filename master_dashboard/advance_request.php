<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();

function advance_db_value(string $source, string $name): string
{
    if (preg_match('/\$' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1\s*;/', $source, $matches)) {
        return $matches[2];
    }

    return '';
}

function advance_connect_from_file(string $dbFile): ?mysqli
{
    if (!class_exists('mysqli') || !is_readable($dbFile)) {
        return null;
    }

    $source = (string)file_get_contents($dbFile);
    $host = advance_db_value($source, 'servername');
    $user = advance_db_value($source, 'username');
    $pass = advance_db_value($source, 'password');
    $db = advance_db_value($source, 'dbname');
    if ($host === '' || $user === '' || $db === '') {
        return null;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = mysqli_init();
    if (!$connection) {
        return null;
    }
    mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    if (!@mysqli_real_connect($connection, $host, $user, $pass, $db)) {
        return null;
    }
    $connection->set_charset('utf8mb4');

    return $connection;
}

$conn = advance_connect_from_file(dirname(__DIR__) . '/advance_request/advance_po_db.php');
$advanceConnectionAvailable = $conn instanceof mysqli;

$stores = ['BQ', 'BQ BUILDERWARE', 'NODAL', 'JETS MARKETING', 'JJS SEAFOODS', 'CITY TYRE'];
$search = trim($_GET['search'] ?? '');
$pageSize = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

function advance_peso($value): string
{
    return '&#8369;' . number_format((float)$value, 2);
}

function advance_page_url(int $page, string $search): string
{
    $query = ['page' => $page];
    if ($search !== '') {
        $query['search'] = $search;
    }

    return 'advance_request.php?' . http_build_query($query);
}

$storeTotals = array_fill_keys($stores, 0.0);
$metricsSql = "
    SELECT store, COALESCE(SUM(amount), 0) AS total_amount
    FROM advancePo
    WHERE delete_status = 0
      AND status = 'Pending'
      AND store IN ('BQ', 'BQ BUILDERWARE', 'NODAL', 'JETS MARKETING', 'JJS SEAFOODS', 'CITY TYRE')
    GROUP BY store";
$metricsResult = $advanceConnectionAvailable ? mysqli_query($conn, $metricsSql) : false;
if ($metricsResult) {
    while ($row = mysqli_fetch_assoc($metricsResult)) {
        if (isset($storeTotals[$row['store']])) {
            $storeTotals[$row['store']] = (float)$row['total_amount'];
        }
    }
}

$where = ['delete_status = 0'];
$types = '';
$params = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = "(store LIKE ? OR date LIKE ? OR invoice_number LIKE ? OR description LIKE ? OR CAST(pcs AS CHAR) LIKE ? OR CAST(unit_price AS CHAR) LIKE ? OR CAST(amount AS CHAR) LIKE ? OR status LIKE ?)";
    $types .= 'ssssssss';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$totalRows = 0;
$countSql = "SELECT COUNT(*) AS total_rows FROM advancePo WHERE {$whereSql}";
$countStmt = $advanceConnectionAvailable ? $conn->prepare($countSql) : false;
if ($countStmt) {
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($countResult && $row = mysqli_fetch_assoc($countResult)) {
        $totalRows = (int)($row['total_rows'] ?? 0);
    }
    $countStmt->close();
}

$totalPages = max(1, (int)ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$rows = [];

$dataSql = "
    SELECT store, date, invoice_number, description, pcs, unit_price, amount, status
    FROM advancePo
    WHERE {$whereSql}
    ORDER BY id DESC
    LIMIT ? OFFSET ?";
$dataStmt = $advanceConnectionAvailable ? $conn->prepare($dataSql) : false;
if ($dataStmt) {
    $dataTypes = $types . 'ii';
    $dataParams = array_merge($params, [$pageSize, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $dataResult = $dataStmt->get_result();
    $rows = master_rows_from_result($dataResult, $pageSize);
    $dataStmt->close();
}

master_page_start('advance_request', 'Advance Request', 'View and search advance request records from the master portal.');
?>
<section class="kpi-grid advance-metrics reveal-on-load">
    <?php foreach ($stores as $index => $store): ?>
        <?php $tones = ['success', 'info', 'warning', 'primary']; ?>
        <article class="metric-card">
            <span class="metric-icon <?php echo master_h($tones[$index % count($tones)]); ?>"><i class="fas fa-receipt"></i></span>
            <div>
                <span class="metric-label"><?php echo master_h($store); ?></span>
                <strong><?php echo advance_peso($storeTotals[$store]); ?></strong>
                <small>Pending total expenses</small>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="dashboard-card workspace-card reveal-on-load">
    <div class="workspace-toolbar">
        <div class="task-tabs">
            <a class="active" href="advance_request.php">Advance Requests</a>
        </div>
        <form class="workspace-search" method="get">
            <i class="fas fa-search"></i>
            <input type="search" name="search" value="<?php echo master_h($search); ?>" placeholder="Search advance requests">
            <button class="primary-button compact" type="submit">Search</button>
            <?php if ($search !== ''): ?>
                <a class="module-link" href="advance_request.php">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="master-table is-active">
        <div class="master-row master-head advance-row">
            <span>Store</span><span>Date</span><span>Invoice No.</span><span>Description</span><span>Pcs</span><span>Unit Price</span><span>Amount</span><span>Status</span>
        </div>
        <?php foreach ($rows as $row): ?>
            <div class="master-row advance-row">
                <span><?php echo master_h($row['store'] ?? ''); ?></span>
                <span><?php echo master_h(master_short_date($row['date'] ?? '')); ?></span>
                <span><?php echo master_h($row['invoice_number'] ?? ''); ?></span>
                <strong><?php echo master_h($row['description'] ?? ''); ?></strong>
                <span><?php echo master_h($row['pcs'] ?? ''); ?></span>
                <span><?php echo advance_peso($row['unit_price'] ?? 0); ?></span>
                <span><?php echo advance_peso($row['amount'] ?? 0); ?></span>
                <span class="status-pill"><?php echo master_h($row['status'] ?? 'Unknown'); ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?><div class="empty-state">No matching advance request records.</div><?php endif; ?>
    </div>

    <div class="master-pagination" aria-label="Advance request pagination">
        <span class="pagination-info">
            Showing <?php echo master_n($totalRows === 0 ? 0 : $offset + 1); ?>-<?php echo master_n(min($offset + $pageSize, $totalRows)); ?> of <?php echo master_n($totalRows); ?>
        </span>
        <div class="pagination-links">
            <a class="<?php echo $page <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : master_h(advance_page_url($page - 1, $search)); ?>">Previous</a>
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a class="<?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo master_h(advance_page_url($i, $search)); ?>"><?php echo master_n($i); ?></a>
            <?php endfor; ?>
            <a class="<?php echo $page >= $totalPages ? 'is-disabled' : ''; ?>" href="<?php echo $page >= $totalPages ? '#' : master_h(advance_page_url($page + 1, $search)); ?>">Next</a>
        </div>
    </div>
</section>
<?php
if ($advanceConnectionAvailable) {
    mysqli_close($conn);
}
master_page_end();
?>
