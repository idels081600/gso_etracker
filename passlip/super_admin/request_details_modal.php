<?php
session_start();
require_once '../dbh.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo '<div class="empty">Please sign in again.</div>';
    exit();
}

if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
    http_response_code(403);
    echo '<div class="empty">You do not have access to this request.</div>';
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(422);
    echo '<div class="empty">Invalid request id.</div>';
    exit();
}

$stmt = $conn->prepare('SELECT * FROM `request` WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    http_response_code(404);
    echo '<div class="empty">Request not found.</div>';
    exit();
}
?>
<form action="../code.php" method="POST" class="super-detail-form">
    <input type="hidden" name="data_id" value="<?= (int) $data['id']; ?>">

    <div class="super-detail-grid">
        <div class="super-detail-item">
            <span>Name</span>
            <strong><?= htmlspecialchars($data['name']); ?></strong>
        </div>
        <div class="super-detail-item">
            <span>Position</span>
            <strong><?= htmlspecialchars($data['position']); ?></strong>
        </div>
        <div class="super-detail-item">
            <span>Date</span>
            <strong><?= htmlspecialchars($data['date']); ?></strong>
        </div>
        <div class="super-detail-item">
            <span>Type</span>
            <strong><?= htmlspecialchars($data['typeofbusiness']); ?></strong>
        </div>
        <div class="super-detail-item wide">
            <span>Destination</span>
            <strong><?= htmlspecialchars($data['destination']); ?></strong>
        </div>
        <div class="super-detail-item wide">
            <span>Purpose</span>
            <strong><?= htmlspecialchars($data['purpose']); ?></strong>
        </div>
    </div>

    <div class="super-form-grid">
        <label>
            <span>Estimated Time</span>
            <input type="time" name="esttime" min="09:00" max="18:00">
        </label>
        <label>
            <span>Status</span>
            <select name="status" data-super-status required>
                <option value="Partially Approved">Partially Approved</option>
                <option value="Declined">Declined</option>
            </select>
        </label>
        <label>
            <span>Confirmed By</span>
            <select name="confirmed_by" required>
                <option>RUBY CASAS</option>
            </select>
        </label>
        <label class="wide" data-super-decline-reason hidden>
            <span>Decline Reason</span>
            <textarea name="decline_reason" rows="3" placeholder="Reason for declining this request"></textarea>
        </label>
    </div>

    <footer class="super-modal-actions">
        <button type="button" class="btn btn-secondary" data-close-super-detail>Close</button>
        <button type="submit" name="approve_req" class="btn btn-primary" data-super-approve>Approve Request</button>
        <button type="submit" name="decline_req" class="btn btn-danger" data-super-decline hidden>Decline Request</button>
    </footer>
</form>
