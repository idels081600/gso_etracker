<?php
require_once __DIR__ . '/auth_guard.php';
requireFuelRole('gas_checker');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function gasCheckerEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function renderIconLabel(string $icon, string $label): void
{
    ?>
    <i class="fas <?= gasCheckerEscape($icon) ?> me-1"></i><?= gasCheckerEscape($label) ?>
    <?php
}

function renderReferenceRow(string $label, string $id): void
{
    ?>
    <div class="gas-item">
        <span><?= gasCheckerEscape($label) ?></span>
        <strong id="<?= gasCheckerEscape($id) ?>">--</strong>
    </div>
    <?php
}

function renderActualInput(array $field): void
{
    ?>
    <div class="col-md-4 mb-3">
        <label for="<?= gasCheckerEscape($field['id']) ?>" class="form-label">
            <?php renderIconLabel($field['icon'], $field['label']); ?>
            <span class="text-danger">*</span>
        </label>
        <?php if (!empty($field['suffix'])): ?>
            <div class="input-group">
                <input type="<?= gasCheckerEscape($field['type']) ?>" class="form-control" id="<?= gasCheckerEscape($field['id']) ?>"
                    name="<?= gasCheckerEscape($field['name']) ?>" placeholder="<?= gasCheckerEscape($field['placeholder']) ?>"
                    min="<?= gasCheckerEscape($field['min']) ?>" step="<?= gasCheckerEscape($field['step']) ?>" required>
                <span class="input-group-text"><?= gasCheckerEscape($field['suffix']) ?></span>
                <div class="invalid-feedback">
                    <?= gasCheckerEscape($field['feedback']) ?>
                </div>
            </div>
        <?php else: ?>
            <input type="<?= gasCheckerEscape($field['type']) ?>" class="form-control" id="<?= gasCheckerEscape($field['id']) ?>"
                name="<?= gasCheckerEscape($field['name']) ?>" placeholder="<?= gasCheckerEscape($field['placeholder']) ?>" required>
            <div class="invalid-feedback">
                <?= gasCheckerEscape($field['feedback']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

$referenceRows = [
    'Vehicle' => 'checkVehicle',
    'Fuel Type Issued' => 'checkFuelType',
    'Liters Issued' => 'checkLitersIssued',
    'Issuance Reference #' => 'checkIssuanceRef',
];

$actualFields = [
    [
        'id' => 'checkOdometer',
        'name' => 'odometer',
        'type' => 'number',
        'icon' => 'fa-tachometer-alt',
        'label' => 'Latest Odometer Reading',
        'placeholder' => 'Enter odometer reading',
        'min' => '0',
        'step' => '1',
        'suffix' => 'km',
        'feedback' => 'Please enter the latest odometer reading.',
    ],
    [
        'id' => 'checkDriver',
        'name' => 'driver',
        'type' => 'text',
        'icon' => 'fa-user',
        'label' => 'Driver Name',
        'placeholder' => 'e.g. Juan Dela Cruz',
        'feedback' => "Please enter the driver's name.",
    ],
    [
        'id' => 'checkActual',
        'name' => 'actual_fuel',
        'type' => 'number',
        'icon' => 'fa-tint',
        'label' => 'Actual Fueled Up',
        'placeholder' => 'Enter actual liters',
        'min' => '0',
        'step' => '0.01',
        'suffix' => 'L',
        'feedback' => 'Please enter the actual fueled up amount.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gas Checker</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="fuel_dashboard.css">
    <link rel="stylesheet" href="gas_checker.css">
</head>

<body>
    <nav class="navbar navbar-expand-md checker-navbar shadow-sm" aria-label="Gas checker navigation">
        <div class="container-fluid checker-navbar-inner">
            <a class="navbar-brand checker-navbar-brand" href="gas_checker.php">
                <span class="checker-navbar-icon">
                    <i class="fas fa-gas-pump"></i>
                </span>
                <span>
                    <strong>Fuel Tracker</strong>
                    <small>Gas Checker</small>
                </span>
            </a>
            <button class="navbar-toggler checker-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#checkerNavbarMenu" aria-controls="checkerNavbarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="checkerNavbarMenu">
                <div class="navbar-nav ms-auto checker-navbar-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm js-reset-all">
                        <i class="fas fa-rotate-left me-1"></i>Reset
                    </button>
                    <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid checker-shell mt-4">
        <div class="checker-search-section shadow-sm">
            <div class="checker-lookup-copy">
                <h2>Gas Checker</h2>
                <p>Search for Issuance ID and confirm issuance release details.</p>
                <div class="checker-search-control">
                    <div class="input-group input-group-lg checker-id-group">
                        <input type="text" class="form-control form-control-lg" id="issuanceIdSearch"
                            placeholder="Search tricycle number or voucher" aria-label="Gas Issuance ID">
                        <button class="btn btn-primary btn-lg" id="searchIssuanceBtn" type="button">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                    <button class="btn btn-outline-secondary btn-lg checker-qr-btn" type="button" id="scanQrBtn" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
                        <i class="fas fa-qrcode me-1"></i>Scan QR
                    </button>
                </div>
            </div>
            <div class="checker-current-card" aria-live="polite">
                <div class="checker-current-label">Current<br>Vehicle</div>
                <div class="checker-current-value" id="currentCheckerVehicle">----</div>
                <div class="checker-current-help" id="currentCheckerHelp">Search to load</div>
            </div>
        </div>

        <div class="checker-form-section" id="checkerFormSection">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-signature me-2"></i>Input Actual Fuel-Up Data & E-Signature
                    </h5>
                </div>
                <div class="card-body">
                    <form id="checkerForm" novalidate>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="issued-gas-info">
                                    <h6 class="fw-bold mb-3">
                                        <i class="fas fa-file-invoice me-2"></i>Issued Gas Reference
                                    </h6>
                                    <?php foreach ($referenceRows as $label => $id): ?>
                                        <?php renderReferenceRow($label, $id); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold mb-3 text-primary">
                            <i class="fas fa-pen me-2"></i>Enter Actual Fuel-Up Information
                        </h6>
                        <div class="row">
                            <?php foreach ($actualFields as $field): ?>
                                <?php renderActualInput($field); ?>
                            <?php endforeach; ?>
                        </div>

                        <div class="row mt-2">
                            <div class="col-12">
                                <label for="checkSignaturePad" class="form-label">
                                    <?php renderIconLabel('fa-signature', 'Driver E-Signature'); ?>
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="signature-pad-wrap">
                                    <canvas id="checkSignaturePad" class="signature-pad" width="900" height="220" aria-label="Driver e-signature pad"></canvas>
                                    <input type="hidden" id="checkSignature" name="signature" required>
                                    <div class="signature-placeholder" id="signaturePlaceholder">Sign here</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">Use mouse or touch to sign before submitting.</small>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearSignatureBtn">
                                        <i class="fas fa-eraser me-1"></i>Clear Signature
                                    </button>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="signatureFeedback">
                                    Please provide the driver's e-signature.
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-lg px-5" id="submitCheckBtn">
                                        <i class="fas fa-file-signature me-1"></i>Submit E-Signature
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-lg js-reset-all">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="checkResult"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="qrScannerModalLabel">
                        <i class="fas fa-qrcode me-2"></i>Scan Gas Issuance QR
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="qr-scanner-frame">
                        <video id="qrScannerVideo" class="qr-scanner-video" autoplay muted playsinline></video>
                        <div class="qr-scanner-target" aria-hidden="true"></div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0" id="qrScannerStatus">
                        Point the camera at the gas issuance QR code.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="restartQrScannerBtn">
                        <i class="fas fa-camera me-1"></i>Restart Camera
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="gas_checker.js"></script>
</body>

</html>
