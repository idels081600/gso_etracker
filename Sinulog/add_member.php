<?php
// Prevent any output before JSON
ob_start();

// Report errors to log file instead of output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    include 'sinulog_db.php';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Only POST requests are allowed.');
    }

    // Get POST data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $number = isset($_POST['number']) ? trim($_POST['number']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

    // Validate required fields
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($number)) {
        $errors[] = 'Number is required';
    }
    
    if (empty($role)) {
        $errors[] = 'Role is required';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone is required';
    }
    
    if (!empty($errors)) {
        // Clear any output buffer before sending JSON
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit;
    }

    // Check if connection exists
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    // Prepare and execute statement
    $stmt = $conn->prepare("INSERT INTO Sinulog (number, name, role, contact_no, status) VALUES (?, ?, ?, ?,'absent')");
    
    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }
    
    $stmt->bind_param("ssss", $number, $name, $role, $phone);
    
    if (!$stmt->execute()) {
        $error_message = 'Error adding member: ' . $stmt->error;
        
        if ($stmt->errno == 1062) {
            $error_message = 'Duplicate entry detected. This number or member may already exist.';
        }
        
        throw new Exception($error_message);
    }
    
    $insert_id = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    
    // Clear output buffer and send JSON
    ob_clean();
    echo json_encode([
        'success' => true, 
        'message' => 'Member added successfully',
        'data' => [
            'id' => $insert_id,
            'name' => $name,
            'number' => $number,
            'role' => $role,
            'phone' => $phone
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Sinulog API Error: ' . $e->getMessage());
    
    // Clear output buffer and send JSON
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e)
    ]);
    
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}

// End output buffering
ob_end_flush();
?>