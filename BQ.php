<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php'; // Assuming this file contains your database connection code
require_once 'display_data.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}

// Better error handling for database operations
$result = display_data_BQ();
if (!$result) {
    error_log("Database query failed: " . mysqli_error($conn));
    die("An error occurred while fetching data. Please try again later.");
}
$result2 = display_data_bq_print();
$total_amount = 0;
$display_payment = display_data_BQ_payments();

// Reset the result pointer before calculating total
mysqli_data_seek($result, 0);
while ($row = mysqli_fetch_assoc($result)) {
    $total_amount += $row["amount"]; // Note lowercase "amount" for BQ table
}

// Reset the result pointer again for later use
mysqli_data_seek($result, 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Tracking System</title>

    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="bq.css">
    <link rel="stylesheet" href="side_bar_sap.css">
</head>

<body>
    <!-- Keep your existing PHP code at the top -->
    <?php /* Your existing PHP code here */ ?>

    <div class="d-flex">
        <div class="sidebar d-flex flex-column">
            <!-- Existing top content -->
            <div class="d-flex align-items-center mb-4">
                <img src="logo.png" alt="Logo" class="me-2" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 3px solid #ffffff; padding: 2px;">
                <div>
                    <span class="fw-bold">Admin</span>
                    <small class="text d-block">Chris John Rener Torralba</small>
                </div>
            </div>

            <!-- Navigation menu -->
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="payables_dashboard.php" class="nav-link">
                        <i class="fas fa-home me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #039a00; color: white;">
                        <i class="fas fa-map me-2"></i> Tracking
                    </a>
                    <ul class="dropdown-menu" style="background-color: #039a00;">
                        <li><a href="sir_bayong.php" class="dropdown-item text-white" style="background-color: #039a00;">Ulysess Dela Cruz</a></li>
                        <li><a href="maam_maricris.php" class="dropdown-item text-white" style="background-color: #039a00;">Maricres Cornell</a></li>
                        <li><a href="BQ.php" class="dropdown-item text-white" style="background-color: #039a00;">March Christine Igang</a></li>
                    </ul>
                </li>
            </ul>

            <!-- Footer section with logout -->
            <div class="mt-auto">
                <a href="logout.php" class="btn btn-danger w-100 mb-3">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
                <div class="text-center text-muted pb-3" style="font-size: 10px;">
                    Powered by E-CGSOTagbilaran<br>
                    SAPSystem v.2
                </div>
            </div>
        </div>


        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid">
                <!-- Form Section -->
                <div class="form-container p-3">
                    <form action="add_data_bq.php" method="POST" class="row g-10">
                        <input type="hidden" id="record_id" name="record_id">
                        <div class="col-md-2">
                            <label for="sr_no" class="form-label">SR/DR</label>
                            <input type="text" class="form-control" id="sr_no" name="sr_no">
                        </div>

                        <div class="col-md-2">
                            <label for="date1" class="form-label">Date</label>
                            <input type="text" class="form-control datepicker" id="date1" name="date" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label for="activity" class="form-label">Activity</label>
                            <input type="text" class="form-control" id="activity" name="activity">
                        </div>
                        <div class="col-md-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description">
                        </div>

                        <div class="col-md-2">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" step="any">
                        </div>

                        <div class="col-md-2">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" min="0" step="any">
                        </div>

                        <div class="col-md-2">
                            <label for="office" class="form-label">Department/Requestor</label>
                            <input type="text" class="form-control" id="office" name="office">
                        </div>

                        <div class="col-md-2">
                            <label for="supplierDropdown" class="form-label">Supplier</label>
                            <select class="form-select" id="supplierDropdown" name="supplier">
                                <option value="">Select a supplier</option>
                                <?php
                                $supplier_query = "SELECT DISTINCT supplier FROM bq WHERE supplier IS NOT NULL";
                                $supplier_result = mysqli_query($conn, $supplier_query);
                                while ($supplier = mysqli_fetch_assoc($supplier_result)) {
                                    echo '<option value="' . htmlspecialchars($supplier['supplier']) . '">' . htmlspecialchars($supplier['supplier']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="paymentDropdown" class="form-label">Payment Order</label>
                            <select class="form-select" id="paymentDropdown" name="payment">
                                <option value="">Select Payment Order</option>
                                <?php
                                $po_query = "SELECT po, amount FROM bq_payments ORDER BY id DESC";
                                $po_result = mysqli_query($conn, $po_query);
                                while ($po = mysqli_fetch_assoc($po_result)) {
                                    echo '<option value="' . $po['po'] . '">PO No.: ' . $po['po'] . ' - ₱' . number_format($po['amount'], 2) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="remarks" class="form-label">Remarks</label>
                            <input type="text" class="form-control" id="remarks" name="remarks" placeholder="Enter your remarks here...">
                        </div>

                        <div class="col-12 mt-4">
                            <div class="btn-group">
                                <button class="btn btn-primary btn-custom" name="save_data">Add Data</button>
                                <button class="btn btn-warning btn-custom text-white" name="save_data2">Update</button>
                                <button type="button" class="btn btn-info btn-custom text-white" id="review_pdf">Review PDF</button>
                                <button type="button" class="btn btn-success btn-custom" id="addtoprint">Add to Print</button>
                                <button type="button" class="btn btn-purple btn-custom text-white" id="add_payment">Add Payment</button>
                            </div>

                        </div>
                    </form>
                    <div id="total-amount" class="mt-3 fs-5 fw-bold">
                        Total Amount: ₱<?php echo number_format($total_amount, 2); ?>
                    </div>
                </div>

                <!-- Table Section -->
                <div class="table_container">
                    <div class="filter-controls mb-3">
                        <div class="row">
                            <div class="col-md-2">
                                <label for="start_date">Start Date:</label>
                                <input type="date" id="start_date" class="form-control" name="start_date">
                            </div>
                            <div class="col-md-2">
                                <label for="end_date">End Date:</label>
                                <input type="date" id="end_date" class="form-control" name="end_date">
                            </div>
                            <div class="col-md-2">
                                <label for="supplier_filter">Supplier:</label>
                                <select id="supplier_filter" class="form-control" name="supplier_filter">
                                    <option value="">All Suppliers</option>
                                    <?php
                                    $supplier_query = "SELECT DISTINCT supplier FROM bq WHERE supplier IS NOT NULL";
                                    $supplier_result = mysqli_query($conn, $supplier_query);
                                    while ($supplier = mysqli_fetch_assoc($supplier_result)) {
                                        echo '<option value="' . htmlspecialchars($supplier['supplier']) . '">' . htmlspecialchars($supplier['supplier']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label> </label>
                                <button id="filter_button" class="btn btn-primary form-control">Filter</button>
                            </div>
                            <div class="col-md-2 ms-auto">
                                <label for="search">Search:</label>
                                <input type="text" id="search" class="form-control" placeholder="Search records...">
                            </div>

                        </div>

                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>SR/DR</th>
                                    <th>Date</th>
                                    <th>Department/Requestor</th>
                                    <th>Activity</th>
                                    <th>Description</th>
                                    <th>Supplier</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>PO No.</th>
                                    <th>PO amount</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                global $conn;
                                $query = "SELECT * FROM `bq` ORDER BY `id` DESC";
                                $result = mysqli_query($conn, $query);

                                while ($row = mysqli_fetch_assoc($result)) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row["SR_DR"] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row["date"] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row["requestor"] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row["activity"] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row["description"] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row["supplier"] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row["quantity"] ?? ''); ?></td>
                                        <td>₱<?php echo number_format($row["amount"] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($row["PO_no"] ?? ''); ?></td>
                                        <td>₱<?php echo number_format($row["PO_amount"] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($row["remarks"] ?? ''); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-sm edit-btn" data-id="<?php echo $row['id']; ?>"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $row['id']; ?>"><i class="fas fa-trash"></i></button>

                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            a
        </div>
        <!-- Payment Modal -->
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentModalLabel">Add Payment Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="po_number" class="form-label">PO Number</label>
                            <input type="text" class="form-control" id="po_number" name="po_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="payment_amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="payment_amount" name="payment_amount" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="save_payment">Save Payment</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Add this modal to your HTML -->
        <div class="modal fade" id="reviewPdfModal" tabindex="-1" aria-labelledby="reviewPdfModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reviewPdfModalLabel">Review PDF</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <select class="form-select" id="modalFilter">
                                    <option value="">Select Payment Order</option>
                                    <?php
                                    $po_query = "SELECT po, amount FROM bq_payments ORDER BY id DESC";
                                    $po_result = mysqli_query($conn, $po_query);
                                    while ($po = mysqli_fetch_assoc($po_result)) {
                                        echo '<option value="' . $po['po'] . '">PO No.: ' . $po['po'] . ' - ₱' . number_format($po['amount'], 2) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-success" id="markAsPaid">Paid</button>
                                <button class="btn btn-danger" id="deleteAllPrint">Delete All</button>
                            </div>
                        </div>



                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>SR/DR</th>
                                        <th>Date</th>
                                        <th>Supplier</th>
                                        <th>Quantity</th>
                                        <th>Description</th>
                                        <th>Department/Requestor</th>
                                        <th>Vehicle</th>
                                        <th>Amount</th>
                                        <th>PO</th>
                                        <th>PO Amount</th>
                                        <th>Remarks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="pdfPreviewContent">
                                    <!-- Data will be loaded here dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="confirmPdf">Generate PDF</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="bq.js"></script>

</body>

</html>