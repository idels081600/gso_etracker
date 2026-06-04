<section class="page-header">
    <div>
        <p class="eyebrow">System history</p>
        <h1>Audit Log</h1>
    </div>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Request</th><th>Summary</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $row): ?>
                <tr>
                    <td><?= h($row['created_at']) ?></td>
                    <td><strong><?= h($row['actor_username']) ?></strong><small><?= h($row['actor_role']) ?></small></td>
                    <td><span class="badge badge-info"><?= h($row['action']) ?></span></td>
                    <td><?= h($row['request_id']) ?></td>
                    <td><?= h($row['summary']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="5" class="empty">Audit events will appear after new app actions are used.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
