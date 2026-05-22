<?php
define('PAYABLES_API', true);
require_once 'auth_payables.php';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';

function log_error($message) {
    payables_log_error($message);
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    payables_verify_csrf_token();
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !payables_require_post_fields([
        'transmittal_type',
        'ib_no',
        'project_name',
        'office',
        'received_by',
        'winning_bidders',
        'NOA_no',
        'COA_date',
        'notice_proceed',
        'deadline',
    ])
) {
    $date_received = payables_post_date_or_now('date_received');
    $notice_proceed_date = payables_normalize_date_or_empty($_POST['notice_proceed']);
    $coaDate = payables_normalize_date_or_empty($_POST['COA_date']);
    [$days, $deadline_date] = payables_calculate_deadline($notice_proceed_date, $_POST['deadline']);

    $amount = payables_sanitize_amount($_POST['amount'] ?? '');

    $stmt = $conn->prepare("INSERT INTO transmittal_bac (
        ib_no, project_name, date_received, office, received_by, winning_bidders, amount, NOA_no, COA_date, notice_proceed, deadline, transmittal_type, calendar_days, delete_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    
    if (!$stmt) {
        log_error('Prepare failed: ' . $conn->error);
        $error_message = 'An error occurred while preparing the statement.';
    } else {
        $stmt->bind_param(
            "sssssssssssss",
            $_POST['ib_no'],
            $_POST['project_name'],
            $date_received,
            $_POST['office'],
            $_POST['received_by'],
            $_POST['winning_bidders'],
            $amount,
            $_POST['NOA_no'],
            $coaDate,
            $notice_proceed_date,
            $deadline_date,
            $_POST['transmittal_type'],
            $days
        );
        
        if (!$stmt->execute()) {
            log_error('Execute failed: ' . $stmt->error);
            $error_message = 'An error occurred while saving the data.';
        }
        $stmt->close();
    }
    
    if (!$error_message) {
        header('Location: transmittal_bac.php');
        exit();
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $missing = payables_require_post_fields([
        'transmittal_type',
        'ib_no',
        'project_name',
        'office',
        'received_by',
        'winning_bidders',
        'NOA_no',
        'COA_date',
        'notice_proceed',
        'deadline',
    ]);
    log_error('Invalid POST data. Missing: ' . implode(', ', $missing));
    $error_message = 'Invalid form submission. Please check your input.';
}

if ($error_message) {
    echo '<div style="color:red; font-weight:bold; padding:20px;">' . htmlspecialchars($error_message) . '<br>Check the error log for details.</div>';
}
?>
