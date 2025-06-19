<?php
// Prevent any output before headers
ob_start();

// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Set content type to JSON
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Function to handle errors
function handleError($message, $error = null) {
    error_log("=== ERROR IN ITEM ADDITION ===");
    error_log("Error message: " . $message);
    if ($error) {
        error_log("Error details: " . $error);
    }
    sendJsonResponse(false, $message);
}

require_once 'logi_db.php';

// Log the start of the script
error_log("=== STARTING ITEM ADDITION PROCESS ===");
error_log("Request Method: " . $_SERVER["REQUEST_METHOD"]);
error_log("Raw POST data: " . file_get_contents('php://input'));
error_log("POST array: " . print_r($_POST, true));

// Create table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_no VARCHAR(50) UNIQUE NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    rack_no INT NOT NULL,
    unit VARCHAR(50) NOT NULL,
    current_balance INT NOT NULL DEFAULT 0,
    description TEXT,
    status VARCHAR(50) DEFAULT 'Available',
    expiry_date DATE NULL,
    expiry_alert_days INT DEFAULT 30,
    expiry_status VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($create_table_query)) {
    handleError('Database error: Could not create table', $conn->error);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    handleError('Invalid request method');
}

try {
    // Log received data
    error_log("=== RECEIVED FORM DATA ===");
    error_log("POST data: " . print_r($_POST, true));

    // Validate required fields
    $required_fields = ['itemNo', 'itemName', 'rackNo', 'unit', 'balance'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            handleError("Missing required field: $field");
        }
    }

    // Get form data and sanitize
    $item_no = mysqli_real_escape_string($conn, $_POST['itemNo']);
    $item_name = mysqli_real_escape_string($conn, $_POST['itemName']);
    $rack_no = (int)$_POST['rackNo'];
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $current_balance = (int)$_POST['balance'];
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');

    // Log sanitized data
    error_log("=== SANITIZED DATA ===");
    error_log("Item No: " . $item_no);
    error_log("Item Name: " . $item_name);
    error_log("Rack No: " . $rack_no);
    error_log("Unit: " . $unit);
    error_log("Current Balance: " . $current_balance);
    error_log("Description: " . $description);

    // Handle expiry date and alert days
    $expiry_date = !empty($_POST['expiryDate']) ? $_POST['expiryDate'] : null;
    $expiry_alert_days = !empty($_POST['expiryAlertDays']) ? (int)$_POST['expiryAlertDays'] : 30;

    error_log("Expiry Date: " . ($expiry_date ?? 'null'));
    error_log("Expiry Alert Days: " . $expiry_alert_days);

    // Validate expiry date format if provided
    if ($expiry_date && !DateTime::createFromFormat('Y-m-d', $expiry_date)) {
        handleError("Invalid expiry date format");
    }

    // Determine status based on current balance and expiry date
    $status = 'Available';
    $expiry_status = '';

    if ($current_balance == 0) {
        $status = 'Out of Stock';
    } elseif ($current_balance <= 10) {
        $status = 'Low Stock';
    }

    error_log("Initial Status: " . $status);

    // Check expiry status if expiry date is provided
    if ($expiry_date) {
        $today = new DateTime();
        $expiry = new DateTime($expiry_date);
        $interval = $today->diff($expiry);
        $days_until_expiry = $interval->invert ? -$interval->days : $interval->days;

        error_log("Days until expiry: " . $days_until_expiry);

        if ($days_until_expiry < 0) {
            $status = 'Expired';
            $expiry_status = 'expired';
        } elseif ($days_until_expiry <= $expiry_alert_days) {
            if ($status === 'Available') {
                $status = 'Expiring Soon';
            }
            $expiry_status = 'expiring_soon';
        } else {
            $expiry_status = 'good';
        }
    }

    error_log("Final Status: " . $status);
    error_log("Expiry Status: " . $expiry_status);

    // Check if item_no already exists
    $check_query = "SELECT id FROM inventory_items WHERE item_no = ?";
    error_log("Checking for existing item: " . $check_query);
    
    $check_stmt = $conn->prepare($check_query);
    if (!$check_stmt) {
        handleError("Prepare check failed", $conn->error);
    }

    $check_stmt->bind_param("s", $item_no);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        handleError("Item number already exists. Please use a different item number.");
    }

    // Prepare and bind - Updated to include expiry fields
    $insert_query = "INSERT INTO inventory_items (item_no, item_name, rack_no, unit, current_balance, description, status, expiry_date, expiry_alert_days, expiry_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    error_log("Insert query: " . $insert_query);

    $stmt = $conn->prepare($insert_query);
    if (!$stmt) {
        handleError("Prepare insert failed", $conn->error);
    }

    $stmt->bind_param("ssisisssis", $item_no, $item_name, $rack_no, $unit, $current_balance, $description, $status, $expiry_date, $expiry_alert_days, $expiry_status);

    // Execute the statement
    if (!$stmt->execute()) {
        handleError("Execute failed", $stmt->error);
    }

    error_log("Item added successfully. Insert ID: " . $conn->insert_id);
    
    $response_data = [
        'success' => true,
        'message' => 'Item added successfully!',
        'item_id' => $conn->insert_id,
        'item_data' => [
            'item_no' => $item_no,
            'item_name' => $item_name,
            'rack_no' => $rack_no,
            'unit' => $unit,
            'current_balance' => $current_balance,
            'status' => $status,
            'expiry_date' => $expiry_date,
            'expiry_alert_days' => $expiry_alert_days,
            'expiry_status' => $expiry_status
        ]
    ];

    // Add expiry warnings to the message if applicable
    if ($expiry_date) {
        $today = new DateTime();
        $expiry = new DateTime($expiry_date);
        $interval = $today->diff($expiry);
        $days_until_expiry = $interval->invert ? -$interval->days : $interval->days;

        if ($days_until_expiry < 0) {
            $response_data['message'] .= "\n⚠️ WARNING: This item has already expired!";
            $response_data['expiry_warning'] = 'expired';
        } elseif ($days_until_expiry <= $expiry_alert_days) {
            $response_data['message'] .= "\n⚠️ ALERT: This item will expire in {$days_until_expiry} days.";
            $response_data['expiry_warning'] = 'expiring_soon';
            $response_data['days_until_expiry'] = $days_until_expiry;
        } else {
            $response_data['message'] .= "\nExpiry date set for " . date('M d, Y', strtotime($expiry_date));
            $response_data['expiry_warning'] = 'good';
            $response_data['days_until_expiry'] = $days_until_expiry;
        }

        $response_data['message'] .= "\nAlert will trigger {$expiry_alert_days} days before expiry.";
    }

    error_log("Sending success response: " . json_encode($response_data));
    sendJsonResponse(true, $response_data['message'], $response_data);

} catch (Exception $e) {
    handleError("An unexpected error occurred", $e->getMessage());
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($check_stmt)) $check_stmt->close();
    $conn->close();
    error_log("=== ITEM ADDITION PROCESS COMPLETED ===");
}

// Clean any output buffer
ob_end_flush();
?>
