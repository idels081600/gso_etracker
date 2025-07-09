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
    // Use date_received from input if provided, else use current date and time
    if (!empty($_POST['date_received'])) {
        $date_received = $_POST['date_received'];
        if (strlen($date_received) === 10) {
            $date_received .= ' 00:00:00';
        }
    } else {
        $date_received = date('Y-m-d H:i:s');
    }

    $notice_proceed_date = $_POST['notice_proceed'];
    $deadline_raw = $_POST['deadline'];
    if (is_numeric($deadline_raw)) {
        $days = intval($deadline_raw);
        $deadline_date = date('Y-m-d', strtotime($notice_proceed_date . ' + ' . ($days - 1) . ' days'));
    } else {
        $days = $deadline_raw;
        $deadline_date = $deadline_raw;
    }

    $amount = isset($_POST['amount']) ? $_POST['amount'] : '';

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