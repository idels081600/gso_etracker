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

function fuelTrackerEnsureVehicleDriverName(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'driver_name'");
    if ($result && $result->num_rows > 0) {
        $ensured = true;
        return;
    }

    if (!$conn->query("ALTER TABLE vehicles ADD COLUMN driver_name VARCHAR(150) NULL AFTER type_of_vehicle")) {
        throw new RuntimeException('Unable to add vehicles.driver_name: ' . $conn->error);
    }

    $ensured = true;
}

function fuelTrackerEnsureScopeColumns(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    fuelTrackerEnsureVehicleSchedules($conn);

    $vehicleScope = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'vehicle_scope'");
    if ($vehicleScope && $vehicleScope->num_rows === 0) {
        if (!$conn->query("ALTER TABLE vehicles ADD COLUMN vehicle_scope VARCHAR(20) NOT NULL DEFAULT 'government' AFTER schedules")) {
            throw new RuntimeException('Unable to add vehicles.vehicle_scope: ' . $conn->error);
        }
    }

    $issuanceScope = $conn->query("SHOW COLUMNS FROM gas_issuances LIKE 'issuance_scope'");
    if ($issuanceScope && $issuanceScope->num_rows === 0) {
        if (!$conn->query("ALTER TABLE gas_issuances ADD COLUMN issuance_scope VARCHAR(20) NOT NULL DEFAULT 'government' AFTER status")) {
            throw new RuntimeException('Unable to add gas_issuances.issuance_scope: ' . $conn->error);
        }
    }

    $conn->query("UPDATE vehicles SET vehicle_scope = 'government' WHERE vehicle_scope IS NULL OR TRIM(vehicle_scope) = ''");
    $conn->query("UPDATE gas_issuances SET issuance_scope = 'government' WHERE issuance_scope IS NULL OR TRIM(issuance_scope) = ''");

    $vehicleIndex = $conn->query("SHOW INDEX FROM vehicles WHERE Key_name = 'idx_vehicles_scope_status'");
    if ($vehicleIndex && $vehicleIndex->num_rows === 0) {
        $conn->query("CREATE INDEX idx_vehicles_scope_status ON vehicles (vehicle_scope, status, id)");
    }

    $issuanceIndex = $conn->query("SHOW INDEX FROM gas_issuances WHERE Key_name = 'idx_gas_issuances_scope_status_date'");
    if ($issuanceIndex && $issuanceIndex->num_rows === 0) {
        $conn->query("CREATE INDEX idx_gas_issuances_scope_status_date ON gas_issuances (issuance_scope, status, issue_date, id)");
    }

    $ensured = true;
}

function fuelTrackerCacheDirectory(): string
{
    $directory = __DIR__ . DIRECTORY_SEPARATOR . '.cache';
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    return $directory;
}

function fuelTrackerCachePath(string $prefix, string $key): string
{
    $safePrefix = preg_replace('/[^a-z0-9_-]+/i', '_', $prefix) ?: 'cache';
    return fuelTrackerCacheDirectory() . DIRECTORY_SEPARATOR . $safePrefix . '_' . sha1($key) . '.json';
}

function fuelTrackerCacheGet(string $prefix, string $key, int $ttlSeconds): ?array
{
    $path = fuelTrackerCachePath($prefix, $key);
    if (!is_file($path) || time() - filemtime($path) > $ttlSeconds) {
        return null;
    }

    $payload = json_decode((string) file_get_contents($path), true);
    return is_array($payload) ? $payload : null;
}

