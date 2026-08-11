<?php
declare(strict_types=1);

function fuelBudgetEnsureTables(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $budgetSql = "
        CREATE TABLE IF NOT EXISTS fuel_budgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ib_no VARCHAR(80) NOT NULL UNIQUE,
            description VARCHAR(255) NULL,
            budget_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            diesel_allocation DECIMAL(14,2) NOT NULL DEFAULT 0,
            unleaded_allocation DECIMAL(14,2) NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($budgetSql)) {
        throw new RuntimeException('Unable to prepare fuel_budgets table: ' . $conn->error);
    }

    $dieselBudgetColumn = $conn->query("SHOW COLUMNS FROM fuel_budgets LIKE 'diesel_allocation'");
    if ($dieselBudgetColumn && $dieselBudgetColumn->num_rows === 0) {
        if (!$conn->query("ALTER TABLE fuel_budgets ADD COLUMN diesel_allocation DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER budget_amount")) {
            throw new RuntimeException('Unable to add diesel budget allocation: ' . $conn->error);
        }
        $conn->query("UPDATE fuel_budgets SET diesel_allocation = budget_amount WHERE diesel_allocation = 0");
    }

    $unleadedBudgetColumn = $conn->query("SHOW COLUMNS FROM fuel_budgets LIKE 'unleaded_allocation'");
    if ($unleadedBudgetColumn && $unleadedBudgetColumn->num_rows === 0) {
        if (!$conn->query("ALTER TABLE fuel_budgets ADD COLUMN unleaded_allocation DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER diesel_allocation")) {
            throw new RuntimeException('Unable to add unleaded budget allocation: ' . $conn->error);
        }
    }

    $deductionSql = "
        CREATE TABLE IF NOT EXISTS fuel_budget_deductions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            budget_id INT NOT NULL,
            summary_hash CHAR(64) NOT NULL UNIQUE,
            office VARCHAR(150) NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            diesel_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            unleaded_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            diesel_liters DECIMAL(12,2) NOT NULL DEFAULT 0,
            unleaded_liters DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            created_by VARCHAR(150) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_fuel_budget_deductions_budget
                FOREIGN KEY (budget_id) REFERENCES fuel_budgets(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($deductionSql)) {
        throw new RuntimeException('Unable to prepare fuel_budget_deductions table: ' . $conn->error);
    }

    $deductionIssuanceSql = "
        CREATE TABLE IF NOT EXISTS fuel_budget_deduction_issuances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            deduction_id INT NOT NULL,
            gas_issuance_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fuel_budget_deduction_issuance (gas_issuance_id),
            KEY idx_fuel_budget_deduction_issuances_deduction (deduction_id),
            CONSTRAINT fk_fuel_budget_deduction_issuances_deduction
                FOREIGN KEY (deduction_id) REFERENCES fuel_budget_deductions(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($deductionIssuanceSql)) {
        throw new RuntimeException('Unable to prepare fuel_budget_deduction_issuances table: ' . $conn->error);
    }

    $weeklyFuelPriceSql = "
        CREATE TABLE IF NOT EXISTS weekly_fuel_prices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            week_start DATE NOT NULL UNIQUE,
            diesel_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            unleaded_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            source_note VARCHAR(255) NULL,
            updated_by VARCHAR(150) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_weekly_fuel_prices_week_start (week_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($weeklyFuelPriceSql)) {
        throw new RuntimeException('Unable to prepare weekly_fuel_prices table: ' . $conn->error);
    }

    $columnCheck = $conn->query("SHOW COLUMNS FROM fuel_budget_deductions LIKE 'summary_group_hash'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        if (!$conn->query("ALTER TABLE fuel_budget_deductions ADD COLUMN summary_group_hash CHAR(64) NULL AFTER summary_hash")) {
            throw new RuntimeException('Unable to update fuel_budget_deductions table: ' . $conn->error);
        }
        $conn->query("UPDATE fuel_budget_deductions SET summary_group_hash = summary_hash WHERE summary_group_hash IS NULL OR summary_group_hash = ''");
    }

    $indexCheck = $conn->query("SHOW INDEX FROM fuel_budget_deductions WHERE Key_name = 'idx_fuel_budget_deductions_group_hash'");
    if ($indexCheck && $indexCheck->num_rows === 0) {
        $conn->query("CREATE INDEX idx_fuel_budget_deductions_group_hash ON fuel_budget_deductions (summary_group_hash)");
    }

    $createdAtIndexCheck = $conn->query("SHOW INDEX FROM fuel_budget_deductions WHERE Key_name = 'idx_fuel_budget_deductions_created_at'");
    if ($createdAtIndexCheck && $createdAtIndexCheck->num_rows === 0) {
        $conn->query("CREATE INDEX idx_fuel_budget_deductions_created_at ON fuel_budget_deductions (created_at)");
    }

    $budgetIndexCheck = $conn->query("SHOW INDEX FROM fuel_budget_deductions WHERE Key_name = 'idx_fuel_budget_deductions_budget_id'");
    if ($budgetIndexCheck && $budgetIndexCheck->num_rows === 0) {
        $conn->query("CREATE INDEX idx_fuel_budget_deductions_budget_id ON fuel_budget_deductions (budget_id)");
    }

    $ensured = true;
}

