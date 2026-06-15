<?php

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/db_asset.php';

asset_require_auth();

$stmt = db_execute(
    $conn,
    'SELECT tent_no, name, Contact_no, location, purpose, date, retrieval_date, no_of_tents, status
     FROM tent
     ORDER BY id DESC'
);
$result = mysqli_stmt_get_result($stmt);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="tent-tracking-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'wb');
fputcsv($output, ['Tent No.', 'Name', 'Contact', 'Location', 'Purpose', 'Date Installed', 'Retrieval Date', 'No. of Tents', 'Status']);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['tent_no'],
        $row['name'],
        $row['Contact_no'],
        $row['location'],
        $row['purpose'],
        $row['date'],
        $row['retrieval_date'],
        $row['no_of_tents'],
        $row['status'],
    ]);
}

fclose($output);
mysqli_stmt_close($stmt);
exit;
