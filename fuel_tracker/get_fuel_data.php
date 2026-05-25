<?php
require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'json');
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(120, 60, 'get_fuel_data', 'json');

// Include database connection
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fuel_budget_data.php';
require_once __DIR__ . '/gas_issuance_data.php';

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

function fuelTableExists(): bool
{
    global $conn;
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $tableResult = mysqli_query($conn, "SHOW TABLES LIKE 'fuel'");
    $exists = $tableResult && mysqli_num_rows($tableResult) > 0;

    return $exists;
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
        if (!fuelTableExists()) {
            logError("Fuel table does not exist");
            return [
                'success' => false,
                'data' => [],
                'count' => 0,
                'message' => 'Fuel table does not exist in database'
            ];
        }

        // SQL query to select all data from fuel table
        $sql = "SELECT * FROM fuel ORDER BY date DESC, id DESC LIMIT 300";
        $result = mysqli_query($conn, $sql);

        if (!$result) {
            throw new Exception("Query failed: " . mysqli_error($conn));
        }

        $fuelRecords = [];

        // Fetch all records
        while ($row = mysqli_fetch_assoc($result)) {
            $fuelRecords[] = $row;
        }

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
        $sql .= " ORDER BY date DESC, id DESC LIMIT 300";

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
        fuelTrackerSyncIssuanceOffices($conn);

        $sql = "SELECT 
                    gi.fuel_type,
                    COUNT(*) as total_records,
                    SUM(COALESCE(gi.authorized_liters, 0)) as total_liters,
                    COUNT(CASE WHEN gi.issue_date = CURDATE() THEN 1 END) as today_records,
                    COUNT(CASE WHEN MONTH(gi.issue_date) = MONTH(CURDATE()) AND YEAR(gi.issue_date) = YEAR(CURDATE()) THEN 1 END) as month_records,
                    MIN(gi.issue_date) as earliest_date,
                    MAX(gi.issue_date) as latest_date
                FROM gas_issuances gi
                WHERE LOWER(gi.status) = 'used'
                    AND gi.fuel_type IS NOT NULL
                    AND TRIM(gi.fuel_type) != ''
                GROUP BY gi.fuel_type
                ORDER BY gi.fuel_type";

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
        fuelTrackerSyncIssuanceOffices($conn);

        $sql = "SELECT 
                    gi.fuel_type,
                    COUNT(*) as total_records,
                    SUM(COALESCE(gi.authorized_liters, 0)) as total_liters,
                    AVG(COALESCE(gi.authorized_liters, 0)) as avg_liters,
                    MIN(gi.issue_date) as period_start,
                    MAX(gi.issue_date) as period_end
                FROM gas_issuances gi
                WHERE LOWER(gi.status) = 'used'
                    AND gi.fuel_type IS NOT NULL
                    AND TRIM(gi.fuel_type) != ''";

        $params = [];
        $types = "";

        // Add date filters if provided
        if (!empty($filters['date_from'])) {
            $sql .= " AND gi.issue_date >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND gi.issue_date <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }

        $sql .= " GROUP BY gi.fuel_type ORDER BY gi.fuel_type";

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

function getFuelBudgetSummary(): array
{
    global $conn;

    try {
        return [
            'success' => true,
            'data' => fuelBudgetSummary($conn),
            'message' => 'Fuel budget summary retrieved successfully'
        ];
    } catch (Throwable $e) {
        logError('Error in getFuelBudgetSummary: ' . $e->getMessage());
        return [
            'success' => false,
            'data' => [
                'total_budget' => 0,
                'used_budget' => 0,
                'remaining_budget' => 0,
                'budgets' => [],
            ],
            'message' => 'Error retrieving fuel budget summary'
        ];
    }
}

function getFuelBudgetDeductions(array $filters = []): array
{
    global $conn;

    try {
        fuelBudgetEnsureTables($conn);

        $conditions = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($filters['date_from'])) {
            $conditions[] = 'd.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
            $types .= 's';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'd.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(b.ib_no LIKE ? OR d.office LIKE ? OR d.created_by LIKE ? OR d.summary_group_hash LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            array_push($params, $search, $search, $search, $search);
            $types .= 'ssss';
        }

        $sql = "
            SELECT
                d.id,
                b.ib_no,
                d.office,
                d.start_date,
                d.end_date,
                d.diesel_price,
                d.unleaded_price,
                d.diesel_liters,
                d.unleaded_liters,
                d.diesel_liters * d.diesel_price AS diesel_amount,
                d.unleaded_liters * d.unleaded_price AS unleaded_amount,
                d.total_amount,
                d.created_by,
                d.created_at,
                d.summary_group_hash
            FROM fuel_budget_deductions d
            INNER JOIN fuel_budgets b ON b.id = d.budget_id
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY d.created_at DESC, d.id DESC
            LIMIT 300
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare budget deductions query: ' . $conn->error);
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            'success' => true,
            'data' => $rows,
            'count' => count($rows),
            'message' => 'Budget deduction transactions retrieved successfully',
        ];
    } catch (Throwable $e) {
        logError('Error in getFuelBudgetDeductions: ' . $e->getMessage());
        return [
            'success' => false,
            'data' => [],
            'count' => 0,
            'message' => 'Error retrieving budget deduction transactions',
        ];
    }
}

