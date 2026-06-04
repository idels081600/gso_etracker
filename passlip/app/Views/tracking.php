<section class="page-header">
    <div>
        <p class="eyebrow">Live operations</p>
        <h1>Employee Tracking</h1>
    </div>
    <form class="toolbar" method="get">
        <input type="hidden" name="page" value="tracking">
        <label><span>Search</span><input type="search" name="search" value="<?= h($_GET['search'] ?? '') ?>" placeholder="Name or destination"></label>
        <label>
            <span>Status</span>
            <select name="status1">
                <option value="">All statuses</option>
                <?php foreach (['Waiting For Pass Slip Approval', 'Scan Qrcode', 'Pass-Slip', 'Present', 'Declined'] as $status): ?>
                    <option value="<?= h($status) ?>" <?= ($_GET['status1'] ?? '') === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-secondary" type="submit">Apply Filters</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Tracked Employees</h2>
            <p><?= count($rows) ?> record(s)</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Destination</th><th>Status</th><th>Type</th><th>Estimated</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?= h($row['name']) ?></strong><small><?= h($row['Role']) ?></small></td>
                    <td><?= h($row['destination']) ?></td>
                    <td><span class="badge badge-status"><?= h($row['status1']) ?></span></td>
                    <td><?= h($row['typeofbusiness']) ?></td>
                    <td><?= h($row['esttime']) ?></td>
                    <td><?= h($row['remarks']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="empty">No records match the current filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
