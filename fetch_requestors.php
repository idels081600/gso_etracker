<?php
// fetch_requestors.php

require_once 'db_asset.php'; // Ensure this file includes your database connection

header('Content-Type: application/json');

$search = isset($_GET['query']) ? $_GET['query'] : '';

if ($search) {
    $search = mysqli_real_escape_string($conn, $search);
    $query = "SELECT requestor FROM requestingParty WHERE requestor LIKE '%$search%'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die(json_encode(["error" => "Query failed: " . mysqli_error($conn)]));
    }

    $requestors = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $requestors[] = $row['requestor'];
    }

    echo json_encode($requestors);
} else {
    echo json_encode([]);
}
