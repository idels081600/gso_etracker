<?php
require_once __DIR__ . '/app_security.php';
asset_require_auth();
require_once __DIR__ . '/db_payables.php';

// Initialize an array to hold unique suppliers and their previous month amounts
$suppliers = [];

// Query to fetch previous month's amounts from bq table
$sql = "SELECT supplier, SUM(amount) AS previous_month_amount
        FROM bq
        WHERE YEAR(date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)
        AND MONTH(date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
        GROUP BY supplier";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $suppliers[] = [
        'supplier' => strtoupper(trim($row['supplier'])),
        'previous_month_amount' => (float) $row['previous_month_amount']
    ];
}

// Query to fetch previous month's amounts from sir_bayong table
$sql = "SELECT Supplier, SUM(Amount) AS previous_month_amount
        FROM sir_bayong
        WHERE YEAR(Date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)
        AND MONTH(Date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
        GROUP BY Supplier";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $suppliers[] = [
        'supplier' => strtoupper(trim($row['Supplier'])),
        'previous_month_amount' => (float) $row['previous_month_amount']
    ];
}

// Query to fetch previous month's amounts from Maam_mariecris table
$sql = "SELECT store AS supplier, SUM(Total) AS previous_month_amount
        FROM Maam_mariecris
        WHERE YEAR(date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)
        AND MONTH(date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
        GROUP BY store";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $suppliers[] = [
        'supplier' => strtoupper(trim($row['supplier'])),
        'previous_month_amount' => (float) $row['previous_month_amount']
    ];
}

// Remove duplicates and reindex array
$uniqueSuppliers = array_values(array_unique($suppliers, SORT_REGULAR));

// Return the collected data as JSON
echo json_encode($uniqueSuppliers);

// Close the connection
$conn->close();