function fuelBudgetTuesdayForDate(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        $base = new DateTimeImmutable('today');
    } else {
        $base = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$base) {
            throw new InvalidArgumentException('Enter a valid weekly price date.');
        }
    }

    $dayOfWeek = (int) $base->format('N');
    return $base->modify((2 - $dayOfWeek) . ' days')->format('Y-m-d');
}

function fuelBudgetSaveWeeklyFuelPrice(
    mysqli $conn,
    string $weekStart,
    float $dieselPrice,
    float $unleadedPrice,
    string $sourceNote = '',
    string $updatedBy = ''
): array {
    fuelBudgetEnsureTables($conn);
    $weekStart = fuelBudgetTuesdayForDate($weekStart);

    if ($dieselPrice < 0 || $unleadedPrice < 0) {
        throw new InvalidArgumentException('Weekly fuel prices must be zero or greater.');
    }
    if ($dieselPrice <= 0 && $unleadedPrice <= 0) {
        throw new InvalidArgumentException('Enter a diesel price, unleaded price, or both.');
    }

    $stmt = $conn->prepare("
        INSERT INTO weekly_fuel_prices
            (week_start, diesel_price, unleaded_price, source_note, updated_by)
        VALUES
            (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))
        ON DUPLICATE KEY UPDATE
            diesel_price = VALUES(diesel_price),
            unleaded_price = VALUES(unleaded_price),
            source_note = VALUES(source_note),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param('sddss', $weekStart, $dieselPrice, $unleadedPrice, $sourceNote, $updatedBy);
    $stmt->execute();
    $stmt->close();

    return fuelBudgetLatestWeeklyFuelPrice($conn) ?? [
        'week_start' => $weekStart,
        'diesel_price' => $dieselPrice,
        'unleaded_price' => $unleadedPrice,
        'source_note' => $sourceNote,
    ];
}

