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
    error_log("Fuel Data API Error: " . $message);
}

// Check if database connection exists
if (!isset($conn) || !$conn) {
    logError("Database connection not available");
    sendResponse([
        'success' => false,
        'message' => 'Database connection failed',
        'data' => [],
        'count' => 0
    ]);
}

// Function to get all fuel records
function getAllFuelRecords()
{
    global $conn;

    try {
        // Check if fuel table exists
        $tableCheck = "SHOW TABLES LIKE 'fuel'";
        $tableResult = mysqli_query($conn, $tableCheck);

        if (mysqli_num_rows($tableResult) == 0) {
            logError("Fuel table does not exist");
            return [
                'success' => false,
                'data' => [],
                'count' => 0,
                'message' => 'Fuel table does not exist in database'
            ];
        }

        // SQL query to select all data from fuel table
        $sql = "SELECT * FROM fuel ORDER BY date DESC, id DESC";
        $result = mysqli_query($conn, $sql);

        if (!$result) {
            throw new Exception("Query failed: " . mysqli_error($conn));
        }

        $fuelRecords = [];

        // Fetch all records
        while ($row = mysqli_fetch_assoc($result)) {
            $fuelRecords[] = $row;
        }

        logError("Successfully retrieved " . count($fuelRecords) . " fuel records");

        // Return success response with data
        return [
            'success' => true,
            'data' => $fuelRecords,
            'count' => count($fuelRecords),
            'message' => 'Fuel records retrieved successfully'
        ];
    } catch (Exception $e) {
        logError("Error in getAllFuelRecords: " . $e->getMessage());
        // Return error response
        return [
            'success' => false,
            'data' => [],
            'count' => 0,
            'message' => 'Error retrieving fuel records: ' . $e->getMessage()
        ];
    }
}

// Function to get fuel records with filters (simplified for date-only filtering)// Function to get fuel records with filters (enhanced with text search)
function getFuelRecordsWithFilters($filters = [])
{
    global $conn;

    try {
        // Base query
        $sql = "SELECT * FROM fuel WHERE 1=1";
        $params = [];
        $types = "";

        // Add date filters if provided
        if (!empty($filters['date_from'])) {
            $sql .= " AND date >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND date <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }

        // Add text search filter if provided
        if (!empty($filters['search'])) {
            $sql .= " AND (office LIKE ? OR vehicle LIKE ? OR driver LIKE ? OR plate_no LIKE ? OR purpose LIKE ? OR remarks LIKE ?)";
            $searchTerm = "%" . $filters['search'] . "%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= "ssssss";
        }

        // Add fuel type filter if provided
        if (!empty($filters['fuel_type'])) {
            $sql .= " AND fuel_type = ?";
            $params[] = $filters['fuel_type'];
            $types .= "s";
        }

        // Add ordering
        $sql .= " ORDER BY date DESC, id DESC";

        // Prepare and execute query
        if (!empty($params)) {
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
            } else {
                throw new Exception("Failed to prepare statement: " . mysqli_error($conn));
            }
        } else {
            $result = mysqli_query($conn, $sql);
        }

        if (!$result) {
            throw new Exception("Query failed: " . mysqli_error($conn));
        }

        $fuelRecords = [];

        // Fetch all records
        while ($row = mysqli_fetch_assoc($result)) {
            $fuelRecords[] = $row;
        }

        // Clean up
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }

        // Return success response with data
        return [
            'success' => true,
            'data' => $fuelRecords,
            'count' => count($fuelRecords),
            'filters_applied' => $filters,
            'message' => 'Fuel records retrieved successfully'
        ];
    } catch (Exception $e) {
        logError("Error in getFuelRecordsWithFilters: " . $e->getMessage());
        // Return error response
        return [
            'success' => false,
            'data' => [],
            'count' => 0,
            'message' => 'Error retrieving fuel records: ' . $e->getMessage()
        ];
    }
}

// Function to get fuel statistics
function getFuelStatistics()
{
    global $conn;

    try {
        // Query for fuel type statistics
        $sql = "SELECT 
                    fuel_type,
                    COUNT(*) as total_records,
                    SUM(CASE WHEN liters_issued IS NOT NULL AND liters_issued != '' AND liters_issued != '0' 
                        THEN CAST(liters_issued AS DECIMAL(10,2)) ELSE 0 END) as total_liters,
                    COUNT(CASE WHEN DATE(date) = CURDATE() THEN 1 END) as today_records,
                    COUNT(CASE WHEN MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE()) THEN 1 END) as month_records,
                    MIN(date) as earliest_date,
                    MAX(date) as latest_date
                FROM fuel
                WHERE fuel_type IS NOT NULL AND fuel_type != ''
                GROUP BY fuel_type
                ORDER BY fuel_type";

        $result = mysqli_query($conn, $sql);

        if (!$result) {
            throw new Exception("Query failed: " . mysqli_error($conn));
        }

        $statistics = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $statistics[] = $row;
        }

        return [
            'success' => true,
            'data' => $statistics,
            'period' => 'all_time',
            'message' => 'Fuel statistics retrieved successfully'
        ];
    } catch (Exception $e) {
        logError("Error in getFuelStatistics: " . $e->getMessage());
        return [
            'success' => false,
            'data' => [],
            'message' => 'Error retrieving fuel statistics: ' . $e->getMessage()
        ];
    }
}

