<?php
session_start();
require_once '../dbh.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo '<div class="empty">Please sign in again.</div>';
    exit();
}

if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Admin' || $_SESSION['role'] == 'TCWS Employee') {
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

$approverName = $_SESSION['pay_name'] ?? $_SESSION['username'];
$isPersonal = ($data['typeofbusiness'] ?? '') === 'Personal';
?>
<form action="../code.php" method="POST" class="request-detail-form">
    <input type="hidden" name="data_id" value="<?= (int) $data['id']; ?>">

    <div class="detail-grid">
        <div class="detail-item">
            <span>Name</span>
            <strong><?= htmlspecialchars($data['name']); ?></strong>
        </div>
        <div class="detail-item">
            <span>Position</span>
            <strong><?= htmlspecialchars($data['position']); ?></strong>
        </div>
        <div class="detail-item">
            <span>Date</span>
            <strong><?= htmlspecialchars($data['date']); ?></strong>
        </div>
        <div class="detail-item">
            <span>Type</span>
            <strong><?= htmlspecialchars($data['typeofbusiness']); ?></strong>
        </div>
        <div class="detail-item wide">
            <span>Destination</span>
            <strong><?= htmlspecialchars($data['destination']); ?></strong>
        </div>
        <div class="detail-item wide">
            <span>Purpose</span>
            <strong><?= htmlspecialchars($data['purpose']); ?></strong>
        </div>
    </div>

    <div class="form-grid">
        <label class="time-field" <?= $isPersonal ? 'hidden' : ''; ?>>
            <span>Hours</span>
            <input type="number" name="fix_hours" min="0" max="<?= $isPersonal ? 0 : 4; ?>" value="<?= $isPersonal ? 0 : 1; ?>">
        </label>
        <label class="time-field">
            <span>Minutes</span>
            <input type="number" name="fix_minutes" min="0" max="<?= $isPersonal ? 30 : 59; ?>" value="0">
        </label>
        <label>
            <span>Status</span>
            <select name="status" data-single-status required>
                <option value="Partially Approved">Partially Approved</option>
                <option value="Declined">Declined</option>
            </select>
        </label>
        <label>
            <span>Confirmed By</span>
            <input name="confirmed_by" value="<?= htmlspecialchars($approverName); ?>" required>
        </label>
        <label class="wide" data-single-decline-reason hidden>
            <span>Decline Reason</span>
            <textarea name="decline_reason" rows="3" placeholder="Reason for declining this request"></textarea>
        </label>
    </div>

    <footer class="modal-actions">
        <button type="button" class="btn btn-secondary" data-close-detail>Close</button>
        <button type="submit" name="approve_req_desk" class="btn btn-primary" data-single-approve>Approve Request</button>
        <button type="submit" name="decline_req_desk" class="btn btn-danger" data-single-decline hidden>Decline Request</button>
    </footer>
</form>
