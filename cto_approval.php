<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Square Calendar</title>
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="main_content.css">
    <link rel="stylesheet" href="cto.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">

</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name">Chris John Rener Torralba</span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="dashboard_asset_tracker.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li>
                <a href="cto_approval.php" id="secure-link"><i class="fas fa-calendar icon-size"></i> Leave Management</a>
            </li>
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

    <div class="container">
        <!-- Month Dropdown & Calendar Section -->
        <section>
            <div class="month-dropdown text-center d-flex justify-content-center align-items-center">
                <select id="yearSelect" class="form-select me-1">
                    <!-- Dynamically populated years -->
                </select>
                <select id="monthSelect" class="form-select me-2">
                    <option value="0">January</option>
                    <option value="1">February</option>
                    <option value="2">March</option>
                    <option value="3">April</option>
                    <option value="4">May</option>
                    <option value="5">June</option>
                    <option value="6">July</option>
                    <option value="7">August</option>
                    <option value="8">September</option>
                    <option value="9">October</option>
                    <option value="10">November</option>
                    <option value="11">December</option>
                </select>
                <button id="leaveButton" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal">Add Leave</button>
                <button id="ctoButton" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ctoModal">Add CTO</button>
                <button id="creditbutton" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ctoCreditModal">Add CTO Credit</button>
                <button id="searchButton" class="btn btn-info " data-bs-toggle="modal" data-bs-target="#searchModal">Search Leave</button>
                <button id="exportPDF" class="btn btn-success ">Export PDF</button>
            </div>

            <div id="calendar" class="calendar mt-3"></div>
        </section>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.8/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <div class="modal fade" id="leaveModal" tabindex="-1" aria-labelledby="leaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveModalLabel">Add Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Title Field -->
                    <div class="mb-3">
                        <label for="leaveTitle" class="form-label">Title</label>
                        <select class="form-control" id="leaveTitle">
                            <option value="SPL">SPL</option>
                            <option value="FL">FL</option>
                        </select>
                    </div>

                    <!-- Name Field -->
                    <div class="mb-3">
                        <label for="leaveName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="leaveName" placeholder="Enter name">
                    </div>
                    <div class="mb-3">
                        <label for="leaveDates" class="form-label">Dates</label>
                        <input type="text" class="form-control" id="leaveDates" placeholder="(e.g., 2024-02-01, 2024-02-15)">
                        <small class="form-text text-muted">Enter multiple dates separated by commas.</small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Save Leave</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="searchModalLabel">Search Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Search Box -->
                    <div class="mb-3">
                        <label for="searchInput" class="form-label">Enter text to search:</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search for data..." />
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Name</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>

                                </tr>
                            </thead>

                            <tbody id="searchTableBody">
                                <!-- Dynamic content based on search -->
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
    <!-- CTO Modal -->
    <div class="modal fade" id="ctoModal" tabindex="-1" aria-labelledby="ctoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ctoModalLabel">Add CTO</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Title Field -->
                    <div class="mb-3">
                        <label for="ctoTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="ctoTitle" placeholder="Enter title" value="CTO">
                    </div>

                    <!-- Name Field -->
                    <div class="mb-3">
                        <label for="ctoName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="ctoName" placeholder="Enter name">
                    </div>
                    <!-- Dates Fields -->
                    <div class="mb-3">
                        <label for="ctoDates" class="form-label">Dates</label>
                        <input type="text" class="form-control" id="ctoDates" placeholder="(e.g., 2024-01-01, 2024-01-15)">
                        <small class="form-text text-muted">Enter multiple dates separated by commas.</small>
                    </div>
                    <!-- Remarks Field -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveCtoBtn">Save CTO</button>

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ctoCreditModal" tabindex="-1" aria-labelledby="ctoCreditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ctoCreditModalLabel">Add CTO Credit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Search Box -->
                    <div class="mb-3">
                        <label for="ctoCreditSearch" class="form-label">Search</label>
                        <input type="text" class="form-control" id="ctoCreditSearch" placeholder="Search for a credit">
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Credit</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="ctoCreditTableBody">
                                <!-- Table rows dynamically added here -->
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
    <div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="passwordModalLabel">Enter Password</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="passwordInput" class="form-label">Password</label>
                        <input type="password" class="form-control" id="passwordInput" placeholder="Enter password">
                        <small id="passwordError" class="text-danger d-none">Incorrect password, please try again.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="submitPassword">Submit</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script src="cto_leave.js"></script>
</body>

</html>