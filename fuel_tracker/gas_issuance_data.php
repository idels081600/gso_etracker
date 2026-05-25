<?php
declare(strict_types=1);

function fuelTrackerEnsureVehiclePastOdometer(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'past_odometer'");
    if ($result && $result->num_rows > 0) {
        $ensured = true;
        return;
    }

    if (!$conn->query('ALTER TABLE vehicles ADD COLUMN past_odometer DECIMAL(12,1) NULL AFTER current_odometer')) {
        throw new RuntimeException('Unable to add vehicles.past_odometer: ' . $conn->error);
    }

    $ensured = true;
}

function fuelTrackerEnsureQueryIndexes(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $indexes = [
        ['gas_issuances', 'idx_gas_issuances_status_issue_date', 'CREATE INDEX idx_gas_issuances_status_issue_date ON gas_issuances (status, issue_date, id)'],
        ['gas_issuances', 'idx_gas_issuances_vehicle_id', 'CREATE INDEX idx_gas_issuances_vehicle_id ON gas_issuances (vehicle_id)'],
        ['vehicle_odometer_logs', 'idx_vehicle_odometer_logs_issuance_id', 'CREATE INDEX idx_vehicle_odometer_logs_issuance_id ON vehicle_odometer_logs (gas_issuance_id, id)'],
    ];

    foreach ($indexes as [$table, $indexName, $sql]) {
        $check = $conn->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
        if ($check && $check->num_rows === 0) {
            $conn->query($sql);
        }
    }

    $ensured = true;
}

function fuelTrackerFetchVehicles(mysqli $conn): array
{
    fuelTrackerEnsureVehiclePastOdometer($conn);
    fuelTrackerEnsureQueryIndexes($conn);

    $sql = "
        SELECT
            id,
            vehicle_id,
            plate_no,
            type_of_vehicle,
            office,
            fuel_type,
            past_odometer,
            current_odometer AS current_odo,
            fuel_capacity AS capacity,
            number_of_cylinder AS cylinders,
            normal_km_per_liter,
            status
        FROM vehicles
        ORDER BY plate_no ASC, type_of_vehicle ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Unable to load vehicles: ' . $conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fuelTrackerSyncIssuanceOffices(mysqli $conn): void
{
    static $synced = false;

    if ($synced) {
        return;
    }

    fuelTrackerEnsureQueryIndexes($conn);

    $sql = "
        UPDATE gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        SET gi.office = v.office,
            gi.updated_at = CURRENT_TIMESTAMP
        WHERE TRIM(COALESCE(v.office, '')) <> ''
            AND (
                gi.office IS NULL
                OR TRIM(gi.office) = ''
                OR LOWER(TRIM(gi.office)) = 'office'
            )
    ";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to sync gas issuance offices: ' . $conn->error);
    }

    $fuelSql = "
        UPDATE gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        SET gi.fuel_type = v.fuel_type,
            gi.updated_at = CURRENT_TIMESTAMP
        WHERE TRIM(COALESCE(v.fuel_type, '')) <> ''
            AND (
                gi.fuel_type IS NULL
                OR TRIM(gi.fuel_type) = ''
                OR LOWER(TRIM(gi.fuel_type)) <> LOWER(TRIM(v.fuel_type))
            )
    ";

    if (!$conn->query($fuelSql)) {
        throw new RuntimeException('Unable to sync gas issuance fuel types: ' . $conn->error);
    }

    $synced = true;
}

function fuelTrackerFetchGasIssuances(mysqli $conn, array $statuses = []): array
{
    fuelTrackerEnsureQueryIndexes($conn);

    $where = '';
    if ($statuses !== []) {
        $allowedStatuses = array_values(array_intersect(
            array_map('strtolower', $statuses),
            ['draft', 'approved', 'valid', 'used', 'expired', 'revoked']
        ));

        if ($allowedStatuses !== []) {
            $quoted = array_map(static fn(string $status): string => "'" . $status . "'", $allowedStatuses);
            $where = 'WHERE LOWER(gi.status) IN (' . implode(',', $quoted) . ')';
        }
    }

    $sql = "
        SELECT
            gi.id,
            gi.serial_no,
            gi.vehicle_id,
            gi.driver_name,
            gi.office,
            gi.purpose,
            gi.fuel_type,
            gi.authorized_liters,
            gi.actual_liters_fueled,
            gi.unit,
            gi.issue_date,
            gi.expiry_date,
            gi.status,
            gi.approved_by,
            gi.approved_at,
            v.plate_no,
            v.type_of_vehicle AS vehicle_type,
            v.current_odometer AS current_odo,
            v.number_of_cylinder AS cylinders,
            v.normal_km_per_liter,
            v.fuel_capacity AS capacity,
            ol.past_odometer,
            ol.current_odometer
        FROM gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        LEFT JOIN vehicle_odometer_logs ol
            ON ol.id = (
                SELECT MAX(vol.id)
                FROM vehicle_odometer_logs vol
                WHERE vol.gas_issuance_id = gi.id
            )
        {$where}
        ORDER BY gi.id DESC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Unable to load gas issuances: ' . $conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fuelTrackerFetchGasIssuancesByIds(mysqli $conn, array $ids): array
{
    fuelTrackerEnsureQueryIndexes($conn);

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if ($ids === []) {
        return [];
    }

    $idList = implode(',', $ids);
    $sql = "
        SELECT
            gi.id,
            gi.serial_no,
            gi.vehicle_id,
            gi.driver_name,
            gi.office,
            gi.purpose,
            gi.fuel_type,
            gi.authorized_liters,
            gi.actual_liters_fueled,
            gi.unit,
            gi.issue_date,
            gi.expiry_date,
            gi.status,
            gi.approved_by,
            gi.approved_at,
            v.plate_no,
            v.type_of_vehicle AS vehicle_type,
            v.current_odometer AS current_odo,
            v.number_of_cylinder AS cylinders,
            v.normal_km_per_liter,
            v.fuel_capacity AS capacity,
            ol.past_odometer,
            ol.current_odometer
        FROM gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        LEFT JOIN vehicle_odometer_logs ol
            ON ol.id = (
                SELECT MAX(vol.id)
                FROM vehicle_odometer_logs vol
                WHERE vol.gas_issuance_id = gi.id
            )
        WHERE gi.id IN ({$idList})
        ORDER BY gi.issue_date ASC, gi.id ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Unable to load selected gas issuances: ' . $conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fuelTrackerVehicleLookupByPlate(array $vehicles): array
{
    $lookup = [];
    foreach ($vehicles as $vehicle) {
        $plate = strtoupper((string) ($vehicle['plate_no'] ?? ''));
        if ($plate !== '') {
            $lookup[$plate] = $vehicle;
        }
    }

    return $lookup;
}
