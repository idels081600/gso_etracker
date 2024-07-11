<?php
$servername = "157.245.193.124";
$username = "bryanmysql";
$password = "gsotagbilaran";
$dbname = "SAP";

// Attempt to establish a connection to the database
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check if the connection was successful
if (!$conn) {
    // If connection fails, output the error message
    echo json_encode(['error' => 'Connection failed: ' . mysqli_connect_error()]);
    exit(); // Exit the script to prevent further execution
}

// Set the character set
$conn->set_charset("utf8mb4");

// Initialize an array to hold unique suppliers and their total amounts
$suppliers = [];

// Query to fetch unique suppliers and their total amounts from bq table
$sql = "SELECT DISTINCT supplier, SUM(amount) AS total_amount
        FROM bq
        GROUP BY supplier";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $suppliers[] = [
        'supplier' => strtoupper(trim($row['supplier'])),
        'total_amount' => (float) $row['total_amount']
    ];
}

// Query to fetch unique suppliers and their total amounts from sir_bayong table
$sql = "SELECT Supplier, SUM(Amount) AS total_amount
        FROM sir_bayong
        GROUP BY Supplier";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $suppliers[] = [
        'supplier' => strtoupper(trim($row['Supplier'])),
        'total_amount' => (float) $row['total_amount']
    ];
}

// Query to fetch unique suppliers from Maam_mariecris table with their total amount
$sql = "SELECT DISTINCT store AS supplier, SUM(Total) AS total_amount
        FROM Maam_mariecris
        GROUP BY store";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $suppliers[] = [
        'supplier' => strtoupper(trim($row['supplier'])),
        'total_amount' => (float) $row['total_amount']
    ];
}

// Remove duplicates and reindex array
$uniqueSuppliers = array_values(array_unique($suppliers, SORT_REGULAR));

// Return the collected data as JSON
echo json_encode($uniqueSuppliers);

// Close the connection
$conn->close();
