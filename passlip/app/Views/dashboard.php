<section class="page-header">
    <div>
        <p class="eyebrow">Approver workspace</p>
        <h1>Pending Requests</h1>
    </div>
    <form class="toolbar" method="get">
        <input type="hidden" name="page" value="dashboard">
        <label>
            <span>Search</span>
            <input type="search" name="search" value="<?= h($_GET['search'] ?? '') ?>" placeholder="Name, position, destination">
        </label>
        <label>
            <span>Type</span>
            <select name="type">
                <option value="">All types</option>
                <option value="Official Business" <?= ($_GET['type'] ?? '') === 'Official Business' ? 'selected' : '' ?>>Official Business</option>
                <option value="Personal" <?= ($_GET['type'] ?? '') === 'Personal' ? 'selected' : '' ?>>Personal</option>
            </select>
        </label>
        <button class="btn btn-secondary" type="submit">Apply Filters</button>
    </form>
</section>

<section class="metric-grid" aria-label="Request summary">
    <div class="metric"><span>Pending</span><strong><?= h($stats['pending']) ?></strong></div>
    <div class="metric"><span>Partially Approved</span><strong><?= h($stats['partial']) ?></strong></div>
    <div class="metric"><span>Outside Office</span><strong><?= h($stats['outside']) ?></strong></div>
    <div class="metric danger"><span>Overdue</span><strong><?= h($stats['overdue']) ?></strong></div>
</section>

<section class="split-layout">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Approval Queue</h2>
                <p><?= count($pending) ?> request(s) waiting</p>
            </div>
            <button class="btn btn-primary" type="button" data-open-batch disabled>Process Selected</button>
        </div>
        <div class="table-wrap">
            <table class="data-table" data-select-table>
                <thead>
                <tr>
                    <th><input type="checkbox" data-select-all aria-label="Select all requests"></th>
                    <th>Name</th>
                    <th>Destination</th>
                    <th>Type</th>
                    <th>Role</th>
                    <th>Age</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pending as $row): ?>
                    <tr>
                        <td><input type="checkbox" name="request_ids[]" value="<?= h($row['id']) ?>" data-row-check aria-label="Select <?= h($row['name']) ?>"></td>
                        <td>
                            <strong><?= h($row['name']) ?></strong>
                            <small><?= h($row['position']) ?></small>
                        </td>
                        <td><?= h($row['destination']) ?></td>
                        <td><span class="badge <?= $row['typeofbusiness'] === 'Personal' ? 'badge-warning' : 'badge-info' ?>"><?= h($row['typeofbusiness']) ?></span></td>
                        <td><?= h($row['Role']) ?></td>
                        <td><?= h($row['date']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pending): ?>
                    <tr><td colspan="6" class="empty">No pending requests match the current filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <aside class="panel">
        <div class="panel-head">
            <div>
                <h2>Overdue Watch</h2>
                <p>Employees past estimated time by more than one hour</p>
            </div>
        </div>
        <div class="stack-list">
            <?php foreach ($overdue as $row): ?>
                <article class="list-item">
                    <strong><?= h($row['name']) ?></strong>
                    <span><?= h($row['destination']) ?></span>
                    <small><?= h($row['minutes_overdue']) ?> minutes overdue</small>
                </article>
            <?php endforeach; ?>
            <?php if (!$overdue): ?>
                <p class="empty">No overdue employees right now.</p>
            <?php endif; ?>
        </div>
    </aside>
</section>

<dialog class="modal" data-batch-modal>
    <form method="post" class="modal-card">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="approve-batch">
        <input type="hidden" name="selected_ids" data-selected-ids>
        <header>
            <div>
                <p class="eyebrow">Batch action</p>
                <h2>Process selected requests</h2>
            </div>
            <button type="button" class="icon-btn" data-close-modal aria-label="Close">x</button>
        </header>
        <div class="form-grid">
            <label>
                <span>Status</span>
                <select name="status" data-status-select required>
                    <option value="Partially Approved">Partially Approved</option>
                    <option value="Declined">Declined</option>
                </select>
            </label>
            <label>
                <span>Confirmed By</span>
                <input name="confirmed_by" value="<?= h(current_user()['name']) ?>" required>
            </label>
            <label data-time-field>
                <span>Hours</span>
                <input type="number" name="fix_hours" min="0" max="8" value="1">
            </label>
            <label data-time-field>
                <span>Minutes</span>
                <input type="number" name="fix_minutes" min="0" max="59" value="0">
            </label>
            <label class="wide" data-decline-field hidden>
                <span>Decline Reason</span>
                <textarea name="decline_reason" rows="3" placeholder="Reason for declining selected requests"></textarea>
            </label>
        </div>
        <div class="selection-summary" data-selection-summary>No requests selected.</div>
        <footer>
            <button class="btn btn-secondary" type="button" data-close-modal>Cancel</button>
            <button class="btn btn-primary" type="submit">Confirm Batch Action</button>
        </footer>
    </form>
</dialog>
