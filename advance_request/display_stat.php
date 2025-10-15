<?php
require_once 'advance_po_db.php';

function display_data_stat_BQ()
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'BQ' AND delete_status = 0";

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function display_data_stat_NODAL()
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'NODAL' AND delete_status = 0";

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function display_data_stat_JETS_MARKETING()
{

    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'JETS MARKETING' AND delete_status = 0";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function  display_data_stat_JJS_SEAFOODS()
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'JJS SEAFOODS' AND delete_status = 0";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
function display_data_stat_CITY_TYRE()
{
    global $conn;
    $query = "SELECT SUM(`amount`) AS total_amount FROM `advancePo` WHERE store = 'CITY TYRE' AND delete_status = 0";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $total_amount = $row['total_amount'] ? $row['total_amount'] : 0;
        return '₱' . number_format($total_amount, 2);
    } else {
        return '₱0.00';
    }
}
