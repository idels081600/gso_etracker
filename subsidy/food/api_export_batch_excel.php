<?php
session_start();
require_once 'db_fuel.php';

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('Unauthorized');
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
                b.status,
                b.created_by,
                b.created_at,
                b.redeemed_at,
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
    die('Batch not found');
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
                vc.claimant_name,
                vc.claim_date
            FROM food_redemption_items bi
            LEFT JOIN food_voucher_claims vc ON bi.voucher_id = vc.id
            WHERE bi.batch_id = $batch_id
            ORDER BY bi.id ASC";

$items_result = mysqli_query($conn, $items_sql);

$items = [];
if ($items_result) {
    while ($row = mysqli_fetch_assoc($items_result)) {
        $items[] = $row;
    }
}

// Set headers for CSV download
$filename = 'Batch_' . $batch['batch_number'] . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Batch header info
fputcsv($output, ['BATCH DETAILS']);
fputcsv($output, ['Batch Number', $batch['batch_number']]);
fputcsv($output, ['Vendor Name', $batch['vendor_name']]);
fputcsv($output, ['Vendor Serial', $batch['vendor_serial']]);
fputcsv($output, ['Area', $batch['area']]);
fputcsv($output, ['Total Vouchers', $batch['total_vouchers']]);
fputcsv($output, ['Total Amount', number_format($batch['total_amount'], 2)]);
fputcsv($output, ['Status', ucfirst($batch['status'])]);
fputcsv($output, ['Created By', $batch['created_by']]);
fputcsv($output, ['Created At', $batch['created_at']]);
fputcsv($output, []);

// Voucher items header
fputcsv($output, ['VOUCHER ITEMS']);
fputcsv($output, ['#', 'Voucher Number', 'Beneficiary Code', 'Beneficiary Name', 'Claimant Name', 'Claim Date', 'Amount']);

$row_num = 1;
foreach ($items as $item) {
    fputcsv($output, [
        $row_num,
        $item['voucher_number'],
        $item['beneficiary_code'] ?: 'N/A',
        $item['beneficiary_name'] ?: 'N/A',
        $item['claimant_name'] ?: 'N/A',
        $item['claim_date'] ?: 'N/A',
        number_format($item['amount'], 2)
    ]);
    $row_num++;
}

fputcsv($output, []);
fputcsv($output, ['TOTAL', '', '', '', '', '', number_format($batch['total_amount'], 2)]);

fclose($output);
exit();
?>