<?php
session_start();
require_once 'db_fuel.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$username = mysqli_real_escape_string($conn, $_SESSION['username']);
$station_id = isset($_SESSION['station_id']) ? (int)$_SESSION['station_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

if ($station_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'No station selected.']);
    exit();
}

if ($action === 'list') {
    $sql = "SELECT id, export_code, date_range_text, total_amount, total_liters, total_vouchers, created_at
            FROM manual_claimed_voucher_exports
            WHERE username = '$username' AND station_id = $station_id
            ORDER BY created_at DESC
            LIMIT 50";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Error loading batches: ' . mysqli_error($conn)]);
        exit();
    }

    $batches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $batches[] = [
            'id' => (int)$row['id'],
            'export_code' => $row['export_code'],
            'date_range_text' => $row['date_range_text'],
            'total_amount' => (float)$row['total_amount'],
            'total_liters' => (float)$row['total_liters'],
            'total_vouchers' => (int)$row['total_vouchers'],
            'created_at' => $row['created_at']
        ];
    }

    echo json_encode(['success' => true, 'batches' => $batches]);
    exit();
}

if ($action === 'load') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid batch selected.']);
        exit();
    }

    $sql = "SELECT id, export_code, date_range_text, start_date, end_date, selected_entries_json
            FROM manual_claimed_voucher_exports
            WHERE id = $id AND username = '$username' AND station_id = $station_id
            LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Error loading batch: ' . mysqli_error($conn)]);
        exit();
    }

    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Batch not found.']);
        exit();
    }

    $row = mysqli_fetch_assoc($result);
    $entries = json_decode($row['selected_entries_json'], true);
    if (!is_array($entries)) {
        $entries = [];
    }

    echo json_encode([
        'success' => true,
        'batch' => [
            'id' => (int)$row['id'],
            'export_code' => $row['export_code'],
            'date_range_text' => $row['date_range_text'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'selected_entries' => $entries
        ]
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>
