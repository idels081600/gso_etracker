<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin', 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(30, 60, 'fuel_price_save', 'json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fuel_budget_data.php';

header('Content-Type: application/json');

function fuelPriceSaveJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function fuelPriceInput(): array
{
    $decoded = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($decoded) ? $decoded : $_POST;
}

$input = fuelPriceInput();
$weekStart = trim((string) ($input['week_start'] ?? ''));
$dieselPrice = is_numeric($input['diesel_price'] ?? null) ? (float) $input['diesel_price'] : -1;
$unleadedPrice = is_numeric($input['unleaded_price'] ?? null) ? (float) $input['unleaded_price'] : -1;
$sourceNote = trim((string) ($input['source_note'] ?? ''));
$updatedBy = trim((string) ($_SESSION['username'] ?? $_SESSION['name'] ?? 'Fuel Admin'));

try {
    $latest = fuelBudgetSaveWeeklyFuelPrice($conn, $weekStart, $dieselPrice, $unleadedPrice, $sourceNote, $updatedBy);

    fuelPriceSaveJson([
        'success' => true,
        'message' => 'Weekly fuel prices saved.',
        'latest' => $latest,
        'history' => fuelBudgetWeeklyFuelPriceHistory($conn, 12),
    ]);
} catch (InvalidArgumentException $e) {
    fuelPriceSaveJson(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Weekly fuel price save error: ' . $e->getMessage());
    fuelPriceSaveJson(['success' => false, 'message' => 'Unable to save weekly fuel prices.'], 500);
}
