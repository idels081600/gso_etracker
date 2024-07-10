<?php
require_once 'db.php'; // Include your database connection

// Query to select all suppliers
$query = "SELECT name FROM Supplier";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(["error" => "Failed to fetch suppliers."]); // Handle error if query fails
    exit;
}

$suppliers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $suppliers[] = $row['name'];
}

mysqli_free_result($result);
mysqli_close($conn);

echo json_encode(["suppliers" => $suppliers]);
