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
    $address = mysqli_real_escape_string($conn, $_POST['address1']); // Added address field

    // Handle retrieval date - set to NULL if empty
    if (!empty($_POST['duration1']) && $_POST['duration1'] !== '') {
        $retrieval_date = date('Y-m-d', strtotime($_POST['duration1']));
        $retrieval_date_value = "'$retrieval_date'";
    } else {
        $retrieval_date_value = "NULL";
    }

    // Debug: Print $_POST variables
    echo "Debug: ID: $id, Tent No: $tent_no, Datepicker: $datepicker, Name: $name, Contact: $contact, TentNo: $tentno, Location: $location, Purpose: $purpose, Status: $status, Address: $address<br>";

    // Only process tent_status updates if tentno is not empty
    if (!empty($tentno)) {
        // Explode tent_no if it contains multiple numbers separated by commas
        $tent_numbers = explode(',', $tentno);

        // Check if $tent_numbers has values
        var_dump($tent_numbers);

        // Determine the status to set in tent_status table based on the selected status
        $tent_status_value = 'Available'; // Default value

        switch ($status) {
            case 'Installed':
                $tent_status_value = 'Installed';
                break;
            case 'For Retrieval':
                $tent_status_value = 'Installed'; // Still installed but marked for retrieval
                break;
            case 'Retrieved':
                $tent_status_value = 'Available'; // Back to available when retrieved
                break;
            case 'Long Term':
                $tent_status_value = 'Long Term'; // Set to Long Term
                break;
            default:
                $tent_status_value = 'Available';
        }

        // Loop through each tent number
        foreach ($tent_numbers as $tentNumber) {
            $tentNumber = trim($tentNumber);
            $searchQuery = "SELECT * FROM tent_status WHERE id = '$tentNumber'";
            $searchResult = mysqli_query($conn, $searchQuery);

            // Check if the tent number exists in the tent_status table
            if (mysqli_num_rows($searchResult) > 0) {
                // Update status column based on the selected status
                $updateQuery = "UPDATE tent_status SET status = '$tent_status_value' WHERE id = '$tentNumber'";
                if (mysqli_query($conn, $updateQuery)) {
                    echo "Status updated to '$tent_status_value' for tent number $tentNumber<br>";
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
    }

    // Check if the status column for the given ID is empty
    $statusQuery = "SELECT status FROM tent WHERE id = '$id'";
    $statusResult = mysqli_query($conn, $statusQuery);

    if ($statusResult) {
        $row = mysqli_fetch_assoc($statusResult);
        $existingStatus = $row['status'];

        // Determine what status to use in the tent table
        $final_status = $status; // Use the selected status from the form

        // If no status was selected and we have tent numbers, default to 'Installed'
        if (empty($status) && !empty($tentno)) {
            $final_status = 'Installed';
        }

        // If status is empty and no tent numbers, keep existing status or set to Pending
        if (empty($status) && empty($tentno)) {
            $final_status = !empty($existingStatus) ? $existingStatus : 'Pending';
        }

        // Update the tent table with the correct status
        $query = "UPDATE tent SET 
                     tent_no = '$tentno',
                     date = '$datepicker',
                     retrieval_date = $retrieval_date_value,
                     name = '$name',
                     Contact_no = '$contact',
                     no_of_tents = '$tent_no',
                     location = '$location',
                     purpose = '$purpose',
                     address = '$address',
                     status = '$final_status'
                   WHERE id = '$id'";

        // Execute query
        if (mysqli_query($conn, $query)) {
            echo "Record updated successfully with status: $final_status<br>";
            header("Location: tracking.php");
            exit(); // Always add exit() after header redirect
        } else {
            echo "Error updating record: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "Error retrieving existing status: " . mysqli_error($conn) . "<br>";
    }
}
