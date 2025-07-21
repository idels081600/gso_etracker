<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../db_asset.php';

// Set content type to JSON
header('Content-Type: application/json');

// Function to send JSON response
function sendResponse($data)
{
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Function to log errors
function logError($message)
{
    error_log("Save Fuel Record API Error: " . $message);
}

// Function to validate input data
function validateFuelRecord($data)
{
    $errors = [];

    // Required fields validation
    if (empty($data['date'])) {
        $errors[] = 'Date is required';
    } else {
        // Validate date format
        $date = DateTime::createFromFormat('Y-m-d', $data['date']);
        if (!$date || $date->format('Y-m-d') !== $data['date']) {
            $errors[] = 'Invalid date format. Use YYYY-MM-DD';
        }
    }

    // Optional field validations
    if (!empty($data['liters_issued'])) {
        if (!is_numeric($data['liters_issued']) || floatval($data['liters_issued']) < 0) {
            $errors[] = 'Liters issued must be a positive number';
        }
    }

    // Validate fuel type if provided
    if (!empty($data['fuel_type'])) {
        $validFuelTypes = ['Unleaded', 'Diesel', 'Premium'];
        if (!in_array($data['fuel_type'], $validFuelTypes)) {
            $errors[] = 'Invalid fuel type. Must be Unleaded, Diesel, or Premium';
        }
    }

    return $errors;
}

// Function to sanitize input data
function sanitizeInput($data)
{
    $sanitized = [];

    // Sanitize each field
    $sanitized['date'] = isset($data['date']) ? trim($data['date']) : '';
    $sanitized['office'] = isset($data['office']) ? trim($data['office']) : '';
    $sanitized['vehicle'] = isset($data['vehicle']) ? trim($data['vehicle']) : '';
    $sanitized['plate_no'] = isset($data['plate_no']) ? trim(strtoupper($data['plate_no'])) : '';
    $sanitized['driver'] = isset($data['driver']) ? trim($data['driver']) : '';
    $sanitized['purpose'] = isset($data['purpose']) ? trim($data['purpose']) : '';
    $sanitized['fuel_type'] = isset($data['fuel_type']) ? trim($data['fuel_type']) : '';
    $sanitized['liters_issued'] = isset($data['liters_issued']) ? trim($data['liters_issued']) : '';
    $sanitized['remarks'] = isset($data['remarks']) ? trim($data['remarks']) : '';

    return $sanitized;
}

// Function to save fuel record
function saveFuelRecord($data)
{
    global $conn;

    try {
        // Check if database connection exists
        if (!isset($conn) || !$conn) {
            throw new Exception("Database connection not available");
        }

        // Check if fuel table exists, if not create it
        $tableCheck = "SHOW TABLES LIKE 'fuel'";
        $tableResult = mysqli_query($conn, $tableCheck);

        if (mysqli_num_rows($tableResult) == 0) {
            // Create fuel table
            $createTableSQL = "CREATE TABLE fuel (
                id INT AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                office VARCHAR(100),
                vehicle VARCHAR(100),
                plate_no VARCHAR(20),
                driver VARCHAR(100),
                purpose TEXT,
                fuel_type VARCHAR(20),
                liters_issued DECIMAL(10,2),
                remarks TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";

            if (!mysqli_query($conn, $createTableSQL)) {
                throw new Exception("Failed to create fuel table: " . mysqli_error($conn));
            }

            logError("Created fuel table successfully");
        }

        // Prepare SQL statement
        $sql = "INSERT INTO fuel (date, office, vehicle, plate_no, driver, purpose, fuel_type, liters_issued, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . mysqli_error($conn));
        }

        // Bind parameters
        mysqli_stmt_bind_param(
            $stmt,
            "sssssssds",
            $data['date'],
            $data['office'],
            $data['vehicle'],
            $data['plate_no'],
            $data['driver'],
            $data['purpose'],
            $data['fuel_type'],
            $data['liters_issued'],
            $data['remarks']
        );

        // Execute the statement
        if (mysqli_stmt_execute($stmt)) {
            $insertId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            logError("Successfully saved fuel record with ID: " . $insertId);

            return [
                'success' => true,
                'message' => 'Fuel record saved successfully',
                'data' => [
                    'id' => $insertId,
                    'date' => $data['date'],
                    'office' => $data['office'],
                    'vehicle' => $data['vehicle'],
                    'plate_no' => $data['plate_no'],
                    'driver' => $data['driver'],
                    'purpose' => $data['purpose'],
                    'fuel_type' => $data['fuel_type'],
                    'liters_issued' => $data['liters_issued'],
                    'remarks' => $data['remarks']
                ]
            ];
        } else {
            throw new Exception("Failed to execute statement: " . mysqli_stmt_error($stmt));
        }
    } catch (Exception $e) {
        logError("Error in saveFuelRecord: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error saving fuel record: ' . $e->getMessage(),
            'data' => null
        ];
    }
}

