<?php
/** @var mysqli $conn */
session_start();
$conn = require(__DIR__ . '/config/database.php');
require_once 'send_vendor_claim_api.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

// Security check
// if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit();
// }

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Also support form-data for Postman testing
if (!$input && !empty($_POST)) {
    $input = $_POST;
}

if (!$input) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid input'
    ]);
    exit();
}

// Validate required fields
$vendor_serial = isset($input['vendor_serial']) ? trim($input['vendor_serial']) : '';
$voucher_ids = isset($input['voucher_ids']) && is_array($input['voucher_ids']) ? $input['voucher_ids'] : [];

if (empty($vendor_serial)) {
    echo json_encode(['success' => false, 'message' => 'Vendor serial is required']);
    exit();
}

if (empty($voucher_ids)) {
    echo json_encode(['success' => false, 'message' => 'No vouchers selected']);
    exit();
}

// Get vendor info
$escaped_serial = mysqli_real_escape_string($conn, $vendor_serial);
$vendor_sql = "SELECT id, vendor_serial, vendor_name, area FROM food_vendors WHERE vendor_serial = '$escaped_serial' LIMIT 1";
$vendor_result = mysqli_query($conn, $vendor_sql);

if (!$vendor_result || mysqli_num_rows($vendor_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    exit();
}

$vendor = mysqli_fetch_assoc($vendor_result);
$vendor_id = (int)$vendor['id'];

// Get personnel_id from session (default to 0 if not set)
$personnel_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$created_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';

// Validate all voucher IDs exist and are available for redemption
$voucher_id_list = array_map(function($id) use ($conn) {
    return "'" . mysqli_real_escape_string($conn, trim($id)) . "'";
}, $voucher_ids);
$voucher_id_str = implode(',', $voucher_id_list);

$check_sql = "SELECT id, beneficiary_id, voucher_number, claimant_name, is_verified, is_redeemed, batch_id 
              FROM food_voucher_claims 
              WHERE id IN ($voucher_id_str)";
$check_result = mysqli_query($conn, $check_sql);

if (!$check_result) {
    echo json_encode(['success' => false, 'message' => 'Database error checking vouchers']);
    exit();
}

$valid_vouchers = [];
$invalid_vouchers = [];

while ($row = mysqli_fetch_assoc($check_result)) {
    $voucher_id = $row['id'];

    if ($row['is_verified'] != 1) {
        $invalid_vouchers[] = ['id' => $voucher_id, 'reason' => 'Not verified'];
        continue;
    }

    if ($row['is_redeemed'] == 1) {
        $invalid_vouchers[] = ['id' => $voucher_id, 'reason' => 'Already redeemed'];
        continue;
    }

    if ($row['batch_id'] !== null) {
        $invalid_vouchers[] = ['id' => $voucher_id, 'reason' => 'Already in a batch'];
        continue;
    }

    $valid_vouchers[] = [
        'id' => $voucher_id,
        'beneficiary_id' => (int)$row['beneficiary_id'],
        'voucher_number' => (int)$row['voucher_number'],
        'claimant_name' => $row['claimant_name']
    ];
}

if (count($valid_vouchers) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No valid vouchers available for redemption',
        'invalid_vouchers' => $invalid_vouchers
    ]);
    exit();
}

// Generate batch number: YYYYMMDD### pure numbers only, no separators
$date_prefix = date('Ymd');
$count_sql = "SELECT COUNT(*) as count FROM food_redemption_batches WHERE batch_number LIKE '$date_prefix%'";
$count_result = mysqli_query($conn, $count_sql);
$count_row = mysqli_fetch_assoc($count_result);
$sequence = (int)$count_row['count'] + 1;
$batch_number = sprintf('%s%03d', $date_prefix, $sequence);

