<section class="page-header">
    <div>
        <p class="eyebrow">Employee request</p>
        <h1>New Pass Slip Request</h1>
    </div>
</section>

<section class="form-panel">
    <form method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create-request">
        <label>
            <span>Name</span>
            <input value="<?= h(current_user()['username']) ?>" readonly>
        </label>
        <label>
            <span>Position</span>
            <input name="position" value="<?= h($position) ?>" required>
        </label>
        <label>
            <span>Date</span>
            <input value="<?= h(date('Y-m-d')) ?>" readonly>
        </label>
        <label>
            <span>Request Type</span>
            <select name="typeofbusiness" required>
                <option value="Official Business">Official Business</option>
                <option value="Personal">Personal</option>
            </select>
        </label>
        <label class="wide">
            <span>Destination</span>
            <input name="destination" required maxlength="191">
        </label>
        <label class="wide">
            <span>Purpose</span>
            <textarea name="purpose" rows="4" required maxlength="500"></textarea>
        </label>
        <div class="form-actions wide">
            <a class="btn btn-secondary" href="<?= h(app_url(['page' => 'employee'])) ?>">Cancel</a>
            <button class="btn btn-primary" type="submit">Submit Request</button>
        </div>
    </form>
</section>
