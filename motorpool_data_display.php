<?php
require_once 'db_asset.php';
function get_vehicles_list()
{
    global $conn;
    $vehicleQuery = "SELECT plate_no, car_model FROM vehicle_records ORDER BY plate_no ASC";
    $vehicleResult = mysqli_query($conn, $vehicleQuery);

    // Store vehicles in an array
    $vehicles = [];
    if ($vehicleResult && mysqli_num_rows($vehicleResult) > 0) {
        while ($row = mysqli_fetch_assoc($vehicleResult)) {
            $vehicles[] = $row;
        }
    }
    return $vehicles;
}

function get_motorpool_repairs()
{
    global $conn;
    $query = "SELECT * FROM motorpool_repair WHERE status IN ('Pending', 'In Progress') ORDER BY id DESC";
    $result = mysqli_query($conn, $query);

    // Store repairs in an array
    $repairs = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $repairs[] = $row;
        }
    }

    return $repairs;
}


function count_daily_repairs()
{
    global $conn;

    // Query to count repairs grouped by date
    $query = "SELECT DATE(repair_date) as repair_day, COUNT(*) as repair_count 
              FROM motorpool_repair 
              GROUP BY DATE(repair_date) 
              ORDER BY repair_day DESC";

    $result = mysqli_query($conn, $query);

    // Store daily repair counts in an array
    $daily_repairs = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $daily_repairs[] = $row;
        }
    }

    return $daily_repairs;
}
function count_pending_repairs()
{
    global $conn;

    // Query to count repairs with status 'Pending'
    $query = "SELECT COUNT(*) as pending_count 
              FROM motorpool_repair 
              WHERE status = 'Pending'";

    $result = mysqli_query($conn, $query);

    // Initialize the count
    $pending_count = 0;

    // Check if query was successful and fetch the count
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $pending_count = $row['pending_count'];
    }

    return $pending_count;
}
function count_repaired_repairs()
{
    global $conn;

    // Query to count repairs with status 'Repaired'
    $query = "SELECT COUNT(*) as repaired_count 
              FROM motorpool_repair 
              WHERE status = 'Completed'";

    $result = mysqli_query($conn, $query);

    // Initialize the count
    $repaired_count = 0;

    // Check if query was successful and fetch the count
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $repaired_count = $row['repaired_count'];
    }

    return $repaired_count;
}
function count_completed_repairs_by_car()
{
    global $conn;

    // Query to count completed repairs grouped by plate_no
    // Order by count in descending order and limit to top 5
    $query = "SELECT plate_no, COUNT(*) as completed_count 
              FROM motorpool_repair 
              WHERE status = 'Completed' 
              GROUP BY plate_no
              ORDER BY completed_count DESC
              LIMIT 5";

    $result = mysqli_query($conn, $query);

    // Initialize the array to store counts
    $completed_counts = array();

    // Check if query was successful and fetch the counts
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $completed_counts[$row['plate_no']] = $row['completed_count'];
        }
    }

    return $completed_counts;
}
function count_repairs_by_office()
{
    global $conn;

    // Get current date in Y-m-d format
    $today = date('Y-m-d');

    // Query to count repairs grouped by office for today only
    $query = "SELECT 
                office, 
                COUNT(*) as repair_count
              FROM 
                motorpool_repair
              WHERE 
                office IS NOT NULL 
                AND office != ''
                AND DATE(repair_date) = '$today'
              GROUP BY 
                office
              ORDER BY 
                repair_count DESC";

    $result = mysqli_query($conn, $query);

    // Initialize the array to store counts
    $office_counts = array();

    // Check if query was successful and fetch the counts
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $office_counts[$row['office']] = $row['repair_count'];
        }
    }

    return $office_counts;
}
function count_in_progress_repairs()
{
    global $conn;
    // Query to count repairs with status 'In Progress'
    $query = "SELECT COUNT(*) as in_progress_count 
              FROM motorpool_repair 
              WHERE status = 'In Progress'";
    $result = mysqli_query($conn, $query);

    // Initialize the count
    $in_progress_count = 0;

    // Check if query was successful and fetch the count
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $in_progress_count = $row['in_progress_count'];
    }

    return $in_progress_count;
}
function count_completed_repairs_by_office()
{
    global $conn;

    // Query to count completed repairs grouped by office
    $query = "SELECT office, COUNT(*) as completed_count 
              FROM motorpool_repair 
              WHERE status = 'Completed' 
              GROUP BY office 
              ORDER BY completed_count DESC";

    $result = mysqli_query($conn, $query);

    // Initialize the array to store office counts
    $office_counts = array();

    // Check if query was successful and fetch the counts
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $office = $row['office'];
            $count = $row['completed_count'];

            // Add to the array with office as key and count as value
            $office_counts[$office] = $count;
        }
    }

    return $office_counts;
}