function fuelBudgetLatestWeeklyFuelPrice(mysqli $conn): ?array
{
    fuelBudgetEnsureTables($conn);
    $result = $conn->query("
        SELECT week_start, diesel_price, unleaded_price, source_note, updated_by, updated_at
        FROM weekly_fuel_prices
        ORDER BY week_start DESC
        LIMIT 1
    ");
    if (!$result) {
        throw new RuntimeException('Unable to load latest weekly fuel price: ' . $conn->error);
    }

    $row = $result->fetch_assoc();
    return $row ?: null;
}

function fuelBudgetWeeklyFuelPriceHistory(mysqli $conn, int $limit = 12): array
{
    fuelBudgetEnsureTables($conn);
    $limit = max(1, min(52, $limit));

    $result = $conn->query("
        SELECT week_start, diesel_price, unleaded_price, source_note, updated_by, updated_at
        FROM (
            SELECT week_start, diesel_price, unleaded_price, source_note, updated_by, updated_at
            FROM weekly_fuel_prices
            ORDER BY week_start DESC
            LIMIT {$limit}
        ) recent_prices
        ORDER BY week_start ASC
    ");
    if (!$result) {
        throw new RuntimeException('Unable to load weekly fuel price history: ' . $conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fuelBudgetNormalizeIssuanceIds(array $sourceIssuanceIds): array
{
    return array_values(array_unique(array_filter(
        array_map('intval', $sourceIssuanceIds),
        static fn(int $id): bool => $id > 0
    )));
}

function fuelBudgetFindDeductedIssuanceIds(mysqli $conn, array $sourceIssuanceIds): array
{
    $sourceIssuanceIds = fuelBudgetNormalizeIssuanceIds($sourceIssuanceIds);
    if ($sourceIssuanceIds === []) {
        return [];
    }

    $idList = implode(',', $sourceIssuanceIds);
    $result = $conn->query("
        SELECT gas_issuance_id
        FROM fuel_budget_deduction_issuances
        WHERE gas_issuance_id IN ({$idList})
    ");
    if (!$result) {
        throw new RuntimeException('Unable to check deducted gas issuances: ' . $conn->error);
    }

    return array_map('intval', array_column($result->fetch_all(MYSQLI_ASSOC), 'gas_issuance_id'));
}

function fuelBudgetLinkDeductedIssuances(mysqli $conn, int $deductionId, array $sourceIssuanceIds): void
{
    $sourceIssuanceIds = fuelBudgetNormalizeIssuanceIds($sourceIssuanceIds);
    if ($deductionId <= 0 || $sourceIssuanceIds === []) {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO fuel_budget_deduction_issuances
            (deduction_id, gas_issuance_id)
        VALUES
            (?, ?)
    ");

    foreach ($sourceIssuanceIds as $gasIssuanceId) {
        $stmt->bind_param('ii', $deductionId, $gasIssuanceId);
        $stmt->execute();
    }

    $stmt->close();
}

function fuelBudgetFetchBudgets(mysqli $conn, bool $activeOnly = true): array
{
    fuelBudgetEnsureTables($conn);

    $where = $activeOnly ? "WHERE b.status = 'active'" : '';
    $sql = "
        SELECT
            b.id,
            b.ib_no,
            b.description,
            b.budget_amount,
            b.diesel_allocation,
            b.unleaded_allocation,
            b.status,
            COALESCE(SUM(d.total_amount), 0) AS used_amount,
            COALESCE(SUM(d.diesel_liters * d.diesel_price), 0) AS used_diesel_amount,
            COALESCE(SUM(d.unleaded_liters * d.unleaded_price), 0) AS used_unleaded_amount,
            b.budget_amount - COALESCE(SUM(d.total_amount), 0) AS remaining_amount,
            GREATEST(b.diesel_allocation - COALESCE(SUM(d.diesel_liters * d.diesel_price), 0), 0) AS remaining_diesel_amount,
            GREATEST(b.unleaded_allocation - COALESCE(SUM(d.unleaded_liters * d.unleaded_price), 0), 0) AS remaining_unleaded_amount
        FROM fuel_budgets b
        LEFT JOIN fuel_budget_deductions d ON d.budget_id = b.id
        {$where}
        GROUP BY b.id, b.ib_no, b.description, b.budget_amount, b.diesel_allocation, b.unleaded_allocation, b.status
        ORDER BY b.created_at ASC, b.id ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Unable to load fuel budgets: ' . $conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fuelBudgetAutoAllocationPlan(mysqli $conn): array
{
    fuelBudgetEnsureTables($conn);

    $sql = "
        SELECT
            b.id,
            b.ib_no,
            b.description,
            b.budget_amount,
            b.diesel_allocation,
            b.unleaded_allocation,
            b.status,
            COALESCE(SUM(d.total_amount), 0) AS used_amount,
            COALESCE(SUM(d.diesel_liters * d.diesel_price), 0) AS used_diesel_amount,
            COALESCE(SUM(d.unleaded_liters * d.unleaded_price), 0) AS used_unleaded_amount,
            b.budget_amount - COALESCE(SUM(d.total_amount), 0) AS remaining_amount,
            GREATEST(b.diesel_allocation - COALESCE(SUM(d.diesel_liters * d.diesel_price), 0), 0) AS remaining_diesel_amount,
            GREATEST(b.unleaded_allocation - COALESCE(SUM(d.unleaded_liters * d.unleaded_price), 0), 0) AS remaining_unleaded_amount
        FROM fuel_budgets b
        LEFT JOIN fuel_budget_deductions d ON d.budget_id = b.id
        WHERE b.status = 'active'
        GROUP BY b.id, b.ib_no, b.description, b.budget_amount, b.diesel_allocation, b.unleaded_allocation, b.status, b.created_at
        HAVING remaining_diesel_amount > 0 OR remaining_unleaded_amount > 0
        ORDER BY b.created_at ASC, b.id ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Unable to load active fuel budget allocations: ' . $conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fuelBudgetActualUsageFromWeeklyPrices(mysqli $conn): array
{
    fuelBudgetEnsureTables($conn);

    $sql = "
        SELECT
            CASE WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) LIKE '%diesel%' THEN 'diesel' ELSE 'unleaded' END AS fuel_bucket,
            COUNT(*) AS record_count,
            SUM(COALESCE(gi.actual_liters_fueled, gi.authorized_liters, 0)) AS liters,
            SUM(
                CASE
                    WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) LIKE '%diesel%'
                        THEN COALESCE(gi.actual_liters_fueled, gi.authorized_liters, 0) * COALESCE(wfp.diesel_price, 0)
                    ELSE COALESCE(gi.actual_liters_fueled, gi.authorized_liters, 0) * COALESCE(wfp.unleaded_price, 0)
                END
            ) AS amount,
            SUM(
                CASE
                    WHEN wfp.id IS NULL THEN 1
                    WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) LIKE '%diesel%' AND COALESCE(wfp.diesel_price, 0) <= 0 THEN 1
                    WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) NOT LIKE '%diesel%' AND COALESCE(wfp.unleaded_price, 0) <= 0 THEN 1
                    ELSE 0
                END
            ) AS missing_price_count
        FROM gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        LEFT JOIN vehicle_odometer_logs vol
            ON vol.id = (
                SELECT MAX(inner_vol.id)
                FROM vehicle_odometer_logs inner_vol
                WHERE inner_vol.gas_issuance_id = gi.id
            )
        LEFT JOIN weekly_fuel_prices wfp
            ON wfp.week_start = DATE_SUB(
                DATE(COALESCE(vol.recorded_at, gi.updated_at, gi.issue_date)),
                INTERVAL (WEEKDAY(DATE(COALESCE(vol.recorded_at, gi.updated_at, gi.issue_date))) - 1) DAY
            )
        WHERE LOWER(COALESCE(gi.status, '')) = 'used'
            AND LOWER(TRIM(COALESCE(gi.issuance_scope, 'government'))) <> 'private'
        GROUP BY fuel_bucket
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Unable to calculate actual weekly-price fuel usage: ' . $conn->error);
    }

    $summary = [
        'actual_used_budget' => 0.0,
        'actual_used_diesel_budget' => 0.0,
        'actual_used_unleaded_budget' => 0.0,
        'actual_diesel_liters' => 0.0,
        'actual_unleaded_liters' => 0.0,
        'actual_diesel_records' => 0,
        'actual_unleaded_records' => 0,
        'actual_missing_price_count' => 0,
    ];

    foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        $bucket = (string) ($row['fuel_bucket'] ?? 'unleaded');
        $liters = (float) ($row['liters'] ?? 0);
        $amount = (float) ($row['amount'] ?? 0);
        $records = (int) ($row['record_count'] ?? 0);
        $missingPrices = (int) ($row['missing_price_count'] ?? 0);

        if ($bucket === 'diesel') {
            $summary['actual_used_diesel_budget'] = $amount;
            $summary['actual_diesel_liters'] = $liters;
            $summary['actual_diesel_records'] = $records;
        } else {
            $summary['actual_used_unleaded_budget'] = $amount;
            $summary['actual_unleaded_liters'] = $liters;
            $summary['actual_unleaded_records'] = $records;
        }

        $summary['actual_used_budget'] += $amount;
        $summary['actual_missing_price_count'] += $missingPrices;
    }

    return $summary;
}

function fuelBudgetWeeklyActualUsageTrend(mysqli $conn, int $limit = 16): array
{
    fuelBudgetEnsureTables($conn);
    $limit = max(1, min(52, $limit));

    $sql = "
        SELECT *
        FROM (
            SELECT
                usage_rows.week_start,
                COUNT(*) AS record_count,
                SUM(CASE WHEN usage_rows.fuel_bucket = 'diesel' THEN usage_rows.liters ELSE 0 END) AS diesel_liters,
                SUM(CASE WHEN usage_rows.fuel_bucket = 'unleaded' THEN usage_rows.liters ELSE 0 END) AS unleaded_liters,
                SUM(CASE WHEN usage_rows.fuel_bucket = 'diesel' THEN usage_rows.amount ELSE 0 END) AS diesel_amount,
                SUM(CASE WHEN usage_rows.fuel_bucket = 'unleaded' THEN usage_rows.amount ELSE 0 END) AS unleaded_amount,
                SUM(usage_rows.amount) AS total_amount,
                MAX(usage_rows.diesel_price) AS diesel_price,
                MAX(usage_rows.unleaded_price) AS unleaded_price,
                SUM(usage_rows.missing_price) AS missing_price_count
            FROM (
                SELECT
                    DATE_SUB(
                        DATE(COALESCE(vol.recorded_at, gi.updated_at, gi.issue_date)),
                        INTERVAL (WEEKDAY(DATE(COALESCE(vol.recorded_at, gi.updated_at, gi.issue_date))) - 1) DAY
                    ) AS week_start,
                    CASE WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) LIKE '%diesel%' THEN 'diesel' ELSE 'unleaded' END AS fuel_bucket,
                    COALESCE(gi.actual_liters_fueled, gi.authorized_liters, 0) AS liters,
                    COALESCE(wfp.diesel_price, 0) AS diesel_price,
                    COALESCE(wfp.unleaded_price, 0) AS unleaded_price,
                    CASE
                        WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) LIKE '%diesel%'
                            THEN COALESCE(gi.actual_liters_fueled, gi.authorized_liters, 0) * COALESCE(wfp.diesel_price, 0)
                        ELSE COALESCE(gi.actual_liters_fueled, gi.authorized_liters, 0) * COALESCE(wfp.unleaded_price, 0)
                    END AS amount,
                    CASE
                        WHEN wfp.id IS NULL THEN 1
                        WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) LIKE '%diesel%' AND COALESCE(wfp.diesel_price, 0) <= 0 THEN 1
                        WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) NOT LIKE '%diesel%' AND COALESCE(wfp.unleaded_price, 0) <= 0 THEN 1
                        ELSE 0
                    END AS missing_price
                FROM gas_issuances gi
                INNER JOIN vehicles v ON v.id = gi.vehicle_id
                LEFT JOIN vehicle_odometer_logs vol
                    ON vol.id = (
                        SELECT MAX(inner_vol.id)
                        FROM vehicle_odometer_logs inner_vol
                        WHERE inner_vol.gas_issuance_id = gi.id
                    )
                LEFT JOIN weekly_fuel_prices wfp
                    ON wfp.week_start = DATE_SUB(
                        DATE(COALESCE(vol.recorded_at, gi.updated_at, gi.issue_date)),
                        INTERVAL (WEEKDAY(DATE(COALESCE(vol.recorded_at, gi.updated_at, gi.issue_date))) - 1) DAY
                    )
                WHERE LOWER(COALESCE(gi.status, '')) = 'used'
                    AND LOWER(TRIM(COALESCE(gi.issuance_scope, 'government'))) <> 'private'
            ) usage_rows
            GROUP BY usage_rows.week_start
            ORDER BY usage_rows.week_start DESC
            LIMIT {$limit}
        ) weekly_usage
        ORDER BY week_start ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Unable to load weekly actual fuel usage trend: ' . $conn->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fuelBudgetSummary(mysqli $conn): array
{
    $budgets = fuelBudgetFetchBudgets($conn, false);
    $actualUsage = fuelBudgetActualUsageFromWeeklyPrices($conn);
    $totalBudget = 0.0;
    $deductedBudget = 0.0;
    $totalDieselBudget = 0.0;
    $totalUnleadedBudget = 0.0;
    $deductedDieselBudget = 0.0;
    $deductedUnleadedBudget = 0.0;

    foreach ($budgets as $budget) {
        $totalBudget += (float) ($budget['budget_amount'] ?? 0);
        $deductedBudget += (float) ($budget['used_amount'] ?? 0);
        $totalDieselBudget += (float) ($budget['diesel_allocation'] ?? 0);
        $totalUnleadedBudget += (float) ($budget['unleaded_allocation'] ?? 0);
        $deductedDieselBudget += (float) ($budget['used_diesel_amount'] ?? 0);
        $deductedUnleadedBudget += (float) ($budget['used_unleaded_amount'] ?? 0);
    }

    $actualUsedBudget = (float) ($actualUsage['actual_used_budget'] ?? 0);
    $actualUsedDieselBudget = (float) ($actualUsage['actual_used_diesel_budget'] ?? 0);
    $actualUsedUnleadedBudget = (float) ($actualUsage['actual_used_unleaded_budget'] ?? 0);

    return array_merge($actualUsage, [
        'total_budget' => $totalBudget,
        'deducted_budget' => $deductedBudget,
        'used_budget' => $actualUsedBudget,
        'remaining_budget' => max(0.0, $totalBudget - $actualUsedBudget),
        'total_diesel_budget' => $totalDieselBudget,
        'total_unleaded_budget' => $totalUnleadedBudget,
        'deducted_diesel_budget' => $deductedDieselBudget,
        'deducted_unleaded_budget' => $deductedUnleadedBudget,
        'used_diesel_budget' => $actualUsedDieselBudget,
        'used_unleaded_budget' => $actualUsedUnleadedBudget,
        'remaining_diesel_budget' => max(0.0, $totalDieselBudget - $actualUsedDieselBudget),
        'remaining_unleaded_budget' => max(0.0, $totalUnleadedBudget - $actualUsedUnleadedBudget),
        'budgets' => $budgets,
    ]);
}

