<?php
session_start();
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'transmit_db.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="transmittal_bac.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Document</title>
</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="transmittal_bac.php"><i class="fas fa-gavel icon-size"></i>Bidding & RFQ</a></li>
            <li><a href="Po_sap.php"><i class="fas fa-shopping-cart icon-size"></i>Purchase Order</a></li>
        </ul>
        <a href="../logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>
    <div class="content" style="margin-left:250px; padding: 40px 20px; min-height: 100vh; background: #f8f9fa;">
        <div class="container py-4">
            <!-- Add Transmittal Button -->
            <div class="mb-3 d-flex justify-content-end gap-2">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTransmittalModal">
                    <i class="fas fa-plus"></i> Receive
                </button>
                <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#printReportModal">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
            <!-- Horizontal Card List for Projects Near Delivery -->
            <div class="mb-4">
                <h5 class="mb-3">Projects Near Delivery Deadline</h5>
                <div class="d-flex flex-row overflow-auto gap-3" style="white-space: nowrap;">
                    <?php
                    $today = date('Y-m-d');
                    $sql = "SELECT *, DATEDIFF(deadline, '$today') AS days_left FROM transmittal_bac WHERE deadline >= '$today' AND delete_status=0 ORDER BY days_left ASC LIMIT 3";
                    $result = mysqli_query($conn, $sql);
                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $days_left = (int)$row['days_left'];
                            $days_class = $days_left <= 14 ? 'days-left-red' : ($days_left <= 28 ? 'days-left-orange' : '');
                            echo '<div class="card shadow-sm border-0" style="min-width: 250px; max-width: 250px;">';
                            echo '<div class="card-body">';
                            echo '<h6 class="card-title mb-2">' . htmlspecialchars($row['project_name']) . '</h6>';
                            echo '<p class="mb-1"><strong>Days Left:</strong> <span class="days-left-num ' . $days_class . '">' . $days_left . '</span></p>';
                            echo '<p class="mb-0 text-muted" style="font-size: 0.9em;">' . htmlspecialchars($row['ib_no']) . '</p>';
                            echo '<p class="mb-0" style="font-size: 0.9em;"><strong>Winning Bidders:</strong> ' . htmlspecialchars($row['winning_bidders']) . '</p>';
                            echo '</div></div>';
                        }
                    } else {
                        echo '<div class="text-muted">No upcoming deadlines.</div>';
                    }
                    ?>
                </div>
            </div>
            <!-- End Horizontal Card List -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h3 class="mb-4">Transmittal BAC</h3>
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search..." aria-label="Search">
                            <button class="btn btn-outline-secondary" type="button" id="searchButton">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive scrollable-table">
                        <table class="table table-bordered table-hover align-middle bg-white rounded-3 overflow-hidden">
                            <thead class="table-light">
                                <tr>
                                    <th>IB no./RFQ no.</th>
                                    <th>Winning Bidders</th>
                                    <th>Project Name</th>
                                    <th>Amount</th>
                                    <th>Date Transmitted From BAC</th>
                                    <th>Office</th>
                                    <th>NOA no.</th>
                                    <th>Notice to Proceed Date</th>
                                    <th>Calendar Days of Delivery</th>
                                    <th>Deadline</th>
                                    <th>Received by</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php require_once 'display_transmit_data.php';
                                display_transmittal_bac_data(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Transmittal Modal -->
    <div class="modal fade" id="addTransmittalModal" tabindex="-1" aria-labelledby="addTransmittalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTransmittalModalLabel">Add Transmittal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="transmittalForm" method="post" action="submit_transmittal.php">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="transmittal_type" class="form-label">Transmittal Type</label>
                                <select class="form-select" id="transmittal_type" name="transmittal_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Infrastructure">Infrastructure</option>
                                    <option value="Goods">Goods</option>
                                    <option value="Services">Services</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="ib_no" class="form-label">IB no./RFQ no.</label>
                                <input type="text" class="form-control" id="ib_no" name="ib_no" required>
                            </div>
                            <div class="col-md-6">
                                <label for="project_name" class="form-label">Project Name</label>
                                <input type="text" class="form-control" id="project_name" name="project_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="date_received" class="form-label">Date Received</label>
                                <input type="date" class="form-control" id="date_received" name="date_received">
                            </div>
                            <div class="col-md-6">
                                <label for="office" class="form-label">Office</label>
                                <input type="text" class="form-control" id="office" name="office" required>
                            </div>
                            <div class="col-md-6">
                                <label for="received_by" class="form-label">Received by</label>
                                <input type="text" class="form-control" id="received_by" name="received_by" required value="<?php echo htmlspecialchars($full_name); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="winning_bidders" class="form-label">Winning Bidders</label>
                                <input type="text" class="form-control" id="winning_bidders" name="winning_bidders" required>
                            </div>
                            <div class="col-md-6">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="text" class="form-control" id="amount" name="amount">
                            </div>
                            <div class="col-md-6">
                                <label for="NOA_no" class="form-label">NOA no.</label>
                                <input type="text" class="form-control" id="NOA_no" name="NOA_no" required>
                            </div>
                            <div class="col-md-6">
                                <label for="COA_date" class="form-label">Contract of Agreement Date</label>
                                <input type="date" class="form-control" id="COA_date" name="COA_date">
                            </div>
                            <div class="col-md-6">
                                <label for="notice_proceed" class="form-label">Notice to Proceed Date</label>
                                <input type="date" class="form-control" id="notice_proceed" name="notice_proceed">
                            </div>
                            <div class="col-md-6">
                                <label for="deadline" class="form-label">Calendar Days of Delivery</label>
                                <input type="text" class="form-control" id="deadline" name="deadline">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Submit</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Edit Transmittal Modal -->
    <div class="modal fade" id="editTransmittalModal" tabindex="-1" aria-labelledby="editTransmittalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTransmittalModalLabel">Edit Transmittal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editTransmittalForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_transmittal_type" class="form-label">Transmittal Type</label>
                                <select class="form-select" id="edit_transmittal_type" name="transmittal_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Infrastructure">Infrastructure</option>
                                    <option value="Goods">Goods</option>
                                    <option value="Services">Services</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_ib_no" class="form-label">IB no.</label>
                                <input type="text" class="form-control" id="edit_ib_no" name="ib_no" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_project_name" class="form-label">Project Name</label>
                                <input type="text" class="form-control" id="edit_project_name" name="project_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_date_received" class="form-label">Date Received</label>
                                <input type="text" class="form-control" id="edit_date_received" name="date_received">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_office" class="form-label">Office</label>
                                <input type="text" class="form-control" id="edit_office" name="office" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_received_by" class="form-label">Received by</label>
                                <input type="text" class="form-control" id="edit_received_by" name="received_by" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_winning_bidders" class="form-label">Winning Bidders</label>
                                <input type="text" class="form-control" id="edit_winning_bidders" name="winning_bidders" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_amount" class="form-label">Amount</label>
                                <input type="text" class="form-control" id="edit_amount" name="amount">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_NOA_no" class="form-label">NOA no.</label>
                                <input type="text" class="form-control" id="edit_NOA_no" name="NOA_no">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_COA_date" class="form-label">Contract of Agreement Date</label>
                                <input type="date" class="form-control" id="edit_COA_date" name="COA_date">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_notice_proceed" class="form-label">Notice to Proceed Date</label>
                                <input type="date" class="form-control" id="edit_notice_proceed" name="notice_proceed">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_deadline" class="form-label">Calendar Days of Delivery</label>
                                <input type="text" class="form-control" id="edit_deadline" name="deadline">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Print Report Modal -->
    <div class="modal fade" id="printReportModal" tabindex="-1" aria-labelledby="printReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printReportModalLabel">Print Transmittal Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 d-flex justify-content-center gap-2">
                        <button class="btn btn-outline-primary" id="printInfraBtn">Infrastructure</button>
                        <button class="btn btn-outline-success" id="printGoodsBtn">Goods</button>
                        <button class="btn btn-outline-warning" id="printServicesBtn">Services</button>
                    </div>
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <label for="reportStartDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="reportStartDate">
                        </div>
                        <div class="col">
                            <label for="reportEndDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="reportEndDate">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle (for modal functionality) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openPrintReport(type) {
            const start = document.getElementById('reportStartDate').value;
            const end = document.getElementById('reportEndDate').value;
            let url = 'print_transmittal_report.php?type=' + encodeURIComponent(type);
            if (start && end) {
                url += '&start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end);
            }
            window.open(url, '_blank');
        }
        document.getElementById('printInfraBtn').addEventListener('click', function() {
            openPrintReport('Infrastructure');
        });
        document.getElementById('printGoodsBtn').addEventListener('click', function() {
            openPrintReport('Goods');
        });
        document.getElementById('printServicesBtn').addEventListener('click', function() {
            openPrintReport('Services');
        });
    </script>
    <script src="transmittal_bac.js"></script>
</body>

</html>