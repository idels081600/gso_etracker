<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole(['fuel_admin', 'coa_admin']);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fuel_report_data.php';

$currentRole = fuelAuthRole();
$today = new DateTimeImmutable('today');
$form = [
    'period_type' => strtolower(trim((string) ($_GET['period_type'] ?? 'monthly'))),
    'report_date' => trim((string) ($_GET['report_date'] ?? $today->format('Y-m-d'))),
    'report_month' => trim((string) ($_GET['report_month'] ?? $today->format('Y-m'))),
    'include_private' => isset($_GET['include_private']) && (string) $_GET['include_private'] === '1',
];
$generated = isset($_GET['generate']);
$errorMessage = '';
$period = null;
$report = ['offices' => [], 'totals' => ['offices' => 0, 'vehicles' => 0, 'fuelings' => 0, 'diesel_liters' => 0, 'unleaded_liters' => 0, 'total_liters' => 0]];

if ($generated) {
    try {
        $period = fuelReportResolvePeriod($form);
        $rows = fuelReportFetchVehicleConsumption($conn, $period, $form['include_private']);
        $report = fuelReportSummarize($rows);
    } catch (InvalidArgumentException $e) {
        $errorMessage = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Fuel report page error: ' . $e->getMessage());
        $errorMessage = 'The report could not be generated. Please try again.';
    }
}

function fuelReportHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fuelReportNumber(float $value): string
{
    return number_format($value, 2);
}

