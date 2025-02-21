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
        <div class="table-responsive">
            <table class="table table-striped mt-4 table-fixed">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <!-- <th>Contact</th> -->
                        <th class="tent-no-column">Tent No.</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php
                    require_once 'db_asset.php';

                    $query = "SELECT t.id, t.name, t.contact_no, t.location, t.status, t.tent_no 
                          FROM tent t 
                          WHERE t.status IN ('Pending', 'Installed', 'For Retrieval', 'Retrieved')";
                    $result = mysqli_query($conn, $query);

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['location']) . "</td>";
                            // echo "<td>" . htmlspecialchars($row['contact_no']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['tent_no']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                            echo '<td class="text-right">
                            <button class="btn btn-primary"
                                data-toggle="modal"
                                data-target="#editModal"
                                data-id="' . htmlspecialchars($row['id']) . '"
                                data-name="' . htmlspecialchars($row['name']) . '"
                                data-address="' . htmlspecialchars($row['location']) . '"
                                data-contact="' . htmlspecialchars($row['contact_no']) . '"
                                data-tent_no="' . htmlspecialchars($row['tent_no']) . '"                               
                                 data-status="' . htmlspecialchars($row['status']) . '">
                                Edit
                            </button>
                        </td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6'>No data found</td></tr>";
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
                            <label for="clientId">Client ID</label>
                            <input type="text" class="form-control" id="clientId" name="clientId" readonly>
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
                                <option value="Retrieved">Retrieved</option>
                                <option value="Installed">Installed</option>
                                <option value="Long Term">Long Term</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tentNumber">Tent Number</label>
                            <input type="text" class="form-control" id="tentNumber" placeholder="Enter tent number">
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-success">Save Changes</button>
                        </div>
                    </form>

                    <div class="box-grid mt-4">
                        <?php
                        $statusQuery = "SELECT Status FROM tent_status";
                        $statusResult = mysqli_query($conn, $statusQuery);

                        for ($i = 1; $i <= 120; $i++) {
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
                                        $boxColor = 'background:rgb(212, 113, 0);'; // Blue
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