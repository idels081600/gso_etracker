<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

// Security check
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit;
}

$export_all = isset($_GET['all']) && $_GET['all'] == '1';

if ($export_all) {
    $date_filter = "";
    $filename = "All_Redemptions_All_Dates.csv";
} else {
    $date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
    $date_filter = "WHERE DATE(b.created_at) = ?";
    $filename = "Daily_Redemption_" . $date . ".csv";
}

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header
if ($export_all) {
    fputcsv($output, ['ALL REDEMPTIONS REPORT - ALL DATES']);
} else {
    fputcsv($output, ['DAILY REDEMPTION REPORT']);
    fputcsv($output, ['Date', $date]);
}
fputcsv($output, []);

// Get all batches
$batch_sql = "SELECT 
                b.id,
                b.batch_number,
                b.total_vouchers,
                b.total_amount,
                b.created_at,
                b.redeemer,
                v.vendor_name,
                v.area as market,
                v.stall_no
              FROM food_redemption_batches b
              LEFT JOIN food_vendors v ON b.vendor_id = v.id
              $date_filter
              ORDER BY b.created_at ASC";

$stmt = mysqli_prepare($conn, $batch_sql);
if (!$export_all) {
    mysqli_stmt_bind_param($stmt, 's', $date);
}
mysqli_stmt_execute($stmt);
$batch_result = mysqli_stmt_get_result($stmt);

$grand_total_vouchers = 0;
$grand_total_amount = 0;

while ($batch = mysqli_fetch_assoc($batch_result)) {
    // Batch header
    fputcsv($output, ['=== BATCH:', $batch['batch_number'], '==']);
    fputcsv($output, ['Market', $batch['market']]);
    fputcsv($output, ['Stall No', $batch['stall_no']]);
    fputcsv($output, ['Vendor', $batch['vendor_name']]);
    fputcsv($output, ['Redeemed By', $batch['redeemer']]);
    fputcsv($output, ['Time', $batch['created_at']]);
    fputcsv($output, ['Total Vouchers', $batch['total_vouchers']]);
    fputcsv($output, ['Total Amount', $batch['total_amount']]);
    fputcsv($output, []);
    
    // Voucher items
    fputcsv($output, ['No.', 'Voucher Code', 'Beneficiary Name', 'Amount']);
    
    $items_sql = "SELECT 
                    bi.beneficiary_code,
                    bi.voucher_number,
                    bi.beneficiary_name,
                    bi.amount
                  FROM food_redemption_items bi
                  WHERE bi.batch_id = ?
                  ORDER BY bi.selection_order ASC";
    
    $item_stmt = mysqli_prepare($conn, $items_sql);
    mysqli_stmt_bind_param($item_stmt, 'i', $batch['id']);
    mysqli_stmt_execute($item_stmt);
    $items_result = mysqli_stmt_get_result($item_stmt);
    
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
    
    mysqli_stmt_close($item_stmt);
    
    fputcsv($output, []);
    fputcsv($output, []);
    
    $grand_total_vouchers += $batch['total_vouchers'];
    $grand_total_amount += $batch['total_amount'];
}

mysqli_stmt_close($stmt);

// Grand totals
if ($export_all) {
    fputcsv($output, ['=== GRAND TOTALS - ALL DATES ==']);
} else {
    fputcsv($output, ['=== GRAND TOTALS FOR TODAY', $date, '==']);
}
fputcsv($output, ['Total Vouchers Redeemed', $grand_total_vouchers]);
fputcsv($output, ['Total Amount Redeemed', $grand_total_amount]);

fclose($output);
exit();
?>