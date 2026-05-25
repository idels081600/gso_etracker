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
$dieselAllocation = is_numeric($input['diesel_allocation'] ?? null) ? (float) $input['diesel_allocation'] : -1;
$unleadedAllocation = is_numeric($input['unleaded_allocation'] ?? null) ? (float) $input['unleaded_allocation'] : -1;
$budgetAmount = $dieselAllocation + $unleadedAllocation;

if ($ibNo === '') {
    fuelBudgetSaveJson(['success' => false, 'message' => 'IB number is required.'], 422);
}
if ($dieselAllocation < 0 || $unleadedAllocation < 0) {
    fuelBudgetSaveJson(['success' => false, 'message' => 'Diesel and unleaded allocations must be zero or greater.'], 422);
}
if ($budgetAmount <= 0) {
    fuelBudgetSaveJson(['success' => false, 'message' => 'Enter an allocation for diesel, unleaded, or both.'], 422);
}

try {
    fuelBudgetEnsureTables($conn);
    $stmt = $conn->prepare("
        INSERT INTO fuel_budgets (ib_no, description, budget_amount, diesel_allocation, unleaded_allocation, status)
        VALUES (?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            budget_amount = VALUES(budget_amount),
            diesel_allocation = VALUES(diesel_allocation),
            unleaded_allocation = VALUES(unleaded_allocation),
            status = 'active',
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param('ssddd', $ibNo, $description, $budgetAmount, $dieselAllocation, $unleadedAllocation);
    $stmt->execute();
    $stmt->close();

    fuelBudgetSaveJson([
        'success' => true,
        'message' => 'IB budget saved.',
        'budget_summary' => fuelBudgetSummary($conn),
    ]);
} catch (Throwable $e) {
    error_log('Fuel budget save error: ' . $e->getMessage());
    fuelBudgetSaveJson(['success' => false, 'message' => 'Unable to save IB budget.'], 500);
}
