<?php
session_start();
require_once '../dbh.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo '<div class="empty">Please sign in again.</div>';
    exit();
}

if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'TCWS Employee') {
    http_response_code(403);
    echo '<div class="empty">You do not have access to this record.</div>';
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
    echo '<div class="empty">Employee record not found.</div>';
    exit();
}

function display_time(?string $value): string
{
    if (!$value || $value === '00:00:00' || $value === '00:00') {
        return '<span class="text-muted">Not recorded</span>';
    }

    return htmlspecialchars(date('g:i A', strtotime($value)));
}
?>
<div class="track-detail-grid">
    <div class="track-detail-item">
        <span>Name</span>
        <strong><?= htmlspecialchars($data['name']); ?></strong>
    </div>
    <div class="track-detail-item">
        <span>Position</span>
        <strong><?= htmlspecialchars($data['position']); ?></strong>
    </div>
    <div class="track-detail-item">
        <span>Date</span>
        <strong><?= htmlspecialchars($data['date']); ?></strong>
    </div>
    <div class="track-detail-item">
        <span>Status</span>
        <strong><?= htmlspecialchars($data['status1'] ?: $data['Status']); ?></strong>
    </div>
    <div class="track-detail-item wide">
        <span>Destination</span>
        <strong><?= htmlspecialchars($data['dest2'] ?: $data['destination']); ?></strong>
    </div>
    <div class="track-detail-item wide">
        <span>Purpose</span>
        <strong><?= htmlspecialchars($data['purpose']); ?></strong>
    </div>
    <div class="track-detail-item">
        <span>Departure</span>
        <strong><?= display_time($data['timedept']); ?></strong>
    </div>
    <div class="track-detail-item">
        <span>Estimated Return</span>
        <strong><?= display_time($data['esttime']); ?></strong>
    </div>
    <div class="track-detail-item">
        <span>Actual Return</span>
        <strong><?= display_time($data['time_returned']); ?></strong>
    </div>
    <div class="track-detail-item">
        <span>Type</span>
        <strong><?= htmlspecialchars($data['typeofbusiness']); ?></strong>
    </div>
    <div class="track-detail-item">
        <span>Confirmed By</span>
        <strong><?= htmlspecialchars($data['confirmed_by']); ?></strong>
    </div>
    <div class="track-detail-item">
        <span>Approval Status</span>
        <strong><?= htmlspecialchars($data['Status']); ?></strong>
    </div>
    <div class="track-detail-item wide">
        <span>Remarks</span>
        <strong><?= htmlspecialchars($data['remarks'] ?: 'No remarks yet.'); ?></strong>
    </div>
</div>
