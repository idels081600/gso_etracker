<?php
require_once 'transmit_db.php';

function log_error($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/transmittal_error.log');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_error('POST data: ' . print_r($_POST, true));
}

// Set timezone to Philippine time
if (!date_default_timezone_get() || date_default_timezone_get() !== 'Asia/Manila') {
    date_default_timezone_set('Asia/Manila');
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset(
        $_POST['transmittal_type'],
        $_POST['ib_no'],
        $_POST['project_name'],
        $_POST['office'],
        $_POST['received_by'],
        $_POST['winning_bidders'],
        $_POST['NOA_no'],
        $_POST['COA_date'],
        $_POST['notice_proceed'],
        $_POST['deadline']
    )
) {
    // Calculate deadline date
    $notice_proceed_date = $_POST['notice_proceed'];
    $days = intval($_POST['deadline']);
    $deadline_date = date('Y-m-d', strtotime($notice_proceed_date . ' + ' . ($days - 1) . ' days'));

    // Set date_received to current date and time
    $date_received = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO transmittal_bac (
        ib_no, project_name, date_received, office, received_by, winning_bidders, NOA_no, COA_date, notice_proceed, deadline, transmittal_type, calendar_days
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        log_error('Prepare failed: ' . $conn->error);
        $error_message = 'An error occurred while preparing the statement.';
    } else {
        $stmt->bind_param(
            "sssssssssssi",
            $_POST['ib_no'],
            $_POST['project_name'],
            $date_received,
            $_POST['office'],
            $_POST['received_by'],
            $_POST['winning_bidders'],
            $_POST['NOA_no'],
            $_POST['COA_date'],
            $_POST['notice_proceed'],
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
    log_error('Invalid POST data: ' . print_r($_POST, true));
    $error_message = 'Invalid form submission. Please check your input.';
}

if ($error_message) {
    echo '<div style="color:red; font-weight:bold; padding:20px;">' . htmlspecialchars($error_message) . '<br>Check the error log for details.</div>';
} 