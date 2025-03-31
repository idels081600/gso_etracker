<?php
require_once 'db.php';

// Query to get PO data with used amounts and remaining balances
$query = "
    SELECT
        p.po AS po_number,
        p.amount AS original_amount,
        p.used_amount AS used_amount,
        p.balance AS remaining_balance
    FROM
        Maam_mariecris_payments p
    ORDER BY
        p.po DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    // Return error if query fails
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database query failed: ' . mysqli_error($conn)]);
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'po_number' => $row['po_number'],
        'original_amount' => $row['original_amount'],
        'used_amount' => $row['used_amount'],
        'remaining_balance' => $row['remaining_balance']
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($data);
