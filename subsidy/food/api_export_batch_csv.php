<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit;
}

// Validate batch_id parameter
if (!isset($_GET['batch_id']) || empty(trim($_GET['batch_id']))) {
    die('Batch ID is required');
}

$batch_id = intval($_GET['batch_id']);

// Get batch header info
$batch_sql = "SELECT 
                b.id,
                b.batch_number,
                b.total_vouchers,
                b.total_amount,
                b.created_at,
                b.redeemer,
                v.vendor_serial,
                v.vendor_name,
                v.area as market,
                v.stall_no
            FROM food_redemption_batches b
            LEFT JOIN food_vendors v ON b.vendor_id = v.id
            WHERE b.id = $batch_id
            LIMIT 1";

$batch_result = mysqli_query($conn, $batch_sql);

if (!$batch_result || mysqli_num_rows($batch_result) === 0) {
    die('Batch not found');
}

$batch = mysqli_fetch_assoc($batch_result);

// Get batch items in selection order
$items_sql = "SELECT 
                bi.beneficiary_code,
                bi.voucher_number,
                bi.beneficiary_name,
                bi.amount,
                bi.selection_order
            FROM food_redemption_items bi
            WHERE bi.batch_id = $batch_id
            ORDER BY bi.selection_order ASC";

$items_result = mysqli_query($conn, $items_sql);

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Batch_' . $batch['batch_number'] . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Batch header information
fputcsv($output, ['Batch Number', $batch['batch_number']]);
fputcsv($output, ['Market', $batch['market']]);
fputcsv($output, ['Stall No', $batch['stall_no']]);
fputcsv($output, ['Vendor Name', $batch['vendor_name']]);
fputcsv($output, ['Vendor Serial', $batch['vendor_serial']]);
fputcsv($output, ['Redeemed By', $batch['redeemer']]);
fputcsv($output, ['Date Created', $batch['created_at']]);
fputcsv($output, ['Total Vouchers', $batch['total_vouchers']]);
fputcsv($output, ['Total Amount', $batch['total_amount']]);
fputcsv($output, []); // Empty line

// Vouchers table header
fputcsv($output, ['No.', 'Voucher Code', 'Beneficiary Name', 'Amount']);

// Voucher items
$count = 1;
while ($item = mysqli_fetch_assoc($items_result)) {
    $voucher_code = ($item['beneficiary_code'] ? $item['beneficiary_code'] : 'N/A') . ' - 00' . $item['voucher_number'];
    fputcsv($output, [
        $count,
        $voucher_code,
        $item['beneficiary_name'],
        $item['amount']
    ]);
    $count++;
}

fclose($output);
exit();
?>