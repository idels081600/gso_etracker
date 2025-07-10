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
    <link href="tent_installers.css" rel="stylesheet">

</head>

<body>
    <div class="container-fluid">
        <h1 class="mt-4">Search Client</h1>
        <div class="form-group">
            <input type="text" class="form-control" id="searchInput" placeholder="Search...">
        </div>
        <div class="mb-3">
            <button type="button" class="btn btn-info" id="todayBtn">Today</button>
            <button type="button" class="btn btn-secondary ml-2" id="showAllBtn">Show All</button>
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
                    <?php
                    require_once 'db_asset.php';

                    // Get today's date
                    $today = date('Y-m-d');

                    // First fetch Pending status (unchanged)
                    $query_pending = "SELECT t.id, t.name, t.contact_no, t.location, t.status, t.tent_no, t.date, t.no_of_tents
             FROM tent t 
             WHERE t.status = 'Pending'
             ORDER BY t.date DESC";
                    $result_pending = mysqli_query($conn, $query_pending);

                    // Fetch For Retrieval status - prioritize today's date
                    $query_for_retrieval = "SELECT t.id, t.name, t.contact_no, t.location, t.status, t.tent_no, t.retrieval_date, t.no_of_tents
           FROM tent t 
           WHERE t.status = 'For Retrieval'
           ORDER BY 
               CASE WHEN t.retrieval_date = '$today' THEN 0 ELSE 1 END,
               t.retrieval_date DESC";
                    $result_for_retrieval = mysqli_query($conn, $query_for_retrieval);

                    // Fetch Installed status - prioritize today's date
                    $query_installed = "SELECT t.id, t.name, t.contact_no, t.location, t.status, t.tent_no, t.retrieval_date, t.no_of_tents
            FROM tent t 
            WHERE t.status = 'Installed'
            ORDER BY 
                CASE WHEN t.retrieval_date = '$today' THEN 0 ELSE 1 END,
                t.retrieval_date DESC";
                    $result_installed = mysqli_query($conn, $query_installed);

                    // Fetch Retrieved status (unchanged)
                    $query_retrieved = "SELECT t.id, t.name, t.contact_no, t.location, t.status, t.tent_no, t.date, t.no_of_tents
           FROM tent t 
           WHERE t.status = 'Retrieved'
           ORDER BY t.date DESC";
                    $result_retrieved = mysqli_query($conn, $query_retrieved);

                    $total_rows = mysqli_num_rows($result_pending) + mysqli_num_rows($result_installed) +
                        mysqli_num_rows($result_retrieved) + mysqli_num_rows($result_for_retrieval);

                    if ($total_rows > 0) {
                        // Display For Retrieval records (now sorted with today's date first)
                        while ($row = mysqli_fetch_assoc($result_for_retrieval)) {
                            // Add a CSS class to highlight today's records
                            $todayClass = ($row['retrieval_date'] == $today) ? 'today-record' : '';
                            echo "<tr class='for-retrieval-row $todayClass'>";
                            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['location']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['no_of_tents']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['retrieval_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                            echo '<td class="text-right">
                <button class="btn btn-primary"
                    data-toggle="modal"
                    data-target="#editModal"
                    data-id="' . htmlspecialchars($row['id']) . '"
                    data-name="' . htmlspecialchars($row['name']) . '"
                    data-address="' . htmlspecialchars($row['location']) . '"
                    data-contact="' . htmlspecialchars($row['contact_no']) . '"
                    data-no_of_tents="' . htmlspecialchars($row['no_of_tents']) . '"
                    data-date="' . htmlspecialchars($row['retrieval_date']) . '"
                    data-tent_no="' . htmlspecialchars($row['tent_no']) . '"                               
                    data-status="' . htmlspecialchars($row['status']) . '">
                    Edit
                </button>
            </td>';
                            echo "</tr>";
                        }
                        
                        // Display Installed records (now sorted with today's date first)
                        while ($row = mysqli_fetch_assoc($result_installed)) {
                            // Add a CSS class to highlight today's records
                            $todayClass = ($row['retrieval_date'] == $today) ? 'today-record' : '';
                            echo "<tr class='installed-row $todayClass'>";
                            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['location']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['no_of_tents']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['retrieval_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                            echo '<td class="text-right">
                <button class="btn btn-primary"
                    data-toggle="modal"
                    data-target="#editModal"
                    data-id="' . htmlspecialchars($row['id']) . '"
                    data-name="' . htmlspecialchars($row['name']) . '"
                    data-address="' . htmlspecialchars($row['location']) . '"
                    data-contact="' . htmlspecialchars($row['contact_no']) . '"
                    data-no_of_tents="' . htmlspecialchars($row['no_of_tents']) . '"
                    data-date="' . htmlspecialchars($row['retrieval_date']) . '"
                    data-tent_no="' . htmlspecialchars($row['tent_no']) . '"                               
                    data-status="' . htmlspecialchars($row['status']) . '">
                    Edit
                </button>
            </td>';
                            echo "</tr>";
                        }
                        
                        // Display Pending records (hidden by default, shown only for Today)
                        while ($row = mysqli_fetch_assoc($result_pending)) {
                            echo "<tr class='pending-row'>";
                            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['location']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['no_of_tents']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                            echo '<td class="text-right">
                <button class="btn btn-primary"
                    data-toggle="modal"
                    data-target="#editModal"
                    data-id="' . htmlspecialchars($row['id']) . '"
                    data-name="' . htmlspecialchars($row['name']) . '"
                    data-address="' . htmlspecialchars($row['location']) . '"
                    data-contact="' . htmlspecialchars($row['contact_no']) . '"
                    data-no_of_tents="' . htmlspecialchars($row['no_of_tents']) . '"
                    data-date="' . htmlspecialchars($row['date']) . '"
                    data-tent_no="' . htmlspecialchars($row['tent_no']) . '"                               
                    data-status="' . htmlspecialchars($row['status']) . '">
                    Edit
                </button>
            </td>';
                            echo "</tr>";
                        }
                        
                        if (mysqli_num_rows($result_for_retrieval) + mysqli_num_rows($result_installed) + mysqli_num_rows($result_pending) == 0) {
                            echo "<tr><td colspan='6'>No data found</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>No data found</td></tr>";
                    }
                    ?>
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
                        $statusQuery = "SELECT Status FROM tent_status";
                        $statusResult = mysqli_query($conn, $statusQuery);

                        for ($i = 1; $i <= 200; $i++) {
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
