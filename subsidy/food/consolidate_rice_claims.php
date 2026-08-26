<?php
session_start();
$conn = require __DIR__ . '/config/database.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['username'], $_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../login_v2.php');
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RICE_VERIFIER') {
    header('Location: ../../login_v2.php');
    exit();
}
if (empty($_SESSION['rice_consolidation_csrf'])) {
    $_SESSION['rice_consolidation_csrf'] = bin2hex(random_bytes(32));
}

$stationName = 'Rice Assistance Verification';
$csrfToken = $_SESSION['rice_consolidation_csrf'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rice Claim Consolidation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
    <style>
        :root {
            --rice-teal: #0f766e;
            --rice-teal-dark: #115e59;
            --rice-teal-soft: #ccfbf1;
            --rice-teal-border: #14b8a6;
        }
        body { background: #f5f7f8; }
        .text-rice-teal { color: var(--rice-teal) !important; }
        .btn-rice-teal { background: var(--rice-teal); border-color: var(--rice-teal); color: #fff; }
        .btn-rice-teal:hover, .btn-rice-teal:focus { background: var(--rice-teal-dark); border-color: var(--rice-teal-dark); color: #fff; }
        .filter-strip { display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .25rem; scrollbar-width: thin; }
        .filter-strip .btn { white-space: nowrap; border-radius: 6px; }
        .filter-strip .btn.active { background: var(--rice-teal); border-color: var(--rice-teal); color: #fff; }
        .claim-table { min-width: 980px; }
        .claim-table th { font-size: .75rem; text-transform: uppercase; color: #5f6b73; letter-spacing: 0; white-space: nowrap; }
        .claim-meta { font-size: .78rem; color: #6c757d; }
        .wave-proof { border: 1px solid #dce2e6; border-radius: 6px; padding: 1rem; height: 100%; background: #fff; }
        .signature-frame { height: 145px; border: 1px solid #ced4da; background: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .signature-frame img { max-width: 100%; max-height: 135px; object-fit: contain; }
        .signature-frame .empty-signature { color: #6c757d; font-size: .875rem; }
        .history-list { max-height: 260px; overflow-y: auto; }
        .history-row { border-bottom: 1px solid #e9ecef; padding: .75rem 0; }
        .history-row:last-child { border-bottom: 0; }
        .status-badge { min-width: 112px; }
        .toolbar-grid { max-width: 100%; }
        .toolbar-search { width: min(70vw, 340px); flex: 0 1 340px; }
        .toolbar-grid > .btn { flex: 0 0 auto; }
        .loading-cell { height: 180px; vertical-align: middle; }
        .modal-xl { --bs-modal-width: 1120px; }
        @media (max-width: 767.98px) {
            .page-heading { font-size: 1.4rem; }
            .toolbar-grid > * { width: 100%; }
            .toolbar-search { flex-basis: auto; }
            .modal-body { padding: 1rem; }
            .signature-frame { height: 120px; }
        }
    </style>
    <script src="./js/session_heartbeat.js"></script>
    <script>SessionHeartbeat.init({ apiUrl: './api_heartbeat.php' });</script>
</head>
<body>
<nav class="navbar bg-body-tertiary fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard_rice.php">Rice Assistance - <?php echo htmlspecialchars($stationName); ?></a>
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
                    <li class="nav-item"><a class="nav-link" href="releasing_rice.php">Next-Wave Releasing</a></li>
                    <li class="nav-item"><a class="nav-link" href="releasing_rice_first_wave.php">First-Wave Releasing</a></li>
                    <li class="nav-item"><a class="nav-link" href="cross_check_rice.php">Cross Check</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="consolidate_rice_claims.php">Claim Consolidation</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="../../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<main class="pt-5">
    <div class="container-fluid py-4 mt-3">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
            <div>
                <h1 class="page-heading h3 fw-semibold mb-1">Claim Consolidation</h1>
                <p class="text-muted mb-0">Compare first- and second-wave proof records by household code.</p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 toolbar-grid">
                <div class="input-group toolbar-search">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="pairSearch" placeholder="Search code or name" autocomplete="off">
                </div>
                <button class="btn btn-outline-secondary" type="button" id="refreshPairsBtn" title="Refresh records"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            </div>
        </div>

        <div class="filter-strip mb-3" id="pairFilters" aria-label="Filter claim pairs">
            <button type="button" class="btn btn-outline-secondary active" data-filter="all">All <span class="badge text-bg-light ms-1" data-count="all">0</span></button>
            <button type="button" class="btn btn-outline-secondary" data-filter="both_waves">Both Waves <span class="badge text-bg-light ms-1" data-count="both_waves">0</span></button>
            <button type="button" class="btn btn-outline-secondary" data-filter="first_wave_only">First Only <span class="badge text-bg-light ms-1" data-count="first_wave_only">0</span></button>
            <button type="button" class="btn btn-outline-secondary" data-filter="second_wave_only">Second Only <span class="badge text-bg-light ms-1" data-count="second_wave_only">0</span></button>
            <button type="button" class="btn btn-outline-secondary" data-filter="name_difference">Name Difference <span class="badge text-bg-light ms-1" data-count="name_difference">0</span></button>
            <button type="button" class="btn btn-outline-secondary" data-filter="unmatched">Unmatched <span class="badge text-bg-light ms-1" data-count="unmatched">0</span></button>
        </div>

        <section class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 claim-table">
                    <thead class="table-light">
                    <tr>
                        <th class="ps-3">Household</th>
                        <th>Pairing</th>
                        <th>First Wave Proof</th>
                        <th>Second Wave Proof</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                    </thead>
                    <tbody id="claimPairsBody">
                    <tr><td colspan="5" class="text-center text-muted loading-cell"><span class="spinner-border spinner-border-sm me-2"></span>Loading claimed records...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-3">
                <span class="small text-muted" id="pairsPaginationInfo">Loading records...</span>
                <div class="btn-group" aria-label="Claim pair pages">
                    <button type="button" class="btn btn-outline-secondary" id="pairsPrevBtn" title="Previous page"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary disabled" id="pairsPageLabel" tabindex="-1">Page 1</button>
                    <button type="button" class="btn btn-outline-secondary" id="pairsNextBtn" title="Next page"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </section>
    </div>
</main>

<div class="modal fade" id="claimPairModal" tabindex="-1" aria-labelledby="claimPairModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="claimPairModalLabel">Claim Proof Review</h5>
                    <div class="small text-muted" id="pairModalCode">--</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-none" id="pairNameWarning"><i class="bi bi-exclamation-triangle me-2"></i>The household names differ between waves. Verify both records before copying a signature.</div>
                <div class="alert alert-secondary d-none" id="pairReadOnlyWarning"><i class="bi bi-lock me-2"></i>This record is unmatched. Proof editing and signature copying are disabled.</div>
                <div class="row g-3">
                    <div class="col-lg-5">
                        <section class="wave-proof" aria-labelledby="firstWaveHeading">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0" id="firstWaveHeading">First Wave</h6>
                                <span class="badge text-bg-secondary" id="firstWaveState">Not found</span>
                            </div>
                            <div class="small text-muted mb-1">Household Name</div><div class="fw-semibold mb-3" id="firstHouseholdName">--</div>
                            <label class="form-label" for="firstClaimantName">Claimant Name</label>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="firstClaimantName" maxlength="150">
                                <button type="button" class="btn btn-outline-success" id="saveFirstClaimantBtn" title="Save first-wave claimant"><i class="bi bi-floppy"></i></button>
                            </div>
                            <div class="row g-2 small mb-3">
                                <div class="col-6"><span class="text-muted d-block">Claim Date</span><span id="firstClaimDate">--</span></div>
                                <div class="col-6"><span class="text-muted d-block">Verifier</span><span id="firstVerifier">--</span></div>
                            </div>
                            <div class="signature-frame"><img id="firstSignature" alt="First-wave signature"><span class="empty-signature" id="firstSignatureEmpty">No signature</span></div>
                        </section>
                    </div>
                    <div class="col-lg-2 d-flex flex-lg-column align-items-center justify-content-center gap-2 py-2">
                        <button type="button" class="btn btn-rice-teal w-100" id="copyFirstToSecondBtn"><i class="bi bi-arrow-right me-1"></i><span class="d-lg-none">Copy First to Second</span><span class="d-none d-lg-inline">To Second</span></button>
                        <button type="button" class="btn btn-outline-success w-100" id="copySecondToFirstBtn"><i class="bi bi-arrow-left me-1"></i><span class="d-lg-none">Copy Second to First</span><span class="d-none d-lg-inline">To First</span></button>
                    </div>
                    <div class="col-lg-5">
                        <section class="wave-proof" aria-labelledby="secondWaveHeading">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0" id="secondWaveHeading">Second Wave</h6>
                                <span class="badge text-bg-secondary" id="secondWaveState">Not found</span>
                            </div>
                            <div class="small text-muted mb-1">Household Name</div><div class="fw-semibold mb-3" id="secondHouseholdName">--</div>
                            <label class="form-label" for="secondClaimantName">Claimant Name</label>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="secondClaimantName" maxlength="150">
                                <button type="button" class="btn btn-outline-success" id="saveSecondClaimantBtn" title="Save second-wave claimant"><i class="bi bi-floppy"></i></button>
                            </div>
                            <div class="row g-2 small mb-3">
                                <div class="col-6"><span class="text-muted d-block">Claim Date</span><span id="secondClaimDate">--</span></div>
                                <div class="col-6"><span class="text-muted d-block">Verifier</span><span id="secondVerifier">--</span></div>
                            </div>
                            <div class="signature-frame"><img id="secondSignature" alt="Second-wave signature"><span class="empty-signature" id="secondSignatureEmpty">No signature</span></div>
                        </section>
                    </div>
                </div>

                <section class="mt-4 border-top pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Change History</h6>
                        <span class="small text-muted">Latest 25 actions</span>
                    </div>
                    <div class="history-list" id="claimHistoryList"><div class="text-muted small py-3">No consolidation changes recorded.</div></div>
                </section>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="copySignatureModal" tabindex="-1" aria-labelledby="copySignatureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="copySignatureModalLabel">Confirm Signature Overwrite</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="copyConfirmationText">Review both signatures before continuing.</p>
                <div class="row g-3">
                    <div class="col-md-6"><div class="small text-muted mb-1">Source Signature</div><div class="signature-frame"><img id="copySourceSignature" alt="Source signature"><span class="text-muted small d-none" id="copySourceSignatureEmpty">No signature</span></div></div>
                    <div class="col-md-6"><div class="small text-muted mb-1">Current Target Signature</div><div class="signature-frame"><img id="copyTargetSignature" alt="Current target signature"><span class="text-muted small d-none" id="copyTargetSignatureEmpty">No signature</span></div></div>
                </div>
                <div class="alert alert-danger mt-3 mb-0"><i class="bi bi-exclamation-octagon me-2"></i>The target signature will be replaced. The previous signature will remain recoverable from Change History.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmCopySignatureBtn"><i class="bi bi-arrow-left-right me-1"></i>Overwrite Signature</button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3"><div class="toast align-items-center text-bg-dark border-0" id="consolidationToast" role="status" aria-live="polite"><div class="d-flex"><div class="toast-body" id="consolidationToastMessage"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.RICE_CONSOLIDATION_CONFIG = <?php echo json_encode(['csrfToken' => $csrfToken], JSON_UNESCAPED_SLASHES); ?>;</script>
<script src="rice_claim_consolidation.js?v=<?php echo rawurlencode((string)filemtime(__DIR__ . '/rice_claim_consolidation.js')); ?>"></script>
</body>
</html>