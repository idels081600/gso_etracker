<section class="page-header">
    <div>
        <p class="eyebrow">Super admin</p>
        <h1>User Management</h1>
    </div>
    <form class="toolbar" method="get">
        <input type="hidden" name="page" value="users">
        <label><span>Search</span><input type="search" name="search" value="<?= h($_GET['search'] ?? '') ?>" placeholder="Name, username, position"></label>
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Username</th><th>Position</th><th>Role</th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><?= h($row['name']) ?></td>
                    <td><?= h($row['username']) ?></td>
                    <td><?= h($row['position']) ?></td>
                    <td><span class="badge badge-info"><?= h($row['role']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <tr><td colspan="4" class="empty">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
