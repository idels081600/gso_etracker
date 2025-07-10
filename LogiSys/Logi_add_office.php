<?php
require_once 'logi_db.php'; // Include database connection

header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $officeName = trim($_POST['officeName'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $contactPerson = trim($_POST['contactPerson'] ?? '');
    $contactEmail = trim($_POST['contactEmail'] ?? '');
    $contactPhone = trim($_POST['contactPhone'] ?? '');

    // Validate required fields
    if (empty($officeName)) {
        echo json_encode(['success' => false, 'message' => 'Office name is required']);
        exit;
    }

    // Validate email format if provided
    if (!empty($contactEmail) && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    // Check if office name already exists
    $check_query = "SELECT id FROM office_balances WHERE office_name = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);

    if (!$check_stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($check_stmt, "s", $officeName);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'Office name already exists']);
        exit;
    }

    mysqli_stmt_close($check_stmt);

    // Insert new office
    $insert_query = "INSERT INTO office_balances (office_name, department, contact_person, contact_email, contact_phone, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $insert_stmt = mysqli_prepare($conn, $insert_query);

    if (!$insert_stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($insert_stmt, "sssss", $officeName, $department, $contactPerson, $contactEmail, $contactPhone);

    if (mysqli_stmt_execute($insert_stmt)) {
        $office_id = mysqli_insert_id($conn);

        mysqli_stmt_close($insert_stmt);
        $insert_stmt = null;

        echo json_encode([
            'success' => true,
            'message' => 'Office added successfully',
            'office_id' => $office_id,
            'office_name' => $officeName
        ]);
    } else {
        throw new Exception('Failed to insert office: ' . mysqli_stmt_error($insert_stmt));
    }
} catch (Exception $e) {
    error_log("Error in Logi_add_office.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.',
        'debug' => $e->getMessage() // Remove this in production
    ]);
} finally {
    // Close any remaining statements and connection
    if (isset($insert_stmt) && $insert_stmt !== null) {
        mysqli_stmt_close($insert_stmt);
    }
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
