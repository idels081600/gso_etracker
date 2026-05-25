<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('print_admin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

$today = date('Y-m-d');
$todayIssuances = array_values(array_filter(
    fuelTrackerFetchGasIssuances($conn, ['approved', 'valid', 'used']),
    static fn(array $issuance): bool => ($issuance['issue_date'] ?? '') === $today
));

$usedToday = count(array_filter($todayIssuances, static fn(array $issuance): bool => strtolower((string) ($issuance['status'] ?? '')) === 'used'));
$totalLiters = array_reduce($todayIssuances, static fn(float $sum, array $issuance): float => $sum + (float) ($issuance['actual_liters_fueled'] ?? $issuance['authorized_liters'] ?? 0), 0.0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Printing - Gas Issuance</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="fuel_dashboard.css">

    <style>
        :root {
            --personnel-green: #039a00;
            --personnel-green-dark: #027300;
            --surface-soft: #f5f8f6;
        }

        body {
            background: #f3f6f4;
        }

        .personnel-shell {
            max-width: 1480px;
        }

        .personnel-hero {
            background: linear-gradient(135deg, var(--personnel-green), var(--personnel-green-dark));
            border-radius: 8px;
            color: #fff;
            padding: 1.25rem;
        }

        .personnel-hero h1 {
            font-size: clamp(1.45rem, 2.3vw, 2rem);
            letter-spacing: 0;
        }

        .summary-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .summary-tile {
            background: #fff;
            border: 1px solid #dfe8df;
            border-radius: 8px;
            padding: 0.85rem 1rem;
        }

        .summary-label {
            color: #5d6b60;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary-value {
            color: #18351c;
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .print-toolbar {
            align-items: end;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: minmax(220px, 1fr) minmax(155px, 190px) auto;
        }

        .approved-table-card {
            border: 0;
            border-radius: 8px;
            overflow: hidden;
        }

        .approved-table thead th {
            background: #f0f6f1;
            color: #244327;
            font-size: 0.76rem;
            letter-spacing: 0;
            padding: 0.85rem 0.9rem;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .approved-table tbody td {
            padding: 0.9rem;
            vertical-align: middle;
        }

        .serial-cell {
            color: #143a17;
            font-weight: 800;
        }

        .vehicle-stack {
            display: grid;
            gap: 0.12rem;
        }

        .vehicle-stack .plate {
            font-weight: 800;
        }

        .vehicle-stack .vehicle {
            color: #6c757d;
            font-size: 0.82rem;
        }

        .print-actions {
            display: flex;
            gap: 0.45rem;
            justify-content: flex-end;
            min-width: 190px;
        }

        .print-actions .btn {
            align-items: center;
            display: inline-flex;
            gap: 0.35rem;
            justify-content: center;
            min-height: 34px;
            white-space: nowrap;
        }

        .status-approved {
            background: #dff3df;
            color: #075f05;
        }

        .status-valid {
            background: #e6f0ff;
            color: #084298;
        }

        .status-used {
            background: #fff3cd;
            color: #7a4f01;
        }

        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .print-toolbar {
                grid-template-columns: 1fr;
            }

            .print-toolbar .btn {
                width: 100%;
            }
        }

        @media (max-width: 767.98px) {
            .personnel-hero {
                padding: 1rem;
            }

            .print-actions {
                flex-direction: column;
                min-width: 150px;
            }
        }
    </style>
</head>

<body>
    <main class="container-fluid personnel-shell py-4">
        <section class="personnel-hero shadow-sm mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <p class="mb-1 text-white-50 fw-semibold">Personnel Printing Desk</p>
                    <h1 class="mb-1 fw-bold">Today's Gas Issuance Printing</h1>
                    <p class="mb-0 text-white-50">Print gas issuance slips for current-day records.</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-white text-success px-3 py-2">
                        <i class="fas fa-calendar-day me-1"></i>Current Day
                    </span>
                    <a href="../logout.php" class="btn btn-light text-danger fw-semibold">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </section>

        <section class="summary-grid mb-4" aria-label="Current day issuance summary">
            <div class="summary-tile shadow-sm">
                <div class="summary-label">Today's Records</div>
                <div class="summary-value" id="approvedCount"><?php echo count($todayIssuances); ?></div>
            </div>
            <div class="summary-tile shadow-sm">
                <div class="summary-label">Fueled Up Today</div>
                <div class="summary-value" id="todayCount"><?php echo $usedToday; ?></div>
            </div>
            <div class="summary-tile shadow-sm">
                <div class="summary-label">Total Fuel Liters</div>
                <div class="summary-value" id="totalLiters"><?php echo number_format($totalLiters, 2); ?> L</div>
            </div>
        </section>

        <section class="card approved-table-card shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1 fw-bold">
                            <i class="fas fa-print me-2 text-success"></i>Ready to Print
                        </h2>
                        <p class="text-muted mb-0 small">Search today's list, then print the needed document.</p>
                    </div>
                </div>
                <div class="print-toolbar">
                    <div>
                        <label for="personnelSearch" class="form-label small fw-bold text-muted">Search issuance, plate, driver, or vehicle</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-success"></i></span>
                            <input type="search" class="form-control" id="personnelSearch" placeholder="Search today's records...">
                        </div>
                    </div>
                    <div>
                        <label for="dateFilter" class="form-label small fw-bold text-muted">Issue date</label>
                        <input type="date" class="form-control" id="dateFilter">
                    </div>
                    <button type="button" class="btn btn-outline-secondary" id="clearFiltersBtn">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table approved-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Issuance No.</th>
                            <th>Date</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th class="text-end">Liters</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th class="text-end">Print</th>
                        </tr>
                    </thead>
                    <tbody id="approvedTableBody">
                        <?php if (empty($todayIssuances)): ?>
                            <tr>
                                <td colspan="8" class="empty-state text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    No current-day gas issuance records available.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($todayIssuances as $issuance): ?>
                                <?php
                                $searchText = strtolower(implode(' ', [
                                    $issuance['serial_no'] ?? '',
                                    $issuance['plate_no'] ?? '',
                                    $issuance['driver_name'] ?? '',
                                    $issuance['vehicle_type'] ?? '',
                                    $issuance['purpose'] ?? '',
                                ]));
                                $status = strtolower((string) ($issuance['status'] ?? 'valid'));
                                $statusClass = 'status-valid';
                                if ($status === 'approved') {
                                    $statusClass = 'status-approved';
                                } elseif ($status === 'used') {
                                    $statusClass = 'status-used';
                                }
                                ?>
                                <tr data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-date="<?php echo htmlspecialchars((string) ($issuance['issue_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="serial-cell font-monospace"><?php echo htmlspecialchars((string) $issuance['serial_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime((string) $issuance['issue_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="vehicle-stack">
                                            <span class="plate"><?php echo htmlspecialchars((string) ($issuance['plate_no'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="vehicle"><?php echo htmlspecialchars((string) ($issuance['vehicle_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </span>
                                    </td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars((string) ($issuance['driver_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end fw-bold"><?php echo htmlspecialchars(number_format((float) ($issuance['actual_liters_fueled'] ?? $issuance['authorized_liters'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?> L</td>
                                    <td>Office</td>
                                    <td><span class="badge <?php echo $statusClass; ?> px-3 py-2"><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <div class="print-actions">
                                            <button type="button"
                                                class="btn btn-outline-success btn-sm action-print-gas"
                                                data-id="<?php echo htmlspecialchars((string) $issuance['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Print gas issuance">
                                                <i class="fas fa-gas-pump"></i><span>Gas</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noFilterResults" class="d-none">
                                <td colspan="8" class="empty-state text-muted">
                                    <i class="fas fa-search fa-2x mb-2 d-block"></i>
                                    No current-day records match your filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
        <div id="personnelToast" class="toast align-items-center border-0 text-bg-success" role="status" aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="personnelToastMessage">Opening document...</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        var approvedIssuances = <?php echo json_encode(array_map(static function (array $issuance): array {
            return [
                'id' => $issuance['id'],
                'serial_no' => $issuance['serial_no'],
                'date' => $issuance['issue_date'],
                'plate_no' => $issuance['plate_no'],
                'driver_name' => $issuance['driver_name'],
                'vehicle_type' => $issuance['vehicle_type'],
                'liters' => $issuance['actual_liters_fueled'] ?? $issuance['authorized_liters'],
                'issued_liters' => $issuance['authorized_liters'],
                'unit' => $issuance['unit'] ?? 'Liters',
                'fuel_type' => $issuance['fuel_type'] ?? 'Unleaded',
                'office' => $issuance['office'] ?? '',
                'purpose' => $issuance['purpose'] ?? 'OFFICIAL TRAVEL',
                'status' => $issuance['status'] ?? 'valid'
            ];
        }, $todayIssuances), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        function getIssuance(id) {
            return approvedIssuances.find(function(item) {
                return String(item.id) === String(id);
            });
        }

        function showToast(message) {
            var toastEl = document.getElementById('personnelToast');
            document.getElementById('personnelToastMessage').textContent = message;
            bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2400 }).show();
        }

        function buildGasParams(item) {
            return new URLSearchParams({
                serial_no: item.serial_no || '',
                date: item.date || '',
                quantity: item.liters || '0',
                unit: item.unit || 'L',
                description: item.fuel_type || 'Unleaded',
                vehicle: item.vehicle_type || '',
                plate_no: item.plate_no || '',
                purpose: item.office || 'Office',
                driver: item.driver_name || ''
            });
        }

        function applyFilters() {
            var query = document.getElementById('personnelSearch').value.trim().toLowerCase();
            var date = document.getElementById('dateFilter').value;
            var rows = Array.from(document.querySelectorAll('#approvedTableBody tr[data-search]'));
            var visible = 0;

            rows.forEach(function(row) {
                var matchesQuery = !query || row.dataset.search.indexOf(query) !== -1;
                var matchesDate = !date || row.dataset.date === date;
                var show = matchesQuery && matchesDate;
                row.classList.toggle('d-none', !show);
                if (show) visible++;
            });

            var noResults = document.getElementById('noFilterResults');
            if (noResults) {
                noResults.classList.toggle('d-none', visible !== 0);
            }
        }

        document.getElementById('personnelSearch').addEventListener('input', applyFilters);
        document.getElementById('dateFilter').addEventListener('change', applyFilters);
        document.getElementById('clearFiltersBtn').addEventListener('click', function() {
            document.getElementById('personnelSearch').value = '';
            document.getElementById('dateFilter').value = '';
            applyFilters();
        });

        document.getElementById('approvedTableBody').addEventListener('click', function(event) {
            var gasButton = event.target.closest('.action-print-gas');

            if (!gasButton) {
                return;
            }

            var item = getIssuance(gasButton.dataset.id);
            if (!item) {
                return;
            }

            window.open('fuel_withdrawal.php?' + buildGasParams(item).toString(), '_blank', 'noopener');
            showToast('Opening gas issuance for ' + item.serial_no + '...');
        });
    </script>
</body>

</html>