function fuelBudgetRecordDeduction(
    mysqli $conn,
    int $budgetId,
    string $summaryHash,
    string $office,
    string $startDate,
    string $endDate,
    float $dieselPrice,
    float $unleadedPrice,
    float $dieselLiters,
    float $unleadedLiters,
    float $totalAmount,
    string $createdBy
): array {
    fuelBudgetEnsureTables($conn);

    if ($budgetId <= 0 || $totalAmount <= 0) {
        return ['deducted' => false, 'message' => 'No budget deduction was recorded.'];
    }

    $existing = $conn->prepare('SELECT id FROM fuel_budget_deductions WHERE summary_hash = ? LIMIT 1');
    $existing->bind_param('s', $summaryHash);
    $existing->execute();
    $existingRow = $existing->get_result()->fetch_assoc();
    $existing->close();

    if ($existingRow) {
        return ['deducted' => false, 'message' => 'This fuel summary was already deducted from the selected IB.'];
    }

    $stmt = $conn->prepare("
        INSERT INTO fuel_budget_deductions
            (budget_id, summary_hash, office, start_date, end_date, diesel_price, unleaded_price, diesel_liters, unleaded_liters, total_amount, created_by)
        VALUES
            (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'issssddddds',
        $budgetId,
        $summaryHash,
        $office,
        $startDate,
        $endDate,
        $dieselPrice,
        $unleadedPrice,
        $dieselLiters,
        $unleadedLiters,
        $totalAmount,
        $createdBy
    );
    $stmt->execute();
    $stmt->close();

    return ['deducted' => true, 'message' => 'Budget deducted from selected IB.'];
}

function fuelBudgetRecordAutoDeduction(
    mysqli $conn,
    string $summaryGroupHash,
    string $office,
    string $startDate,
    string $endDate,
    float $dieselPrice,
    float $unleadedPrice,
    float $dieselLiters,
    float $unleadedLiters,
    float $totalAmount,
    string $createdBy,
    array $sourceIssuanceIds = []
): array {
    fuelBudgetEnsureTables($conn);
    $sourceIssuanceIds = fuelBudgetNormalizeIssuanceIds($sourceIssuanceIds);

    if ($totalAmount <= 0) {
        return [
            'deducted' => false,
            'message' => 'No budget deduction was recorded.',
            'allocations' => [],
        ];
    }

    $existing = $conn->prepare('SELECT id FROM fuel_budget_deductions WHERE summary_group_hash = ? LIMIT 1');
    $existing->bind_param('s', $summaryGroupHash);
    $existing->execute();
    $existingRow = $existing->get_result()->fetch_assoc();
    $existing->close();

    if ($existingRow) {
        $allocations = [];
        $allocationStmt = $conn->prepare("
            SELECT
                b.id AS budget_id,
                b.ib_no,
                d.total_amount AS amount,
                d.diesel_liters * d.diesel_price AS diesel_amount,
                d.unleaded_liters * d.unleaded_price AS unleaded_amount,
                b.budget_amount - COALESCE(used_totals.used_amount, 0) AS remaining_after,
                b.diesel_allocation - COALESCE(used_totals.used_diesel_amount, 0) AS remaining_diesel_after,
                b.unleaded_allocation - COALESCE(used_totals.used_unleaded_amount, 0) AS remaining_unleaded_after
            FROM fuel_budget_deductions d
            INNER JOIN fuel_budgets b ON b.id = d.budget_id
            LEFT JOIN (
                SELECT
                    budget_id,
                    SUM(total_amount) AS used_amount,
                    SUM(diesel_liters * diesel_price) AS used_diesel_amount,
                    SUM(unleaded_liters * unleaded_price) AS used_unleaded_amount
                FROM fuel_budget_deductions
                GROUP BY budget_id
            ) used_totals ON used_totals.budget_id = b.id
            WHERE d.summary_group_hash = ?
            ORDER BY d.id ASC
        ");
        $allocationStmt->bind_param('s', $summaryGroupHash);
        $allocationStmt->execute();
        $allocationRows = $allocationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $allocationStmt->close();

        foreach ($allocationRows as $row) {
            $allocations[] = [
                'budget_id' => (int) ($row['budget_id'] ?? 0),
                'ib_no' => (string) ($row['ib_no'] ?? ''),
                'amount' => (float) ($row['amount'] ?? 0),
                'diesel_amount' => (float) ($row['diesel_amount'] ?? 0),
                'unleaded_amount' => (float) ($row['unleaded_amount'] ?? 0),
                'remaining_after' => (float) ($row['remaining_after'] ?? 0),
                'remaining_diesel_after' => (float) ($row['remaining_diesel_after'] ?? 0),
                'remaining_unleaded_after' => (float) ($row['remaining_unleaded_after'] ?? 0),
            ];
        }

        return [
            'deducted' => false,
            'message' => 'This fuel summary was already deducted from the annual IB budget pool.',
            'allocations' => $allocations,
        ];
    }

    $deductedIssuanceIds = fuelBudgetFindDeductedIssuanceIds($conn, $sourceIssuanceIds);
    if ($deductedIssuanceIds !== []) {
        return [
            'deducted' => false,
            'message' => 'This fuel summary overlaps gas issuances that were already deducted: ' . implode(', ', $deductedIssuanceIds) . '.',
            'allocations' => [],
            'duplicate_issuance_ids' => $deductedIssuanceIds,
        ];
    }

    $budgets = fuelBudgetAutoAllocationPlan($conn);
    $remainingPool = array_reduce(
        $budgets,
        static fn(float $sum, array $budget): float => $sum + max(0.0, (float) ($budget['remaining_amount'] ?? 0)),
        0.0
    );
    $remainingDieselPool = array_reduce(
        $budgets,
        static fn(float $sum, array $budget): float => $sum + max(0.0, (float) ($budget['remaining_diesel_amount'] ?? 0)),
        0.0
    );
    $remainingUnleadedPool = array_reduce(
        $budgets,
        static fn(float $sum, array $budget): float => $sum + max(0.0, (float) ($budget['remaining_unleaded_amount'] ?? 0)),
        0.0
    );
    $dieselAmount = $dieselLiters * $dieselPrice;
    $unleadedAmount = $unleadedLiters * $unleadedPrice;

    if ($remainingPool + 0.0001 < $totalAmount) {
        return [
            'deducted' => false,
            'message' => 'Insufficient active fuel budget remaining. Add another IB budget before printing this summary.',
            'allocations' => [],
            'shortfall' => $totalAmount - $remainingPool,
        ];
    }
    if ($remainingDieselPool + 0.0001 < $dieselAmount) {
        return [
            'deducted' => false,
            'message' => 'Insufficient diesel allocation remaining. Add or update an IB diesel allocation before printing this summary.',
            'allocations' => [],
            'shortfall' => $dieselAmount - $remainingDieselPool,
            'fuel_type' => 'diesel',
        ];
    }
    if ($remainingUnleadedPool + 0.0001 < $unleadedAmount) {
        return [
            'deducted' => false,
            'message' => 'Insufficient unleaded allocation remaining. Add or update an IB unleaded allocation before printing this summary.',
            'allocations' => [],
            'shortfall' => $unleadedAmount - $remainingUnleadedPool,
            'fuel_type' => 'unleaded',
        ];
    }

    $conn->begin_transaction();

    try {
        $insert = $conn->prepare("
            INSERT INTO fuel_budget_deductions
                (budget_id, summary_hash, summary_group_hash, office, start_date, end_date, diesel_price, unleaded_price, diesel_liters, unleaded_liters, total_amount, created_by)
            VALUES
                (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?)
        ");

        $dieselAmountLeft = $dieselAmount;
        $unleadedAmountLeft = $unleadedAmount;
        $allocations = [];
        $sequence = 1;
        $firstDeductionId = 0;

        foreach ($budgets as $budget) {
            if ($dieselAmountLeft <= 0.0001 && $unleadedAmountLeft <= 0.0001) {
                break;
            }

            $budgetDieselRemaining = max(0.0, (float) ($budget['remaining_diesel_amount'] ?? 0));
            $budgetUnleadedRemaining = max(0.0, (float) ($budget['remaining_unleaded_amount'] ?? 0));
            $allocationDieselAmount = min($dieselAmountLeft, $budgetDieselRemaining);
            $allocationUnleadedAmount = min($unleadedAmountLeft, $budgetUnleadedRemaining);
            $allocationAmount = $allocationDieselAmount + $allocationUnleadedAmount;

            if ($allocationAmount <= 0) {
                continue;
            }

            $allocationDieselLiters = $dieselPrice > 0 ? $allocationDieselAmount / $dieselPrice : 0.0;
            $allocationUnleadedLiters = $unleadedPrice > 0 ? $allocationUnleadedAmount / $unleadedPrice : 0.0;
            $allocationHash = hash('sha256', $summaryGroupHash . '|' . (string) $budget['id'] . '|' . (string) $sequence);
            $budgetId = (int) $budget['id'];

            $insert->bind_param(
                'isssssddddds',
                $budgetId,
                $allocationHash,
                $summaryGroupHash,
                $office,
                $startDate,
                $endDate,
                $dieselPrice,
                $unleadedPrice,
                $allocationDieselLiters,
                $allocationUnleadedLiters,
                $allocationAmount,
                $createdBy
            );
            $insert->execute();
            if ($firstDeductionId === 0) {
                $firstDeductionId = (int) $conn->insert_id;
                fuelBudgetLinkDeductedIssuances($conn, $firstDeductionId, $sourceIssuanceIds);
            }

            $dieselAmountLeft -= $allocationDieselAmount;
            $unleadedAmountLeft -= $allocationUnleadedAmount;
            $allocations[] = [
                'budget_id' => $budgetId,
                'ib_no' => (string) ($budget['ib_no'] ?? ''),
                'amount' => $allocationAmount,
                'diesel_amount' => $allocationDieselAmount,
                'unleaded_amount' => $allocationUnleadedAmount,
                'remaining_after' => max(0.0, $budgetDieselRemaining - $allocationDieselAmount) + max(0.0, $budgetUnleadedRemaining - $allocationUnleadedAmount),
                'remaining_diesel_after' => $budgetDieselRemaining - $allocationDieselAmount,
                'remaining_unleaded_after' => $budgetUnleadedRemaining - $allocationUnleadedAmount,
            ];
            $sequence++;
        }

        $insert->close();
        $conn->commit();

        return [
            'deducted' => true,
            'message' => 'Budget auto-deducted using oldest active IB first.',
            'allocations' => $allocations,
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
