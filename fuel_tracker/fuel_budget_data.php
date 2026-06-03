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

function fuelBudgetSummary(mysqli $conn): array
{
    $budgets = fuelBudgetFetchBudgets($conn, false);
    $totalBudget = 0.0;
    $usedBudget = 0.0;
    $totalDieselBudget = 0.0;
    $totalUnleadedBudget = 0.0;
    $usedDieselBudget = 0.0;
    $usedUnleadedBudget = 0.0;

    foreach ($budgets as $budget) {
        $totalBudget += (float) ($budget['budget_amount'] ?? 0);
        $usedBudget += (float) ($budget['used_amount'] ?? 0);
        $totalDieselBudget += (float) ($budget['diesel_allocation'] ?? 0);
        $totalUnleadedBudget += (float) ($budget['unleaded_allocation'] ?? 0);
        $usedDieselBudget += (float) ($budget['used_diesel_amount'] ?? 0);
        $usedUnleadedBudget += (float) ($budget['used_unleaded_amount'] ?? 0);
    }

    return [
        'total_budget' => $totalBudget,
        'used_budget' => $usedBudget,
        'remaining_budget' => $totalBudget - $usedBudget,
        'total_diesel_budget' => $totalDieselBudget,
        'total_unleaded_budget' => $totalUnleadedBudget,
        'used_diesel_budget' => $usedDieselBudget,
        'used_unleaded_budget' => $usedUnleadedBudget,
        'remaining_diesel_budget' => max(0.0, $totalDieselBudget - $usedDieselBudget),
        'remaining_unleaded_budget' => max(0.0, $totalUnleadedBudget - $usedUnleadedBudget),
        'budgets' => $budgets,
    ];
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
