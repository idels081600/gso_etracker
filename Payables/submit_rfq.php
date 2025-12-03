<?php
require_once 'transmit_db.php';

function log_error($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/transmittal_error.log');
}

function sanitize_amount($amount) {
    // Remove all characters except digits, commas, and periods
    $amount = preg_replace('/[^\d,.]/', '', $amount);
    
    // Handle different number formats
    if (empty($amount)) {
        return 0.00;
    }
    
    // Count commas and periods to determine format
    $comma_count = substr_count($amount, ',');
    $period_count = substr_count($amount, '.');
    
    // If no separators, it's a simple number
    if ($comma_count === 0 && $period_count === 0) {
        return floatval($amount);
    }
    
    // If only periods, could be thousands separator or decimal
    if ($comma_count === 0 && $period_count === 1) {
        $parts = explode('.', $amount);
        // If last part has 1-2 digits, it's likely decimal
        if (strlen($parts[1]) <= 2) {
            return floatval($amount);
        } else {
            // Treat period as thousands separator
            $amount = str_replace('.', '', $amount);
            return floatval($amount);
        }
    }
    
    // If only commas, could be thousands separator or decimal (European style)
    if ($comma_count === 1 && $period_count === 0) {
        $parts = explode(',', $amount);
        // If last part has 1-2 digits, it's likely decimal (European style)
        if (strlen($parts[1]) <= 2) {
            $amount = str_replace(',', '.', $amount);
            return floatval($amount);
        } else {
            // Treat comma as thousands separator
            $amount = str_replace(',', '', $amount);
            return floatval($amount);
        }
    }
    
    // Mixed format: assume last separator is decimal
    $last_comma_pos = strrpos($amount, ',');
    $last_period_pos = strrpos($amount, '.');
    
    if ($last_period_pos > $last_comma_pos) {
        // Period is decimal separator
        $decimal_part = substr($amount, $last_period_pos + 1);
        $integer_part = substr($amount, 0, $last_period_pos);
        $integer_part = str_replace([',', '.'], '', $integer_part);
        
        if (strlen($decimal_part) <= 2) {
            $clean_amount = $integer_part . '.' . $decimal_part;
            return floatval($clean_amount);
        }
    } else if ($last_comma_pos > $last_period_pos) {
        // Comma is decimal separator (European style)
        $decimal_part = substr($amount, $last_comma_pos + 1);
        $integer_part = substr($amount, 0, $last_comma_pos);
        $integer_part = str_replace([',', '.'], '', $integer_part);
        
        if (strlen($decimal_part) <= 2) {
            $clean_amount = $integer_part . '.' . $decimal_part;
            return floatval($clean_amount);
        }
    }
    
    // Fallback: remove all separators and treat as whole number
    $amount = str_replace([',', '.'], '', $amount);
    return floatval($amount);
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
        $_POST['received_by'],
        $_POST['status']
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
    
    // Sanitize and format the amount
    $amount = isset($_POST['amount']) ? sanitize_amount($_POST['amount']) : 0.00;
    
    $stmt = $conn->prepare("INSERT INTO PO_sap (
        RFQ_no, supplier, description, amount, date_received, office, received_by, status, delete_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
    
    if (!$stmt) {
        log_error('Prepare failed: ' . $conn->error);
        $error_message = 'An error occurred while preparing the statement.';
    } else {
        $stmt->bind_param(
            "sssdsssi",
            $_POST['rfq_no'],
            $_POST['supplier'],
            $_POST['description'],
            $amount,
            $date_received,
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
    // Log specific missing POST parameters
    $missing_params = [];
    if (!isset($_POST['rfq_no'])) $missing_params[] = 'RFQ_no';
    if (!isset($_POST['supplier'])) $missing_params[] = 'supplier';
    if (!isset($_POST['description'])) $missing_params[] = 'description';
    if (!isset($_POST['amount'])) $missing_params[] = 'amount';
    if (!isset($_POST['date_received'])) $missing_params[] = 'date_received';
    if (!isset($_POST['office'])) $missing_params[] = 'office';
    if (!isset($_POST['received_by'])) $missing_params[] = 'received_by';
    if (!isset($_POST['status'])) $missing_params[] = 'status';

    log_error('Missing POST parameters: ' . implode(', ', $missing_params));

    $error_message = 'Invalid form submission. Please check your input. Missing parameters: ' . htmlspecialchars(implode(', ', $missing_params));
}

if ($error_message) {
    echo '<div style="color:red; font-weight:bold; padding:20px;">' . htmlspecialchars($error_message) . '<br>Check the error log for details.</div>';
}
?>
