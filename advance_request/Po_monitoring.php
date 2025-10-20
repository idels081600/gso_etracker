<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("location: ../login_v2.php");
    exit;
}
require_once 'display_stat.php';
$start_date_param = !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date_param = !empty($_GET['end_date']) ? $_GET['end_date'] : null;


$total_amount_JETS_MARKETING = display_data_PO_monitoring($start_date_param, $end_date_param);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            width: 20rem;
            height: 13rem;
        }

        .card-text {
            margin-top: 3rem;
            font-size: 1.6rem;
            color: green;
            font-weight: bold;
        }

        .table-container {
            overflow: auto;
            margin: 0 auto;
            margin-top: 2rem;
            max-width: 90vw;
            max-height: 70vh;
        }

        .table-container::-webkit-scrollbar {
            display: none;
        }

        .table {
            padding: 2rem;
            min-width: 800px;
            width: 100%;
            border-collapse: collapse;
        }

        .table thead th {
            position: sticky;
            top: 0;
            background-color: #00772eff;
            color: white;
            z-index: 10;
        }

        .cards-row {
            display: flex;
            flex-direction: row;
            overflow-x: auto;
            gap: 1rem;
            padding: 0.5rem;
        }

        .table-container-modal {
            overflow: auto;
            max-height: 60vh;
        }

        .table-container-modal table {
            min-width: 1000px;
        }
    </style>
</head>

