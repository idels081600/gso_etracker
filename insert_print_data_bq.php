<?php
// Include your database connection file
require 'db.php';

// Initialize response array
$response = array();

// Check if data is received via POST
if (isset($_POST['data'])) {
    // Decode the JSON data into an associative array
    $tableData = json_decode($_POST['data'], true);

    // Prepare SQL statement for inserting data into bq_print table
    $insertQuery = "INSERT INTO bq_print (SR_DR, date, supplier, requestor, activity, description, quantity, amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    // Prepare the statement
    $stmt = $conn->prepare($insertQuery);

    // Check if the statement was prepared successfully
    if (!$stmt) {
        error_log("Failed to prepare insert statement: " . $conn->error); // Log error if statement preparation fails
        $response['success'] = false;
        $response['message'] = 'Failed to prepare insert statement.';
        echo json_encode($response);
        exit;
    }

    // Loop through each row of the table data
    foreach ($tableData as $row) {
        // Assign values to variables from the $row array
        $SR_DR = $row['SR_DR'];
        $date = $row['Date']; // Ensure correct case here, matching the table structure
        $supplier = $row['Supplier'];
        $requestor = $row['Requestor'];
        $activity = $row['Activity'];
        $description = $row['Description'];
        $quantity = (int)$row['Quantity']; // Ensure quantity is treated as an integer

        // Remove the peso sign (₱) and commas from the amount field and cast to a float
        $amount = (float)str_replace(['₱', ','], '', $row['Amount']);

        // Log the data being inserted for debugging
        error_log("Inserting Data: SR_DR: $SR_DR, Date: $date, Supplier: $supplier, Requestor: $requestor, Activity: $activity, Description: $description, Quantity: $quantity, Amount: $amount");

        // Bind the parameters dynamically after assigning values
        $stmt->bind_param("ssssssii", $SR_DR, $date, $supplier, $requestor, $activity, $description, $quantity, $amount);

        // Execute the insert statement for each row
        if (!$stmt->execute()) {
            error_log("Insert failed: " . $stmt->error); // Log SQL errors
            $response['success'] = false;
            $response['message'] = 'Insert failed: ' . $stmt->error;
            echo json_encode($response);
            exit;
        }
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
