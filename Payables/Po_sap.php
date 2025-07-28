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
    <link rel="stylesheet" href="Po_sap.css">
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
            <li><a href="transmittal_bac.php"><i class="fas fa-gavel icon-size"></i>Bidding</a></li>
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
                <!-- <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#printReportModal">
                    <i class="fas fa-print"></i> Print Report
                </button> -->
            </div>
            <!-- Horizontal Card List for Projects Near Delivery -->
            
            <!-- End Horizontal Card List -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h3 class="mb-4">RFQ Receiving</h3>
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
                                    <th>RFQ No,</th>
                                    <th>Supplier</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Date Received</th>
                                    <th>Office</th>
                                    <th>Received by</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php require_once 'display_transmit_data.php';
                                display_transmittal_rfq_data(); ?>
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
                    <h5 class="modal-title" id="addTransmittalModalLabel">Add RFQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="transmittalForm" method="post" action="submit_rfq.php">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="rfq_no" class="form-label">RFQ No.</label>
                                <input type="text" class="form-control" id="rfq_no" name="rfq_no" required>
                            </div>
                            <div class="col-md-6">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="description" name="description" required>
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
                                <label for="supplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="supplier" name="supplier" required>
                            </div>
                            <div class="col-md-6">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="text" class="form-control" id="amount" name="amount">
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
                    <h5 class="modal-title" id="editTransmittalModalLabel">Edit RFQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editTransmittalForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_rfq_no" class="form-label">RFQ No.</label>
                                <input type="text" class="form-control" id="edit_rfq_no" name="rfq_no">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="edit_description" name="description">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_date_received" class="form-label">Date Received</label>
                                <input type="text" class="form-control" id="edit_date_received" name="date_received">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_office" class="form-label">Office</label>
                                <input type="text" class="form-control" id="edit_office" name="office">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_received_by" class="form-label">Received by</label>
                                <input type="text" class="form-control" id="edit_received_by" name="received_by">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_supplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="edit_supplier" name="supplier">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_amount" class="form-label">Amount</label>
                                <input type="text" class="form-control" id="edit_amount" name="amount">
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
    <script src="PO_sap.js"></script>
</body>

</html>