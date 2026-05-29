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

function fuelTrackerEnsureVehicleSchedules(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'schedules'");
    if ($result && $result->num_rows > 0) {
        $ensured = true;
        return;
    }

    if (!$conn->query("ALTER TABLE vehicles ADD COLUMN schedules VARCHAR(120) NULL AFTER fuel_type")) {
        throw new RuntimeException('Unable to add vehicles.schedules: ' . $conn->error);
    }

    $ensured = true;
}

function fuelTrackerEnsureVehicleBalanceTank(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'balance_tank'");
    if ($result && $result->num_rows > 0) {
        $ensured = true;
        return;
    }

    if (!$conn->query("ALTER TABLE vehicles ADD COLUMN balance_tank DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER status")) {
        throw new RuntimeException('Unable to add vehicles.balance_tank: ' . $conn->error);
    }

    $ensured = true;
}

function fuelTrackerEnsureVehicleFixedLiters(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'fixed_liters'");
    if ($result && $result->num_rows > 0) {
        $ensured = true;
        return;
    }

    if (!$conn->query("ALTER TABLE vehicles ADD COLUMN fixed_liters DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER fuel_capacity")) {
        throw new RuntimeException('Unable to add vehicles.fixed_liters: ' . $conn->error);
    }

    $ensured = true;
}

function fuelTrackerNormalizeSchedule(mixed $value): string
{
    $text = strtolower(trim((string) $value));
    if ($text === '') {
        return '';
    }

    if (str_contains($text, 'as_needed') || str_contains($text, 'as needed') || str_contains($text, 'as-needed')) {
        return 'as_needed';
    }

    if (str_contains($text, 'daily') || str_contains($text, 'everyday') || str_contains($text, 'every day')) {
        return 'daily';
    }

    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $selected = [];
    foreach ($days as $day) {
        if (preg_match('/\b' . preg_quote($day, '/') . '\b/', $text) === 1) {
            $selected[] = $day;
        }
    }

    return implode(',', array_values(array_unique($selected)));
}

function fuelTrackerScheduleMatchesDate(mixed $schedule, ?DateTimeInterface $date = null): bool
{
    $normalized = fuelTrackerNormalizeSchedule($schedule);
    if ($normalized === '') {
        return false;
    }
    if ($normalized === 'as_needed') {
        $date ??= new DateTimeImmutable('today');
        return $date->format('Y-m-d') === (new DateTimeImmutable('today'))->format('Y-m-d');
    }
    if ($normalized === 'daily') {
        return true;
    }

    $date ??= new DateTimeImmutable('today');
    $today = strtolower($date->format('l'));
    return in_array($today, explode(',', $normalized), true);
}

function fuelTrackerScheduledIssuanceLiters(array $vehicle): float
{
    $fixedLiters = max(0.0, (float) ($vehicle['fixed_liters'] ?? 0));
    if ($fixedLiters > 0) {
        return $fixedLiters;
    }

    $capacity = max(0.0, (float) ($vehicle['fuel_capacity'] ?? $vehicle['capacity'] ?? 0));
    $balanceTank = max(0.0, (float) ($vehicle['balance_tank'] ?? 0));
    $liters = $capacity - $balanceTank;

    return $liters > 0 ? $liters : $capacity;
}

function fuelTrackerCreateScheduledIssuances(mysqli $conn, ?DateTimeInterface $date = null): int
{
    fuelTrackerEnsureVehicleSchedules($conn);
    fuelTrackerEnsureVehicleBalanceTank($conn);
    fuelTrackerEnsureVehicleFixedLiters($conn);
    fuelTrackerEnsureQueryIndexes($conn);

    $date ??= new DateTimeImmutable('today');
    $date = DateTimeImmutable::createFromInterface($date);
    $issueDate = $date->format('Y-m-d');
    $expiryDate = $date->modify('+7 days')->format('Y-m-d');

    $result = $conn->query("
        SELECT
            id,
            office,
            fuel_type,
            fuel_capacity,
            fixed_liters,
            balance_tank,
            schedules
        FROM vehicles
        WHERE LOWER(TRIM(COALESCE(status, ''))) IN ('', 'active')
            AND TRIM(COALESCE(schedules, '')) <> ''
    ");
    if (!$result) {
        throw new RuntimeException('Unable to load scheduled vehicles: ' . $conn->error);
    }

    $vehicles = $result->fetch_all(MYSQLI_ASSOC);
    if ($vehicles === []) {
        return 0;
    }

    $exists = $conn->prepare("
        SELECT id
        FROM gas_issuances
        WHERE vehicle_id = ?
            AND issue_date = ?
        LIMIT 1
    ");
    $insert = $conn->prepare("
        INSERT INTO gas_issuances
            (serial_no, vehicle_id, driver_name, office, purpose, fuel_type, authorized_liters, unit, issue_date, expiry_date, status, approved_at)
        VALUES
            (?, ?, 'TBD', ?, 'OFFICIAL TRAVEL', ?, ?, 'Liters', ?, ?, 'draft', NULL)
    ");

    $created = 0;
    foreach ($vehicles as $vehicle) {
        if (!fuelTrackerScheduleMatchesDate($vehicle['schedules'] ?? '', $date)) {
            continue;
        }

        $vehicleId = (int) ($vehicle['id'] ?? 0);
        if ($vehicleId <= 0) {
            continue;
        }

        $exists->bind_param('is', $vehicleId, $issueDate);
        $exists->execute();
        if ($exists->get_result()->fetch_assoc()) {
            continue;
        }

        $serialNo = 'FI-' . $date->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $office = trim((string) ($vehicle['office'] ?? 'Office')) ?: 'Office';
        $fuelType = trim((string) ($vehicle['fuel_type'] ?? 'unleaded')) ?: 'unleaded';
        $authorizedLiters = fuelTrackerScheduledIssuanceLiters($vehicle);

        if ($authorizedLiters <= 0) {
            continue;
        }

        $insert->bind_param('sissdss', $serialNo, $vehicleId, $office, $fuelType, $authorizedLiters, $issueDate, $expiryDate);
        $insert->execute();
        $created++;
    }

    $exists->close();
    $insert->close();

    return $created;
}

function fuelTrackerCreateUpcomingScheduledIssuances(mysqli $conn, int $daysAhead = 14, ?DateTimeInterface $startDate = null): int
{
    $daysAhead = max(0, min(62, $daysAhead));
    $startDate ??= new DateTimeImmutable('today');
    $startDate = DateTimeImmutable::createFromInterface($startDate);
    $created = 0;

    for ($offset = 0; $offset <= $daysAhead; $offset++) {
        $created += fuelTrackerCreateScheduledIssuances($conn, $startDate->modify('+' . $offset . ' days'));
    }

    return $created;
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
    fuelTrackerEnsureVehicleSchedules($conn);
    fuelTrackerEnsureVehicleBalanceTank($conn);
    fuelTrackerEnsureVehicleFixedLiters($conn);
    fuelTrackerEnsureQueryIndexes($conn);

    $sql = "
        SELECT
            id,
            vehicle_id,
            plate_no,
            type_of_vehicle,
            office,
            fuel_type,
            schedules,
            balance_tank,
            past_odometer,
            current_odometer AS current_odo,
            fuel_capacity AS capacity,
            fixed_liters,
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
