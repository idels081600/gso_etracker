<section class="page-header">
    <div>
        <p class="eyebrow">Employee workspace</p>
        <h1>My Pass Slip</h1>
    </div>
    <a class="btn btn-primary" href="<?= h(app_url(['page' => 'request-new'])) ?>">New Request</a>
</section>

<section class="employee-status">
    <?php if ($current): ?>
        <div class="status-hero">
            <span class="badge badge-status"><?= h($current['status1'] ?: $current['Status']) ?></span>
            <h2><?= h($current['Status']) ?></h2>
            <p><?= h($current['destination']) ?> · <?= h($current['typeofbusiness']) ?></p>
            <dl>
                <div><dt>Confirmed By</dt><dd><?= h($current['confirmed_by'] ?: 'Waiting') ?></dd></div>
                <div><dt>Departure</dt><dd><?= h($current['timedept']) ?></dd></div>
                <div><dt>Estimated Return</dt><dd><?= h($current['esttime']) ?></dd></div>
            </dl>
        </div>
        <aside class="next-action">
            <h2>Next Action</h2>
            <?php if ($current['Status'] === 'Pending'): ?>
                <p>Wait for approval before scanning your QR code.</p>
            <?php elseif ($current['Status'] === 'Partially Approved'): ?>
                <p>Scan your QR code at the desk before leaving.</p>
            <?php elseif ($current['Status'] === 'Approved'): ?>
                <p>Scan again when you return to complete the pass slip.</p>
            <?php elseif ($current['Status'] === 'Declined'): ?>
                <p>Review the decline reason and create a new request if needed.</p>
            <?php else: ?>
                <p>Your latest pass slip is complete.</p>
            <?php endif; ?>
        </aside>
    <?php else: ?>
        <div class="empty-panel">
            <h2>No requests yet</h2>
            <p>Create a request before leaving the office.</p>
            <a class="btn btn-primary" href="<?= h(app_url(['page' => 'request-new'])) ?>">Create Request</a>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Recent History</h2>
            <p>Latest 30 pass-slip records</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Destination</th><th>Type</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= h($row['date']) ?></td>
                    <td><?= h($row['destination']) ?></td>
                    <td><?= h($row['typeofbusiness']) ?></td>
                    <td><span class="badge badge-status"><?= h($row['status1'] ?: $row['Status']) ?></span></td>
                    <td><?= h($row['remarks']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?>
                <tr><td colspan="5" class="empty">No history available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