function fuelTrackerCacheSet(string $prefix, string $key, array $payload): void
{
    $path = fuelTrackerCachePath($prefix, $key);
    file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function fuelTrackerCacheClear(string $prefix): void
{
    $safePrefix = preg_replace('/[^a-z0-9_-]+/i', '_', $prefix) ?: 'cache';
    foreach (glob(fuelTrackerCacheDirectory() . DIRECTORY_SEPARATOR . $safePrefix . '_*.json') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function fuelTrackerClearGasIssuanceCache(): void
{
    fuelTrackerCacheClear('gas_issuance_page');
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

    $aliases = [
        'm' => 'monday',
        'mon' => 'monday',
        'monday' => 'monday',
        't' => 'tuesday',
        'tu' => 'tuesday',
        'tue' => 'tuesday',
        'tues' => 'tuesday',
        'tuesday' => 'tuesday',
        'w' => 'wednesday',
        'wed' => 'wednesday',
        'weds' => 'wednesday',
        'wednesday' => 'wednesday',
        'th' => 'thursday',
        'thu' => 'thursday',
        'thur' => 'thursday',
        'thurs' => 'thursday',
        'thursday' => 'thursday',
        'f' => 'friday',
        'fri' => 'friday',
        'friday' => 'friday',
        'sat' => 'saturday',
        'saturday' => 'saturday',
        'sun' => 'sunday',
        'sunday' => 'sunday',
    ];

    $selected = [];
    $tokens = preg_split('/[^a-z]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($tokens) === 1 && preg_match('/^[mtwf]+$/', $tokens[0]) === 1) {
        $tokens = str_split($tokens[0]);
    }

    foreach ($tokens as $token) {
        if (isset($aliases[$token])) {
            $selected[] = $aliases[$token];
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
    fuelTrackerEnsureVehicleDriverName($conn);
    fuelTrackerEnsureScopeColumns($conn);
    fuelTrackerEnsureQueryIndexes($conn);

    $date ??= new DateTimeImmutable('today');
    $date = DateTimeImmutable::createFromInterface($date);
    $issueDate = $date->format('Y-m-d');
    $expiryDate = $date->modify('+7 days')->format('Y-m-d');

    $result = $conn->query("
        SELECT
            id,
            office,
            driver_name,
            fuel_type,
            fuel_capacity,
            fixed_liters,
            balance_tank,
            schedules
        FROM vehicles
        WHERE LOWER(TRIM(COALESCE(status, ''))) IN ('', 'active')
            AND LOWER(TRIM(COALESCE(vehicle_scope, 'government'))) <> 'private'
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
            AND LOWER(TRIM(COALESCE(issuance_scope, 'government'))) <> 'private'
        LIMIT 1
    ");
    $insert = $conn->prepare("
        INSERT INTO gas_issuances
            (serial_no, vehicle_id, driver_name, office, purpose, fuel_type, authorized_liters, unit, issue_date, expiry_date, status, issuance_scope, approved_at)
        VALUES
            (?, ?, ?, ?, 'OFFICIAL TRAVEL', ?, ?, 'Liters', ?, ?, 'draft', 'government', NULL)
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
        $driverName = trim((string) ($vehicle['driver_name'] ?? '')) ?: 'TBD';
        $fuelType = trim((string) ($vehicle['fuel_type'] ?? 'unleaded')) ?: 'unleaded';
        $authorizedLiters = fuelTrackerScheduledIssuanceLiters($vehicle);

        if ($authorizedLiters <= 0) {
            continue;
        }

        $insert->bind_param('sisssdss', $serialNo, $vehicleId, $driverName, $office, $fuelType, $authorizedLiters, $issueDate, $expiryDate);
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

function fuelTrackerFetchVehicles(mysqli $conn, string $scope = 'government'): array
{
    fuelTrackerEnsureVehiclePastOdometer($conn);
    fuelTrackerEnsureVehicleSchedules($conn);
    fuelTrackerEnsureVehicleBalanceTank($conn);
    fuelTrackerEnsureVehicleFixedLiters($conn);
    fuelTrackerEnsureVehicleDriverName($conn);
    fuelTrackerEnsureScopeColumns($conn);
    fuelTrackerEnsureQueryIndexes($conn);

    $scope = strtolower(trim($scope));
    $where = '';
    if ($scope === 'private') {
        $where = "WHERE LOWER(TRIM(COALESCE(vehicle_scope, 'government'))) = 'private'";
    } elseif ($scope !== 'all') {
        $where = "WHERE LOWER(TRIM(COALESCE(vehicle_scope, 'government'))) <> 'private'";
    }

    $sql = "
        SELECT
            id,
            vehicle_id,
            plate_no,
            type_of_vehicle,
            driver_name,
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
            status,
            vehicle_scope
        FROM vehicles
        {$where}
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

    fuelTrackerEnsureScopeColumns($conn);
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

function fuelTrackerFetchGasIssuances(
    mysqli $conn,
    array $statuses = [],
    string $scope = 'government',
    int $limit = 0,
    string $orderMode = 'latest',
    ?string $dateFrom = null,
    ?string $dateTo = null
): array {
    fuelTrackerEnsureScopeColumns($conn);
    fuelTrackerEnsureQueryIndexes($conn);

    $conditions = [];
    if ($statuses !== []) {
        $allowedStatuses = array_values(array_intersect(
            array_map('strtolower', $statuses),
            ['draft', 'approved', 'valid', 'used', 'expired', 'revoked']
        ));

        if ($allowedStatuses !== []) {
            $quoted = array_map(static fn(string $status): string => "'" . $status . "'", $allowedStatuses);
            $conditions[] = 'LOWER(gi.status) IN (' . implode(',', $quoted) . ')';
        }
    }

    $scope = strtolower(trim($scope));
    if ($scope === 'private') {
        $conditions[] = "LOWER(TRIM(COALESCE(gi.issuance_scope, 'government'))) = 'private'";
    } elseif ($scope !== 'all') {
        $conditions[] = "LOWER(TRIM(COALESCE(gi.issuance_scope, 'government'))) <> 'private'";
    }

    if (is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
        $conditions[] = "gi.issue_date >= '" . $conn->real_escape_string($dateFrom) . "'";
    }

    if (is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
        $conditions[] = "gi.issue_date <= '" . $conn->real_escape_string($dateTo) . "'";
    }

    $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $limitClause = $limit > 0 ? ' LIMIT ' . min($limit, 1000) : '';

    $orderMode = strtolower(trim($orderMode));
    $orderBy = $orderMode === 'schedule_window'
        ? 'CASE WHEN gi.issue_date >= CURDATE() THEN 0 ELSE 1 END, gi.issue_date ASC, gi.id DESC'
        : 'gi.id DESC';

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
            gi.issuance_scope,
            gi.approved_by,
            gi.approved_at,
            v.plate_no,
            v.type_of_vehicle AS vehicle_type,
            v.vehicle_scope,
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
        ORDER BY {$orderBy}
        {$limitClause}
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Unable to load gas issuances: ' . $conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fuelTrackerFetchGasIssuancesByIds(mysqli $conn, array $ids, string $scope = 'government'): array
{
    fuelTrackerEnsureScopeColumns($conn);
    fuelTrackerEnsureQueryIndexes($conn);

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if ($ids === []) {
        return [];
    }

    $idList = implode(',', $ids);
    $scope = strtolower(trim($scope));
    $scopeCondition = '';
    if ($scope === 'private') {
        $scopeCondition = "AND LOWER(TRIM(COALESCE(gi.issuance_scope, 'government'))) = 'private'";
    } elseif ($scope !== 'all') {
        $scopeCondition = "AND LOWER(TRIM(COALESCE(gi.issuance_scope, 'government'))) <> 'private'";
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
            gi.issuance_scope,
            gi.approved_by,
            gi.approved_at,
            v.plate_no,
            v.type_of_vehicle AS vehicle_type,
            v.vehicle_scope,
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
        {$scopeCondition}
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
