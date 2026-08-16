<?php
session_start();
$conn = require(__DIR__ . '/config/database.php');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    header("Location: ../../login_v2.php");
    exit();
}
$station_name = 'Rice Assistance Verification';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Next-Wave Rice Assistance Releasing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
    <style>
        :root {
            --rice-teal: #0f766e;
            --rice-teal-dark: #115e59;
            --rice-teal-soft: #ccfbf1;
            --rice-teal-border: #14b8a6;
        }
        .btn-rice-teal {
            background-color: var(--rice-teal);
            border-color: var(--rice-teal);
            color: #fff;
        }
        .btn-rice-teal:hover,
        .btn-rice-teal:focus {
            background-color: var(--rice-teal-dark);
            border-color: var(--rice-teal-dark);
            color: #fff;
        }
        .text-rice-teal { color: var(--rice-teal) !important; }
        .bg-rice-teal-soft { background-color: var(--rice-teal-soft) !important; }
        .border-rice-teal { border-color: var(--rice-teal-border) !important; }
        .bg-rice-danger-soft { background-color: #fee2e2 !important; }
        .border-rice-danger { border-color: #ef4444 !important; }
    </style>
    <script src="./js/session_heartbeat.js"></script>
    <script>
        SessionHeartbeat.init({ apiUrl: './api_heartbeat.php' });
    </script>
</head>
<body>
    <nav class="navbar bg-body-tertiary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard_rice.php">Rice Assistance - <?php echo htmlspecialchars($station_name); ?></a>

            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Rice Assistance Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                        <li class="nav-item"><a class="nav-link" href="dashboard_rice.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="releasing_rice.php">Next-Wave Releasing</a></li>
                        <li class="nav-item"><a class="nav-link" href="releasing_rice_first_wave.php">First-Wave Releasing</a></li>
                        <li class="nav-item"><a class="nav-link" href="cross_check_rice.php">Cross Check</a></li>
                        <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-md-10 col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body py-5">
                            <div class="row align-items-center">
                                <div class="col-md-8 mb-4 mb-md-0">
                                    <h2 class="fw-bold mb-2">Next-Wave Rice Assistance Releasing</h2>
                                    <p class="text-muted mb-4">Search for a household and confirm the one-time rice voucher release.</p>
                                    <div class="input-group input-group-lg position-relative">
                                        <input id="mainSearch" type="text" class="form-control" placeholder="Search household code" aria-label="Search household" autocomplete="off">
                                        <button class="btn btn-rice-teal" type="button" id="searchBtn"><i class="bi bi-search me-2"></i>Search</button>
                                        <div id="searchDropdown" class="dropdown-menu w-100 shadow" style="display: none; top: 100%; left: 0; border-radius: 0 0 8px 8px; z-index: 1050; max-height: 320px; overflow-y: auto;">
                                            <div class="text-muted text-center py-2 small">Searching...</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-4 bg-light p-4 h-100 d-flex flex-column justify-content-center align-items-center text-center">
                                        <p class="text-uppercase text-secondary mb-2">Current Household</p>
                                        <h1 id="currentHouseholdCode" class="display-5 fw-bold mb-1">----</h1>
                                        <p id="currentHouseholdName" class="mb-1 text-muted">No household selected</p>
                                        <p id="currentHouseholdStatus" class="mb-0 text-muted">Search to load</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 mt-4">
                        <div class="card-body">
                            <h5 class="fw-semibold mb-3">Rice Claim Status</h5>
                            <p class="text-muted mb-4">Each household can receive exactly one rice assistance voucher.</p>
                            <div class="row justify-content-center">
                                <div class="col-lg-8">
                                    <div id="claimStateCard" class="card border-2 border-secondary-subtle bg-light">
                                        <div class="card-body text-center py-5">
                                            <p class="text-uppercase text-muted small mb-2">Voucher Status</p>
                                            <h3 id="claimStateText" class="fw-bold mb-2">No household loaded</h3>
                                            <p id="claimStateHint" class="text-muted mb-0">Search by household code or household name.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 d-flex gap-2 justify-content-center">
                                <button type="button" class="btn btn-rice-teal" id="reviewClaimBtn" data-bs-toggle="modal" data-bs-target="#claimModal" disabled>
                                    <i class="bi bi-check-lg me-1"></i>Claim
                                </button>
                                <button type="button" class="btn btn-outline-info d-none" id="viewProofBtn">
                                    <i class="bi bi-eye me-1"></i>View Proof
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clearBtn">
                                    <i class="bi bi-x-lg me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="proofModal" tabindex="-1" aria-labelledby="proofModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-rice-teal" id="proofModalLabel">Proof of Rice Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <h6 class="mb-3">Household: <span id="proofHouseholdCode" class="text-success fw-bold"></span></h6>
                    <p class="mb-2"><strong>Claimant:</strong> <span id="proofClaimant" class="text-muted">N/A</span></p>
                    <p class="mb-2"><strong>Claim Date:</strong> <span id="proofDate" class="text-muted">N/A</span></p>
                    <div class="border rounded p-2 bg-light">
                        <img id="proofSignature" src="" alt="E-Signature" class="img-fluid" style="max-height: 200px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="claimModal" tabindex="-1" aria-labelledby="claimModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="claimModalLabel">Claimant Details & E-Signature</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name of Claimant</label>
                        <div class="mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="claimantOption" id="claimantHousehold" value="household" checked>
                                <label class="form-check-label" for="claimantHousehold">Use Household Name</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="claimantOption" id="claimantManual" value="manual">
                                <label class="form-check-label" for="claimantManual">Enter Manually</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <select class="form-select" id="claimantNameHousehold">
                                <option value="">Select Household Name...</option>
                            </select>
                        </div>
                        <input type="text" class="form-control" id="claimantNameManual" placeholder="Enter claimant name manually" disabled>
                        <input type="hidden" id="claimantName">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-Signature</label>
                        <div id="signatureOptionalNotice" class="alert alert-warning py-2 px-3 d-none">
                            E-signature is currently optional. This claim can be submitted without drawing a signature.
                        </div>
                        <div id="signatureCanvasWrapper" class="border rounded p-2 bg-light">
                            <canvas id="signatureCanvas" height="150" style="width: 100%; height: 150px; border: 1px dashed #ccc; background: #fff; cursor: crosshair;"></canvas>
                        </div>
                        <div class="mt-2" id="clearSignatureWrapper">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">
                                <i class="bi bi-eraser me-1"></i>Clear Signature
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-rice-teal" id="confirmSubmit">
                        <i class="bi bi-check-lg me-1"></i>Review & Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="finalConfirmModal" tabindex="-1" aria-labelledby="finalConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-rice-teal-soft border-rice-teal">
                    <h5 class="modal-title" id="finalConfirmModalLabel">
                        <i class="bi bi-clipboard-check me-2"></i>Final Confirmation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>Please review the rice claim details before final submission.
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted pe-3">Household Code:</td>
                                    <td class="fw-bold" id="confirmHouseholdCode">---</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pe-3">Household Name:</td>
                                    <td class="fw-bold" id="confirmHouseholdName">---</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pe-3">Claimant Name:</td>
                                    <td class="fw-bold" id="confirmClaimantName">---</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small mb-1">E-Signature Preview:</label>
                            <div class="border rounded p-2 bg-light text-center">
                                <img id="confirmSignaturePreview" src="" alt="E-Signature Preview" class="img-fluid" style="max-height: 100px;">
                                <div id="confirmSignatureOptionalText" class="small text-muted d-none">Signature not required for this claim.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-rice-teal" id="finalConfirmSubmit">
                        <i class="bi bi-check-lg me-1"></i>Confirm & Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>window.RICE_RELEASE_CONFIG = { source: 'next_wave' };</script>
    <script src="releasing_rice.js?v=<?php echo rawurlencode((string)filemtime(__DIR__ . '/releasing_rice.js')); ?>"></script>
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin {
            animation: spin 1s linear infinite;
        }
    </style>
</body>
</html>
