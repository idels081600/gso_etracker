<?php
// Include your database connection file
require 'db.php';

// Check if data is received via POST
if (isset($_POST['data'])) {
    // Decode the JSON data sent via POST
    $tableData = json_decode($_POST['data'], true);

    // Check if tableData is valid
    if (!is_array($tableData) || count($tableData) === 0) {
        echo json_encode(["status" => "error", "message" => "No valid data received."]);
        exit;
    }

    // Prepare SQL statement for inserting data into Maam_mariecris_print table
    $insertStmt = $conn->prepare("INSERT INTO Maam_mariecris_print (SR_DR, date, department, store, activity, no_of_pax, amount, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    // Check if the statement was prepared successfully
    if (!$insertStmt) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare insert statement."]);
        exit;
    }

    // Loop through each row of the received data and insert into the Maam_mariecris_print table
    foreach ($tableData as $row) {
        // Sanitize and process the fields
        $SR_DR = trim($row['SR_DR']);
        $date = date('Y-m-d', strtotime($row['date'])); // Convert date to proper format
        $department = trim($row['department']);
        $store = trim($row['store']);
        $activity = trim($row['activity']);
        $no_of_pax = intval($row['no_of_pax']); // Ensure it's an integer
        $amount = str_replace(['₱', ','], '', $row['amount']);
        $total = str_replace(['₱', ','], '', $row['total']);

        // Check if the data already exists in Maam_mariecris_print table
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM Maam_mariecris_print WHERE SR_DR = ? AND date = ? AND department = ? AND store = ? AND activity = ? AND no_of_pax = ? AND amount = ? AND total = ?");
        $checkStmt->bind_param("ssssssis", $SR_DR, $date, $department, $store, $activity, $no_of_pax, $amount, $total);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

        // If the data doesn't exist, insert it into the Maam_mariecris_print table
        if ($count == 0) {
            // Bind parameters to the prepared statement
            $insertStmt->bind_param("ssssssis", $SR_DR, $date, $department, $store, $activity, $no_of_pax, $amount, $total);

            // Execute the prepared statement to insert data
            if (!$insertStmt->execute()) {
                echo json_encode(["status" => "error", "message" => "Insert failed for data: " . $SR_DR]);
                exit;
            }
        }
    }

    // Close the prepared statement
    $insertStmt->close();

    // Return success message
    echo json_encode(["status" => "success", "message" => "Data successfully added to print."]);
} else {
    // Return error message if no data is received
    echo json_encode(["status" => "error", "message" => "No data received."]);
}

// Close the database connection
$conn->close();
