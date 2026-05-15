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

if ($station_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'No station selected.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($_GET['action']) ? $_GET['action'] : (isset($input['action']) ? $input['action'] : '');

if ($action === 'load' && $method === 'GET') {
    $sql = "SELECT draft_data, updated_at
            FROM manual_claimed_voucher_drafts
            WHERE username = '$username' AND station_id = $station_id
            LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Error loading draft: ' . mysqli_error($conn)]);
        exit();
    }

    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => true, 'draft' => null]);
        exit();
    }

    $row = mysqli_fetch_assoc($result);
    $draft = json_decode($row['draft_data'], true);

    echo json_encode([
        'success' => true,
        'draft' => $draft,
        'updated_at' => $row['updated_at']
    ]);
    exit();
}

if ($action === 'save' && $method === 'POST') {
    $draft = isset($input['draft']) ? $input['draft'] : null;

    if (!is_array($draft)) {
        echo json_encode(['success' => false, 'message' => 'Draft data is required.']);
        exit();
    }

    $draftJson = json_encode($draft);
    if ($draftJson === false) {
        echo json_encode(['success' => false, 'message' => 'Unable to encode draft data.']);
        exit();
    }

    $draftJson = mysqli_real_escape_string($conn, $draftJson);
    $sql = "INSERT INTO manual_claimed_voucher_drafts (username, station_id, draft_data)
            VALUES ('$username', $station_id, '$draftJson')
            ON DUPLICATE KEY UPDATE draft_data = VALUES(draft_data), updated_at = CURRENT_TIMESTAMP";

    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Draft saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error saving draft: ' . mysqli_error($conn)]);
    }
    exit();
}

if ($action === 'delete' && $method === 'POST') {
    $sql = "DELETE FROM manual_claimed_voucher_drafts
            WHERE username = '$username' AND station_id = $station_id";

    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Draft deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting draft: ' . mysqli_error($conn)]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action or request method.']);
?>
