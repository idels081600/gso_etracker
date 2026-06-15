<?php
require_once __DIR__ . '/app_security.php';
asset_require_auth();
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Include database connection
    require_once __DIR__ . '/db_asset.php';

    // Check connection
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed: " . (mysqli_connect_error() ?? "Unknown error"));
    }

    // Query to get all vehicle records
    $query = "SELECT id, plate_no, car_model, office, status, old_mileage, latest_mileage, no_of_repairs, new_repair_date, date_procured, no_dispatch FROM vehicle_records ORDER BY plate_no ASC";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        throw new Exception('Unable to load vehicle records.');
    }

    // Fetch all records
    $vehicles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Format dates
        $latest_repair_date = !empty($row['new_repair_date']) ? date('Y-m-d', strtotime($row['new_repair_date'])) : '';
        $date_procured = !empty($row['date_procured']) ? date('Y-m-d', strtotime($row['date_procured'])) : '';

        // Add to vehicles array
        $vehicles[] = [
            'plate_no' => $row['plate_no'],
            'id' => $row['id'],
            'car_model' => $row['car_model'] ?? '',
            'office' => $row['office'] ?? '',
            'status' => $row['status'],
            'old_mileage' => $row['old_mileage'],
            'latest_mileage' => $row['latest_mileage'],
            'no_of_repairs' => $row['no_of_repairs'],
            'new_repair_date' => $latest_repair_date,
            'date_procured' => $date_procured,
            'no_dispatch' => $row['no_dispatch']
        ];
    }

    mysqli_close($conn);

    // Return data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $vehicles,
        'count' => count($vehicles)
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

