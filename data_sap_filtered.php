<?php
$servername = "157.245.193.124";
$username = "bryanmysql";
$password = "gsotagbilaran";
$dbname = "SAP";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    echo json_encode(['error' => 'Connection failed: ' . mysqli_connect_error()]);
    exit();
}

$conn->set_charset("utf8mb4");

$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$suppliers = [];

if ($month > 0 && $year > 0) {
    $date_start = "$year-$month-01";
    $date_end = date("Y-m-t", strtotime($date_start));

    // Query to fetch unique suppliers and their total amounts from bq table
    $sql = "SELECT DISTINCT supplier, SUM(amount) AS total_amount
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

    // Query to fetch unique suppliers and their total amounts from sir_bayong table
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
    echo json_encode(['error' => 'Invalid month or year provided']);
    exit();
}

$uniqueSuppliers = array_values(array_unique($suppliers, SORT_REGULAR));

echo json_encode($uniqueSuppliers);

$conn->close();
