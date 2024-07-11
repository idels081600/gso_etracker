<?php
require_once 'db_asset.php';
require_once 'display_data_asset.php';
$result = display_data_dashboard();
$transpo = display_data_transpo();
$on_stock = display_tent_status();
$total_rows = countTotalTentStatusRows();
$on_stock_percent = round(($on_stock / $total_rows) * 100);
$on_field = display_tent_status_Installed();
$on_retrieval = display_tent_status_Retrieval();
$longterm = display_tent_status_Longterm();
$ongarage = display_vehicle_ongarage();
$departed = display_vehicle_departed();
$departed_Status = display_vehicle_Departed_status();
$on_stock_minus_20 = $on_stock -  $longterm;
$vehicle = display_vehicle_status();
$rfq = display_data_rfq();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="main_content.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Bootstrap CSS for table -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/css/bootstrap.min.css">

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get all dropdown list items
            var dropdowns = document.querySelectorAll('.dropdown');

            // Loop through each dropdown list item
            dropdowns.forEach(function(dropdown) {
                // Add click event listener to toggle the dropdown menu
                dropdown.addEventListener('click', function(event) {
                    // Toggle the 'active' class on the dropdown menu
                    this.querySelector('.dropdown-menu').classList.toggle('active');
                    this.classList.toggle('open'); // Toggle 'open' class on the dropdown item
                });
            });

            // Close dropdown menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.dropdown')) {
                    var activeDropdowns = document.querySelectorAll('.dropdown-menu.active');
                    activeDropdowns.forEach(function(activeDropdown) {
                        activeDropdown.classList.remove('active');
                        activeDropdown.closest('.dropdown').classList.remove('open'); // Remove 'open' class
                    });
                }
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            // Loop through each status cell
            $('.status-cell').each(function() {
                var status = $(this).text().trim(); // Get the status text
                console.log("Status:", status); // Log the status to check if it's correct

                // Check the status and add or remove the 'red' class accordingly
                switch (status) {
                    case 'Pending':
                        // Remove any existing classes
                        $(this).removeClass('red');
                        break;
                    case 'Installed':
                        // Add the 'red' class
                        $(this).addClass('red');
                        break;
                    case 'Retrieval':
                        // Remove any existing classes
                        $(this).removeClass('red');
                        break;
                    case 'Retrieved':
                        // Remove any existing classes
                        $(this).removeClass('red');
                        break;
                    default:
                        // Default action (if any)
                        break;
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the input field and tables
            var input = document.getElementById('search-input');
            var tableTent = document.getElementById('table_tent1');
            var tableTransportation = document.getElementById('table_transportation');
            var tableRFQ = document.getElementById('table_rfq');
            var rowsTent = tableTent.getElementsByTagName('tr');
            var rowsTransportation = tableTransportation.getElementsByTagName('tr');
            var rowsRFQ = tableRFQ.getElementsByTagName('tr');

            // Function to toggle row display based on search input
            function toggleRowDisplay(rows, filter) {
                for (var i = 1; i < rows.length; i++) {
                    var cells = rows[i].getElementsByTagName('td');
                    var rowVisible = false;
                    for (var j = 0; j < cells.length; j++) {
                        var cellText = cells[j].textContent.toLowerCase();
                        if (cellText.indexOf(filter) > -1) {
                            rowVisible = true;
                            break;
                        }
                    }
                    rows[i].style.display = rowVisible ? '' : 'none';
                }
            }

            // Add event listener to the search input
            input.addEventListener('input', function() {
                var filter = input.value.toLowerCase(); // Convert input to lowercase for case-insensitive search
                // Toggle row display for both tables
                toggleRowDisplay(rowsTent, filter);
                toggleRowDisplay(rowsTransportation, filter);
                toggleRowDisplay(rowsRFQ, filter);
            });
        });
    </script>


    <style>
        .status-cell {
            /* Add any existing CSS styles for the status cell here */
            /* Ensure that the color property is set to inherit to allow overriding */
            color: inherit;
        }
    </style>


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
            <li class="dropdown">
                <a href="#"><i class="fas fa-map icon-size"></i> Tracking <i class="fas fa-chevron-down dropdown-icon"></i></a>
                <ul class="dropdown-menu">
                    <li><a href="tracking.php">Tent</a></li>
                    <li><a href="transpo.php">Transportation</a></li>
                    <!-- <li><a href="pay_track.php">Payables</a></li> -->
                    <li><a href="rfq_tracking.php">RFQ</a></li>

                </ul>
            </li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="#" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="content">
        <h1>Welcome Back!</h1>
        <div class="row">
            <div class="container1">
                <h1 class="tent_label">Tent On Stock Status</h1>
                <div role="progressbar1" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="--value:<?php echo $on_stock_percent; ?> "></div>
            </div>
            <div class="container2">
                <h1 class="tent_label">Tent Details</h1>
                <div class="meter">
                    <span style="width: <?php echo display_tent_status(); ?>%"></span>
                </div>
                <div class="meter1">
                    <span style="width: <?php echo display_tent_status_Installed(); ?>%"></span>
                </div>
                <div class="meter2">
                    <span style="width: <?php echo display_tent_status_Retrieval(); ?>%"></span>
                </div>
                <div class="meter3">
                    <span style="width: <?php echo display_tent_status_Longterm(); ?>%"></span>
                </div>
                <!-- Content for container 2 -->
                <div class="row1">
                    <h1 id="on_stock"><?php echo $on_stock; ?></h1>
                    <h1 id="on_field"><?php echo $on_field; ?></h1>
                    <h1 id="on_retrieval"><?php echo $on_retrieval; ?></h1>
                    <h1 id="long_term_use"><?php echo $longterm; ?></h1>
                </div>
                <div class="row2">
                    <h1 id="label1">On Stock</h1>
                    <h1 id="label2">On Field</h1>
                    <h1 id="label3">Retrieval</h1>
                    <h1 id="label4">Long Term </h1>
                </div>
            </div>
            <div class="container3">
                <h1 class="tent_label">Transportation On Garage Status</h1>
                <div role="progressbar2" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="--value: <?php echo $vehicle; ?> "></div>
                <!-- Content for container 2 -->
            </div>
            <div class="container4">
                <h1 class="tent_label">Transportation Details</h1>
                <div class="meter">
                    <span style="width: <?php echo $vehicle; ?>%"></span>
                </div>
                <div class="meter1">
                    <span style="width: <?php echo $departed_Status; ?>%"></span>
                </div>
                <!-- <div class="meter2">
                    <span style="width: 23%"></span>
                </div>
                <div class="meter3">
                    <span style="width: 20%"></span>
                </div> -->
                <!-- Content for container 2 -->
                <div class="row1">
                    <h1 id="on_garage"><?php echo $ongarage; ?></h1>
                    <h1 id="on_field_transpo"><?php echo $departed; ?></h1>
                    <!-- <h1 id="on_retrieval">23</h1>
                    <h1 id="long_term_use">20</h1> -->
                </div>
                <div class="row2">
                    <h1 id="label5">On Garage</h1>
                    <h1 id="label6">On Field</h1>
                    <!-- <h1 id="label7">Driver</h1>
                    <h1 id="label8">Long Term </h1>111 -->
                </div>
                <!-- Content for container 2 -->
            </div>
        </div>
        <!-- Bootstrap Table -->
        <div class="container_table">
            <div class="column">
                <div class="dropdown_menu">
                    <select class="menu" id="sel1" name='typeofbusiness'>
                        <option>Tent</option>
                        <option>Transportation</option>
                        <option>RFQ</option>
                    </select>
                </div>
                <div class="search-container">
                    <input type="text" id="search-input" placeholder="Search...">
                    <!-- <button id="search-button"><i class="fas fa-search"></i></button> -->
                </div>
            </div>
            <div class="container_table_content1">
                <div class="table-container1">
                    <table id="table_tent1" class="table_tent1" style="display: table;">
                        <thead>
                            <tr>
                                <th>Tent No.</th>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Contact Number</th>
                                <th>No. of Tents</th>
                                <th>Purpose</th>
                                <th>Location</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                <tr>
                                    <td><?php echo $row["tent_no"]; ?></td>
                                    <td><?php echo $row["date"]; ?></td>
                                    <td><?php echo $row["name"]; ?></td>
                                    <td><?php echo $row["Contact_no"]; ?></td>
                                    <td><?php echo $row["no_of_tents"]; ?></td>
                                    <td><?php echo $row["purpose"]; ?></td>
                                    <td><?php echo $row["location"]; ?></td>
                                    <?php
                                    // Determine the class based on the status value
                                    $status_class = '';
                                    switch ($row["status"]) {
                                        case 'Installed':
                                            $status_class = 'orange';
                                            break;
                                        case 'Retrieval':
                                            $status_class = 'red';
                                            break;
                                        case 'On Stock':
                                            $status_class = 'green';
                                            break;
                                            // Add more cases if needed
                                        default:
                                            // Default action (if any)
                                            break;
                                    }
                                    ?>
                                    <td class="status-cell <?php echo $status_class; ?>"><?php echo $row["status"]; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                    <table id="table_transportation" class="table_transportation" style="display: none;">
                        <thead>
                            <tr>
                                <th>Plate No.</th>
                                <th>Date</th>
                                <th>Type of Vehicle</th>
                                <th>Driver</th>
                                <th>Purpose</th>
                                <th>Location</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($transpo)) { ?>
                                <tr>
                                    <td><?php echo $row["Plate_no"]; ?></td>
                                    <td><?php echo $row["Date"]; ?></td>
                                    <td><?php echo $row["Vehicle"]; ?></td>
                                    <td><?php echo $row["Driver"]; ?></td>
                                    <td><?php echo $row["Purpose"]; ?></td>
                                    <td><?php echo $row["Location"]; ?></td>
                                    <td><?php echo $row["Departure"]; ?></td>
                                    <td><?php echo $row["Arrival"]; ?></td>
                                    <?php
                                    // Determine the class based on the status value
                                    $status_class = '';
                                    switch ($row["Status1"]) {
                                        case 'Stand By':
                                            $status_class = 'green';
                                            break;
                                        case 'Departed':
                                            $status_class = 'red';
                                            break;
                                        case 'Arrived':
                                            $status_class = 'blue';
                                            break;
                                            // Add more cases if needed
                                        default:
                                            // Default action (if any)
                                            break;
                                    }
                                    ?>
                                    <td class="status-cell <?php echo $status_class; ?>"><?php echo $row["Status1"]; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                    <table id="table_rfq" class="table_rfq" style="display: none;">
                        <thead>
                            <tr>
                                <th>RFQ No.</th>
                                <th>PR No.</th>
                                <th>RFQ Name</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Requestor</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <!-- Adjust the width of each column as needed -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($rfq)) { ?>
                                <tr>
                                    <td><?php echo $row["rfq_no"]; ?></td>
                                    <td><?php echo $row["pr_no"]; ?></td>
                                    <td><?php echo $row["rfq_name"]; ?></td>
                                    <td><?php echo $row["date"]; ?></td>
                                    <td><?php echo $row["amount"]; ?></td>
                                    <td><?php echo $row["requestor"]; ?></td>
                                    <td><?php echo $row["supplier"]; ?></td>
                                    <?php
                                    // Determine the class based on the status value
                                    $status_class = '';
                                    switch ($row["Status"]) {
                                        case 'SAP':
                                            $status_class = 'green';
                                            break;
                                        case 'Office Clerk':
                                            $status_class = 'red';
                                            break;
                                        case 'CGSO-Head':
                                            $status_class = 'blue';
                                            break;
                                            // Add more cases if needed
                                        default:
                                            // Default action (if any)
                                            break;
                                    }
                                    ?>
                                    <td class="status-cell <?php echo $status_class; ?>"><?php echo $row["Status"]; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                window.onload = function() {
                    document.getElementById('sel1').addEventListener('change', function() {
                        var selectedOption = this.value;
                        document.getElementById('table_tent1').style.display = 'none';
                        document.getElementById('table_transportation').style.display = 'none';
                        document.getElementById('table_rfq').style.display = 'none';

                        if (selectedOption === 'Tent') {
                            document.getElementById('table_tent1').style.display = 'table';
                        } else if (selectedOption === 'Transportation') {
                            document.getElementById('table_transportation').style.display = 'table';
                        } else if (selectedOption === 'RFQ') {
                            document.getElementById('table_rfq').style.display = 'table';
                        }
                    });
                };
            </script>


        </div>

</body>

</html>