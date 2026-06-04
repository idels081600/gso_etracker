<section class="page-header">
    <div>
        <p class="eyebrow">Reporting</p>
        <h1>Export Center</h1>
    </div>
</section>

<section class="panel">
    <div class="report-grid">
        <?php
        $exports = [
            ['Today CGSO', '../admin_r/export_r.php?range=today'],
            ['First 15 Days CGSO', '../admin_r/export_r.php?range=first15'],
            ['Second 15 Days CGSO', '../admin_r/export_r.php?range=second15'],
            ['Today TCWS', '../admin_approver/twcs_employee_export.php?range=today'],
            ['First 15 Days TCWS', '../admin_approver/twcs_employee_export.php?range=first15'],
            ['Second 15 Days TCWS', '../admin_approver/twcs_employee_export.php?range=second15'],
        ];
        ?>
        <?php foreach ($exports as [$label, $href]): ?>
            <a class="report-tile" href="<?= h($href) ?>">
                <strong><?= h($label) ?></strong>
                <span>Download existing export</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
