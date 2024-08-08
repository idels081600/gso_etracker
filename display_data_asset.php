<?php
require_once "db_asset.php";
function display_data()
{
    global $conn;
    $query = "SELECT * FROM `tent` WHERE `status` IN ('Pending', 'Installed','For Retrieval') ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_dashboard()
{
    global $conn;
    $query = "SELECT * FROM `tent` ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}
function display_tent_status()
{
    global $conn;

    // Query to get count of 'On Stock' tents
    $query = "SELECT COUNT(*) AS stock_count FROM `tent_status` WHERE `Status` = 'On Stock'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];

    return $stock_count;
}
function countTotalTentStatusRows()
{
    global $conn;

    // Query to count total rows in the `tent_status` table
    $total_rows_query = "SELECT COUNT(*) AS total_count FROM `tent_status`";
    $total_result = mysqli_query($conn, $total_rows_query);
    if (!$total_result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $total_row = mysqli_fetch_assoc($total_result);
    $total_count = $total_row['total_count'];

    return $total_count;
}

function display_tent_status_Installed()
{
    global $conn;

    // Query to get count of 'On Stock' tents
    $query = "SELECT COUNT(*) AS stock_count FROM `tent_status` WHERE `Status` = 'Installed'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];

    return $stock_count;
}
function display_tent_status_Retrieved()
{
    global $conn;

    // Query to get count of 'On Stock' tents
    $query = "SELECT COUNT(*) AS stock_count FROM `tent_status` WHERE `Status` = 'Retrieved'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];

    return $stock_count;
}
function display_tent_status_Retrieval()
{
    global $conn;

    // Query to get count of 'On Stock' tents
    $query = "SELECT COUNT(*) AS stock_count FROM `tent_status` WHERE `Status` = 'For Retrieval'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];


    return  $stock_count;
}
function display_tent_status_Longterm()
{
    global $conn;

    // Query to get count of 'Long Term' tents
    $query = "SELECT COUNT(*) AS stock_count FROM `tent_status` WHERE `Status` = 'Long Term'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];

    return $stock_count;
}

function display_data_transpo()
{
    global $conn;
    $query = "SELECT * FROM `Transportation` ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}

function display_data_driver()
{
    global $conn;
    $query = "SELECT * FROM `Drivers` ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_vehicle()
{
    global $conn;
    $query = "SELECT * FROM `Vehicle` ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);

    // Check if query was successful
    if ($result) {
        // Fetch data into an associative array
        $data = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    } else {
        // Query failed, return false or handle error accordingly
        return false;
    }
}
function display_data_rfq()
{
    global $conn;
    $query = "SELECT * FROM `RFQ` ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);
    return $result;
}

function display_vehicle_status()
{
    global $conn;

    // Query to get count of 'Stand By' vehicles
    $query = "SELECT COUNT(*) AS stock_count FROM `Vehicle` WHERE `Status` = 'Stand By'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];

    // Debug: Print stock count
    error_log("Stock Count: " . $stock_count);

    // Query to get total count of vehicles
    $total_rows_query = "SELECT COUNT(*) AS total_count FROM `Vehicle`";
    $total_result = mysqli_query($conn, $total_rows_query);
    if (!$total_result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $total_row = mysqli_fetch_assoc($total_result);
    $total_count = $total_row['total_count'];

    // Debug: Print total count
    error_log("Total Count: " . $total_count);

    // Calculate percentage
    if ($total_count > 0) {
        $percentage = ($stock_count / $total_count) * 100;
        $percentage = round($percentage); // Round to the nearest whole number
    } else {
        $percentage = 0; // Avoid division by zero
    }

    // Debug: Print percentage
    error_log("Percentage: " . $percentage);

    return $percentage;
}

function display_vehicle_ongarage()
{
    global $conn;

    // Query to get count of 'Stand By' vehicles
    $query = "SELECT COUNT(*) AS stock_count FROM `Vehicle` WHERE `Status` = 'Stand By'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];

    // Debug: Print stock count
    error_log("Stock Count: " . $stock_count);

    return $stock_count;
}
function display_vehicle_Departed_status()
{
    global $conn;

    // Query to get count of 'Stand By' vehicles
    $query = "SELECT COUNT(*) AS stock_count FROM `Vehicle` WHERE `Status` = 'Departed'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];

    // Debug: Print stock count
    error_log("Stock Count: " . $stock_count);

    // Query to get total count of vehicles
    $total_rows_query = "SELECT COUNT(*) AS total_count FROM `Vehicle`";
    $total_result = mysqli_query($conn, $total_rows_query);
    if (!$total_result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $total_row = mysqli_fetch_assoc($total_result);
    $total_count = $total_row['total_count'];

    // Debug: Print total count
    error_log("Total Count: " . $total_count);

    // Calculate percentage
    if ($total_count > 0) {
        $percentage = ($stock_count / $total_count) * 100;
        $percentage = round($percentage); // Round to the nearest whole number
    } else {
        $percentage = 0; // Avoid division by zero
    }

    // Debug: Print percentage
    error_log("Percentage: " . $percentage);

    return $percentage;
}

function display_vehicle_departed()
{
    global $conn;

    // Query to get count of 'Stand By' vehicles
    $query = "SELECT COUNT(*) AS stock_count FROM `Vehicle` WHERE `Status` = 'Departed'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];

    // Debug: Print stock count
    error_log("Stock Count: " . $stock_count);

    return $stock_count;
}
function display_vehicle_dispatched()
{
    global $conn;

    // Query to get count of 'Stand By' vehicles
    $query = "SELECT COUNT(*) AS stock_count FROM `Transportation` WHERE `Status1` = 'Arrived'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    $row = mysqli_fetch_assoc($result);
    $stock_count = $row['stock_count'];

    // Debug: Print stock count
    error_log("Stock Count: " . $stock_count);

    return $stock_count;
}
function display_data_transpo_ongrage_hover()
{
    global $conn;
    $query = "SELECT * FROM `Vehicle` WHERE `Status` = 'Stand By' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);

    $data = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return json_encode($data);
}

function display_data_transpo_onfield_hover()
{
    global $conn;
    $query = "SELECT * FROM `Vehicle` WHERE `Status` = 'Departed' ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);

    $data = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return json_encode($data);
}