$pdfParams = [
    'period_type' => $form['period_type'],
    'report_date' => $form['report_date'],
    'report_month' => $form['report_month'],
];
if ($form['include_private']) {
    $pdfParams['include_private'] = '1';
}
$pdfUrl = 'fuel_report_pdf.php?' . http_build_query($pdfParams);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fuel Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="fuel_dashboard.css">
    <link rel="stylesheet" href="fuel_reports.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm" aria-label="Fuel Tracker navigation">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo $currentRole === 'fuel_admin' ? 'fuel_dashboard.php' : 'sub_admin.php'; ?>">
            <i class="fas fa-gas-pump text-primary me-2"></i><span class="fw-bold text-dark">Fuel Tracker</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#fuelReportNav" aria-controls="fuelReportNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="fuelReportNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($currentRole === 'fuel_admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="fuel_dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="gas_issuance.php"><i class="fas fa-receipt me-1"></i>Gas Issuance</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="sub_admin.php"><i class="fas fa-print me-1"></i>Print Desk</a></li>
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="fuel_reports.php"><i class="fas fa-chart-column me-1"></i>Reports</a></li>
            </ul>
            <a class="btn btn-outline-danger btn-sm" href="../logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<main class="container-fluid fuel-report-shell py-4">
    <section class="fuel-report-hero mb-4">
        <div>
            <p class="fuel-report-kicker mb-1">Operational reporting</p>
            <h1 class="h3 mb-2">Weekly and monthly fuel consumption</h1>
            <p class="mb-0 text-secondary">Generate issued-liter reports by office and vehicle, then download the same result as a PDF.</p>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="get" action="fuel_reports.php" id="fuelReportForm" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-semibold" for="periodType">Report period</label>
                    <select class="form-select" id="periodType" name="period_type">
                        <option value="weekly" <?php echo $form['period_type'] === 'weekly' ? 'selected' : ''; ?>>Weekly (Monday-Sunday)</option>
                        <option value="monthly" <?php echo $form['period_type'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 fuel-period-field" data-period-field="weekly">
                    <label class="form-label fw-semibold" for="reportDate">Date within week</label>
                    <input class="form-control" type="date" id="reportDate" name="report_date" value="<?php echo fuelReportHtml($form['report_date']); ?>">
                    <div class="form-text">The report automatically uses Monday through Sunday.</div>
                </div>
                <div class="col-lg-3 col-md-6 fuel-period-field" data-period-field="monthly">
                    <label class="form-label fw-semibold" for="reportMonth">Month</label>
                    <input class="form-control" type="month" id="reportMonth" name="report_month" value="<?php echo fuelReportHtml($form['report_month']); ?>">
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="form-check fuel-report-scope-check">
                        <input class="form-check-input" type="checkbox" value="1" id="includePrivate" name="include_private" <?php echo $form['include_private'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="includePrivate">Include Other Cars</label>
                        <div class="form-text">Off by default to exclude private vehicles.</div>
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit" name="generate" value="1"><i class="fas fa-chart-line me-1"></i>Generate report</button>
                    <?php if ($generated && $period !== null && $errorMessage === ''): ?>
                        <a class="btn btn-outline-primary" href="<?php echo fuelReportHtml($pdfUrl); ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-1"></i>Download PDF</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger" role="alert"><i class="fas fa-circle-exclamation me-2"></i><?php echo fuelReportHtml($errorMessage); ?></div>
    <?php elseif ($generated && $period !== null): ?>
        <section class="fuel-report-heading d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <p class="fuel-report-kicker mb-1"><?php echo fuelReportHtml(ucfirst($period['type']) . ' report'); ?></p>
                <h2 class="h4 mb-1"><?php echo fuelReportHtml($period['label']); ?></h2>
                <p class="mb-0 text-secondary"><?php echo $form['include_private'] ? 'Government vehicles and Other Cars included.' : 'Private vehicles and Other Cars excluded.'; ?></p>
            </div>
            <span class="badge text-bg-light border px-3 py-2"><?php echo fuelReportHtml($period['start_date'] . ' to ' . $period['end_date']); ?></span>
        </section>

        <section class="row g-3 mb-4">
            <?php foreach ([
                ['Offices', number_format((int) $report['totals']['offices'])],
                ['Vehicles', number_format((int) $report['totals']['vehicles'])],
                ['Fuelings', number_format((int) $report['totals']['fuelings'])],
                ['Diesel', fuelReportNumber((float) $report['totals']['diesel_liters']) . ' L'],
                ['Unleaded', fuelReportNumber((float) $report['totals']['unleaded_liters']) . ' L'],
                ['Total issued', fuelReportNumber((float) $report['totals']['total_liters']) . ' L'],
            ] as [$label, $value]): ?>
                <div class="col-xl-2 col-md-4 col-6"><div class="fuel-report-stat h-100"><span><?php echo fuelReportHtml($label); ?></span><strong><?php echo fuelReportHtml($value); ?></strong></div></div>
            <?php endforeach; ?>
        </section>

        <?php if (empty($report['offices'])): ?>
            <div class="card border-0 shadow-sm"><div class="card-body p-5 text-center"><i class="fas fa-file-circle-xmark fa-2x text-secondary mb-3"></i><h3 class="h5">No used fuel records found</h3><p class="text-secondary mb-0">Choose another period or include Other Cars.</p></div></div>
        <?php else: ?>
            <?php foreach ($report['offices'] as $office): ?>
                <section class="card border-0 shadow-sm mb-3 fuel-office-card">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                        <div><h3 class="h6 mb-1 fw-bold"><?php echo fuelReportHtml($office['office']); ?></h3><span class="text-secondary small"><?php echo number_format((int) $office['vehicles']); ?> vehicles · <?php echo number_format((int) $office['fuelings']); ?> fuelings</span></div>
                        <strong><?php echo fuelReportNumber((float) $office['total_liters']); ?> L</strong>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0 fuel-report-table">
                            <thead><tr><th>Plate / ID</th><th>Vehicle type</th><th class="text-end">Fuelings</th><th class="text-end">Diesel issued L</th><th class="text-end">Unleaded issued L</th><th class="text-end">Total issued L</th><th class="text-end">Latest odo</th></tr></thead>
                            <tbody>
                            <?php foreach ($office['rows'] as $row): ?>
                                <tr><td><?php echo fuelReportHtml($row['plate_no']); ?></td><td><?php echo fuelReportHtml($row['vehicle_type']); ?></td><td class="text-end"><?php echo number_format((int) $row['fueling_events']); ?></td><td class="text-end"><?php echo fuelReportNumber((float) $row['diesel_issued_liters']); ?></td><td class="text-end"><?php echo fuelReportNumber((float) $row['unleaded_issued_liters']); ?></td><td class="text-end fw-semibold"><?php echo fuelReportNumber((float) $row['total_issued_liters']); ?></td><td class="text-end"><?php echo $row['latest_odometer'] !== null ? fuelReportHtml(number_format((float) $row['latest_odometer'], 1) . ' km') : '-'; ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr><th colspan="2">Office subtotal</th><th class="text-end"><?php echo number_format((int) $office['fuelings']); ?></th><th class="text-end"><?php echo fuelReportNumber((float) $office['diesel_liters']); ?></th><th class="text-end"><?php echo fuelReportNumber((float) $office['unleaded_liters']); ?></th><th class="text-end"><?php echo fuelReportNumber((float) $office['total_liters']); ?></th><th></th></tr></tfoot>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <section class="fuel-report-empty card border-0 shadow-sm"><div class="card-body p-5"><div class="fuel-report-empty-icon"><i class="fas fa-calendar-days"></i></div><h2 class="h5">Choose a week or month</h2><p class="text-secondary mb-0">Generate a report to review issued liters by office and vehicle.</p></div></section>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="fuel_reports.js"></script>
</body>
</html>
