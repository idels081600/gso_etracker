<?php
session_start();
require_once __DIR__ . '/rice_release_batches.php';
$conn = require(__DIR__ . '/config/database.php');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'RICE_VERIFIER') {
    header("Location: ../../login_v2.php");
    exit;
}

$wave = $_GET['wave'] ?? 'first_wave';
$batch = riceReleaseBatch($wave);
if (!$batch) {
    http_response_code(400);
    exit('Invalid rice batch.');
}
$household_table = $batch['households'];
$claim_table = $batch['claims'];
session_write_close();
$export_all = isset($_GET['all']) && $_GET['all'] === '1';

if ($export_all) {
    $filename = 'Rice_Assistance_All_Dates.csv';
    $where_sql = '';
} else {
    $date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
    $filename = 'Rice_Assistance_' . $date . '.csv';
    $where_sql = 'WHERE DATE(rvc.claim_date) = ?';
}

if ($wave !== 'first_wave') {
    $filename = substr($filename, 0, -4) . '_' . str_replace(' ', '_', $batch['label']) . '.csv';
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

if ($export_all) {
    fputcsv($output, ['RICE ASSISTANCE REPORT - ALL DATES']);
} else {
    fputcsv($output, ['RICE ASSISTANCE REPORT']);
    fputcsv($output, ['Date', $date]);
}
fputcsv($output, []);
fputcsv($output, ['Household Code', 'Household Name', 'Claimant Name', 'Verified By', 'Claim Date']);

$sql = "SELECT
            rh.household_code,
            rh.household_name,
            rvc.claimant_name,
            rvc.verifier_name,
            rvc.claim_date
        FROM {$claim_table} rvc
        INNER JOIN {$household_table} rh ON rvc.household_id = rh.id
        $where_sql
        ORDER BY rvc.claim_date ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!$export_all) {
    mysqli_stmt_bind_param($stmt, 's', $date);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$total = 0;
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['household_code'],
        $row['household_name'],
        $row['claimant_name'],
        $row['verifier_name'],
        $row['claim_date']
    ]);
    $total++;
}

fputcsv($output, []);
fputcsv($output, ['Total Claimed Households', $total]);

mysqli_stmt_close($stmt);
fclose($output);
exit();
?>
