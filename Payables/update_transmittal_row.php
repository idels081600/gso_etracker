<?php
require_once 'transmit_db.php';
header('Content-Type: application/json');

function log_error($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/transmittal_error.log');
}

function sanitize_amount($amount) {
    // Remove all characters except digits, commas, and periods
    $amount = preg_replace('/[^\d,.]/', '', $amount);
    
    // Handle different number formats
    if (empty($amount)) {
        return '0.00';
    }
    
    // Count commas and periods to determine format
    $comma_count = substr_count($amount, ',');
    $period_count = substr_count($amount, '.');
    
    // If no separators, it's a simple number
    if ($comma_count === 0 && $period_count === 0) {
        return number_format(floatval($amount), 2, '.', '');
    }
    
    // If only periods, could be thousands separator or decimal
    if ($comma_count === 0 && $period_count === 1) {
        $parts = explode('.', $amount);
        // If last part has 1-2 digits, it's likely decimal
        if (strlen($parts[1]) <= 2) {
            return number_format(floatval($amount), 2, '.', '');
        } else {
            // Treat period as thousands separator
            $amount = str_replace('.', '', $amount);
            return number_format(floatval($amount), 2, '.', '');
        }
    }
    
    // If only commas, could be thousands separator or decimal (European style)
    if ($comma_count === 1 && $period_count === 0) {
        $parts = explode(',', $amount);
        // If last part has 1-2 digits, it's likely decimal (European style)
        if (strlen($parts[1]) <= 2) {
            $amount = str_replace(',', '.', $amount);
            return number_format(floatval($amount), 2, '.', '');
        } else {
            // Treat comma as thousands separator
            $amount = str_replace(',', '', $amount);
            return number_format(floatval($amount), 2, '.', '');
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
            return number_format(floatval($clean_amount), 2, '.', '');
        }
    } else if ($last_comma_pos > $last_period_pos) {
        // Comma is decimal separator (European style)
        $decimal_part = substr($amount, $last_comma_pos + 1);
        $integer_part = substr($amount, 0, $last_comma_pos);
        $integer_part = str_replace([',', '.'], '', $integer_part);
        
        if (strlen($decimal_part) <= 2) {
            $clean_amount = $integer_part . '.' . $decimal_part;
            return number_format(floatval($clean_amount), 2, '.', '');
        }
    }
    
    // Fallback: remove all separators and treat as whole number
    $amount = str_replace([',', '.'], '', $amount);
    return number_format(floatval($amount), 2, '.', '');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Validate required fields
$required = [
    'id',
    'ib_no',
    'project_name',
    'office',
    'received_by',
    'winning_bidders',
    'amount',
    'NOA_no',
    'COA_date',
    'notice_proceed',
    'deadline',
    'transmittal_type'
];

foreach ($required as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
        exit;
    }
}

$id = intval($_POST['id']);
$notice_proceed_date = $_POST['notice_proceed'];
$deadline_raw = $_POST['deadline'];

if (is_numeric($deadline_raw)) {
    $days = intval($deadline_raw);
    $deadline_date = date('Y-m-d', strtotime($notice_proceed_date . ' + ' . ($days - 1) . ' days'));
} else {
    $days = $deadline_raw;
    $deadline_date = $deadline_raw;
}

// Sanitize and format the amount
$sanitized_amount = sanitize_amount($_POST['amount']);

$stmt = $conn->prepare("UPDATE transmittal_bac SET ib_no=?, project_name=?, office=?, received_by=?, winning_bidders=?, amount=?, NOA_no=?, COA_date=?, notice_proceed=?, deadline=?, transmittal_type=?, calendar_days=? WHERE id=?");

if (!$stmt) {
    log_error('Prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    "ssssssssssssi",
    $_POST['ib_no'],
    $_POST['project_name'],
    $_POST['office'],
    $_POST['received_by'],
    $_POST['winning_bidders'],
    $sanitized_amount,
    $_POST['NOA_no'],
    $_POST['COA_date'],
    $_POST['notice_proceed'],
    $deadline_date,
    $_POST['transmittal_type'],
    $days,
    $id
);

if (!$stmt->execute()) {
    log_error('Execute failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$stmt->close();
echo json_encode(['success' => true]);
?>