<?php
// Include your database connection file
require 'db.php';

// Initialize response array
$response = array();

// Check if data is received via POST
if (isset($_POST['data'])) {
    // Decode the JSON data
    $tableData = json_decode($_POST['data'], true);

    // Check if the data is an array and not empty
    if (!is_array($tableData) || empty($tableData)) {
        echo json_encode(["status" => "error", "message" => "No data received."]);
        exit;
    }

    // Prepare SQL statement for inserting data into bq_print table
    $insertStmt = $conn->prepare("INSERT INTO bq_print (SR_DR, Date, Supplier, Requestor, Activity, Description, Quantity, Amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    // Check if the statement was prepared successfully
    if (!$insertStmt) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare insert statement."]);
        exit;
    }

    // Loop through each row of the table data and insert into the bq_print table
    foreach ($tableData as $row) {
        // Remove the peso sign (₱) and any commas from the amount field
        $amount = str_replace(['₱', ','], '', $row['Amount']);

        // Check if the data already exists in the bq_print table
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM bq_print WHERE SR_DR = ? AND Date = ? AND Supplier = ? AND Requestor = ? AND Activity = ? AND Description = ? AND Quantity = ? AND Amount = ?");
        $checkStmt->bind_param("ssssssii", $row['SR_DR'], $row['Date'], $row['Supplier'], $row['Requestor'], $row['Activity'], $row['Description'], $row['Quantity'], $amount);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

        // If the data doesn't exist, insert it into the bq_print table
        if ($count == 0) {
            // Bind parameters to the prepared statement
            $insertStmt->bind_param("ssssssii", $row['SR_DR'], $row['Date'], $row['Supplier'], $row['Requestor'], $row['Activity'], $row['Description'], $row['Quantity'], $amount);

            // Execute the prepared statement to insert data
            if (!$insertStmt->execute()) {
                error_log("Insert failed: " . $insertStmt->error);
            }
        }
    }

    // Close the prepared statements
    $insertStmt->close();

    // Return success message
    echo json_encode(["status" => "success", "message" => "Data successfully added to print."]);
} else {
    // Return error message if no data is received
    echo json_encode(["status" => "error", "message" => "No data received."]);
}

// Close the database connection
$conn->close();
