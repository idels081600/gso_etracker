<?php
session_start();
require_once 'auth_payables.php';
$full_name = isset($_SESSION['pay_name']) ? $_SESSION['pay_name'] : '';
require_once 'transmit_db.php';
require_once 'payables_helpers.php';

payables_ensure_date_column($conn, 'PO_sap', 'award');
payables_ensure_text_column($conn, 'PO_sap', 'pr_included');

$searchTerm = trim($_GET['search'] ?? '');
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$perPage = 25;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="payables-csrf-token" content="<?php echo htmlspecialchars(payables_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="Po_sap.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Payables - RFQ Receiving</title>
</head>

<body class="rfq-receiving-page">
    <?php $payablesActivePage = 'po'; require 'payables_sidebar.php'; ?>
    <div class="content receiving-content">
        <section class="rfq-shell" aria-label="RFQ receiving list">
            <div class="rfq-header">
                <div>
                    <span class="rfq-eyebrow">Purchase Order</span>
                    <h1>RFQ Receiving</h1>
                </div>
                <div class="rfq-actions">
                    <a class="rfq-print-button" href="print_po_pending.php" target="_blank" rel="noopener">
                        <i class="fas fa-print"></i>
                        <span>Print Pending</span>
                    </a>
                    <button type="button" class="rfq-add-button" data-bs-toggle="modal" data-bs-target="#addTransmittalModal">
                        <i class="fas fa-plus"></i>
                        <span>Receive</span>
                    </button>
                    <form class="rfq-search" method="get" role="search">
                        <input type="search" id="searchInput" name="search" placeholder="Search RFQ, PR, supplier, award, office, or status" aria-label="Search RFQ records" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" id="searchButton" aria-label="Search">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm !== ''): ?>
                            <a href="Po_sap.php" aria-label="Clear search">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="rfq-table-wrap">
                <table class="rfq-table">
                    <thead>
                        <tr>
                            <th>RFQ No.</th>
                            <th>PR Included</th>
                            <th>Supplier</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Date Received</th>
                            <th>Award</th>
                            <th>Office</th>
                            <th>Received by</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php require_once 'display_transmit_data.php';
                        $pagination = display_transmittal_rfq_data($searchTerm, $currentPage, $perPage); ?>
                    </tbody>
                </table>
            </div>
            <?php payables_render_pagination('Po_sap.php', $pagination['page'], $pagination['total_rows'], $pagination['per_page'], $searchTerm); ?>
        </section>
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
                    <?php echo payables_csrf_input(); ?>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="rfq_no" class="form-label">RFQ No.</label>
                                <input type="text" class="form-control" id="rfq_no" name="rfq_no" required>
                            </div>
                            <div class="col-md-6">
                                <label for="pr_included" class="form-label">PR Included</label>
                                <textarea class="form-control" id="pr_included" name="pr_included" rows="2" placeholder="1234-1234, 4567-45789"></textarea>
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
                                <label for="award" class="form-label">Award</label>
                                <input type="date" class="form-control" id="award" name="award">
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
                                <input type="text" class="form-control" id="amount" name="amount" inputmode="decimal">
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <input type="text" class="form-control" id="status" name="status" list="rfqStatusOptions">
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
                        <?php echo payables_csrf_input(); ?>
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_rfq_no" class="form-label">RFQ No.</label>
                                <input type="text" class="form-control" id="edit_rfq_no" name="rfq_no">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_pr_included" class="form-label">PR Included</label>
                                <textarea class="form-control" id="edit_pr_included" name="pr_included" rows="2" placeholder="1234-1234, 4567-45789"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="edit_description" name="description">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_date_received" class="form-label">Date Received</label>
                                <input type="date" class="form-control" id="edit_date_received" name="date_received">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_award" class="form-label">Award</label>
                                <input type="date" class="form-control" id="edit_award" name="award">
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
                                <input type="text" class="form-control" id="edit_amount" name="amount" inputmode="decimal">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_status" class="form-label">Status</label>
                                <input type="text" class="form-control" id="edit_status" name="status" list="rfqStatusOptions">
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
    <datalist id="rfqStatusOptions">
        <option value="Received"></option>
        <option value="Pending"></option>
        <option value="For SAP"></option>
        <option value="Completed"></option>
    </datalist>
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content delete-confirm-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Delete RFQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">This will remove the selected RFQ from the active list.</p>
                    <div class="alert alert-danger d-none mt-3 mb-0" id="actionError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <span class="action-label">Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle (for modal functionality) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Po_sap.js"></script>
</body>

</html>
