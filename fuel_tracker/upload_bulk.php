<?php
header('Content-Type: application/json');
require_once '../db_asset.php';
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_MIMES', ['text/csv', 'application/vnd.ms-excel']);
function sanitizeData($data)
{
    return trim($data);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}
if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
    ];
    $errorMessage = $errorMessages[$_FILES['csvFile']['error']] ?? 'An unknown upload error occurred.';
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload error: ' . $errorMessage]);
    exit();
}
$file = $_FILES['csvFile'];
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds the maximum allowed (5 MB).']);
    exit();
}
if (!in_array($file['type'], ALLOWED_MIMES)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only CSV files are allowed.']);
    exit();
}
$filePath = $file['tmp_name'];
$totalRows = 0;
$successfulRows = 0;
$failedRows = [];
try {
    $conn->begin_transaction();
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        $header = array_map('trim', fgetcsv($handle, 1000, ","));
        if ($header === FALSE) {
            throw new Exception("Could not read CSV header or file is empty.");
        }
        $expectedColumns = [
            'date', 'office', 'vehicle', 'plate_no', 'driver', 'purpose', 'fuel_type', 'liters_issued', 'remarks'
        ];
        $columnMapping = [];
        foreach ($expectedColumns as $expectedCol) {
            $foundIndex = array_search($expectedCol, $header);
            if ($foundIndex === false) {
                $columnMapping[$expectedCol] = -1;
            } else {
                $columnMapping[$expectedCol] = $foundIndex;
            }
        }
        $tableName = 'fuel';
        $rowNumber = 1;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $data = array_map('trim', $data); // Trim each field
            $totalRows++;
            $rowNumber++;
            $rowData = [];
            $rowErrors = [];
            foreach ($expectedColumns as $colName) {
                $index = $columnMapping[$colName];
                $rowData[$colName] = ($index !== -1 && isset($data[$index])) ? $data[$index] : null;
            }
            $validatedData = array_fill_keys(array_keys($columnMapping), null);
            if (!empty($rowData['date'])) {
                $dateString = trim($rowData['date']);
                $dateObj = date_create($dateString);
                if ($dateObj && $dateObj->format('Y-m-d') == $dateString) {
                    $validatedData['date'] = $dateObj->format('Y-m-d');
                } else {
                    $rowErrors[] = "Row $rowNumber: Invalid date format \"$dateString\". Expected YYYY-MM-DD.";
                }
            } else {
                $rowErrors[] = "Row $rowNumber: Date is required.";
            }
            if (!empty($rowData['office'])) {
                $validatedData['office'] = sanitizeData($rowData['office']);
            } else {
                $rowErrors[] = "Row $rowNumber: Office is required.";
            }
            if (!empty($rowData['vehicle'])) {
                $validatedData['vehicle'] = sanitizeData($rowData['vehicle']);
            } else {
                $rowErrors[] = "Row $rowNumber: Vehicle is required.";
            }
            $validatedData['plate_no'] = !empty($rowData['plate_no']) ? sanitizeData($rowData['plate_no']) : null;
            $validatedData['driver'] = !empty($rowData['driver']) ? sanitizeData($rowData['driver']) : null;
            $validatedData['purpose'] = !empty($rowData['purpose']) ? sanitizeData($rowData['purpose']) : null;
            $allowedFuelTypes = ['Unleaded', 'Diesel'];
            if (!empty($rowData['fuel_type'])) {
                $fuelType = sanitizeData($rowData['fuel_type']);
                // Make the comparison case-insensitive
                if (in_array(strtolower($fuelType), array_map('strtolower', $allowedFuelTypes))) {
                    // Convert to uppercase for database insertion
                    $validatedData['fuel_type'] = strtoupper($fuelType);
                } else {
                    $rowErrors[] = "Row $rowNumber: Invalid fuel_type \"$fuelType\". Allowed: " . implode(', ', $allowedFuelTypes) . ".";
                }
            } else {
                $rowErrors[] = "Row $rowNumber: Fuel Type is required.";
            }
            if (isset($rowData['liters_issued']) && is_numeric($rowData['liters_issued'])) {
                $liters = floatval($rowData['liters_issued']);
                if ($liters >= 0) {
                    $validatedData['liters_issued'] = $liters;
                } else {
                    $rowErrors[] = "Row $rowNumber: Liters Issued must be a non-negative number.";
                }
            } else {
                $rowErrors[] = "Row $rowNumber: Liters Issued is required and must be a valid number.";
            }
            $validatedData['remarks'] = !empty($rowData['remarks']) ? sanitizeData($rowData['remarks']) : null;
            if (empty($rowErrors)) {
                $escapedDate = $conn->real_escape_string($validatedData['date']);
                $escapedOffice = $conn->real_escape_string($validatedData['office']);
                $escapedVehicle = $conn->real_escape_string($validatedData['vehicle']);
                $escapedPlateNo = $validatedData['plate_no'] !== null ? "'" . $conn->real_escape_string($validatedData['plate_no']) . "'" : 'NULL';
                $escapedDriver = $validatedData['driver'] !== null ? "'" . $conn->real_escape_string($validatedData['driver']) . "'" : 'NULL';
                $escapedPurpose = $validatedData['purpose'] !== null ? "'" . $conn->real_escape_string($validatedData['purpose']) . "'" : 'NULL';
                // Use the uppercase fuel type for insertion
                $escapedFuelType = $conn->real_escape_string($validatedData['fuel_type']);
                $escapedLitersIssued = $validatedData['liters_issued'];
                $escapedRemarks = $validatedData['remarks'] !== null ? "'" . $conn->real_escape_string($validatedData['remarks']) . "'" : 'NULL';
                $sql = "INSERT INTO {$tableName} (date, office, vehicle, plate_no, driver, purpose, fuel_type, liters_issued, remarks)
VALUES ('$escapedDate', '$escapedOffice', '$escapedVehicle', $escapedPlateNo, $escapedDriver, $escapedPurpose,
'$escapedFuelType', $escapedLitersIssued, $escapedRemarks)";
                if ($conn->query($sql) === TRUE) {
                    $successfulRows++;
                } else {
                    $rowErrors[] = "Row $rowNumber: Database error: " . $conn->error;
                    $failedRows[] = ['row' => $rowNumber, 'data' => $rowData, 'errors' => $rowErrors];
                }
            } else {
                $failedRows[] = ['row' => $rowNumber, 'data' => $rowData, 'errors' => $rowErrors];
            }
        }
        fclose($handle);
    } else {
        throw new Exception("Failed to open uploaded CSV file.");
    }
    if (empty($failedRows)) {
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'CSV imported successfully!',
            'total_rows' => $totalRows,
            'successful_rows' => $successfulRows,
            'failed_rows_count' => 0,
            'failed_rows' => []
        ]);
    } else {
        $conn->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'CSV import completed with errors. No data was imported due to detected issues.',
            'total_rows' => $totalRows,
            'successful_rows' => 0,
            'failed_rows_count' => count($failedRows),
            'failed_rows' => $failedRows
        ]);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error during CSV import: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

?>