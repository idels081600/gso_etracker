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

// Check if all required POST parameters are set
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset(
        $_POST['rfq_no'],
        $_POST['supplier'],
        $_POST['description'],
        $_POST['amount'],
        $_POST['date_received'],
        $_POST['office'],
        $_POST['received_by']
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
    $amount = isset($_POST['amount']) ? $_POST['amount'] : '';
    $stmt = $conn->prepare("INSERT INTO PO_sap (
        RFQ_no, supplier, description, amount, date_received, office, received_by, delete_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
    if (!$stmt) {
        log_error('Prepare failed: ' . $conn->error);
        $error_message = 'An error occurred while preparing the statement.';
    } else { 
        $stmt->bind_param(
            "sssdsss",
            $_POST['rfq_no'],
            $_POST['supplier'],
            $_POST['description'],
            $amount,
            $date_received,
            $_POST['office'],
            $_POST['received_by']
        );

        if (!$stmt->execute()) {
            log_error('Execute failed: ' . $stmt->error);
            $error_message = 'An error occurred while saving the data.';
        }

        $stmt->close();
    }

    if (!$error_message) {
            header('Location: Po_sap.php');
            exit();
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log specific missing POST parameters
    $missing_params = [];
    if (!isset($_POST['rfq_no'])) $missing_params[] = 'RFQ_no';
    if (!isset($_POST['supplier'])) $missing_params[] = 'supplier';
    if (!isset($_POST['description'])) $missing_params[] = 'description';
    if (!isset($_POST['amount'])) $missing_params[] = 'amount';
    if (!isset($_POST['date_received'])) $missing_params[] = 'date_received';
    if (!isset($_POST['office'])) $missing_params[] = 'office';
    if (!isset($_POST['received_by'])) $missing_params[] = 'received_by';

    log_error('Missing POST parameters: ' . implode(', ', $missing_params));

    $error_message = 'Invalid form submission. Please check your input. Missing parameters: ' . htmlspecialchars(implode(', ', $missing_params));
}

if ($error_message) {
    echo '<div style="color:red; font-weight:bold; padding:20px;">' . htmlspecialchars($error_message) . '<br>Check the error log for details.</div>';
}
?>