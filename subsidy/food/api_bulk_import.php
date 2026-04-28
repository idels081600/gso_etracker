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

// Check if file was uploaded
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['csv_file']['tmp_name'];
$filename = $_FILES['csv_file']['name'];

// Validate file extension
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload a CSV file.']);
    exit;
}

// Read CSV file
$handle = fopen($file, 'r');
if ($handle === false) {
    echo json_encode(['success' => false, 'message' => 'Error reading CSV file']);
    exit;
}

// Get header row
$header = fgetcsv($handle);
if ($header === false) {
    fclose($handle);
    echo json_encode(['success' => false, 'message' => 'Empty CSV file']);
    exit;
}

// Expected columns
$expectedColumns = ['tricycle_no', 'driver_name', 'address', 'contact_number', 'total_vouchers'];

// Validate header (case-insensitive)
$headerLower = array_map('strtolower', $header);
$missingColumns = array_diff($expectedColumns, $headerLower);
if (!empty($missingColumns)) {
    fclose($handle);
    echo json_encode(['success' => false, 'message' => 'Missing columns: ' . implode(', ', $missingColumns)]);
    exit;
}

// Map column indices
$columnMap = [];
foreach ($expectedColumns as $col) {
    $columnMap[$col] = array_search($col, $headerLower);
}

// Process rows
$inserted = 0;
$duplicates = 0;
$errors = [];
$rowNum = 1; // Header is row 1

while (($row = fgetcsv($handle)) !== false) {
    $rowNum++;
    
    // Skip empty rows
    if (empty(array_filter($row))) {
        continue;
    }
    
    // Extract data
    $tricycle_no = mysqli_real_escape_string($conn, trim($row[$columnMap['tricycle_no']]));
    $driver_name = mysqli_real_escape_string($conn, trim($row[$columnMap['driver_name']]));
    $address = mysqli_real_escape_string($conn, trim($row[$columnMap['address']]));
    $contact_number = mysqli_real_escape_string($conn, trim($row[$columnMap['contact_number']]));
    $total_vouchers = isset($row[$columnMap['total_vouchers']]) ? (int)trim($row[$columnMap['total_vouchers']]) : 10;
    
    // Validate required fields
    if (empty($tricycle_no) || empty($driver_name)) {
        $errors[] = "Row $rowNum: Missing required fields (tricycle_no or driver_name)";
        continue;
    }
    
    // Check if tricycle_no already exists
    $check_sql = "SELECT id FROM tricycle_records WHERE tricycle_no = '$tricycle_no'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $duplicates++;
        continue;
    }
    
    // Insert record
    $sql = "INSERT INTO tricycle_records (tricycle_no, driver_name, address, contact_number, total_vouchers, status) 
            VALUES ('$tricycle_no', '$driver_name', '$address', '$contact_number', $total_vouchers, 'Active')";
    
    if (mysqli_query($conn, $sql)) {
        $inserted++;
    } else {
        $errors[] = "Row $rowNum: " . mysqli_error($conn);
    }
}

fclose($handle);

// Build response message
$message = "Import complete. ";
$message .= "$inserted record(s) inserted. ";
if ($duplicates > 0) {
    $message .= "$duplicates duplicate(s) skipped. ";
}
if (!empty($errors)) {
    $message .= count($errors) . " error(s) occurred.";
}

echo json_encode([
    'success' => true,
    'message' => $message,
    'inserted' => $inserted,
    'duplicates' => $duplicates,
    'errors' => $errors
]);
?>