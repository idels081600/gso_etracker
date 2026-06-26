<?php
session_start();
require_once '../dbh.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo '<div class="alert alert-warning mb-0">Please sign in again.</div>';
    exit();
}

if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
    http_response_code(403);
    echo '<div class="alert alert-danger mb-0">You do not have access to this request.</div>';
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(422);
    echo '<div class="alert alert-danger mb-0">Invalid request id.</div>';
    exit();
}

$stmt = $conn->prepare("SELECT * FROM `request` WHERE `id` = ? AND (`Role` = 'Employee' OR `Role` = 'TCWS Employee') LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    http_response_code(404);
    echo '<div class="alert alert-warning mb-0">Request not found.</div>';
    exit();
}

$isPersonal = ($data['typeofbusiness'] ?? '') === 'Personal';
$confirmedBy = $_SESSION['pay_name'] ?? $_SESSION['username'];
?>
<form action="../code.php" method="POST" class="request-modal-form">
    <input type="hidden" name="data_id" value="<?= (int) $data['id']; ?>">
    <input type="hidden" name="esttime" value="<?= htmlspecialchars(date('H:i')); ?>">

    <div class="request-detail-grid">
        <div>
            <span>Name</span>
            <strong><?= htmlspecialchars($data['name']); ?></strong>
        </div>
        <div>
            <span>Position</span>
            <strong><?= htmlspecialchars($data['position']); ?></strong>
        </div>
        <div>
            <span>Date</span>
            <strong><?= htmlspecialchars($data['date']); ?></strong>
        </div>
        <div>
            <span>Type</span>
            <strong><?= htmlspecialchars($data['typeofbusiness']); ?></strong>
        </div>
        <div class="wide">
            <span>Destination</span>
            <strong><?= htmlspecialchars($data['destination']); ?></strong>
        </div>
        <div class="wide">
            <span>Purpose</span>
            <strong><?= htmlspecialchars($data['purpose']); ?></strong>
        </div>
    </div>

    <div class="request-modal-fields">
        <label data-single-time <?= $isPersonal ? 'data-personal-hidden hidden' : ''; ?>>
            <span>Hours</span>
            <input type="number" class="form-control" name="fix_hours" min="0" max="<?= $isPersonal ? 0 : 4; ?>" value="0">
        </label>
        <label data-single-time>
            <span>Minutes</span>
            <input type="number" class="form-control" name="fix_minutes" min="0" max="<?= $isPersonal ? 30 : 59; ?>" value="20">
        </label>
        <label>
            <span>Confirmed By</span>
            <select class="form-control" name="confirmed_by" required>
                <option <?= $confirmedBy === 'CAGULADA RENE ART' ? 'selected' : ''; ?>>CAGULADA RENE ART</option>
                <option <?= $confirmedBy === 'CASAS RUBY' ? 'selected' : ''; ?>>CASAS RUBY</option>
                <?php if (!in_array($confirmedBy, ['CAGULADA RENE ART', 'CASAS RUBY'], true)): ?>
                    <option selected><?= htmlspecialchars($confirmedBy); ?></option>
                <?php endif; ?>
            </select>
        </label>
        <label>
            <span>Status</span>
            <select class="form-control" name="status" data-single-status required>
                <option value="Partially Approved">Partially Approved</option>
                <option value="Declined">Declined</option>
            </select>
        </label>
        <label class="wide" data-single-decline-reason hidden>
            <span>Decline Reason</span>
            <textarea class="form-control" name="decline_reason" rows="3" placeholder="Reason for declining this request"></textarea>
        </label>
    </div>

    <div class="request-modal-actions">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="submit" name="approve_req_r" class="btn btn-success" data-single-approve>Approve Request</button>
        <button type="submit" name="decline_req_r" class="btn btn-danger" data-single-decline hidden>Decline Request</button>
    </div>
</form>
