<?php
require_once "logi_db.php";

function display_inventory_items($limit = null, $offset = 0)
{
    global $conn;
    if ($limit === null) {
        $query = "SELECT * FROM `inventory_items` ORDER BY `id` DESC";
        $result = mysqli_query($conn, $query);
    } else {
        $limit = max(1, min((int)$limit, 100));
        $offset = max(0, (int)$offset);
        $query = "SELECT * FROM `inventory_items` ORDER BY `id` DESC LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            die("Query failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    }

    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    return $result;
}
function display_transactions($limit = 25, $offset = 0)
{
    global $conn;
    $limit = max(1, min((int)$limit, 100));
    $offset = max(0, (int)$offset);
    $query = "SELECT id, item_name, item_no, quantity, previous_balance, new_balance, reason, requestor, transaction_type, created_at
              FROM `inventory_transactions`
              ORDER BY `id` DESC
              LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        die("Query failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    return $result;
}
function display_requested_items($limit = 100, $offset = 0)
{
    global $conn;
    $limit = max(1, min((int)$limit, 500));
    $offset = max(0, (int)$offset);
    $query = "SELECT * FROM `items_requested` WHERE status = 'Approved' AND uploaded = 0 ORDER BY `id` DESC LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        die("Query failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    return $result;
}
