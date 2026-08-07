<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();

require_once __DIR__ . '/master_data.php';


$today = date('Y-m-d');
$metrics = get_deployment_metrics();
$equipmentTypes = get_equipment_types();
$deployments = get_deployments_with_items();
$activeStatus = trim($_GET['status'] ?? 'ALL');
$search = trim($_GET['search'] ?? '');
$statuses = ['ALL', 'Pending', 'Deployed', 'For Retrieval', 'Retrieved', 'Long Term', 'Overdue'];

if (!in_array($activeStatus, $statuses, true)) {
    $activeStatus = 'ALL';
}

function chairs_status_label(string $status, string $retrievalDate, string $today): string
{
    if ($status === 'For Retrieval' && $retrievalDate < $today) {
        return 'Overdue';
    }

    return $status === 'Deployed' ? 'Installed' : $status;
}

function chairs_status_class(string $status, string $retrievalDate, string $today): string
{
    return strtolower(str_replace(' ', '-', chairs_status_label($status, $retrievalDate, $today)));
}

function chairs_items_summary(array $items, ?string $category = null): string
{
    $parts = [];
    foreach ($items as $item) {
        if ($category !== null && ($item['category'] ?? '') !== $category) {
            continue;
        }
        $parts[] = (int)($item['quantity'] ?? 0) . ' ' . ($item['subtype_name'] ?? $item['display_name'] ?? 'item');
    }

    return $parts ? implode(', ', $parts) : 'None';
}

$inventory = [
    'chairs_total' => 0,
    'chairs_available' => 0,
    'tables_total' => 0,
    'tables_available' => 0,
];

foreach ($equipmentTypes as $type) {
    $category = $type['category'] ?? '';
    if ($category === 'Chair') {
        $inventory['chairs_total'] += (int)($type['total_qty'] ?? 0);
        $inventory['chairs_available'] += (int)($type['available_qty'] ?? 0);
    } elseif ($category === 'Table') {
        $inventory['tables_total'] += (int)($type['total_qty'] ?? 0);
        $inventory['tables_available'] += (int)($type['available_qty'] ?? 0);
    }
}

$filteredDeployments = array_values(array_filter($deployments, function (array $deployment) use ($activeStatus, $search, $today): bool {
    $statusLabel = chairs_status_label((string)($deployment['status'] ?? ''), (string)($deployment['retrieval_date'] ?? ''), $today);
    if ($activeStatus !== 'ALL' && $activeStatus !== $statusLabel && $activeStatus !== ($deployment['status'] ?? '')) {
        return false;
    }

    if ($search === '') {
        return true;
    }

    $haystack = strtolower(implode(' ', [
        $deployment['name'] ?? '',
        $deployment['contact_no'] ?? '',
        $deployment['purpose'] ?? '',
        $deployment['location'] ?? '',
        $deployment['address'] ?? '',
        $statusLabel,
        chairs_items_summary($deployment['items'] ?? []),
    ]));

    return strpos($haystack, strtolower($search)) !== false;
}));

master_page_start('chairs_table', 'Chairs & Tables', 'Search chairs and tables deployment records inside the master portal.');
?>
<section class="kpi-grid reveal-on-load">
    <article class="metric-card"><span class="metric-icon success"><i class="fas fa-chair"></i></span><div><span class="metric-label">Available Chairs</span><strong class="count-up" data-count="<?php echo (int)$inventory['chairs_available']; ?>"><?php echo master_n($inventory['chairs_available']); ?></strong><small><?php echo master_n($inventory['chairs_total']); ?> total chairs</small></div></article>
    <article class="metric-card"><span class="metric-icon info"><i class="fas fa-table"></i></span><div><span class="metric-label">Available Tables</span><strong class="count-up" data-count="<?php echo (int)$inventory['tables_available']; ?>"><?php echo master_n($inventory['tables_available']); ?></strong><small><?php echo master_n($inventory['tables_total']); ?> total tables</small></div></article>
    <article class="metric-card"><span class="metric-icon warning"><i class="fas fa-truck-loading"></i></span><div><span class="metric-label">For Retrieval</span><strong class="count-up" data-count="<?php echo (int)($metrics['due_today'] + $metrics['overdue']); ?>"><?php echo master_n($metrics['due_today'] + $metrics['overdue']); ?></strong><small><?php echo master_n($metrics['overdue']); ?> overdue</small></div></article>
    <article class="metric-card"><span class="metric-icon primary"><i class="fas fa-clipboard-list"></i></span><div><span class="metric-label">Deployment Records</span><strong class="count-up" data-count="<?php echo (int)$metrics['total']; ?>"><?php echo master_n($metrics['total']); ?></strong><small><?php echo master_n($metrics['pending']); ?> pending</small></div></article>
</section>

<section class="dashboard-card workspace-card reveal-on-load">
    <div class="workspace-toolbar">
        <div class="task-tabs">
            <?php foreach ($statuses as $status): ?>
                <?php $label = $status === 'ALL' ? 'ALL' : ($status === 'Deployed' ? 'INSTALLED' : strtoupper($status)); ?>
                <a class="<?php echo $activeStatus === $status && $search === '' ? 'active' : ''; ?>" href="chairs_table.php?status=<?php echo rawurlencode($status); ?>"><?php echo master_h($label); ?></a>
            <?php endforeach; ?>
        </div>
        <form class="workspace-search" method="get">
            <i class="fas fa-search"></i>
            <input type="hidden" name="status" value="<?php echo master_h($activeStatus); ?>">
            <input type="search" name="search" value="<?php echo master_h($search); ?>" placeholder="Search deployment records">
            <button class="primary-button compact" type="submit">Search</button>
        </form>
    </div>

    <div class="master-table is-active">
        <div class="master-row master-head chairs-row">
            <span>Name / Contact</span><span>Purpose / Location</span><span>Chairs</span><span>Tables</span><span>Install Date</span><span>Retrieval Date</span><span>Status</span>
        </div>
        <?php foreach ($filteredDeployments as $deployment): ?>
            <?php
            $statusLabel = chairs_status_label((string)($deployment['status'] ?? ''), (string)($deployment['retrieval_date'] ?? ''), $today);
            $statusClass = chairs_status_class((string)($deployment['status'] ?? ''), (string)($deployment['retrieval_date'] ?? ''), $today);
            ?>
            <div class="master-row chairs-row">
                <span><strong><?php echo master_h($deployment['name'] ?? ''); ?></strong><small><?php echo master_h($deployment['contact_no'] ?? ''); ?></small></span>
                <span><strong><?php echo master_h($deployment['purpose'] ?? ''); ?></strong><small><?php echo master_h(($deployment['location'] ?? '') . ' - ' . ($deployment['address'] ?? '')); ?></small></span>
                <span><?php echo master_h(chairs_items_summary($deployment['items'] ?? [], 'Chair')); ?></span>
                <span><?php echo master_h(chairs_items_summary($deployment['items'] ?? [], 'Table')); ?></span>
                <span><?php echo master_h(master_short_date($deployment['date'] ?? '')); ?></span>
                <span><?php echo master_h(master_short_date($deployment['retrieval_date'] ?? '')); ?></span>
                <span class="status-pill status-<?php echo master_h($statusClass); ?>"><?php echo master_h($statusLabel); ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$filteredDeployments): ?><div class="empty-state">No matching chairs and tables deployment records.</div><?php endif; ?>
    </div>
</section>
<?php master_page_end(); ?>
