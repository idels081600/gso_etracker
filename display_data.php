<?php
require_once "db.php";
function display_data_sir_bayong()
{
    global $conn;
    $query = "SELECT * FROM `sir_bayong` ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_sir_bayong_payments()
{
    global $conn;
    $query = "SELECT * FROM `sir_bayong_payments` ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_sir_bayong_print()
{
    global $conn;
    $query = "SELECT * FROM `sir_bayong_print` ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}

function display_data_maam_mariecris()
{
    global $conn;
    $query = "SELECT * FROM `Maam_mariecris` ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_maam_mariecris_print()
{
    global $conn;
    $query = "SELECT * FROM `Maam_mariecris_print` ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_Maam_mariecris_payments()
{
    global $conn;
    $query = "SELECT * FROM `Maam_mariecris_payments` ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}

function display_data_BQ()
{
    global $conn;
    $query = "SELECT * FROM `bq` ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}
function display_data_BQ_payments()
{
    global $conn;
    $query = "SELECT * FROM `bq_payments` ORDER BY `id` DESC";

    $result = mysqli_query($conn, $query);
    return $result;
}

function display_data_bq_print()
{
    global $conn;
    $query = "SELECT * FROM `bq_print` ORDER BY `id` DESC";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die('Query Failed: ' . mysqli_error($conn));
    }

    return $result;
}
