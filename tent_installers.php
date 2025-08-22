<?php
session_start();

// Redirect to login page if user is not authenticated
if (!isset($_SESSION['username'])) {
    header("location: login_v2.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="tent_installers.css" rel="stylesheet">

</head>

<body>
    <div class="container-fluid">
        <h1 class="mt-4">Search Client</h1>

        <!-- Filter Controls -->
        <div class="row mb-3">
            <div class="col-md-6">
                <button type="button" class="btn btn-outline-success" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </button>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search...">
                </div>
            </div>
        </div>

        <!-- Auto-refresh Status -->
        <div class="row mb-2">
            <div class="col-12">
                <small class="text-muted">
                    <i class="fas fa-calendar-day"></i> Showing today's data only
                    <span class="ml-3">
                        <i class="fas fa-sync-alt"></i> Auto-refresh: Every 10 seconds
                        <span id="lastUpdated" class="ml-2"></span>
                    </span>
                </small>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped mt-4 table-fixed">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>No. of Tents</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Table data will be loaded via AJAX -->
                    <tr><td colspan="6" class="text-center">Loading data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Client</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <div class="form-group">
                            <label for="noOfTents">No. of Tents</label>
                            <input type="number" class="form-control" id="noOfTents" placeholder="Enter number of tents" min="1">
                        </div>
                        <div class="form-group">
                            <label for="clientName">Name</label>
                            <input type="text" class="form-control" id="clientName" placeholder="Enter name">
                        </div>
                        <div class="form-group">
                            <label for="clientAddress">Address</label>
                            <input type="text" class="form-control" id="clientAddress" placeholder="Enter address">
                        </div>
                        <div class="form-group">
                            <label for="clientContact">Contact</label>
                            <input type="text" class="form-control" id="clientContact" placeholder="Enter contact">
                        </div>
                        <div class="form-group">
                            <label for="clientStatus">Status</label>
                            <select class="form-control" id="clientStatus">
                                <!-- Options will be populated dynamically by JavaScript -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tentNumber">Tent Number</label>
                            <input type="text" class="form-control" id="tentNumber" placeholder="Enter tent number" required>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-success">Save Changes</button>
                        </div>
                    </form>

                    <div class="box-grid mt-4">
                        <?php
                        require_once 'db_asset.php';
                        $statusQuery = "SELECT Status FROM tent_status";
                        $statusResult = mysqli_query($conn, $statusQuery);

                        for ($i = 1; $i <= 300; $i++) {
                            $status = mysqli_fetch_assoc($statusResult);
                            $boxColor = '';

                            if ($status) {
                                switch ($status['Status']) {
                                    case 'Retrieved':
                                        $boxColor = 'background: #28a745;'; // Green
                                        break;
                                    case 'Installed':
                                        $boxColor = 'background: #dc3545;'; // Red
                                        break;
                                    case 'Long Term':
                                        $boxColor = 'background: #007bff;'; // Blue
                                        break;
                                    case 'For Retrieval':
                                        $boxColor = 'background:rgb(212, 113, 0);'; // Orange
                                        break;
                                    default:
                                        $boxColor = 'background: #ddd;'; // Default gray
                                }
                            }

                            echo "<div class='box' style='$boxColor'>$i</div>";
                        }
                        mysqli_close($conn);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="tent_installers.js"></script>

</body>

</html>