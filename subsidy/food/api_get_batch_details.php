<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF);

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validate batch_id parameter
if (!isset($_GET['batch_id']) || empty(trim($_GET['batch_id']))) {
    echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
    exit();
}

$batch_id = intval($_GET['batch_id']);

// Get batch header info
$batch_sql = "SELECT 
                b.id,
                b.batch_number,
                b.total_vouchers,
                b.total_amount,
                b.status,
                b.created_by,
                b.created_at,
                b.redeemed_at,
                b.redeemer,
                b.remarks,
                v.vendor_serial,
                v.vendor_name,
                v.area
            FROM food_redemption_batches b
            LEFT JOIN food_vendors v ON b.vendor_id = v.id
            WHERE b.id = $batch_id
            LIMIT 1";

$batch_result = mysqli_query($conn, $batch_sql);

if (!$batch_result || mysqli_num_rows($batch_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Batch not found']);
    exit();
}

$batch = mysqli_fetch_assoc($batch_result);

// Get batch items
$items_sql = "SELECT 
                bi.id,
                bi.voucher_id,
                bi.amount,
                bi.beneficiary_name,
                bi.beneficiary_code,
                bi.voucher_number,
                bi.created_at,
                vc.claimant_name,
                vc.claim_date,
                vc.e_signature
            FROM food_redemption_items bi
            LEFT JOIN food_voucher_claims vc ON bi.voucher_id = vc.id
            WHERE bi.batch_id = $batch_id
            ORDER BY bi.id ASC";

$items_result = mysqli_query($conn, $items_sql);

$items = [];
if ($items_result) {
    while ($row = mysqli_fetch_assoc($items_result)) {
        $items[] = [
            'id' => (int)$row['id'],
            'voucher_id' => (int)$row['voucher_id'],
            'amount' => (float)$row['amount'],
            'beneficiary_name' => $row['beneficiary_name'],
            'beneficiary_code' => $row['beneficiary_code'],
            'voucher_number' => (int)$row['voucher_number'],
            'claimant_name' => $row['claimant_name'],
            'claim_date' => $row['claim_date'],
            'e_signature' => $row['e_signature'],
            'added_at' => $row['created_at']
        ];
    }
}

echo json_encode([
    'success' => true,
    'batch' => [
        'id' => (int)$batch['id'],
        'batch_number' => $batch['batch_number'],
        'total_vouchers' => (int)$batch['total_vouchers'],
        'total_amount' => (float)$batch['total_amount'],
        'status' => $batch['status'],
        'created_by' => $batch['created_by'],
        'created_at' => $batch['created_at'],
        'redeemed_at' => $batch['redeemed_at'],
        'redeemer' => $batch['redeemer'],
        'remarks' => $batch['remarks'],
        'vendor' => [
            'vendor_serial' => $batch['vendor_serial'],
            'vendor_name' => $batch['vendor_name'],
            'area' => $batch['area']
        ]
    ],
    'items' => $items,
    'item_count' => count($items)
]);

exit();
?>