$total_vouchers = count($valid_vouchers);
$total_amount = $total_vouchers * 200.00;

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Insert batch record
    $insert_batch_sql = "INSERT INTO food_redemption_batches 
        (batch_number, vendor_id, personnel_id, total_vouchers, total_amount, status, created_by, created_at, redeemed_at, remarks) 
        VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW(), NULL)";

    $stmt = mysqli_prepare($conn, $insert_batch_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare batch insert: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 'siidds', $batch_number, $vendor_id, $personnel_id, $total_vouchers, $total_amount, $created_by);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to insert batch: ' . mysqli_stmt_error($stmt));
    }

    $batch_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Insert batch items and update voucher claims
    $insert_item_sql = "INSERT INTO food_redemption_items 
        (batch_id, voucher_id, amount, beneficiary_name, beneficiary_code, voucher_number) 
        VALUES (?, ?, 200.00, ?, ?, ?)";

    $item_stmt = mysqli_prepare($conn, $insert_item_sql);
    if (!$item_stmt) {
        throw new Exception('Failed to prepare item insert: ' . mysqli_error($conn));
    }

    $update_voucher_sql = "UPDATE food_voucher_claims 
        SET is_redeemed = 1, batch_id = ? 
        WHERE id = ?";

    $update_stmt = mysqli_prepare($conn, $update_voucher_sql);
    if (!$update_stmt) {
        throw new Exception('Failed to prepare voucher update: ' . mysqli_error($conn));
    }

    foreach ($valid_vouchers as $voucher) {
        // Get beneficiary info
        $beneficiary_id = $voucher['beneficiary_id'];
        $beneficiary_sql = "SELECT full_name, beneficiary_code FROM food_beneficiaries WHERE id = $beneficiary_id LIMIT 1";
        $beneficiary_result = mysqli_query($conn, $beneficiary_sql);
        $beneficiary = mysqli_fetch_assoc($beneficiary_result);

        $beneficiary_name = $beneficiary ? $beneficiary['full_name'] : $voucher['claimant_name'];
        $beneficiary_code = $beneficiary ? $beneficiary['beneficiary_code'] : '';
        $voucher_number = $voucher['voucher_number'];
        $voucher_id = $voucher['id'];

        // Insert batch item
        mysqli_stmt_bind_param($item_stmt, 'iissi', $batch_id, $voucher_id, $beneficiary_name, $beneficiary_code, $voucher_number);
        if (!mysqli_stmt_execute($item_stmt)) {
            throw new Exception('Failed to insert batch item: ' . mysqli_stmt_error($item_stmt));
        }

        // Update voucher claim
        mysqli_stmt_bind_param($update_stmt, 'ii', $batch_id, $voucher_id);
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception('Failed to update voucher: ' . mysqli_stmt_error($update_stmt));
        }
    }

    mysqli_stmt_close($item_stmt);
    mysqli_stmt_close($update_stmt);

    // Collect voucher numbers for API payload
    $voucher_numbers = array_column($valid_vouchers, 'voucher_number');
    
    // Safe external API call with output buffering (prevents broken JSON)
    ob_start();
    try {
        // Send batch details to Food Voucher Vendor Claim API
        $apiResult = sendVendorClaimAPI($vendor, $batch_number, $total_amount, $voucher_numbers);
    } catch (Exception $apiException) {
        $apiResult = 'API call failed: ' . $apiException->getMessage();
    }
    // Discard any output/warnings from API call
    ob_end_clean();

    // Commit transaction ONLY AFTER everything is ready
    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Batch created successfully',
        'batch' => [
            'batch_id' => $batch_id,
            'batch_number' => $batch_number,
            'vendor_name' => $vendor['vendor_name'],
            'vendor_serial' => $vendor['vendor_serial'],
            'total_vouchers' => $total_vouchers,
            'total_amount' => $total_amount,
            'created_at' => date('Y-m-d H:i:s')
        ],
        'invalid_vouchers' => $invalid_vouchers,
        'vendor_api_status' => $apiResult
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();
