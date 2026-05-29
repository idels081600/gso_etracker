<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('coa_admin', 'json');
requireFuelAjaxRequest();
require_once __DIR__ . '/rate_limiter.php';
require_rate_limit(20, 60, 'fuel_summary_deduction_save', 'json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';
require_once __DIR__ . '/fuel_budget_data.php';

header('Content-Type: application/json; charset=UTF-8');

function fuelSummaryDeductionJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function fuelSummaryDeductionValue(array $input, string $key, string $default = ''): string
{
    $value = $input[$key] ?? $default;
    $value = is_scalar($value) ? trim((string) $value) : $default;
    return $value !== '' ? $value : $default;
}

function fuelSummaryDeductionFloat(mixed $value): float
{
    if (is_string($value)) {
        $value = str_replace(',', '', trim($value));
    }

    return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
}

function fuelSummaryDeductionDate(string $date): string
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
}

function fuelSummaryDeductionRows(mysqli $conn, string $office, string $startDate, string $endDate): array
{
    fuelTrackerSyncIssuanceOffices($conn);

    $conditions = [
        "LOWER(gi.status) = 'used'",
        "LOWER(TRIM(COALESCE(gi.issuance_scope, 'government'))) <> 'private'",
    ];
    $types = '';
    $params = [];

    if ($office !== '') {
        $conditions[] = 'gi.office = ?';
        $types .= 's';
        $params[] = $office;
    }
    if ($startDate !== '') {
        $conditions[] = 'gi.issue_date >= ?';
        $types .= 's';
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $conditions[] = 'gi.issue_date <= ?';
        $types .= 's';
        $params[] = $endDate;
    }

    $sql = "
        SELECT
            gi.id,
            gi.fuel_type,
            COALESCE(gi.authorized_liters, 0) AS liters
        FROM gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY gi.issue_date ASC, gi.id ASC
    ";

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$office = fuelSummaryDeductionValue($input, 'office');
$startDate = fuelSummaryDeductionDate(fuelSummaryDeductionValue($input, 'start_date'));
$endDate = fuelSummaryDeductionDate(fuelSummaryDeductionValue($input, 'end_date'));
$dieselPrice = fuelSummaryDeductionFloat(fuelSummaryDeductionValue($input, 'diesel_price'));
$unleadedPrice = fuelSummaryDeductionFloat(fuelSummaryDeductionValue($input, 'unleaded_price'));

try {
    $rows = fuelSummaryDeductionRows($conn, $office, $startDate, $endDate);
    $dieselLiters = 0.0;
    $unleadedLiters = 0.0;
    $sourceIssuanceIds = [];

    foreach ($rows as $row) {
        $sourceIssuanceIds[] = (int) ($row['id'] ?? 0);
        $fuelType = strtolower((string) ($row['fuel_type'] ?? ''));
        $liters = fuelSummaryDeductionFloat($row['liters'] ?? 0);
        if (str_contains($fuelType, 'diesel')) {
            $dieselLiters += $liters;
        } else {
            $unleadedLiters += $liters;
        }
    }

    $totalAmount = ($dieselLiters * $dieselPrice) + ($unleadedLiters * $unleadedPrice);
    if ($totalAmount <= 0) {
        fuelSummaryDeductionJson(['success' => false, 'message' => 'No deduction amount found for this summary.'], 422);
    }

    $summaryGroupHash = hash('sha256', implode('|', [
        $office,
        $startDate,
        $endDate,
        number_format($dieselPrice, 2, '.', ''),
        number_format($unleadedPrice, 2, '.', ''),
        number_format($dieselLiters, 2, '.', ''),
        number_format($unleadedLiters, 2, '.', ''),
    ]));

    $deduction = fuelBudgetRecordAutoDeduction(
        $conn,
        $summaryGroupHash,
        $office,
        $startDate,
        $endDate,
        $dieselPrice,
        $unleadedPrice,
        $dieselLiters,
        $unleadedLiters,
        $totalAmount,
        (string) ($_SESSION['username'] ?? $_SESSION['pay_name'] ?? 'coa_admin'),
        $sourceIssuanceIds
    );

    if (!empty($deduction['duplicate_issuance_ids'])) {
        fuelSummaryDeductionJson([
            'success' => false,
            'message' => (string) ($deduction['message'] ?? 'This fuel summary overlaps gas issuances that were already deducted.'),
            'duplicate_issuance_ids' => $deduction['duplicate_issuance_ids'],
        ], 409);
    }

    if (isset($deduction['shortfall']) && (float) $deduction['shortfall'] > 0) {
        fuelSummaryDeductionJson([
            'success' => false,
            'message' => $deduction['message'] . ' Shortfall: PHP ' . number_format((float) $deduction['shortfall'], 2),
        ], 422);
    }

    fuelSummaryDeductionJson([
        'success' => true,
        'message' => (string) ($deduction['message'] ?? 'Budget deduction saved.'),
        'deduction' => $deduction,
        'budget_summary' => fuelBudgetSummary($conn),
    ]);
} catch (Throwable $e) {
    error_log('Fuel summary deduction save error: ' . $e->getMessage());
    fuelSummaryDeductionJson(['success' => false, 'message' => 'Unable to save fuel summary deduction.'], 500);
}