function getFuelConsumptionRankings(): array
{
    global $conn;

    try {
        fuelTrackerSyncIssuanceOffices($conn);

        $rankings = [
            'offices' => [],
            'vehicles' => [],
        ];

        $officeSql = "
            SELECT
                COALESCE(NULLIF(TRIM(gi.office), ''), NULLIF(TRIM(v.office), ''), 'Unassigned Office') AS label,
                SUM(CASE WHEN LOWER(gi.fuel_type) LIKE '%diesel%' THEN COALESCE(gi.authorized_liters, 0) ELSE 0 END) AS diesel_liters,
                SUM(CASE WHEN LOWER(gi.fuel_type) NOT LIKE '%diesel%' THEN COALESCE(gi.authorized_liters, 0) ELSE 0 END) AS unleaded_liters,
                SUM(COALESCE(gi.authorized_liters, 0)) AS total_liters
            FROM gas_issuances gi
            INNER JOIN vehicles v ON v.id = gi.vehicle_id
            WHERE LOWER(gi.status) = 'used'
            GROUP BY label
            HAVING total_liters > 0
            ORDER BY total_liters DESC
            LIMIT 10
        ";
        $officeResult = $conn->query($officeSql);
        if ($officeResult) {
            $rankings['offices'] = $officeResult->fetch_all(MYSQLI_ASSOC);
        }

        $vehicleSql = "
            SELECT
                TRIM(CONCAT(v.type_of_vehicle, ' ', v.plate_no)) AS label,
                SUM(CASE WHEN LOWER(gi.fuel_type) LIKE '%diesel%' THEN COALESCE(gi.authorized_liters, 0) ELSE 0 END) AS diesel_liters,
                SUM(CASE WHEN LOWER(gi.fuel_type) NOT LIKE '%diesel%' THEN COALESCE(gi.authorized_liters, 0) ELSE 0 END) AS unleaded_liters,
                SUM(COALESCE(gi.authorized_liters, 0)) AS total_liters
            FROM gas_issuances gi
            INNER JOIN vehicles v ON v.id = gi.vehicle_id
            WHERE LOWER(gi.status) = 'used'
            GROUP BY label
            HAVING total_liters > 0
            ORDER BY total_liters DESC
            LIMIT 10
        ";
        $vehicleResult = $conn->query($vehicleSql);
        if ($vehicleResult) {
            $rankings['vehicles'] = $vehicleResult->fetch_all(MYSQLI_ASSOC);
        }

        return [
            'success' => true,
            'data' => $rankings,
            'message' => 'Fuel consumption rankings retrieved successfully'
        ];
    } catch (Throwable $e) {
        logError('Error in getFuelConsumptionRankings: ' . $e->getMessage());
        return [
            'success' => false,
            'data' => ['offices' => [], 'vehicles' => []],
            'message' => 'Error retrieving fuel consumption rankings'
        ];
    }
}

// Handle different request types
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'all';

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

        case 'budget_summary':
            $response = getFuelBudgetSummary();
            break;

        case 'budget_deductions':
            $filters = [];
            if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            $response = getFuelBudgetDeductions($filters);
            break;

        case 'consumption_rankings':
            $response = getFuelConsumptionRankings();
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
                'message' => 'Invalid action specified: ' . $action . '. Valid actions: all, filtered, statistics, filtered_statistics, budget_summary, budget_deductions, consumption_rankings, single'
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
