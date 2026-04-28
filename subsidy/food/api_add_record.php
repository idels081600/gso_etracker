<?php

session_start();

// Security check - return error if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$conn = require(__DIR__ . '/config/database.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$tricycle_no = isset($_POST['tricycle_no']) ? mysqli_real_escape_string($conn, $_POST['tricycle_no']) : '';
$driver_name = isset($_POST['driver_name']) ? mysqli_real_escape_string($conn, $_POST['driver_name']) : '';
$address = isset($_POST['address']) ? mysqli_real_escape_string($conn, $_POST['address']) : '';
$contact_number = isset($_POST['contact_number']) ? mysqli_real_escape_string($conn, $_POST['contact_number']) : '';
$total_vouchers = isset($_POST['total_vouchers']) ? (int)$_POST['total_vouchers'] : 10;
$status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : 'Active';

// Validation
if (empty($tricycle_no) || empty($driver_name)) {
    echo json_encode(['success' => false, 'message' => 'Tricycle number and driver name are required']);
    exit;
}

// Check if tricycle number already exists
$check_sql = "SELECT id FROM tricycle_records WHERE tricycle_no = '$tricycle_no'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) > 0) {
    echo json_encode(['success' => false, 'message' => 'Tricycle number already exists']);
    exit;
}

// Insert new record
$sql = "INSERT INTO tricycle_records (tricycle_no, driver_name, address, contact_number, total_vouchers, status) 
        VALUES ('$tricycle_no', '$driver_name', '$address', '$contact_number', $total_vouchers, '$status')";

if (mysqli_query($conn, $sql)) {
    echo json_encode([
        'success' => true, 
        'message' => 'Record added successfully',
        'id' => mysqli_insert_id($conn)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error adding record: ' . mysqli_error($conn)]);
}
?>