<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit;
}

$export_all = isset($_GET['all']) && $_GET['all'] === '1';

if ($export_all) {
    $filename = 'Rice_Assistance_Raw_All_Dates.csv';
    $where_sql = '';
} else {
    $date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
    $filename = 'Rice_Assistance_Raw_' . $date . '.csv';
    $where_sql = 'WHERE DATE(rvc.claim_date) = ?';
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, [
    'claim_id',
    'household_code',
    'household_name',
    'claimant_name',
    'verifier_name',
    'claim_date'
]);

$sql = "SELECT
            rvc.id AS claim_id,
            rh.household_code,
            rh.household_name,
            rvc.claimant_name,
            rvc.verifier_name,
            rvc.claim_date
        FROM rice_voucher_claims rvc
        INNER JOIN rice_households rh ON rvc.household_id = rh.id
        $where_sql
        ORDER BY rvc.claim_date ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!$export_all) {
    mysqli_stmt_bind_param($stmt, 's', $date);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['claim_id'],
        $row['household_code'],
        $row['household_name'],
        $row['claimant_name'],
        $row['verifier_name'],
        $row['claim_date']
    ]);
}

mysqli_stmt_close($stmt);
fclose($output);
exit();
?>
