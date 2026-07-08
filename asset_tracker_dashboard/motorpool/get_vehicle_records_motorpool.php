<?php
require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/api_helpers.php';
asset_require_auth();

try {
    require_once dirname(__DIR__) . '/db_asset.php';

    if (!isset($conn) || !$conn) {
        throw new RuntimeException('Database connection failed.');
    }

    $query = "SELECT id, plate_no, car_model, office, status, old_mileage, latest_mileage, no_of_repairs, new_repair_date, date_procured, no_dispatch FROM vehicle_records ORDER BY plate_no ASC";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        throw new Exception('Unable to load vehicle records.');
    }

    $vehicles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $latest_repair_date = !empty($row['new_repair_date']) ? date('Y-m-d', strtotime($row['new_repair_date'])) : '';
        $date_procured = !empty($row['date_procured']) ? date('Y-m-d', strtotime($row['date_procured'])) : '';

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

    api_response(true, 'Vehicle records loaded.', [
        'vehicles' => $vehicles,
        'count' => count($vehicles),
    ]);
} catch (Throwable $error) {
    api_database_error($error);
}

