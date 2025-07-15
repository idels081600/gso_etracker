<?php
require_once 'db_asset.php';
require_once 'display_data_asset.php';
$on_stock = display_tent_status();
$on_field = display_tent_status_Installed();
$on_retrieval = display_tent_status_Retrieval();
$longterm = display_tent_status_Longterm();
session_start();
if (!isset($_SESSION['username'])) {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}
$result = display_data();
if (isset($_POST['save_data'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $contact_no = mysqli_real_escape_string($conn, $_POST['contact']);
    $date = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_POST['datepicker'])));
    $no_of_tents = mysqli_real_escape_string($conn, $_POST['tent_no']);
    $purpose = mysqli_real_escape_string($conn, $_POST['No_tents']);
    $location = "";
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $duration = mysqli_real_escape_string($conn, $_POST['duration']);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Check if "other" input is not empty
        if (!empty($_POST['other'])) {
            $location = mysqli_real_escape_string($conn, $_POST['other']); // Use "other" input value
        } else {
            // "other" input is empty, use the value from the dropdown
            $location = mysqli_real_escape_string($conn, $_POST['Location']);
        }
    }

    // Calculate retrieval date
    $retrieval_date = date('Y-m-d', strtotime($date . ' + ' . $duration . ' days'));

    $query = "INSERT INTO tent(name, contact_no, no_of_tents, purpose, location, address, status, date, retrieval_date) 
              VALUES ('$name', '$contact_no', '$no_of_tents', '$purpose', '$location', '$address', 'Pending', '$date', '$retrieval_date')";

    $query_run = mysqli_query($conn, $query);
    if ($query_run) {
        header("Location: tracking.php");
    } else {
        // Handle insert error
        header("Location: tracking.php");
        exit();
    }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tent Tracker</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="tracking_style.css">
    <link rel="stylesheet" href="style_box.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- jQuery -->
