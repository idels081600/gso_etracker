<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$conn = require(__DIR__ . '/config/database.php');
header('Content-Type: application/json');

$station_id = mysqli_real_escape_string($conn, $_SESSION['station_id']);

// Get optional pump price parameter
$pumpPrice = isset($_GET['pump_price']) ? (float)$_GET['pump_price'] : 0;

// Voucher value configuration
$VOUCHER_VALUE = 200; // Each voucher = 200 Pesos

// Count today's data
$today_sql = "SELECT 
                COUNT(DISTINCT vc.tricycle_id) AS today_count,
                COUNT(vc.id) AS today_vouchers
              FROM voucher_claims vc
              INNER JOIN tricycle_records tr ON vc.tricycle_id = tr.id
              WHERE DATE(vc.claim_date) = CURDATE() 
              AND vc.station_id = '$station_id'";

$today_result = mysqli_query($conn, $today_sql);
$today_row = mysqli_fetch_assoc($today_result);
$today_liters = $today_row['today_vouchers'] * $VOUCHER_VALUE;

// Count total all time liters/vouchers
$total_sql = "SELECT COUNT(vc.id) AS total_vouchers
              FROM voucher_claims vc
              WHERE vc.station_id = '$station_id'";

$total_result = mysqli_query($conn, $total_sql);
$total_row = mysqli_fetch_assoc($total_result);
$total_liters = $total_row['total_vouchers'] * $VOUCHER_VALUE;

// Calculate actual liters if pump price is provided
if ($pumpPrice > 0) {
    $today_liters = $today_liters / $pumpPrice;
    $total_liters = $total_liters / $pumpPrice;
}

echo json_encode([
    'success' => true,
    'today_count' => (int)$today_row['today_count'],
    'today_vouchers' => (int)$today_row['today_vouchers'],
    'total_vouchers' => (int)$total_row['total_vouchers'],
    'today_liters' => round($today_liters, 2),
    'total_liters' => round($total_liters, 2)
]);
?>