// Function to update existing fuel record
function updateFuelRecord($id, $data)
{
    global $conn;

    try {
        // Check if record exists
        $checkSQL = "SELECT id FROM fuel WHERE id = ?";
        $checkStmt = mysqli_prepare($conn, $checkSQL);
        mysqli_stmt_bind_param($checkStmt, "i", $id);
        mysqli_stmt_execute($checkStmt);
        $result = mysqli_stmt_get_result($checkStmt);

        if (mysqli_num_rows($result) == 0) {
            throw new Exception("Fuel record with ID $id not found");
        }

        mysqli_stmt_close($checkStmt);

        // Prepare update SQL statement
        $sql = "UPDATE fuel SET 
                date = ?, 
                office = ?, 
                vehicle = ?, 
                plate_no = ?, 
                driver = ?, 
                purpose = ?, 
                fuel_type = ?, 
                liters_issued = ?, 
                remarks = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new Exception("Failed to prepare update statement: " . mysqli_error($conn));
        }

        // Bind parameters
        mysqli_stmt_bind_param(
            $stmt,
            "sssssssdsi",
            $data['date'],
            $data['office'],
            $data['vehicle'],
            $data['plate_no'],
            $data['driver'],
            $data['purpose'],
            $data['fuel_type'],
            $data['liters_issued'],
            $data['remarks'],
            $id
        );

        // Execute the statement
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);

            logError("Successfully updated fuel record with ID: " . $id);

            return [
                'success' => true,
                'message' => 'Fuel record updated successfully',
                'data' => [
                    'id' => $id,
                    'date' => $data['date'],
                    'office' => $data['office'],
                    'vehicle' => $data['vehicle'],
                    'plate_no' => $data['plate_no'],
                    'driver' => $data['driver'],
                    'purpose' => $data['purpose'],
                    'fuel_type' => $data['fuel_type'],
                    'liters_issued' => $data['liters_issued'],
                    'remarks' => $data['remarks']
                ]
            ];
        } else {
            throw new Exception("Failed to execute update statement: " . mysqli_stmt_error($stmt));
        }
    } catch (Exception $e) {
        logError("Error in updateFuelRecord: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error updating fuel record: ' . $e->getMessage(),
            'data' => null
        ];
    }
}

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid JSON data: ' . json_last_error_msg(),
            'data' => null
        ]);
    }

    // Log the received data for debugging
    logError("Received data: " . print_r($data, true));

    // Sanitize input data
    $sanitizedData = sanitizeInput($data);

    // Validate input data
    $validationErrors = validateFuelRecord($sanitizedData);

    if (!empty($validationErrors)) {
        sendResponse([
            'success' => false,
            'message' => 'Validation errors: ' . implode(', ', $validationErrors),
            'data' => null,
            'errors' => $validationErrors
        ]);
    }

    // Check if this is an update (has ID) or insert (new record)
    if (isset($data['id']) && !empty($data['id'])) {
        // Update existing record
        $response = updateFuelRecord($data['id'], $sanitizedData);
    } else {
        // Save new record
        $response = saveFuelRecord($sanitizedData);
    }

    sendResponse($response);
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request for testing
    sendResponse([
        'success' => true,
        'message' => 'Save Fuel Record API is working',
        'methods' => ['POST'],
        'required_fields' => ['date'],
        'optional_fields' => ['office', 'vehicle', 'plate_no', 'driver', 'purpose', 'fuel_type', 'liters_issued', 'remarks']
    ]);
} else {
    // Method not allowed
    http_response_code(405);
    sendResponse([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are supported for saving data.',
        'allowed_methods' => ['POST', 'GET']
    ]);
}

// Close database connection
if (isset($conn)) {
    mysqli_close($conn);
}
