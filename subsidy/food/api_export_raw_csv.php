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
    $filename = "Raw_Redemptions_All_Dates.csv";
} else {
    $date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
    $date_filter = "WHERE DATE(b.created_at) = ?";
    $filename = "Raw_Redemptions_" . $date . ".csv";
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

// Column headers
fputcsv($output, [
    'batch_number',
    'market',
    'stall_no',
    'vendor_name',
    'redeemer',
    'created_at',
    'selection_order',
    'voucher_code',
    'beneficiary_name',
    'amount'
]);

// Get all items
$sql = "SELECT 
            b.batch_number,
            v.area as market,
            v.stall_no,
            v.vendor_name,
            b.redeemer,
            b.created_at,
            bi.selection_order,
            bi.beneficiary_code,
            bi.voucher_number,
            bi.beneficiary_name,
            bi.amount
        FROM food_redemption_items bi
        JOIN food_redemption_batches b ON bi.batch_id = b.id
        LEFT JOIN food_vendors v ON b.vendor_id = v.id
        $date_filter
        ORDER BY b.created_at ASC, bi.selection_order ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!$export_all) {
    mysqli_stmt_bind_param($stmt, 's', $date);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $voucher_code = ($row['beneficiary_code'] ? $row['beneficiary_code'] : 'N/A') . ' - 00' . $row['voucher_number'];
    fputcsv($output, [
        $row['batch_number'],
        $row['market'],
        $row['stall_no'],
        $row['vendor_name'],
        $row['redeemer'],
        $row['created_at'],
        $row['selection_order'],
        $voucher_code,
        $row['beneficiary_name'],
        $row['amount']
    ]);
}

mysqli_stmt_close($stmt);
fclose($output);
exit();
?>