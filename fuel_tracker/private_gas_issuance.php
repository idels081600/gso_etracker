<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
requireFuelRole('fuel_admin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gas_issuance_data.php';

fuelTrackerEnsureScopeColumns($conn);

$today = date('Y-m-d');
$nextWeek = date('Y-m-d', strtotime('+7 days'));
$privateVehicles = fuelTrackerFetchVehicles($conn, 'private');
$privateIssuances = fuelTrackerFetchGasIssuances($conn, [], 'private');
$serialNo = 'PFI-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

function privateGasEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function privateGasStatusClass(string $status): string
{
    return match (strtolower($status)) {
        'approved', 'valid' => 'text-bg-success',
        'used' => 'text-bg-warning',
        'expired', 'revoked' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Gas Issuance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="fuel_dashboard.css">
    <style>
        body {
            background: #f5f7fa;
        }
        .private-shell {
            max-width: 1480px;
        }
        .private-hero {
            align-items: center;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }
        .private-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        .metric-strip {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .metric-box {
            background: #fff;
            border: 1px solid #e5e9ef;
            border-radius: 8px;
            padding: 1rem;
        }
        .metric-label {
            color: #6c757d;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .metric-value {
            color: #12263f;
            font-size: 1.65rem;
            font-weight: 900;
            line-height: 1.15;
        }
        .private-table th {
            background: #f0f4f8;
            color: #344054;
            font-size: 0.76rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .private-table td {
            vertical-align: middle;
        }
        .vehicle-cell {
            min-width: 220px;
            white-space: normal;
        }
        .vehicle-cell .plate {
            color: #132f1b;
            font-weight: 800;
        }
        .vehicle-cell .type {
            color: #667085;
            font-size: 0.84rem;
        }
        .action-buttons {
            display: flex;
            gap: 0.4rem;
            justify-content: flex-end;
            white-space: nowrap;
        }
        .approval-cell {
            min-width: 110px;
        }
        .approval-cell .form-check {
            align-items: center;
            display: inline-flex;
            gap: 0.35rem;
            margin: 0;
            min-height: 0;
        }
        .approval-cell .form-check-input {
            cursor: pointer;
            height: 1.1rem;
            margin: 0;
            width: 1.1rem;
        }
        .optional-note {
            color: #6c757d;
            font-size: 0.78rem;
        }
        .empty-state {
            color: #667085;
            padding: 3rem 1rem;
            text-align: center;
        }
        @media (max-width: 991.98px) {
            .private-hero {
                align-items: stretch;
                flex-direction: column;
            }
            .private-actions {
                justify-content: stretch;
            }
            .private-actions .btn {
                flex: 1 1 180px;
            }
            .metric-strip {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="fuel_dashboard.php">
                <i class="fas fa-gas-pump text-primary me-2"></i>
                <span class="fw-bold text-dark">Fuel Tracker</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="fuel_dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="gas_issuance.php"><i class="fas fa-receipt me-1"></i>Gas Issuance</a></li>
                    <li class="nav-item"><a class="nav-link active" href="private_gas_issuance.php"><i class="fas fa-car-side me-1"></i>Private Issuance</a></li>
                </ul>
                <a class="btn btn-outline-secondary btn-sm" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="container-fluid private-shell py-4">
        <div class="private-hero mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fas fa-car-side text-primary fs-3"></i>
                    <h1 class="h3 mb-0 fw-bold">Private Vehicle Gas Issuance</h1>
                </div>
                <p class="text-muted mb-0">Manual fuel issuance records for private vehicles, separated from government reports and budgets.</p>
            </div>
            <div class="private-actions">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#privateVehicleModal">
                    <i class="fas fa-plus-circle me-1"></i>Add Private Vehicle
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#privateIssuanceModal" <?php echo $privateVehicles === [] ? 'disabled' : ''; ?>>
                    <i class="fas fa-file-circle-plus me-1"></i>Create Private Issuance
                </button>
            </div>
        </div>

        <div id="privateAlert" class="alert d-none" role="alert"></div>

        <section class="metric-strip mb-3">
            <div class="metric-box">
                <div class="metric-label">Private Vehicles</div>
                <div class="metric-value"><?php echo count($privateVehicles); ?></div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Private Issuances</div>
                <div class="metric-value"><?php echo count($privateIssuances); ?></div>
            </div>
            <div class="metric-box">
                <div class="metric-label">Total Liters</div>
                <div class="metric-value">
                    <?php
                    $totalLiters = array_reduce($privateIssuances, static fn(float $sum, array $row): float => $sum + (float) ($row['authorized_liters'] ?? 0), 0.0);
                    echo privateGasEscape(number_format($totalLiters, 2));
                    ?> L
                </div>
            </div>
        </section>

        <section class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="h5 mb-0"><i class="fas fa-list-check me-2 text-primary"></i>Private Issuance Records</h2>
                <span class="badge text-bg-primary">Private</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle private-table mb-0">
                    <thead>
                        <tr>
                            <th>Serial No.</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Fuel</th>
                            <th>Liters</th>
                            <th>Issue Date</th>
                            <th>Approved</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($privateIssuances === []): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-file-circle-plus fa-2x mb-2 text-primary"></i>
                                    <div class="fw-bold">No private gas issuance records yet.</div>
                                    <div>Add a private vehicle, then create the first manual issuance.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($privateIssuances as $issuance): ?>
                            <?php
                            $serial = (string) ($issuance['serial_no'] ?? '');
                            $status = strtolower((string) ($issuance['status'] ?? 'draft'));
                            $isApproved = in_array($status, ['approved', 'valid', 'used'], true);
                            $approvalLocked = in_array($status, ['used', 'expired', 'revoked'], true);
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo privateGasEscape($serial); ?></div>
                                    <span class="badge text-bg-light border">Private</span>
                                </td>
                                <td class="vehicle-cell">
                                    <div class="plate"><?php echo privateGasEscape($issuance['plate_no'] ?? ''); ?></div>
                                    <div class="type"><?php echo privateGasEscape($issuance['vehicle_type'] ?? 'Private Vehicle'); ?></div>
                                </td>
                                <td><?php echo privateGasEscape($issuance['driver_name'] ?: ''); ?></td>
                                <td class="text-capitalize"><?php echo privateGasEscape($issuance['fuel_type'] ?? ''); ?></td>
                                <td class="fw-bold"><?php echo privateGasEscape(number_format((float) ($issuance['authorized_liters'] ?? 0), 2)); ?> L</td>
                                <td><?php echo privateGasEscape($issuance['issue_date'] ?? ''); ?></td>
                                <td class="approval-cell">
                                    <label class="form-check" title="<?php echo $approvalLocked ? 'This status can no longer be changed.' : 'Approve private issuance'; ?>">
                                        <input
                                            type="checkbox"
                                            class="form-check-input private-approval-checkbox"
                                            data-id="<?php echo privateGasEscape($issuance['id'] ?? ''); ?>"
                                            <?php echo $isApproved ? 'checked' : ''; ?>
                                            <?php echo $approvalLocked ? 'disabled' : ''; ?>
                                        >
                                        <span class="small fw-semibold">Approved</span>
                                    </label>
                                </td>
                                <td><span class="badge <?php echo privateGasEscape(privateGasStatusClass($status)); ?>" data-status-badge="<?php echo privateGasEscape($issuance['id'] ?? ''); ?>"><?php echo privateGasEscape(ucfirst($status)); ?></span></td>
                                <td>
                                    <div class="action-buttons">
                                        <a class="btn btn-sm btn-outline-primary" href="fuel_withdrawal.php?serial_no=<?php echo urlencode($serial); ?>" target="_blank" title="Print gas issuance">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div class="modal fade" id="privateVehicleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-car-side me-2"></i>Add Private Vehicle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="privateVehicleForm" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="privateVehiclePlate" class="form-label">Plate No. <span class="optional-note">(optional if vehicle name is provided)</span></label>
                                <input type="text" class="form-control" id="privateVehiclePlate" placeholder="e.g. ABC 1234">
                            </div>
                            <div class="col-md-6">
                                <label for="privateVehicleType" class="form-label">Vehicle Name/Type <span class="optional-note">(optional if plate is provided)</span></label>
                                <input type="text" class="form-control" id="privateVehicleType" placeholder="e.g. Toyota Vios">
                            </div>
                            <div class="col-md-4">
                                <label for="privateVehicleFuelType" class="form-label">Fuel Type</label>
                                <select class="form-select" id="privateVehicleFuelType" required>
                                    <option value="">Select fuel</option>
                                    <option value="unleaded">Unleaded</option>
                                    <option value="diesel">Diesel</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="privateVehicleOffice" class="form-label">Office <span class="optional-note">(optional)</span></label>
                                <input type="text" class="form-control" id="privateVehicleOffice" placeholder="PRIVATE">
                            </div>
                            <div class="col-md-4">
                                <label for="privateVehicleStatus" class="form-label">Status</label>
                                <select class="form-select" id="privateVehicleStatus">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="privateVehicleCapacity" class="form-label">Fuel Capacity</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="privateVehicleCapacity" min="0" step="0.01">
                                    <span class="input-group-text">L</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="privateVehicleFixedLiters" class="form-label">Fixed Liters</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="privateVehicleFixedLiters" min="0" step="0.01">
                                    <span class="input-group-text">L</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="privateVehicleOdometer" class="form-label">Current Odometer</label>
                                <input type="number" class="form-control" id="privateVehicleOdometer" min="0" step="0.1">
                            </div>
                            <div class="col-md-3">
                                <label for="privateVehicleKmLiter" class="form-label">Normal km/liter</label>
                                <input type="number" class="form-control" id="privateVehicleKmLiter" min="0" step="0.01">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="savePrivateVehicleBtn">
                        <i class="fas fa-save me-1"></i>Save Private Vehicle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="privateIssuanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-file-circle-plus me-2"></i>Create Private Issuance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="privateIssuanceForm" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="privateIssuanceSerial" class="form-label">Serial No.</label>
                                <input type="text" class="form-control" id="privateIssuanceSerial" value="<?php echo privateGasEscape($serialNo); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="privateIssuanceVehicle" class="form-label">Private Vehicle</label>
                                <select class="form-select" id="privateIssuanceVehicle" required>
                                    <option value="">Select private vehicle</option>
                                    <?php foreach ($privateVehicles as $vehicle): ?>
                                        <option
                                            value="<?php echo privateGasEscape($vehicle['id'] ?? ''); ?>"
                                            data-fuel-type="<?php echo privateGasEscape($vehicle['fuel_type'] ?? ''); ?>"
                                            data-office="<?php echo privateGasEscape($vehicle['office'] ?? 'PRIVATE'); ?>"
                                        >
                                            <?php echo privateGasEscape(trim(($vehicle['plate_no'] ?? '') . ' - ' . ($vehicle['type_of_vehicle'] ?? 'Private Vehicle'))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="privateIssuanceDate" class="form-label">Issue Date</label>
                                <input type="date" class="form-control" id="privateIssuanceDate" value="<?php echo privateGasEscape($today); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="privateIssuanceExpiry" class="form-label">Expiry Date <span class="optional-note">(optional)</span></label>
                                <input type="date" class="form-control" id="privateIssuanceExpiry" value="<?php echo privateGasEscape($nextWeek); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="privateIssuanceLiters" class="form-label">Authorized Liters</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="privateIssuanceLiters" min="0.01" step="0.01" required>
                                    <span class="input-group-text">L</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="privateIssuanceFuelType" class="form-label">Fuel Type <span class="optional-note">(optional)</span></label>
                                <select class="form-select" id="privateIssuanceFuelType">
                                    <option value="">Use vehicle fuel type</option>
                                    <option value="unleaded">Unleaded</option>
                                    <option value="diesel">Diesel</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="privateIssuanceStatus" class="form-label">Status</label>
                                <select class="form-select" id="privateIssuanceStatus">
                                    <option value="draft">Draft</option>
                                    <option value="approved">Approved</option>
                                    <option value="valid">Valid</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="privateIssuanceOffice" class="form-label">Office <span class="optional-note">(optional)</span></label>
                                <input type="text" class="form-control" id="privateIssuanceOffice" placeholder="PRIVATE">
                            </div>
                            <div class="col-md-6">
                                <label for="privateIssuanceDriver" class="form-label">Driver <span class="optional-note">(optional)</span></label>
                                <input type="text" class="form-control" id="privateIssuanceDriver" placeholder="Leave blank if unknown">
                            </div>
                            <div class="col-md-6">
                                <label for="privateIssuancePurpose" class="form-label">Purpose <span class="optional-note">(optional)</span></label>
                                <input type="text" class="form-control" id="privateIssuancePurpose" placeholder="PRIVATE VEHICLE FUEL ISSUANCE">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="savePrivateIssuanceBtn">
                        <i class="fas fa-save me-1"></i>Save Private Issuance
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const privateVehicles = <?php echo json_encode($privateVehicles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        function showPrivateAlert(message, type = 'success') {
            const alert = document.getElementById('privateAlert');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alert.classList.remove('d-none');
        }

        async function postPrivateJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to save record.');
            }
            return data;
        }

        function setButtonLoading(button, loading) {
            if (!button) return;
            if (loading) {
                button.dataset.originalHtml = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
                return;
            }
            button.disabled = false;
            button.innerHTML = button.dataset.originalHtml || button.innerHTML;
        }

        function privateStatusClass(status) {
            const normalized = String(status || '').toLowerCase();
            if (normalized === 'approved' || normalized === 'valid') return 'text-bg-success';
            if (normalized === 'used') return 'text-bg-warning';
            if (normalized === 'expired' || normalized === 'revoked') return 'text-bg-danger';
            return 'text-bg-secondary';
        }

        document.querySelectorAll('.private-approval-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', async function () {
                const previousValue = !this.checked;
                const id = this.dataset.id;
                this.disabled = true;
                try {
                    const data = await postPrivateJson('private_gas_issuance_save.php', {
                        action: 'approval',
                        id,
                        approved: this.checked,
                    });
                    const status = data.status || (this.checked ? 'approved' : 'draft');
                    const badge = document.querySelector(`[data-status-badge="${CSS.escape(id)}"]`);
                    if (badge) {
                        badge.className = `badge ${privateStatusClass(status)}`;
                        badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    }
                    showPrivateAlert(data.message || 'Approval updated.', 'success');
                } catch (error) {
                    this.checked = previousValue;
                    showPrivateAlert(error.message, 'danger');
                } finally {
                    this.disabled = false;
                }
            });
        });

        document.getElementById('savePrivateVehicleBtn')?.addEventListener('click', async function () {
            const plateNo = document.getElementById('privateVehiclePlate').value.trim();
            const typeOfVehicle = document.getElementById('privateVehicleType').value.trim();
            const fuelType = document.getElementById('privateVehicleFuelType').value;
            if (!plateNo && !typeOfVehicle) {
                showPrivateAlert('Enter a plate number or vehicle name.', 'warning');
                return;
            }
            if (!fuelType) {
                showPrivateAlert('Select a fuel type for the private vehicle.', 'warning');
                return;
            }

            setButtonLoading(this, true);
            try {
                await postPrivateJson('private_vehicle_save.php', {
                    action: 'create',
                    plate_no: plateNo,
                    type_of_vehicle: typeOfVehicle,
                    fuel_type: fuelType,
                    office: document.getElementById('privateVehicleOffice').value,
                    status: document.getElementById('privateVehicleStatus').value,
                    fuel_capacity: document.getElementById('privateVehicleCapacity').value,
                    fixed_liters: document.getElementById('privateVehicleFixedLiters').value,
                    current_odometer: document.getElementById('privateVehicleOdometer').value,
                    normal_km_per_liter: document.getElementById('privateVehicleKmLiter').value,
                });
                showPrivateAlert('Private vehicle saved. Reloading list...', 'success');
                window.location.reload();
            } catch (error) {
                showPrivateAlert(error.message, 'danger');
            } finally {
                setButtonLoading(this, false);
            }
        });

        document.getElementById('privateIssuanceVehicle')?.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const fuelType = selected?.dataset?.fuelType || '';
            const office = selected?.dataset?.office || '';
            document.getElementById('privateIssuanceFuelType').value = fuelType;
            if (!document.getElementById('privateIssuanceOffice').value.trim()) {
                document.getElementById('privateIssuanceOffice').value = office;
            }
        });

        document.getElementById('savePrivateIssuanceBtn')?.addEventListener('click', async function () {
            const vehicleId = document.getElementById('privateIssuanceVehicle').value;
            const issueDate = document.getElementById('privateIssuanceDate').value;
            const liters = document.getElementById('privateIssuanceLiters').value;
            if (!vehicleId || !issueDate || !liters || Number(liters) <= 0) {
                showPrivateAlert('Private vehicle, issue date, and liters are required.', 'warning');
                return;
            }

            setButtonLoading(this, true);
            try {
                await postPrivateJson('private_gas_issuance_save.php', {
                    action: 'create',
                    serial_no: document.getElementById('privateIssuanceSerial').value,
                    vehicle_id: vehicleId,
                    issue_date: issueDate,
                    expiry_date: document.getElementById('privateIssuanceExpiry').value,
                    authorized_liters: liters,
                    fuel_type: document.getElementById('privateIssuanceFuelType').value,
                    status: document.getElementById('privateIssuanceStatus').value,
                    office: document.getElementById('privateIssuanceOffice').value,
                    driver_name: document.getElementById('privateIssuanceDriver').value,
                    purpose: document.getElementById('privateIssuancePurpose').value,
                    unit: 'Liters',
                });
                showPrivateAlert('Private gas issuance saved. Reloading list...', 'success');
                window.location.reload();
            } catch (error) {
                showPrivateAlert(error.message, 'danger');
            } finally {
                setButtonLoading(this, false);
            }
        });
    </script>
</body>

</html>
