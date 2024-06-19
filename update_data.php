<?php
// Include your database connection file
include 'db_asset.php';

// Check if form is submitted via POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input to prevent SQL injection
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $tent_no = mysqli_real_escape_string($conn, $_POST['tent_no1']);
    $datepicker = date('Y-m-d', strtotime($_POST['datepicker1']));
    $name = mysqli_real_escape_string($conn, $_POST['name1']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact1']);
    $tentno = mysqli_real_escape_string($conn, $_POST['tentno']);
    $location = mysqli_real_escape_string($conn, $_POST['Location1']);
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose1']);
    $status = mysqli_real_escape_string($conn, $_POST['status']); // Get the selected status value

    // Debug: Print $_POST variables
    echo "Debug: ID: $id, Tent No: $tent_no, Datepicker: $datepicker, Name: $name, Contact: $contact, TentNo: $tentno, Location: $location, Purpose: $purpose, Status: $status<br>";

    // Explode tent_no if it contains multiple numbers separated by commas
    $tent_numbers = explode(',', $tentno);

    // Check if $tent_numbers has values
    var_dump($tent_numbers);

    // Loop through each tent number
    foreach ($tent_numbers as $tentNumber) {
        $tentNumber = trim($tentNumber);
        $searchQuery = "SELECT * FROM tent_status WHERE id = '$tentNumber'";
        $searchResult = mysqli_query($conn, $searchQuery);

        // Check if the tent number exists in the tent_status table
        if (mysqli_num_rows($searchResult) > 0) {
            // Update status column to "Installed" for the searched tent number
            $updateQuery = "UPDATE tent_status SET status = 'Installed' WHERE id = '$tentNumber'";
            if (mysqli_query($conn, $updateQuery)) {
                echo "Status updated to 'Installed' for tent number $tentNumber<br>";
            } else {
                echo "Error updating status for tent number $tentNumber: " . mysqli_error($conn) . "<br>";
            }
        } else {
            echo "Tent number $tentNumber not found in tent_status table<br>";
        }
        echo "Processing tent number: $tentNumber<br>";
    }

    // Ensure the loop is entered
    echo "Loop executed.";


    // Check if the status column for the given ID is empty
    $statusQuery = "SELECT status FROM tent WHERE id = '$id'";
    $statusResult = mysqli_query($conn, $statusQuery);
    if ($statusResult) {
        $row = mysqli_fetch_assoc($statusResult);
        $existingStatus = $row['status'];

        // If the existing status is empty, update the status to 'Installed'
        if (empty($existingStatus) || $existingStatus === 'Pending') {
            $query = "UPDATE tent SET
                          tent_no = '$tentno',
                          date = '$datepicker',
                          name = '$name',
                          Contact_no = '$contact',
                          no_of_tents = '$tent_no',
                          location = '$location',
                          purpose = '$purpose',
                          status = 'Installed' 
                        WHERE id = '$id'";
        } else {
            // If the existing status is not empty, update without modifying the status column
            $query = "UPDATE tent SET
                          tent_no = '$tentno',
                          date = '$datepicker',
                          name = '$name',
                          Contact_no = '$contact',
                          no_of_tents = '$tent_no',
                          location = '$location',
                          purpose = '$purpose'
                        WHERE id = '$id'";
        }

        // Execute query
        if (mysqli_query($conn, $query)) {
            echo "Record updated successfully<br>";
            header("Location: tracking.php");
        } else {
            echo "Error updating record: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "Error retrieving existing status: " . mysqli_error($conn) . "<br>";
    }
}
