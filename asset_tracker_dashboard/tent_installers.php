<?php

$assetRoot = is_dir(__DIR__ . '/asset_tracker_dashboard')
    ? __DIR__ . '/asset_tracker_dashboard'
    : dirname(__DIR__) . '/asset_tracker_dashboard';

require_once $assetRoot . '/app_security.php';
require_once $assetRoot . '/db_asset.php';
require_once $assetRoot . '/ui_helpers.php';

asset_start_session();
$allowedRoles = ['TENT INSTALLERS', 'ASSET', 'ASSET2', 'Admin', 'master_admin'];
if (empty($_SESSION['logged_in']) || empty($_SESSION['username']) || !in_array((string) ($_SESSION['role'] ?? ''), $allowedRoles, true)) {
    $loginPath = is_file(__DIR__ . '/login_v2.php') ? 'login_v2.php' : '../login_v2.php';
    header('Location: ' . $loginPath);
    exit;
}

$statusRows = [];
$statusResult = mysqli_query($conn, 'SELECT id, Status FROM tent_status ORDER BY id ASC');
if ($statusResult) {
    while ($statusRow = mysqli_fetch_assoc($statusResult)) {
        $statusRows[(int) $statusRow['id']] = (string) $statusRow['Status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tent Installer Daily Jobs</title>
    <?php echo asset_csrf_meta(); ?>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="tent_installers.css" rel="stylesheet">
</head>
<body>
    <main class="container-fluid py-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
            <div>
                <h1 class="h3 mb-1">Tent Installer Daily Jobs</h1>
                <p class="text-muted mb-0">Install today’s pending requests and complete due or overdue retrievals.</p>
            </div>
            <button type="button" class="btn btn-outline-success mt-3 mt-md-0" id="refreshBtn">
                <i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh Data
            </button>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <small class="text-muted">
                    <i class="fas fa-calendar-day" aria-hidden="true"></i>
                    Showing today’s pending jobs and all due or overdue retrievals
                    <span id="lastUpdated" class="d-block d-md-inline ml-md-3"></span>
                </small>
            </div>
            <div class="col-md-6 mt-2 mt-md-0">
                <label class="sr-only" for="searchInput">Search jobs</label>
                <input type="search" class="form-control" id="searchInput" placeholder="Search jobs" autocomplete="off">
            </div>
        </div>

        <div class="alert alert-danger" id="pageMessage" role="alert" hidden></div>

        <div class="table-responsive">
            <table class="table table-striped table-fixed">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Location</th>
                        <th>No. of Tents</th>
                        <th>Scheduled Date</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="6" class="text-center">Loading data...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="editModalLabel">Update Tent Job</h5>
                        <small class="text-muted" id="currentJobStatus"></small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editForm">
                    <div class="modal-body">
                        <input type="hidden" id="clientId">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="noOfTents">No. of Tents</label>
                                <input type="number" class="form-control" id="noOfTents" readonly>
                            </div>
                            <div class="form-group col-md-8">
                                <label for="clientName">Name</label>
                                <input type="text" class="form-control" id="clientName" readonly>
                            </div>
                            <div class="form-group col-12">
                                <label for="clientLocation">Location</label>
                                <input type="text" class="form-control" id="clientLocation" readonly>
                            </div>
                            <div class="form-group col-12">
                                <label for="clientAddress">Address</label>
                                <textarea class="form-control installer-location-field" id="clientAddress" rows="3" readonly></textarea>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="clientContact">Contact</label>
                                <input type="text" class="form-control" id="clientContact" readonly>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="clientStatus">Next Status</label>
                                <select class="form-control" id="clientStatus" required></select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="tentNumber">Assigned Tent Numbers</label>
                                <input type="text" class="form-control" id="tentNumber" readonly required>
                                <small class="form-text text-muted" id="tentSelectionHelp"></small>
                            </div>
                        </div>

                        <div class="tent-legend mb-2" aria-label="Tent status legend">
                            <span><i class="legend-swatch is-available"></i>Available</span>
                            <span><i class="legend-swatch is-installed"></i>Installed</span>
                            <span><i class="legend-swatch is-retrieval"></i>For Retrieval</span>
                            <span><i class="legend-swatch is-long-term"></i>Long Term</span>
                        </div>
                        <div class="box-grid" id="tentGrid">
                            <?php for ($i = 1; $i <= 300; $i++):
                                $inventoryStatus = $statusRows[$i] ?? 'Unknown';
                                $statusClass = strtolower(str_replace(' ', '-', $inventoryStatus));
                            ?>
                                <button
                                    class="box status-<?php echo e($statusClass); ?>"
                                    type="button"
                                    data-tent-id="<?php echo $i; ?>"
                                    data-status="<?php echo e($inventoryStatus); ?>"
                                    aria-pressed="false"
                                    title="Tent <?php echo $i; ?>: <?php echo e($inventoryStatus); ?>"
                                ><?php echo $i; ?></button>
                            <?php endfor; ?>
                        </div>
                        <div class="alert alert-danger mt-3 mb-0" id="formMessage" role="alert" hidden></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="saveButton">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="tent_installers.js"></script>
</body>
</html>