// Function to get filtered fuel statistics by date range (simplified for date-only filtering)
function getFilteredFuelStatistics($filters = [])
{
    global $conn;

    try {
        // Base query for fuel type statistics
        $sql = "SELECT 
                    fuel_type,
                    COUNT(*) as total_records,
                    SUM(CASE WHEN liters_issued IS NOT NULL AND liters_issued != '' AND liters_issued != '0' 
                        THEN CAST(liters_issued AS DECIMAL(10,2)) ELSE 0 END) as total_liters,
                    AVG(CASE WHEN liters_issued IS NOT NULL AND liters_issued != '' AND liters_issued != '0' 
                        THEN CAST(liters_issued AS DECIMAL(10,2)) ELSE NULL END) as avg_liters,
                    MIN(date) as period_start,
                    MAX(date) as period_end
                FROM fuel
                WHERE fuel_type IS NOT NULL AND fuel_type != ''";

        $params = [];
        $types = "";

        // Add date filters if provided
        if (!empty($filters['date_from'])) {
            $sql .= " AND date >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND date <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }

        $sql .= " GROUP BY fuel_type ORDER BY fuel_type";

        // Prepare and execute query
        if (!empty($params)) {
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
            } else {
                throw new Exception("Failed to prepare statement: " . mysqli_error($conn));
            }
        } else {
            $result = mysqli_query($conn, $sql);
        }

        if (!$result) {
            throw new Exception("Query failed: " . mysqli_error($conn));
        }

        $statistics = [];

        while ($row = mysqli_fetch_assoc($result)) {
            // Add formatted average
            $row['avg_liters'] = $row['avg_liters'] ? round($row['avg_liters'], 2) : 0;
            $statistics[] = $row;
        }

        // Clean up
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }

        return [
            'success' => true,
            'data' => $statistics,
            'filters_applied' => $filters,
            'date_range' => [
                'from' => $filters['date_from'] ?? null,
                'to' => $filters['date_to'] ?? null
            ],
            'period' => 'filtered',
            'message' => 'Filtered fuel statistics retrieved successfully'
        ];
    } catch (Exception $e) {
        logError("Error in getFilteredFuelStatistics: " . $e->getMessage());
        return [
            'success' => false,
            'data' => [],
            'message' => 'Error retrieving filtered fuel statistics: ' . $e->getMessage()
        ];
    }
}

// Handle different request types
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'all';

    logError("Processing request with action: " . $action);

    switch ($action) {
        case 'all':
            $response = getAllFuelRecords();
            break;

        case 'filtered':
            $filters = [];

            // Get date filter parameters
            if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }

            // Get search parameter
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }

            // Get fuel type filter
            if (isset($_GET['fuel_type']) && !empty($_GET['fuel_type'])) {
                $filters['fuel_type'] = $_GET['fuel_type'];
            }

            $response = getFuelRecordsWithFilters($filters);
            break;

        case 'statistics':
            $response = getFuelStatistics();
            break;

        case 'filtered_statistics':
            $filters = [];

            // Get filter parameters from GET request (date filters only)
            if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }

            $response = getFilteredFuelStatistics($filters);
            break;

        case 'single':
            if (isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt = $conn->prepare("SELECT * FROM fuel WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $record = $result->fetch_assoc();
                $stmt->close();

                if ($record) {
                    sendResponse(['success' => true, 'data' => $record]);
                } else {
                    sendResponse(['success' => false, 'message' => 'Record not found']);
                }
            } else {
                sendResponse(['success' => false, 'message' => 'ID parameter required']);
            }
            break;

        default:
            $response = [
                'success' => false,
                'message' => 'Invalid action specified: ' . $action . '. Valid actions: all, filtered, statistics, filtered_statistics, single'
            ];
    }

    // Output JSON response
    sendResponse($response);
} else {
    // Method not allowed
    http_response_code(405);
    sendResponse([
        'success' => false,
        'message' => 'Method not allowed. Only GET requests are supported.'
    ]);
}

// Close database connection
if (isset($conn)) {
    mysqli_close($conn);
}