</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name">Rene Art Cagulada</span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="dashboard_asset_tracker.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li class="dropdown">
                <a href="#"><i class="fas fa-map icon-size"></i> Tracking <i class="fas fa-chevron-down dropdown-icon"></i></a>
                <ul class="dropdown-menu">
                    <li><a href="pay_track.php">Payables</a></li>
                    <li><a href="rfq_tracking.php">RFQ</a></li>
                </ul>
            </li>
            <li><a href="tracking.php"><i class="fas fa-campground icon-size"></i> Tent</a></li>
            <li><a href="transpo.php"><i class="fas fa-truck icon-size"></i> Transportation</a></li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="content">

        <h1>Tent</h1>
        <ul class="tally_list" id="tallyList">
            <li class="tally" id="on_standby">
                <div class="available">Available</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="on_stocks_value"><?php echo $on_stock; ?></h1>
                    </div>
                </div>
            </li>
            <li class="tally" id="on_field_item">
                <div class="on_field">On Field</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="on_field_value"><?php echo $on_field; ?></h1>
                    </div>
                </div>
            </li>
            <li class="tally" id="for_retrieval_item">
                <div class="for_retrieval">For Retrieval</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="for_retrieval_value"><?php echo $on_retrieval; ?></h1>
                    </div>
                </div>
            </li>
            <li class="tally" id="long_term_item">
                <div class="long_term">Long Term</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="long_term_value"><?php echo $longterm; ?></h1>
                    </div>
                </div>
            </li>
        </ul>


        <div class="container_table">
            <div class="container_table_content">
                <div class="d-flex justify-content-between align-items-center mb-3 px-3" style="padding-left:0;padding-right:0;">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-success" id="addButton" role="button" data-bs-toggle="modal" data-bs-target="#detailsModal">Install Tent</button>
                        <button class="btn btn-secondary" id="printButton" data-bs-toggle="modal" data-bs-target="#printModal"><i class="fas fa-print"></i> Print</button>
                    </div>
                    <input type="text" id="search-input" class="form-control w-auto" placeholder="Search...">
                </div>
                <div class="table-container">
                    <div class="table-responsive">

                        <table id="table_tent" class="table table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">Tent No.</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Retrieval Date</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Contact Number</th>
                                    <th scope="col">No. of Tents</th>
                                    <th scope="col">Purpose</th>
                                    <th scope="col">Location</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                    <tr>
                                        <td><?php echo $row["tent_no"]; ?></td>
                                        <td><?php echo $row["date"]; ?></td>
                                        <td class="retrieval-date"><?php echo $row["retrieval_date"]; ?></td>
                                        <td><?php echo $row["name"]; ?></td>
                                        <td><?php echo $row["Contact_no"]; ?></td>
                                        <td><?php echo $row["no_of_tents"]; ?></td>
                                        <td><?php echo $row["purpose"]; ?></td>
                                        <td><?php echo $row["location"]; ?></td>
                                        <?php if (empty($row['status'])) : ?>
                                            <td class="dropdown-cell">
                                                <div class="form-element">
                                                    <select class="form-select status-dropdown" name="status" id="drop_status">
                                                        <option value="" data-id="">Select Status</option>
                                                        <option value="Installed" data-id="<?php echo $row['id']; ?>">Installed</option>
                                                        <option value="For Retrieval" data-id="<?php echo $row['id']; ?>">For Retrieval</option>
                                                        <option value="Retrieved" data-id="<?php echo $row['id']; ?>">Retrieved</option>
                                                    </select>
                                                </div>
                                            </td>
                                        <?php else : ?>
                                            <td class="dropdown-cell">
                                                <div class="form-element">
                                                    <select class="form-select status-dropdown" name="status" id="drop_status">
                                                        <option value="" data-id="">Select Status</option>
                                                        <option value="Installed" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'Installed') ? 'selected' : ''; ?>>Installed</option>
                                                        <option value="For Retrieval" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'For Retrieval') ? 'selected' : ''; ?>>For Retrieval</option>
                                                        <option value="Retrieved" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'Retrieved') ? 'selected' : ''; ?>>Retrieved</option>
                                                    </select>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <button class="btn btn-primary btn-sm viewButton" data-id="<?php echo $row['id']; ?>" role="button" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm deleteButton" data-id="<?php echo $row['id']; ?>" role="button" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="viewEditModal" tabindex="-1" aria-labelledby="viewEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewEditModalLabel">View/Edit Tent Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="viewEditForm" autocomplete="off">
                    <div class="modal-body">
                        <div class="boxContainer">
                            <div class="boxes">
                                <?php for ($i = 1; $i <= 200; $i++): ?>
                                    <div class="box" data-box="<?php echo $i; ?>"><?php echo $i; ?></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="row g-3">
                            <!-- Add this hidden field inside your viewEditModal form -->
                            <input type="hidden" id="id" name="id">

                            <div class="col-md-6">
                                <label for="viewEditTentNo" class="form-label">Tent No.</label>
                                <input type="text" class="form-control" id="viewEditTentNo" name="tent_no" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditDate" class="form-label">Date</label>
                                <input type="text" class="form-control" id="viewEditDate" name="date">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditRetrievalDate" class="form-label">Retrieval Date</label>
                                <input type="text" class="form-control" id="viewEditRetrievalDate" name="retrieval_date">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditName" class="form-label">Name</label>
                                <input type="text" class="form-control" id="viewEditName" name="name">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditContactNo" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="viewEditContactNo" name="contact_no">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditNoOfTents" class="form-label">No. of Tents</label>
                                <input type="number" class="form-control" id="viewEditNoOfTents" name="no_of_tents">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditPurpose" class="form-label">Purpose</label>
                                <input type="text" class="form-control" id="viewEditPurpose" name="purpose">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditLocation" class="form-label">Location</label>
                                <input type="text" class="form-control" id="viewEditLocation" name="location">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditStatus" class="form-label">Status</label>
                                <select class="form-select" id="viewEditStatus" name="status">
                                    <option value="">Select Status</option>
                                    <option value="Installed">Installed</option>
                                    <option value="For Retrieval">For Retrieval</option>
                                    <option value="Retrieved">Retrieved</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditAddress" class="form-label">Address</label>
                                <input type="text" class="form-control" id="viewEditAddress" name="address">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Install Tent Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Install Tent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="form" action="" method="POST" autocomplete="off">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="tent_no" class="form-label">No. of Tents</label>
                                <input type="number" class="form-control" id="tent_no" name="tent_no" min="0" step="1" pattern="\d+" required>
                            </div>
                            <div class="col-md-6">
                                <label for="datepicker" class="form-label">Date</label>
                                <input type="text" class="form-control" id="datepicker" placeholder="Select a date" name="datepicker" required>
                            </div>
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact" class="form-label">Contact no.</label>
                                <input type="text" class="form-control" id="contact" name="contact" required>
                            </div>
                            <div class="col-md-6">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" required>
                            </div>
                            <div class="col-md-6">
                                <label for="Location" class="form-label">Barangay</label>
                                <select class="form-select" id="Location" name="Location">
                                    <option value="">Select Location</option>
                                    <option value="Bool">Bool</option>
                                    <option value="Booy">Booy</option>
                                    <option value="Cabawan">Cabawan</option>
                                    <option value="Cogon">Cogon</option>
                                    <option value="Dao">Dao</option>
                                    <option value="Dampas">Dampas</option>
                                    <option value="Manga">Manga</option>
                                    <option value="Mansasa">Mansasa</option>
                                    <option value="Poblacion I">Poblacion I</option>
                                    <option value="Poblacion II">Poblacion II</option>
                                    <option value="Poblacion III">Poblacion III</option>
                                    <option value="San Isidro">San Isidro</option>
                                    <option value="Taloto">Taloto</option>
                                    <option value="Tiptip">Tiptip</option>
                                    <option value="Ubujan">Ubujan</option>
                                    <option value="Outside_Tagbilaran">Outside Tagbilaran</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="No_tents" class="form-label">Purpose</label>
                                <select class="form-select" id="No_tents" name="No_tents" required>
                                    <option value="">Select Purpose</option>
                                    <option value="Wake">Wake</option>
                                    <option value="Fiesta">Fiesta</option>
                                    <option value="Birthday">Birthday</option>
                                    <option value="Wedding">Wedding</option>
                                    <option value="Baptism">Baptism</option>
                                    <option value="Personal">Personal</option>
                                    <option value="Private">Private</option>
                                    <option value="Church">Church</option>
                                    <option value="School">School</option>
                                    <option value="City Government">City Government</option>
                                    <option value="LGU">LGU</option>
                                    <option value="Municipalities">Municipalities</option>
                                    <option value="Province">Province</option>
                                    <option value="Burial">Burial</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="tent_duration" class="form-label">Tent Duration</label>
                                <input type="number" class="form-control" id="tent_duration" name="duration" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-success" role="button" name="save_data">Submit</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Print Modal -->
    <div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printModalLabel">Print Tent Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex flex-column align-items-center gap-3">
                    <button class="btn btn-primary w-100" id="printPendingBtn">Pending</button>
                    <button class="btn btn-warning w-100" id="printRetrievalBtn">For Retrieval</button>
                </div>
            </div>
        </div>
    </div>
</body>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script src="tracking.js"></script>
<!-- Bootstrap 5 JS Bundle (with Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('#printPendingBtn').on('click', function() {
            window.open('print_tent_pending.php', '_blank');
        });
        $('#printRetrievalBtn').on('click', function() {
            window.open('print_tent_for_retrieval.php', '_blank');
        });
    });
</script>

</html>