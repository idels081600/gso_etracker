<?php
// Check if session is already started, only start if not active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Return empty or default values for stats
    echo '₱0.00';
    exit;
}
require_once 'advance_po_db.php';

function display_data_stat_BQ($start_date = null, $end_date = null)
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'BQ' AND delete_status = 0 AND status = 'Pending'";

    $conditions = [];
    if ($start_date) {
        $conditions[] = "date >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    }
    if ($end_date) {
        $conditions[] = "date <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function display_data_stat_NODAL($start_date = null, $end_date = null)
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'NODAL' AND delete_status = 0 AND status = 'Pending'";

    $conditions = [];
    if ($start_date) {
        $conditions[] = "date >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    }
    if ($end_date) {
        $conditions[] = "date <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function display_data_stat_JETS_MARKETING($start_date = null, $end_date = null)
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'JETS MARKETING' AND delete_status = 0 AND status = 'Pending'";

    $conditions = [];
    if ($start_date) {
        $conditions[] = "date >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    }
    if ($end_date) {
        $conditions[] = "date <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function display_data_stat_JJS_SEAFOODS($start_date = null, $end_date = null)
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'JJS SEAFOODS' AND delete_status = 0 AND status = 'Pending'";

    $conditions = [];
    if ($start_date) {
        $conditions[] = "date >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    }
    if ($end_date) {
        $conditions[] = "date <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function display_data_stat_CITY_TYRE($start_date = null, $end_date = null)
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'CITY TYRE' AND delete_status = 0 AND status = 'Pending'";

    $conditions = [];
    if ($start_date) {
        $conditions[] = "date >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    }
    if ($end_date) {
        $conditions[] = "date <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function display_data_stat_BQ_BUILDERWARE($start_date = null, $end_date = null)
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'BQ BUILDERWARE' AND delete_status = 0 AND status = 'Pending'";

    $conditions = [];
    if ($start_date) {
        $conditions[] = "date >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    }
    if ($end_date) {
        $conditions[] = "date <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function display_data_PO_monitoring($start_date = null, $end_date = null)
{
    global $conn;
    $query = "SELECT SUM(`price`) AS total_amount FROM `poMonitoring` WHERE supplier = 'JETS MARKETING' AND delete_status = 0 AND status = 'Pending'";

    $conditions = [];
    if ($start_date) {
        $conditions[] = "po_date >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    }
    if ($end_date) {
        $conditions[] = "po_date <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
