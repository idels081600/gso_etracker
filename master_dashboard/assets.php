<?php
require_once __DIR__ . '/master_layout.php';
master_require_admin();

$root = dirname(__DIR__);
set_include_path($root . PATH_SEPARATOR . get_include_path());
require_once $root . '/db_asset.php';
require_once $root . '/display_data_asset.php';

$tentRows = master_rows_from_result(display_data_dashboard(), 300);
$kpis = [
    'available' => display_tent_status(),
    'on_field' => display_tent_status_Installed(),
    'retrieval' => display_tent_status_Retrieval(),
    'long_term' => display_tent_status_Longterm(),
];

master_page_start('assets', 'Tents', 'Search tent records from the master workspace.');
?>
<section class="kpi-grid reveal-on-load">
    <article class="metric-card"><span class="metric-icon success"><i class="fas fa-campground"></i></span><div><span class="metric-label">Available Tents</span><strong class="count-up" data-count="<?php echo (int)$kpis['available']; ?>"><?php echo master_n($kpis['available']); ?></strong><small><?php echo master_n($kpis['on_field']); ?> on field</small></div></article>
    <article class="metric-card"><span class="metric-icon warning"><i class="fas fa-undo"></i></span><div><span class="metric-label">For Retrieval</span><strong class="count-up" data-count="<?php echo (int)$kpis['retrieval']; ?>"><?php echo master_n($kpis['retrieval']); ?></strong><small><?php echo master_n($kpis['long_term']); ?> long term</small></div></article>
    <article class="metric-card"><span class="metric-icon info"><i class="fas fa-map-marker-alt"></i></span><div><span class="metric-label">On Field</span><strong class="count-up" data-count="<?php echo (int)$kpis['on_field']; ?>"><?php echo master_n($kpis['on_field']); ?></strong><small>Installed tents</small></div></article>
    <article class="metric-card"><span class="metric-icon primary"><i class="fas fa-clock"></i></span><div><span class="metric-label">Long Term</span><strong class="count-up" data-count="<?php echo (int)$kpis['long_term']; ?>"><?php echo master_n($kpis['long_term']); ?></strong><small>Extended use</small></div></article>
</section>

<section class="dashboard-card workspace-card reveal-on-load">
    <div class="workspace-toolbar">
        <div class="task-tabs" data-master-tabs>
            <button type="button" class="active" data-tab-target="tentTable">Tent Records</button>
        </div>
        <div class="workspace-search">
            <i class="fas fa-search"></i>
            <input type="search" class="master-table-search" placeholder="Search current table" aria-label="Search records">
            <input type="date" class="master-start-date" aria-label="Start date">
            <input type="date" class="master-end-date" aria-label="End date">
        </div>
    </div>

    <div class="master-table is-active" id="tentTable" data-date-column="1">
        <div class="master-row master-head"><span>Tent No.</span><span>Date</span><span>Name</span><span>Contact</span><span>Tents</span><span>Purpose</span><span>Location</span><span>Status</span></div>
        <?php foreach ($tentRows as $row): ?>
            <div class="master-row">
                <span><?php echo master_h($row['tent_no'] ?? ''); ?></span>
                <span><?php echo master_h($row['date'] ?? ''); ?></span>
                <strong><?php echo master_h($row['name'] ?? ''); ?></strong>
                <span><?php echo master_h($row['Contact_no'] ?? ''); ?></span>
                <span><?php echo master_h($row['no_of_tents'] ?? ''); ?></span>
                <span><?php echo master_h($row['purpose'] ?? ''); ?></span>
                <span><?php echo master_h($row['location'] ?? ''); ?></span>
                <span class="status-pill"><?php echo master_h($row['status'] ?? 'Unknown'); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php master_page_end(); ?>
