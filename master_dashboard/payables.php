<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();

$envPath = dirname(__DIR__) . '/Payables/.env';
$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '=') === false || strpos(trim($line), '#') === 0) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

$payConn = mysqli_connect($env['DB_HOST'] ?? 'localhost', $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', $env['DB_DATABASE'] ?? '', (int)($env['DB_PORT'] ?? 3306));
$payablesAvailable = (bool)$payConn;
if ($payablesAvailable) {
    $payConn->set_charset('utf8mb4');
}

$activeStatus = strtoupper(trim($_GET['status'] ?? 'GSO'));
$statuses = ['GSO', 'BUDGET', 'ACCOUNTING', 'CTO'];
if (!in_array($activeStatus, $statuses, true)) {
    $activeStatus = 'GSO';
}
$search = trim($_GET['search'] ?? '');
$rows = [];
$counts = array_fill_keys(['GSO', 'BUDGET', 'ACCOUNTING', 'CTO', 'RELEASED'], 0);

if ($payablesAvailable) {
    $metricsSql = "SELECT
        SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'GSO' THEN 1 ELSE 0 END) AS gso_count,
        SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'BUDGET' THEN 1 ELSE 0 END) AS budget_count,
        SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'ACCOUNTING' THEN 1 ELSE 0 END) AS accounting_count,
        SUM(CASE WHEN COALESCE(pws.main_status, 'GSO') = 'CTO' THEN 1 ELSE 0 END) AS cto_count,
        SUM(CASE WHEN COALESCE(pws.released, 0) = 1 THEN 1 ELSE 0 END) AS released_count
        FROM transmittal_bac tb
        LEFT JOIN payables_workflow_status pws ON pws.record_type = 'bac' AND pws.record_id = tb.id
        WHERE tb.delete_status = 0";
    $metrics = mysqli_query($payConn, $metricsSql);
    if ($metrics && $row = mysqli_fetch_assoc($metrics)) {
        $counts['GSO'] = (int)($row['gso_count'] ?? 0);
        $counts['BUDGET'] = (int)($row['budget_count'] ?? 0);
        $counts['ACCOUNTING'] = (int)($row['accounting_count'] ?? 0);
        $counts['CTO'] = (int)($row['cto_count'] ?? 0);
        $counts['RELEASED'] = (int)($row['released_count'] ?? 0);
    }

    $where = ["tb.delete_status = 0"];
    $types = '';
    $params = [];
    if ($search === '') {
        $where[] = "COALESCE(pws.main_status, 'GSO') = ?";
        $types .= 's';
        $params[] = $activeStatus;
    } else {
        $like = '%' . $search . '%';
        $where[] = "(tb.ib_no LIKE ? OR tb.winning_bidders LIKE ? OR tb.project_name LIKE ? OR CAST(tb.amount AS CHAR) LIKE ? OR COALESCE(pws.main_status, 'GSO') LIKE ?)";
        $types .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $sql = "SELECT tb.id, tb.ib_no, tb.winning_bidders, tb.project_name, tb.amount,
        COALESCE(pws.main_status, 'GSO') AS main_status,
        COALESCE(pws.inspection, 0) AS inspection,
        COALESCE(pws.obr, 0) AS obr,
        COALESCE(pws.ics, 0) AS ics,
        COALESCE(pws.par, 0) AS par,
        COALESCE(pws.ris, 0) AS ris
        FROM transmittal_bac tb
        LEFT JOIN payables_workflow_status pws ON pws.record_type = 'bac' AND pws.record_id = tb.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY tb.id DESC
        LIMIT 150";
    $stmt = $payConn->prepare($sql);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = master_rows_from_result($result, 150);
        $stmt->close();
    }
}

master_page_start('payables', 'BAC Payables', 'Search and update BAC workflow records inside the master portal.');
?>
<section class="kpi-grid reveal-on-load">
    <?php foreach (['GSO', 'BUDGET', 'ACCOUNTING', 'CTO'] as $status): ?>
        <article class="metric-card"><span class="metric-icon success"><i class="fas fa-file-invoice"></i></span><div><span class="metric-label"><?php echo master_h($status); ?></span><strong class="count-up" data-count="<?php echo (int)$counts[$status]; ?>"><?php echo master_n($counts[$status]); ?></strong><small>Workflow records</small></div></article>
    <?php endforeach; ?>
</section>

<section class="dashboard-card workspace-card reveal-on-load">
    <?php if (!$payablesAvailable): ?>
        <div class="empty-state prominent">Payables database is unavailable right now.</div>
    <?php else: ?>
        <div class="workspace-toolbar">
            <div class="task-tabs">
                <?php foreach ($statuses as $status): ?>
                    <a class="<?php echo $activeStatus === $status && $search === '' ? 'active' : ''; ?>" href="payables.php?status=<?php echo master_h($status); ?>"><?php echo master_h($status); ?></a>
                <?php endforeach; ?>
            </div>
            <form class="workspace-search" method="get">
                <i class="fas fa-search"></i>
                <input type="hidden" name="status" value="<?php echo master_h($activeStatus); ?>">
                <input type="search" name="search" value="<?php echo master_h($search); ?>" placeholder="Search BAC records">
                <button class="primary-button compact" type="submit">Search</button>
            </form>
        </div>
        <div class="master-table is-active">
            <div class="master-row master-head payables-row readonly"><span>IB No.</span><span>Bidder / Project</span><span>Amount</span><span>Checklist</span></div>
            <?php foreach ($rows as $row): ?>
                <div class="master-row payables-row readonly" data-record-id="<?php echo (int)$row['id']; ?>">
                    <span><?php echo master_h($row['ib_no'] ?? ''); ?></span>
                    <strong><?php echo master_h(($row['winning_bidders'] ?? '') . ' / ' . ($row['project_name'] ?? '')); ?></strong>
                    <span>&#8369;<?php echo number_format((float)($row['amount'] ?? 0), 2); ?></span>
                    <span class="checklist-inline readonly" aria-label="Current checklist">
                        <?php foreach (['inspection' => 'Inspection', 'obr' => 'OBR', 'ics' => 'ICS', 'par' => 'PAR', 'ris' => 'RIS'] as $key => $label): ?>
                            <span class="check-chip <?php echo !empty($row[$key]) ? 'is-complete' : 'is-missing'; ?>">
                                <i class="fas <?php echo !empty($row[$key]) ? 'fa-check' : 'fa-minus'; ?>"></i>
                                <?php echo master_h($label); ?>
                            </span>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if (!$rows): ?><div class="empty-state">No matching BAC records.</div><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php master_page_end(); ?>
