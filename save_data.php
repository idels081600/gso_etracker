<?php
require_once 'db_asset.php';

if (isset($_POST['save_data'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $contact_no = mysqli_real_escape_string($conn, $_POST['contact']);
    $date = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_POST['datepicker'])));
    $no_of_tents = mysqli_real_escape_string($conn, $_POST['tent_no']);
    $purpose = mysqli_real_escape_string($conn, $_POST['No_tents']);
    $location = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Check if "other" input is not empty
        if (!empty($_POST['other'])) {
            $location = mysqli_real_escape_string($conn, $_POST['other']); // Use "other" input value
        } else {
            // "other" input is empty, use the value from the dropdown
            $location = mysqli_real_escape_string($conn, $_POST['Location']);
        }
    }

    $query = "INSERT INTO tent(tent_no, name, contact_no, no_of_tents, purpose, location, status, date) VALUES ('0', '$name', '$contact_no', '$no_of_tents', '$purpose', '$location', '', '$date')";

    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        // Send JSON response to indicate success
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit();
    } else {
        // Send JSON response to indicate failure
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to save data']);
        exit();
    }
}
?>