<body>
    <?php require_once 'advance_po_db.php'; ?>
    <nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="dashboard.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="modal" data-bs-target="#exampleModal">Add data</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="modal" data-bs-target="#editModal">Edit data</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="modal" data-bs-target="#printModal">Report</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="Po_monitoring.php">Po Monitoring</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="../logout.php">Logout</a>
                    </li>
                    <!-- <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Dropdown
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Action</a></li>
                            <li><a class="dropdown-item" href="#">Another action</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#">Something else here</a></li>
                        </ul>
                    </li> -->
                    <!-- <li class="nav-item">
                        <a class="nav-link disabled" aria-disabled="true">Disabled</a>
                    </li> -->
                </ul>
                <!-- <form class="d-flex" role="search">
                    <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search" />
                    <button class="btn btn-outline-success" type="submit">Search</button>
                </form> -->
            </div>
        </div>
    </nav>
    <div class="container-fluid">
        <!-- Notification container for toasts -->
        <div id="notification-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;" aria-live="polite" aria-atomic="true"></div>

        <!-- Content here -->
        <h1>PO Monitoring</h1>
        <div class="my-container">
            <div class="cards-row">

                <div class="col-auto">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">JETS MARKETING</h5>
                            <h6 class="card-subtitle mb-2 text-body-secondary">Total Expenses</h6>
                            <p class="card-text"><?php echo $total_amount_JETS_MARKETING; ?></p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="filter-container" style="margin: 0 auto; margin-top: 2rem; margin-bottom: 1rem; max-width: 90vw;">
        <form method="GET" action="" id="filterForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="text" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>" placeholder="Select start date">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="text" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>" placeholder="Select end date">
                </div>
                <div class="col-md-2">
                    <label for="filter_store" class="form-label">Store</label>
                    <select class="form-select" id="filter_store" name="filter_store">
                        <!-- <option value="">All Stores</option> -->
                        <!-- <option value="BQ" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'BQ') ? 'selected' : ''; ?>>BQ</option>
                        <option value="NODAL" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'NODAL') ? 'selected' : ''; ?>>NODAL</option> -->
                        <option value="JETS MARKETING" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'JETS MARKETING') ? 'selected' : ''; ?>>JETS MARKETING</option>
                        <!-- <option value="JJS SEAFOODS" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'JJS SEAFOODS') ? 'selected' : ''; ?>>JJS SEAFOODS</option>
                        <option value="CITY TYRE" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'CITY TYRE') ? 'selected' : ''; ?>>CITY TYRE</option>
                        <option value="BQ BUILDERWARE" <?php echo (isset($_GET['filter_store']) && $_GET['filter_store'] == 'BQ BUILDERWARE') ? 'selected' : ''; ?>>BQ BUILDERWARE</option> -->
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search_term" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search_term" name="search_term" value="<?php echo isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>" placeholder="Search all columns">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary ms-1">Clear Filters</a>
                </div>
            </div>
        </form>
    </div>
    <div class="table-container">

        <table class="table">
            <thead>
                <tr>
                    <th scope="col">PO Number</th>
                    <th scope="col">PO Date</th>
                    <th scope="col">Supplier</th>
                    <th scope="col">Office</th>
                    <th scope="col">Description</th>
                    <th scope="col">Tracking</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Lacking Requirements</th>
                    <th scope="col">Status</th>
                    <th scope="col">View</th>
                </tr>
            </thead>
            <tbody class="table-group-divider">
                <?php
                $query = "SELECT id, supplier, po_date, po_number, description, office, price, destination, "
                    . "preAuditchecklist_cb, obr_cb, dv_cb, billing_request_cb, certWarranty_cb, omnibus_cb, ris_cb, acceptance_cb, rfq_cb, recommending_cb, PR_cb, PO_cb, receipts_cb, delegation_cb, mayorsPermit_cb, jetsCert_cb, "
                    . "status FROM poMonitoring WHERE delete_status = 0";

                $whereConditions = [];

                if (!empty($_GET['start_date'])) {
                    $whereConditions[] = "po_date >= '" . mysqli_real_escape_string($conn, $_GET['start_date']) . "'";
                }

                if (!empty($_GET['end_date'])) {
                    $whereConditions[] = "po_date <= '" . mysqli_real_escape_string($conn, $_GET['end_date']) . "'";
                }

                if (!empty($_GET['filter_store'])) {
                    $whereConditions[] = "supplier = '" . mysqli_real_escape_string($conn, $_GET['filter_store']) . "'";
                }

                if (!empty($_GET['search_term'])) {
                    $search = mysqli_real_escape_string($conn, $_GET['search_term']);
                    $whereConditions[] = "(supplier LIKE '%" . $search . "%' OR po_date LIKE '%" . $search . "%' OR po_number LIKE '%" . $search . "%' OR description LIKE '%" . $search . "%' OR office LIKE '%" . $search . "%' OR price LIKE '%" . $search . "%' OR destination LIKE '%" . $search . "%' OR status LIKE '%" . $search . "%')";
                }

                if (!empty($whereConditions)) {
                    $query .= " AND " . implode(" AND ", $whereConditions);
                }

                $query .= " ORDER BY id DESC";

                $result = mysqli_query($conn, $query);

                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        // Calculate lacking (count of unchecked checkboxes)
                        $totalCheckboxes = 16; // Count of checkboxes in the form
                        $checkedCount = 0;
                        $checkboxes = ['preAuditchecklist_cb', 'obr_cb', 'dv_cb', 'billing_request_cb', 'certWarranty_cb', 'omnibus_cb', 'ris_cb', 'acceptance_cb', 'rfq_cb', 'recommending_cb', 'PR_cb', 'PO_cb', 'receipts_cb', 'delegation_cb', 'mayorsPermit_cb', 'jetsCert_cb'];

                        foreach ($checkboxes as $cb) {
                            if ($row[$cb] == '1') {
                                $checkedCount++;
                            }
                        }
                        $lacking = $totalCheckboxes - $checkedCount;

                        echo "<tr>
                        <td>{$row['po_number']}</td>
                        <td>{$row['po_date']}</td>
                        <td>{$row['supplier']}</td>
                        <td>{$row['office']}</td>
                        <td>{$row['description']}</td>
                        <td>{$row['destination']}</td>
                        <td>₱" . number_format($row['price'], 2) . "</td>
                        <td>{$lacking}</td>
                        <td>{$row['status']}</td>
                        <td class='text-center'>
                            <button class='btn btn-info btn-sm view-btn' data-id='{$row['id']}' data-bs-toggle='modal' data-bs-target='#viewModal' title='View Lacking Checklist'>
                                <i class='fas fa-eye'></i>
                            </button>
                        </td>
                      </tr>";
                    }
                } else {
                    echo "<tr><td colspan='10' class='text-center'>No records found</td></tr>";
                }

                // Close the database connection
                mysqli_close($conn);
                ?>
            </tbody>
        </table>
    </div>
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="exampleModalLabel">Add items</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addDataForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Supplier</label>
                                <select class="form-select supplier" name="supplier">
                                    <option value="JETS MARKETING">JETS MARKETING</option>
                                    <option value="BQ">BQ</option>
                                    <option value="NODAL">NODAL</option>
                                    <option value="JJS SEAFOODS">JJS SEAFOODS</option>
                                    <option value="CITY TYRE">CITY TYRE</option>
                                    <option value="BQ BUILDERWARE">BQ BUILDERWARE</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="po_number" class="form-label">PO Number</label>
                                <input type="text" class="form-control" id="po_number" name="po_number" placeholder="Enter Po number">
                            </div>
                            <div class="col-md-6">
                                <label for="po_date" class="form-label">PO Date</label>
                                <input type="text" class="form-control" id="po_date" name="po_date" placeholder="Select a date">
                            </div>
                            <div class="col-md-6">
                                <label for="office" class="form-label">Office</label>
                                <input type="text" class="form-control" id="office" name="office">
                            </div>
                            <div class="col-md-6">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="description" name="description">
                            </div>
                            <div class="col-md-6">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="amount" name="amount" min="0" step="1">
                            </div>
                            <div class="col-md-6">
                                <label for="destination" class="form-label">Destination</label>
                                <input type="text" class="form-control" id="destination" name="destination">
                            </div>
                            <h6>Checklist</h6>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="requirements_cb" name="requirements_cb">
                                    <label class="form-check-label" for="requirements_cb">
                                        PRE-AUDIT CHECKLIST
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="requirements_remarks_section" style="display: none;">
                                <label for="audit_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="audit_remarks" name="audit_remarks" rows="3" placeholder="Enter remarks for pre-audit checklist"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="obr_cb" name="obr_cb">
                                    <label class="form-check-label" for="obr_cb">
                                        OBR
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="obr_remarks_section" style="display: none;">
                                <label for="obr_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="obr_remarks" name="obr_remarks" rows="3" placeholder="Enter remarks for OBR"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="dv_cb" name="dv_cb">
                                    <label class="form-check-label" for="dv_cb">
                                        DV
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="dv_remarks_section" style="display: none;">
                                <label for="dv_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="dv_remarks" name="dv_remarks" rows="3" placeholder="Enter remarks for DV"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="billing_request_cb" name="billing_request_cb">
                                    <label class="form-check-label" for="billing_request_cb">
                                        BILLING REQUEST
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="billing_request_remarks_section" style="display: none;">
                                <label for="billing_request_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="billing_request_remarks" name="billing_request_remarks" rows="3" placeholder="Enter remarks for BILLING REQUEST"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cert_warranty_cb" name="cert_warranty_cb">
                                    <label class="form-check-label" for="cert_warranty_cb">
                                        CERT. WARRANTY
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="cert_warranty_remarks_section" style="display: none;">
                                <label for="cert_warranty_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="cert_warranty_remarks" name="cert_warranty_remarks" rows="3" placeholder="Enter remarks for CERT. WARRANTY"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="omnibus_cb" name="omnibus_cb">
                                    <label class="form-check-label" for="omnibus_cb">
                                        OMNIBUS
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="omnibus_remarks_section" style="display: none;">
                                <label for="omnibus_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="omnibus_remarks" name="omnibus_remarks" rows="3" placeholder="Enter remarks for OMNIBUS"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ris_cb" name="ris_cb">
                                    <label class="form-check-label" for="ris_cb">
                                        RIS
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="ris_remarks_section" style="display: none;">
                                <label for="ris_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="ris_remarks" name="ris_remarks" rows="3" placeholder="Enter remarks for RIS"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="acceptance_cb" name="acceptance_cb">
                                    <label class="form-check-label" for="acceptance_cb">
                                        ACCEPTANCE
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="acceptance_remarks_section" style="display: none;">
                                <label for="acceptance_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="acceptance_remarks" name="acceptance_remarks" rows="3" placeholder="Enter remarks for acceptance"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rfq_cb" name="rfq_cb">
                                    <label class="form-check-label" for="rfq_cb">
                                        RFQ
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="rfq_remarks_section" style="display: none;">
                                <label for="rfq_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="rfq_remarks" name="rfq_remarks" rows="3" placeholder="Enter remarks for RFQ"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="recommending_cb" name="recommending_cb">
                                    <label class="form-check-label" for="recommending_cb">
                                        RECOMMENDING
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="recommending_remarks_section" style="display: none;">
                                <label for="recommending_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="recommending_remarks" name="recommending_remarks" rows="3" placeholder="Enter remarks for RECOMMENDING"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="PR_cb" name="PR_cb">
                                    <label class="form-check-label" for="PR_cb">
                                        PR
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="PR_remarks_section" style="display: none;">
                                <label for="PR_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="PR_remarks" name="PR_remarks" rows="3" placeholder="Enter remarks for PR"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="PO_cb" name="PO_cb">
                                    <label class="form-check-label" for="PO_cb">
                                        PO
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="PO_remarks_section" style="display: none;">
                                <label for="PO_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="PO_remarks" name="PO_remarks" rows="3" placeholder="Enter remarks for PO"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="RECEIPTS_cb" name="RECEIPTS_cb">
                                    <label class="form-check-label" for="RECEIPTS_cb">
                                        RECEIPTS
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="RECEIPTS_remarks_section" style="display: none;">
                                <label for="RECEIPTS_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="RECEIPTS_remarks" name="RECEIPTS_remarks" rows="3" placeholder="Enter remarks for RECEIPTS"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="DELEGATION_cb" name="DELEGATION_cb">
                                    <label class="form-check-label" for="DELEGATION_cb">
                                        DELEGATION
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="DELEGATION_remarks_section" style="display: none;">
                                <label for="DELEGATION_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="DELEGATION_remarks" name="DELEGATION_remarks" rows="3" placeholder="Enter remarks for DELEGATION"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="MAYORS_PERMIT_cb" name="MAYORS_PERMIT_cb">
                                    <label class="form-check-label" for="MAYORS_PERMIT_cb">
                                        MAYORS PERMIT
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="MAYORS_PERMIT_remarks_section" style="display: none;">
                                <label for="MAYORS_PERMIT_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="MAYORS_PERMIT_remarks" name="MAYORS_PERMIT_remarks" rows="3" placeholder="Enter remarks for MAYORS_PERMIT"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="JETS_CERTIFICATION_cb" name="JETS_CERTIFICATION_cb">
                                    <label class="form-check-label" for="JETS_CERTIFICATION_cb">
                                        JETS CERTIFICATION
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="JETS_CERTIFICATION_remarks_section" style="display: none;">
                                <label for="JETS_CERTIFICATION_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="JETS_CERTIFICATION_remarks" name="JETS_CERTIFICATION_remarks" rows="3" placeholder="Enter remarks for JETS_CERTIFICATION"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="saveSingleItemBtn">
                        <i class="fas fa-save"></i> Save changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- jQuery and jQuery UI for datepicker -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Po_monitoring.js"></script>

    <!-- Edit Data Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <input type="text" class="form-control" id="editSearchInput" placeholder="Search records in edit modal...">
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-success btn-sm" id="refreshDataBtn">
                                <i class="fas fa-sync"></i> Refresh Data
                            </button>
                        </div>
                    </div>
                    <div class="table-container-modal">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Supplier</th>
                                    <th scope="col">PO Date</th>
                                    <th scope="col">PO Number</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Office</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Destination</th>
                                    <th scope="col">Lacking</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="editDataTable">
                                <!-- Data will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Row Modal -->
    <div class="modal fade" id="editRowModal" tabindex="-1" aria-labelledby="editRowModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRowModalLabel">Edit Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editRowForm">
                        <input type="hidden" id="edit_row_id" name="edit_row_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Supplier</label>
                                <select class="form-select" id="edit_supplier" name="edit_supplier">
                                    <option value="JETS MARKETING">JETS MARKETING</option>
                                    <option value="BQ">BQ</option>
                                    <option value="NODAL">NODAL</option>
                                    <option value="JJS SEAFOODS">JJS SEAFOODS</option>
                                    <option value="CITY TYRE">CITY TYRE</option>
                                    <option value="BQ BUILDERWARE">BQ BUILDERWARE</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_po_number" class="form-label">PO Number</label>
                                <input type="text" class="form-control" id="edit_po_number" name="edit_po_number">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_po_date" class="form-label">PO Date</label>
                                <input type="text" class="form-control" id="edit_po_date" name="edit_po_date">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_office" class="form-label">Office</label>
                                <input type="text" class="form-control" id="edit_office" name="edit_office">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="edit_description" name="edit_description">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_price" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="edit_price" name="edit_price" min="0" step="0.01">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_destination" class="form-label">Destination</label>
                                <input type="text" class="form-control" id="edit_destination" name="edit_destination">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="edit_status">
                                    <option value="Pending">Pending</option>
                                    <option value="Cancelled">Cancelled</option>
                                    <option value="Declined">Declined</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <!-- Add checkboxes here similar to add form -->
                            <h6>Checklist</h6>
                            <!-- Copy the checklist from the add form with edit_ prefixes -->
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_pre_audit_checklist_cb" name="edit_pre_audit_checklist_cb">
                                    <label class="form-check-label" for="edit_pre_audit_checklist_cb">
                                        PRE-AUDIT CHECKLIST
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_pre_audit_checklist_remarks_section" style="display: none;">
                                <label for="pre_audit_checklist_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="pre_audit_checklist_remarks" name="pre_audit_checklist_remarks" rows="3" placeholder="Enter remarks for pre-audit checklist"></textarea>
                            </div>
                            <!-- Add all other checkboxes -->
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_obr_cb" name="edit_obr_cb">
                                    <label class="form-check-label" for="edit_obr_cb">
                                        OBR
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_obr_section" style="display: none;">
                                <label for="obr_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="obr_remarks" name="obr_remarks" rows="3" placeholder="Enter remarks for OBR"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_dv_cb" name="edit_dv_cb">
                                    <label class="form-check-label" for="edit_dv_cb">
                                        DV
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_dv_remarks_section" style="display: none;">
                                <label for="dv_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="dv_remarks" name="dv_remarks" rows="3" placeholder="Enter remarks for DV"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_billing_request_cb" name="edit_billing_request_cb">
                                    <label class="form-check-label" for="edit_billing_request_cb">
                                        BILLING REQUEST
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_billing_request_remarks_section" style="display: none;">
                                <label for="billing_request_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="billing_request_remarks" name="billing_request_remarks" rows="3" placeholder="Enter remarks for BILLING REQUEST"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_cert_warranty_cb" name="edit_cert_warranty_cb">
                                    <label class="form-check-label" for="edit_cert_warranty_cb">
                                        CERT. WARRANTY
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_cert_warranty_remarks_section" style="display: none;">
                                <label for="cert_warranty_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="cert_warranty_remarks" name="cert_warranty_remarks" rows="3" placeholder="Enter remarks for CERT. WARRANTY"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_omnibus_cb" name="edit_omnibus_cb">
                                    <label class="form-check-label" for="edit_omnibus_cb">
                                        OMNIBUS
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_omnibus_remarks_section" style="display: none;">
                                <label for="omnibus_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="omnibus_remarks" name="omnibus_remarks" rows="3" placeholder="Enter remarks for OMNIBUS"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_ris_cb" name="edit_ris_cb">
                                    <label class="form-check-label" for="edit_ris_cb">
                                        RIS
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_ris_remarks_section" style="display: none;">
                                <label for="ris_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="ris_remarks" name="ris_remarks" rows="3" placeholder="Enter remarks for RIS"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_acceptance_cb" name="edit_acceptance_cb">
                                    <label class="form-check-label" for="edit_acceptance_cb">
                                        ACCEPTANCE
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_acceptance_remarks_section" style="display: none;">
                                <label for="acceptance_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="acceptance_remarks" name="acceptance_remarks" rows="3" placeholder="Enter remarks for acceptance"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_rfq_cb" name="edit_rfq_cb">
                                    <label class="form-check-label" for="edit_rfq_cb">
                                        RFQ
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_rfq_remarks_section" style="display: none;">
                                <label for="rfq_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="rfq_remarks" name="rfq_remarks" rows="3" placeholder="Enter remarks for RFQ"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_recommending_cb" name="edit_recommending_cb">
                                    <label class="form-check-label" for="edit_recommending_cb">
                                        RECOMMENDING
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_recommending_remarks_section" style="display: none;">
                                <label for="recommending_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="recommending_remarks" name="recommending_remarks" rows="3" placeholder="Enter remarks for RECOMMENDING"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_PR_cb" name="edit_PR_cb">
                                    <label class="form-check-label" for="edit_PR_cb">
                                        PR
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_PR_remarks_section" style="display: none;">
                                <label for="PR_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="PR_remarks" name="PR_remarks" rows="3" placeholder="Enter remarks for PR"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_PO_cb" name="edit_PO_cb">
                                    <label class="form-check-label" for="edit_PO_cb">
                                        PO
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_PO_remarks_section" style="display: none;">
                                <label for="PO_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="PO_remarks" name="PO_remarks" rows="3" placeholder="Enter remarks for PO"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_RECEIPTS_cb" name="edit_RECEIPTS_cb">
                                    <label class="form-check-label" for="edit_RECEIPTS_cb">
                                        RECEIPTS
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_RECEIPTS_remarks_section" style="display: none;">
                                <label for="RECEIPTS_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="RECEIPTS_remarks" name="RECEIPTS_remarks" rows="3" placeholder="Enter remarks for RECEIPTS"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_DELEGATION_cb" name="edit_DELEGATION_cb">
                                    <label class="form-check-label" for="edit_DELEGATION_cb">
                                        DELEGATION
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_DELEGATION_remarks_section" style="display: none;">
                                <label for="DELEGATION_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="DELEGATION_remarks" name="DELEGATION_remarks" rows="3" placeholder="Enter remarks for DELEGATION"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_MAYORS_PERMIT_cb" name="edit_MAYORS_PERMIT_cb">
                                    <label class="form-check-label" for="edit_MAYORS_PERMIT_cb">
                                        MAYORS PERMIT
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_MAYORS_PERMIT_remarks_section" style="display: none;">
                                <label for="MAYORS_PERMIT_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="MAYORS_PERMIT_remarks" name="MAYORS_PERMIT_remarks" rows="3" placeholder="Enter remarks for MAYORS PERMIT"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_JETS_CERTIFICATION_cb" name="edit_JETS_CERTIFICATION_cb">
                                    <label class="form-check-label" for="edit_JETS_CERTIFICATION_cb">
                                        JETS CERTIFICATION
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="edit_JETS_CERTIFICATION_remarks_section" style="display: none;">
                                <label for="JETS_CERTIFICATION_remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="JETS_CERTIFICATION_remarks" name="JETS_CERTIFICATION_remarks" rows="3" placeholder="Enter remarks for JETS CERTIFICATION"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">Checklist Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Completed Checklists:</h6>
                    <div class="row g-3" id="checklistContainer">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_preAuditchecklist_cb">
                                <label class="form-check-label" for="view_preAuditchecklist_cb" id="view_preAuditchecklist_cb_label">
                                    PRE-AUDIT CHECKLIST
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_obr_cb">
                                <label class="form-check-label" for="view_obr_cb" id="view_obr_cb_label">
                                    OBR
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_dv_cb">
                                <label class="form-check-label" for="view_dv_cb" id="view_dv_cb_label">
                                    DV
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_billing_request_cb">
                                <label class="form-check-label" for="view_billing_request_cb" id="view_billing_request_cb_label">
                                    BILLING REQUEST
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_certWarranty_cb">
                                <label class="form-check-label" for="view_certWarranty_cb" id="view_certWarranty_cb_label">
                                    CERT. WARRANTY
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_omnibus_cb">
                                <label class="form-check-label" for="view_omnibus_cb" id="view_omnibus_cb_label">
                                    OMNIBUS
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_ris_cb">
                                <label class="form-check-label" for="view_ris_cb" id="view_ris_cb_label">
                                    RIS
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_acceptance_cb">
                                <label class="form-check-label" for="view_acceptance_cb" id="view_acceptance_cb_label">
                                    ACCEPTANCE
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_rfq_cb">
                                <label class="form-check-label" for="view_rfq_cb" id="view_rfq_cb_label">
                                    RFQ
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_recommending_cb">
                                <label class="form-check-label" for="view_recommending_cb" id="view_recommending_cb_label">
                                    RECOMMENDING
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_PR_cb">
                                <label class="form-check-label" for="view_PR_cb" id="view_PR_cb_label">
                                    PR
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_PO_cb">
                                <label class="form-check-label" for="view_PO_cb" id="view_PO_cb_label">
                                    PO
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_receipts_cb">
                                <label class="form-check-label" for="view_receipts_cb" id="view_receipts_cb_label">
                                    RECEIPTS
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_delegation_cb">
                                <label class="form-check-label" for="view_delegation_cb" id="view_delegation_cb_label">
                                    DELEGATION
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_mayorsPermit_cb">
                                <label class="form-check-label" for="view_mayorsPermit_cb" id="view_mayorsPermit_cb_label">
                                    MAYORS PERMIT
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="view_jetsCert_cb">
                                <label class="form-check-label" for="view_jetsCert_cb" id="view_jetsCert_cb_label">
                                    JETS CERTIFICATION
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


</body>

</html>
