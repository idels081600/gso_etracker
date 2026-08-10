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
        'rfq_no',
        'supplier',
        'description',
        'amount',
        'office',
        'received_by',
        'status',
    ])
) {
    payables_ensure_date_column($conn, 'PO_sap', 'award');
    $date_received = payables_post_date_or_now('date_received');
    $award = payables_post_nullable_date('award');
    
    $amount = (float)payables_sanitize_amount($_POST['amount'] ?? '');
    
    $stmt = $conn->prepare("INSERT INTO PO_sap (
        RFQ_no, supplier, description, amount, date_received, award, office, received_by, status, delete_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    
    if (!$stmt) {
        log_error('Prepare failed: ' . $conn->error);
        $error_message = 'An error occurred while preparing the statement.';
    } else {
        $stmt->bind_param(
            "sssdsssss",
            $_POST['rfq_no'],
            $_POST['supplier'],
            $_POST['description'],
            $amount,
            $date_received,
            $award,
            $_POST['office'],
            $_POST['received_by'],
            $_POST['status']
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
    $missing_params = payables_require_post_fields([
        'rfq_no',
        'supplier',
        'description',
        'amount',
        'office',
        'received_by',
        'status',
    ]);

    log_error('Missing POST parameters: ' . implode(', ', $missing_params));

    $error_message = 'Invalid form submission. Please check your input. Missing parameters: ' . htmlspecialchars(implode(', ', $missing_params));
}

if ($error_message) {
    echo '<div style="color:red; font-weight:bold; padding:20px;">' . htmlspecialchars($error_message) . '<br>Check the error log for details.</div>';
}
?>
