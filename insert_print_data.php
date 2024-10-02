<?php
// Include your database connection file
require 'db.php';

// Initialize response array
$response = array();

// Check if data is received via POST
if (isset($_POST['data'])) {
    // Decode the JSON data into an associative array
    $tableData = json_decode($_POST['data'], true);

    // Prepare the insert query
    $insertQuery = "INSERT INTO sir_bayong_print (SR_DR, Date, Supplier, Office, Description, Vehicle, Plate, Quantity, Amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Prepare the statement
    $stmt = $conn->prepare($insertQuery);

    // Bind the parameters
    $stmt->bind_param("sssssssss", $SR_DR, $Date, $Supplier, $Office, $Description, $Vehicle, $Plate, $Quantity, $Amount);

    // Loop through the data and insert each row
    foreach ($tableData as $row) {
        // Assign values to variables from the $row array
        $SR_DR = $row['SR_DR'];
        $Date = $row['Date'];
        $Supplier = $row['Supplier'];
        $Office = $row['Office'];
        $Description = $row['Description'];
        $Vehicle = $row['Vehicle'];
        $Plate = $row['Plate'];
        $Quantity = $row['Quantity'];

        // Remove the peso sign (₱) and any commas from the Amount field
        $Amount = str_replace(['₱', ','], '', $row['Amount']);

        // Log the data being inserted
        error_log("Inserting Data: SR_DR: $SR_DR, Date: $Date, Supplier: $Supplier, Office: $Office, Description: $Description, Vehicle: $Vehicle, Plate: $Plate, Quantity: $Quantity, Amount: $Amount");

        // Execute the insert statement for each row
        $stmt->execute();
    }

    // Close the statement
    $stmt->close();

    // Send success response
    $response['success'] = true;
    $response['message'] = 'All data successfully added to print.';
} else {
    // Send error response if no data is received
    $response['success'] = false;
    $response['message'] = 'No data received.';
}

// Close the database connection
$conn->close();

// Send response as JSON
header('Content-Type: application/json');
echo json_encode($response);
