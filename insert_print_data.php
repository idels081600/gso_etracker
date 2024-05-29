<?php
// Include your database connection file
require 'db.php';

// Check if IDs are received via POST
if (isset($_POST['ids'])) {
    // Get the IDs from the POST request
    $selectedIDs = $_POST['ids'];

    // Prepare a placeholder string for the IN clause
    $placeholders = rtrim(str_repeat('?, ', count($selectedIDs)), ', ');

    // Prepare SQL statement to search for data based on the selected IDs in the sir_bayong table
    $stmt = $conn->prepare("SELECT SR_DR, Date, Quantity, Description, Amount, Office, Vehicle, Plate FROM sir_bayong WHERE id IN ($placeholders)");

    // Bind parameters to the prepared statement
    $types = str_repeat('i', count($selectedIDs)); // Assuming IDs are integers
    $stmt->bind_param($types, ...$selectedIDs);

    // Execute the prepared statement
    $stmt->execute();

    // Get the result set
    $result = $stmt->get_result();

    // Prepare SQL statement for inserting data into sir_bayong_print table
    $insertStmt = $conn->prepare("INSERT INTO sir_bayong_print (SR_DR, Date, Quantity, Description, Amount, Office, Vehicle, Plate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    // Loop through each row of the result set and insert into the sir_bayong_print table
    while ($row = $result->fetch_assoc()) {
        // Check if the data already exists in sir_bayong_print table
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM sir_bayong_print WHERE SR_DR = ? AND Date = ? AND Quantity = ? AND Description = ? AND Amount = ? AND Office = ? AND Vehicle = ? AND Plate = ?");
        $checkStmt->bind_param("ssisssss", $row['SR_DR'], $row['Date'], $row['Quantity'], $row['Description'], $row['Amount'], $row['Office'], $row['Vehicle'], $row['Plate']);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

        // If the data doesn't exist, insert it into the sir_bayong_print table
        if ($count == 0) {
            // Bind parameters to the prepared statement
            $insertStmt->bind_param("ssisssss", $row['SR_DR'], $row['Date'], $row['Quantity'], $row['Description'], $row['Amount'], $row['Office'], $row['Vehicle'], $row['Plate']);

            // Execute the prepared statement to insert data
            $insertStmt->execute();
        }
    }

    // Close the prepared statements
    $stmt->close();
    $insertStmt->close();

    // Return success message
    echo json_encode(["status" => "success", "message" => "Data successfully added to print."]);
} else {
    // Return error message if no IDs are received
    echo json_encode(["status" => "error", "message" => "No IDs received."]);
}

// Close the database connection
$conn->close();
