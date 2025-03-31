<?php
require_once 'db_payables.php';

// Check if date parameters are provided
if (isset($_GET['date_start']) && isset($_GET['date_end'])) {
    $date_start = mysqli_real_escape_string($conn, $_GET['date_start']);
    $date_end = mysqli_real_escape_string($conn, $_GET['date_end']);
    
    $suppliers = [];
    
    // Query to fetch unique suppliers from bq table with their total amount
    $sql = "SELECT supplier AS supplier, SUM(amount) AS total_amount
            FROM bq
            WHERE DATE(date) BETWEEN '$date_start' AND '$date_end'
            GROUP BY supplier";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = [
                'supplier' => strtoupper(trim($row['supplier'])),
                'total_amount' => (float) $row['total_amount']
            ];
        }
    } else {
        echo json_encode(['error' => 'Error executing query for bq: ' . $conn->error]);
        exit();
    }

    // Query to fetch unique suppliers from sir_bayong table
    $sql = "SELECT Supplier, SUM(Amount) AS total_amount
            FROM sir_bayong
            WHERE DATE(Date) BETWEEN '$date_start' AND '$date_end'
            GROUP BY Supplier";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = [
                'supplier' => strtoupper(trim($row['Supplier'])),
                'total_amount' => (float) $row['total_amount']
            ];
        }
    } else {
        echo json_encode(['error' => 'Error executing query for sir_bayong: ' . $conn->error]);
        exit();
    }

    // Query to fetch unique suppliers from Maam_mariecris table with their total amount
    $sql = "SELECT DISTINCT store AS supplier, SUM(Total) AS total_amount
            FROM Maam_mariecris
            WHERE DATE(date) BETWEEN '$date_start' AND '$date_end'
            GROUP BY store";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = [
                'supplier' => strtoupper(trim($row['supplier'])),
                'total_amount' => (float) $row['total_amount']
            ];
        }
    } else {
        echo json_encode(['error' => 'Error executing query for Maam_mariecris: ' . $conn->error]);
        exit();
    }
} else {
    echo json_encode(['error' => 'Invalid date range provided']);
    exit();
}

// Combine suppliers with the same name (case-insensitive)
$uniqueSuppliers = [];
$supplierMap = [];

foreach ($suppliers as $supplier) {
    $key = strtoupper(trim($supplier['supplier']));
    
    if (!isset($supplierMap[$key])) {
        $supplierMap[$key] = [
            'supplier' => $key,
            'total_amount' => 0
        ];
    }
    
    $supplierMap[$key]['total_amount'] += $supplier['total_amount'];
}

$uniqueSuppliers = array_values($supplierMap);

echo json_encode($uniqueSuppliers);

$conn->close();
?>
