<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(20, 60, 'fuel_budget_save', 'json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fuel_budget_data.php';

header('Content-Type: application/json');

function fuelBudgetSaveJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$ibNo = strtoupper(trim((string) ($input['ib_no'] ?? '')));
$description = trim((string) ($input['description'] ?? ''));
$fuelCoverage = strtolower(trim((string) ($input['fuel_coverage'] ?? 'both')));
$validFuelCoverages = ['diesel', 'unleaded', 'both'];
$updatesDiesel = in_array($fuelCoverage, ['diesel', 'both'], true);
$updatesUnleaded = in_array($fuelCoverage, ['unleaded', 'both'], true);
$dieselAllocation = is_numeric($input['diesel_allocation'] ?? null) ? (float) $input['diesel_allocation'] : -1;
$unleadedAllocation = is_numeric($input['unleaded_allocation'] ?? null) ? (float) $input['unleaded_allocation'] : -1;

if ($ibNo === '') {
    fuelBudgetSaveJson(['success' => false, 'message' => 'IB number is required.'], 422);
}
if (!in_array($fuelCoverage, $validFuelCoverages, true)) {
    fuelBudgetSaveJson(['success' => false, 'message' => 'Select diesel, unleaded, or both.'], 422);
}
if (($updatesDiesel && $dieselAllocation <= 0) || ($updatesUnleaded && $unleadedAllocation <= 0)) {
    fuelBudgetSaveJson(['success' => false, 'message' => 'Each selected fuel allocation must be greater than zero.'], 422);
}

try {
    fuelBudgetEnsureTables($conn);
    $conn->begin_transaction();

    $existingStmt = $conn->prepare("
        SELECT id, description, diesel_allocation, unleaded_allocation
        FROM fuel_budgets
        WHERE ib_no = ?
        LIMIT 1
        FOR UPDATE
    ");
    $existingStmt->bind_param('s', $ibNo);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    $usedDieselAmount = 0.0;
    $usedUnleadedAmount = 0.0;
    if ($existing) {
        $budgetId = (int) $existing['id'];
        $usedStmt = $conn->prepare("
            SELECT
                COALESCE(SUM(diesel_liters * diesel_price), 0) AS used_diesel_amount,
                COALESCE(SUM(unleaded_liters * unleaded_price), 0) AS used_unleaded_amount
            FROM fuel_budget_deductions
            WHERE budget_id = ?
        ");
        $usedStmt->bind_param('i', $budgetId);
        $usedStmt->execute();
        $usedAmounts = $usedStmt->get_result()->fetch_assoc() ?: [];
        $usedStmt->close();
        $usedDieselAmount = (float) ($usedAmounts['used_diesel_amount'] ?? 0);
        $usedUnleadedAmount = (float) ($usedAmounts['used_unleaded_amount'] ?? 0);
    }

    $finalDieselAllocation = $updatesDiesel
        ? $dieselAllocation
        : (float) ($existing['diesel_allocation'] ?? 0);
    $finalUnleadedAllocation = $updatesUnleaded
        ? $unleadedAllocation
        : (float) ($existing['unleaded_allocation'] ?? 0);
    if ($finalDieselAllocation + 0.0001 < $usedDieselAmount) {
        throw new InvalidArgumentException('Diesel allocation cannot be lower than the already deducted amount of ' . number_format($usedDieselAmount, 2) . '.');
    }
    if ($finalUnleadedAllocation + 0.0001 < $usedUnleadedAmount) {
        throw new InvalidArgumentException('Unleaded allocation cannot be lower than the already deducted amount of ' . number_format($usedUnleadedAmount, 2) . '.');
    }

    $budgetAmount = $finalDieselAllocation + $finalUnleadedAllocation;
    if ($existing) {
        $description = $description !== '' ? $description : (string) ($existing['description'] ?? '');
        $update = $conn->prepare("
            UPDATE fuel_budgets
            SET description = ?, budget_amount = ?, diesel_allocation = ?, unleaded_allocation = ?,
                status = 'active', updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $update->bind_param('sdddi', $description, $budgetAmount, $finalDieselAllocation, $finalUnleadedAllocation, $budgetId);
        $update->execute();
        $update->close();
        $message = 'IB budget updated.';
    } else {
        $insert = $conn->prepare("
            INSERT INTO fuel_budgets (ib_no, description, budget_amount, diesel_allocation, unleaded_allocation, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $insert->bind_param('ssddd', $ibNo, $description, $budgetAmount, $finalDieselAllocation, $finalUnleadedAllocation);
        $insert->execute();
        $insert->close();
        $message = 'IB budget added.';
    }

    $conn->commit();

    fuelBudgetSaveJson([
        'success' => true,
        'message' => $message,
        'budget_summary' => fuelBudgetSummary($conn),
    ]);
} catch (InvalidArgumentException $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log('Fuel budget validation rollback error: ' . $rollbackError->getMessage());
    }
    fuelBudgetSaveJson(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log('Fuel budget rollback error: ' . $rollbackError->getMessage());
    }
    error_log('Fuel budget save error: ' . $e->getMessage());
    fuelBudgetSaveJson(['success' => false, 'message' => 'Unable to save IB budget.'], 500);
}
