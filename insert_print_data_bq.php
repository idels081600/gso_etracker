<?php
// Include your database connection file
require 'db.php';

// Check if IDs are received via POST
if (isset($_POST['ids'])) {
    // Get the IDs from the POST request
    $selectedIDs = $_POST['ids'];

    // Validate selectedIDs to ensure it contains only integers
    if (!is_array($selectedIDs) || !all_integers($selectedIDs)) {
        echo json_encode(["status" => "error", "message" => "Invalid IDs received."]);
        exit;
    }

    // Prepare a placeholder string for the IN clause
    $placeholders = implode(',', array_fill(0, count($selectedIDs), '?'));

    // Prepare SQL statement to search for data based on the selected IDs in the bq table
    $stmt = $conn->prepare("SELECT SR_DR, date, requestor, activity, description, quantity, amount FROM bq WHERE id IN ($placeholders)");

    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare select statement."]);
        exit;
    }

    // Bind parameters to the prepared statement
    $types = str_repeat('i', count($selectedIDs)); // Assuming IDs are integers
    $stmt->bind_param($types, ...$selectedIDs);

    // Execute the prepared statement
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Failed to execute select statement."]);
        $stmt->close();
        exit;
    }

    // Get the result set
    $result = $stmt->get_result();

    // Prepare SQL statement for inserting data into bq_print table
    $insertStmt = $conn->prepare("INSERT INTO bq_print (SR_DR, date, requestor, activity, description, quantity, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");

    if (!$insertStmt) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare insert statement."]);
        $stmt->close();
        exit;
    }

    // Loop through each row of the result set and insert into the bq_print table
    while ($row = $result->fetch_assoc()) {
        // Check if the data already exists in bq_print table
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM bq_print WHERE SR_DR = ? AND date = ? AND requestor = ? AND activity = ? AND description = ? AND quantity = ? AND amount = ?");
        $checkStmt->bind_param("sssssis", $row['SR_DR'], $row['date'], $row['requestor'], $row['activity'], $row['description'], $row['quantity'], $row['amount']);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

        // If the data doesn't exist, insert it into the bq_print table
        if ($count == 0) {
            // Bind parameters to the prepared statement
            $insertStmt->bind_param("sssssis", $row['SR_DR'], $row['date'], $row['requestor'], $row['activity'], $row['description'], $row['quantity'], $row['amount']);

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

// Function to validate all elements in the array are integers
function all_integers($array)
{
    foreach ($array as $element) {
        if (!filter_var($element, FILTER_VALIDATE_INT)) {
            return false;
        }
    }
    return true;
}
