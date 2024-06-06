<?php
// Include your database connection file
require 'db.php';

// Initialize response array
$response = array();

// Check if IDs are received via POST
if (isset($_POST['ids'])) {
    // Get the IDs from the POST request
    $selectedIDs = $_POST['ids'];

    // Ensure the IDs are safe to use in a SQL query
    $ids = array_map('intval', $selectedIDs); // Convert to integers to prevent SQL injection
    $idList = implode(',', $ids); // Create a comma-separated string of IDs

    // Prepare the SQL query to select the data from sir_bayong
    $selectQuery = "SELECT SR_DR, Date, Supplier, Description, Quantity, Amount, Office, Vehicle, Plate 
                    FROM sir_bayong 
                    WHERE id IN ($idList)";

    // Execute the query
    $result = $conn->query($selectQuery);

    // Check if any rows are returned
    if ($result->num_rows > 0) {
        // Prepare the insert query
        $insertQuery = "INSERT INTO sir_bayong_print (SR_DR, Date, Supplier, Description, Quantity, Amount, Office, Vehicle, Plate) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Prepare the statement
        $stmt = $conn->prepare($insertQuery);

        // Bind the parameters
        $stmt->bind_param("sssssssss", $SR_DR, $Date, $Supplier, $Description, $Quantity, $Amount, $Office, $Vehicle, $Plate);

        // Loop through the selected rows and insert each into sir_bayong_print
        while ($row = $result->fetch_assoc()) {
            // Assign values to variables
            $SR_DR = $row['SR_DR'];
            $Date = $row['Date'];
            $Supplier = $row['Supplier'];
            $Description = $row['Description'];
            $Quantity = $row['Quantity'];
            $Amount = $row['Amount'];
            $Office = $row['Office'];
            $Vehicle = $row['Vehicle'];
            $Plate = $row['Plate'];

            // Check if the row already exists in sir_bayong_print
            $checkQuery = "SELECT id FROM sir_bayong_print WHERE SR_DR = ? AND Date = ? AND Supplier = ? AND Description = ? AND Quantity = ? AND Amount = ? AND Office = ? AND Vehicle = ? AND Plate = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("sssssssss", $SR_DR, $Date, $Supplier, $Description, $Quantity, $Amount, $Office, $Vehicle, $Plate);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows == 0) { // If row doesn't exist, insert it
                // Execute the insert statement
                $stmt->execute();
                $response['success'] = true;
                $response['message'] = 'Data successfully added to print.';
            } else {
                $response['success'] = false;
                $response['message'] = 'Data is already uploaded.';
            }
            // Close the check statement
            $checkStmt->close();
        }

        // Close the statement
        $stmt->close();
    } else {
        $response['success'] = false;
        $response['message'] = 'No data found to upload.';
    }

    // Close the result set
    $result->close();
} else {
    $response['success'] = false;
    $response['message'] = 'No IDs received.';
}

// Close the database connection
$conn->close();

// Send response as JSON
header('Content-Type: application/json');
echo json_encode($response);
