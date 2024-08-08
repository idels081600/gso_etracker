<?php
require_once "display_data_sap.php";
require_once "db_payables.php";
$sir_bayong = display_data_sir_bayong();
$maam_march = display_data_BQ();
$maam_cornell = display_data_maam_mariecris();
$total_amount_bq = get_total_amount_BQ();
session_start();
if (!isset($_SESSION['username'])) {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="sidebar_asset.css">
    <!-- <link rel="stylesheet" href="main_content.css"> -->
    <link rel="stylesheet" href="pay.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@0.7.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Payables</title>

    <style>
        /* Your custom styles */
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
        <h1>Payables</h1>
        <!-- Example Usage -->
        <ul class="supplier_list" id="supplierList">
            <li class="supplier_container" id="loadingContainer">
                <div class="supplier_name">Loading...</div>
                <div class="info">
                    <div class="amount skeleton-loader"></div>
                </div>
            </li>
            <!-- Additional list items will be dynamically added -->
        </ul>

        <div class="parent_container_table">
            <div class="dropdown_menu">
                <select class="menu" id="sel1" name='typeofbusiness'>
                    <option>Ulysess Dela Cruz</option>
                    <option>Maricres Cornell</option>
                    <option>March Christine Igang</option>
                </select>
            </div>
            <input type="text" id="date4" name="date4" placeholder="Start">
            <input type="text" id="date3" name="date3" placeholder="End">

            <div class="search-container">
                <input type="text" id="search-input" placeholder="Search...">
            </div>
            <div class="container_pay_table">
                <table id="pay_table_bayong" style="display: table;">
                    <thead>
                        <tr>
                            <th>SR/DR</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Department/Requestor</th>
                            <th>Description</th>
                            <th>Vehicle</th>
                            <th>Plate</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Reset the result pointer and re-fetch the rows to display them
                        mysqli_data_seek($sir_bayong, 0);
                        while ($row = mysqli_fetch_assoc($sir_bayong)) { ?>
                            <tr class="clickable-row3" data-rfq-id="<?php echo $row['id']; ?>"> <!-- Add data-rfq-id attribute with the row's ID -->
                                <td><?php echo $row["SR_DR"]; ?></td>
                                <td><?php echo $row["Date"]; ?></td>
                                <td><?php echo $row["Supplier"]; ?></td>
                                <td><?php echo $row["Office"]; ?></td>
                                <td><?php echo $row["Description"]; ?></td>
                                <td><?php echo $row["Vehicle"]; ?></td>
                                <td><?php echo $row["Plate"]; ?></td>
                                <td><?php echo $row["Quantity"]; ?></td>
                                <td><?php echo '₱' . number_format($row["Amount"], 2); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <table id="pay_table_cornell" style="display: none;">
                    <thead>
                        <tr>
                            <th>SR/DR</th>
                            <th>Date</th>
                            <th>Department/Requestor</th>
                            <th>Store</th>
                            <th>Activity</th>
                            <th>No.of PAX</th>
                            <th>Amount</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Reset the result pointer and re-fetch the rows to display them
                        mysqli_data_seek($maam_cornell, 0);
                        while ($row = mysqli_fetch_assoc($maam_cornell)) { ?>
                            <tr class="clickable-row3" data-rfq-id="<?php echo $row['id']; ?>"> <!-- Add data-rfq-id attribute with the row's ID -->
                                <td><?php echo $row["SR_DR"]; ?></td>
                                <td><?php echo $row["date"]; ?></td>
                                <td><?php echo $row["department"]; ?></td>
                                <td><?php echo $row["store"]; ?></td>
                                <td><?php echo $row["activity"]; ?></td>
                                <td><?php echo $row["no_of_pax"]; ?></td>
                                <td><?php echo '₱' . number_format($row["amount"], 2); ?></td>
                                <td><?php echo $row["total"]; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <table id="pay_table_march" style="display: none;">
                    <thead>
                        <tr>
                            <th>SR/DR</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Department</th>
                            <th>Activity</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Reset the result pointer and re-fetch the rows to display them
                        mysqli_data_seek($maam_march, 0);
                        while ($row = mysqli_fetch_assoc($maam_march)) { ?>
                            <tr class="clickable-row3" data-rfq-id="<?php echo $row['id']; ?>"> <!-- Add data-rfq-id attribute with the row's ID -->
                                <td><?php echo $row["SR_DR"]; ?></td>
                                <td><?php echo $row["date"]; ?></td>
                                <td><?php echo $row["supplier"]; ?></td>
                                <td><?php echo $row["requestor"]; ?></td>
                                <td><?php echo $row["activity"]; ?></td>
                                <td><?php echo $row["description"]; ?></td>
                                <td><?php echo $row["quantity"]; ?></td>
                                <td><?php echo '₱' . number_format($row["amount"], 2); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script>
        // JavaScript for dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            var dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(function(dropdown) {
                var dropdownIcon = dropdown.querySelector('.dropdown-icon');
                var dropdownMenu = dropdown.querySelector('.dropdown-menu');

                dropdown.addEventListener('click', function() {
                    dropdown.classList.toggle('open');
                    dropdownMenu.classList.toggle('active');
                });
            });
        });
    </script>
    <script type="text/javascript">
        $(document).ready(function() {
            $("#date3, #date4").datepicker({
                dateFormat: "yy-mm-dd" // Set the date format
            });

            // Event listener for date change
            $("#date3, #date4").on("change", function() {
                filterTableByDate();
            });

            // Function to filter table by date
            function filterTableByDate() {
                var startDate = $("#date4").val();
                var endDate = $("#date3").val();

                // Convert dates to timestamps for comparison
                var startTimestamp = new Date(startDate).getTime();
                var endTimestamp = new Date(endDate).getTime();

                // Loop through each row in the currently displayed table
                $(".container_pay_table table:visible tbody tr").each(function() {
                    var rowDate = $(this).find("td:eq(1)").text();
                    var rowTimestamp = new Date(rowDate).getTime();

                    if (rowTimestamp >= startTimestamp && rowTimestamp <= endTimestamp) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        });

        window.onload = function() {
            document.getElementById('sel1').addEventListener('change', function() {
                var selectedOption = this.value;
                document.getElementById('pay_table_bayong').style.display = 'none';
                document.getElementById('pay_table_march').style.display = 'none';
                document.getElementById('pay_table_cornell').style.display = 'none';

                if (selectedOption === 'Ulysess Dela Cruz') {
                    document.getElementById('pay_table_bayong').style.display = 'table';
                } else if (selectedOption === 'March Christine Igang') {
                    document.getElementById('pay_table_march').style.display = 'table';
                } else if (selectedOption === 'Maricres Cornell') {
                    document.getElementById('pay_table_cornell').style.display = 'table';
                }
            });
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const supplierList = document.querySelector('.supplier_list');

            supplierList.addEventListener('wheel', function(event) {
                event.preventDefault(); // Prevent default vertical scroll
                supplierList.scrollLeft += event.deltaY * 5; // Adjust scroll speed as needed
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const supplierContainers = document.querySelectorAll('.supplier_container');

            supplierContainers.forEach(container => {
                container.addEventListener('mouseenter', function() {
                    const amount = container.querySelector('.amount');
                    const amountText = amount.textContent.trim();
                    const digitCount = amountText.replace(/[^0-9]/g, '').length; // Count digits only

                    // Adjust font size based on digit count
                    if (digitCount > 12) { // Adjust this threshold based on your design and content
                        amount.style.fontSize = '1.5rem'; // Example smaller font size
                    } else if (digitCount > 9) {
                        amount.style.fontSize = '1.8rem'; // Example medium font size
                    } else {
                        amount.style.fontSize = '2em'; // Example default font size
                    }
                });

                container.addEventListener('mouseleave', function() {
                    const amount = container.querySelector('.amount');
                    amount.style.fontSize = '2em'; // Reset to default font size when not hovering
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the input field and tables
            var input = document.getElementById('search-input');
            var tableTent = document.getElementById('pay_table_bayong');
            var tableTransportation = document.getElementById('pay_table_march');
            var tableRFQ = document.getElementById('pay_table_cornell');
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
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            function fetchDataFromSAP() {
                return fetch('data_sap.php')
                    .then(response => response.json())
                    .then(data => {
                        console.log("Fetched Data from SAP:", data);
                        if (data.error) {
                            console.error("Error fetching SAP data:", data.error);
                            return [];
                        } else if (Array.isArray(data)) {
                            let suppliersMap = new Map();

                            data.forEach(item => {
                                let supplier = item.supplier.toUpperCase().trim();
                                let total_amount = parseFloat(item.total_amount);

                                if (suppliersMap.has(supplier)) {
                                    let currentAmount = suppliersMap.get(supplier);
                                    suppliersMap.set(supplier, currentAmount + total_amount);
                                } else {
                                    suppliersMap.set(supplier, total_amount);
                                }
                            });

                            let uniqueSuppliers = Array.from(suppliersMap, ([supplier, total_amount]) => ({
                                supplier,
                                total_amount
                            }));

                            console.log("Unique Suppliers with Total Amounts:", uniqueSuppliers);

                            return uniqueSuppliers;
                        } else {
                            console.error("Unexpected data format from SAP:", data);
                            return [];
                        }
                    })
                    .catch(error => {
                        console.error("Fetch error from SAP:", error);
                        return [];
                    });
            }

            function fetchDataFromLastMonth() {
                return fetch('data_last_month.php')
                    .then(response => response.json())
                    .then(data => {
                        console.log("Fetched Data from Last Month:", data);
                        if (data.error) {
                            console.error("Error fetching Last Month data:", data.error);
                            return [];
                        } else if (Array.isArray(data)) {
                            let suppliersMap = new Map();

                            data.forEach(item => {
                                let supplier = item.supplier.toUpperCase().trim();
                                let previous_month_amount = parseFloat(item.previous_month_amount);

                                if (suppliersMap.has(supplier)) {
                                    let currentAmount = suppliersMap.get(supplier);
                                    suppliersMap.set(supplier, currentAmount + previous_month_amount);
                                } else {
                                    suppliersMap.set(supplier, previous_month_amount);
                                }
                            });

                            let uniqueSuppliers = Array.from(suppliersMap, ([supplier, previous_month_amount]) => ({
                                supplier,
                                previous_month_amount
                            }));

                            console.log("Unique Suppliers with Last Month Amounts:", uniqueSuppliers);

                            return uniqueSuppliers;
                        } else {
                            console.error("Unexpected data format from Last Month:", data);
                            return [];
                        }
                    })
                    .catch(error => {
                        console.error("Fetch error from Last Month:", error);
                        return [];
                    });
            }

            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            function getPreviousMonthName() {
                const date = new Date();
                const month = date.getMonth(); // getMonth() returns 0-11
                const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                return monthNames[month - 1] || "December"; // If month is January, previous month is December
            }

            function updateDOM(sapData, lastMonthData) {
                console.log("SAP Data:", sapData);
                console.log("Last Month Data:", lastMonthData);

                let combinedDataMap = new Map();

                sapData.forEach(item => {
                    let supplier = item.supplier.toUpperCase().trim();
                    let total_amount = parseFloat(item.total_amount);
                    if (combinedDataMap.has(supplier)) {
                        let currentData = combinedDataMap.get(supplier);
                        currentData.total_amount = total_amount;
                        combinedDataMap.set(supplier, currentData);
                    } else {
                        combinedDataMap.set(supplier, {
                            supplier,
                            total_amount,
                            previous_month_amount: 0
                        });
                    }
                });

                lastMonthData.forEach(item => {
                    let supplier = item.supplier.toUpperCase().trim();
                    let previous_month_amount = parseFloat(item.previous_month_amount) || 0;
                    if (combinedDataMap.has(supplier)) {
                        let currentData = combinedDataMap.get(supplier);
                        currentData.previous_month_amount = previous_month_amount;
                        combinedDataMap.set(supplier, currentData);
                    } else {
                        combinedDataMap.set(supplier, {
                            supplier,
                            total_amount: 0,
                            previous_month_amount
                        });
                    }
                });

                let combinedDataArray = Array.from(combinedDataMap.values());

                combinedDataArray.sort((a, b) => b.total_amount - a.total_amount);

                let output = document.getElementById('supplierList');
                output.innerHTML = '';

                const previousMonthName = getPreviousMonthName();

                combinedDataArray.forEach(item => {
                    let li = document.createElement('li');
                    li.classList.add('supplier_container');

                    let supplierName = document.createElement('div');
                    supplierName.classList.add('supplier_name');
                    supplierName.textContent = item.supplier;
                    li.appendChild(supplierName);

                    let info = document.createElement('div');
                    info.classList.add('info');

                    let lastMonthValue = document.createElement('div');
                    lastMonthValue.classList.add('last_month_value');
                    lastMonthValue.textContent = `${previousMonthName}: ₱${formatNumber(item.previous_month_amount)}`;
                    info.appendChild(lastMonthValue);

                    let amount = document.createElement('div');
                    amount.classList.add('amount');
                    amount.textContent = `₱${formatNumber(item.total_amount)}`;
                    info.appendChild(amount);



                    li.appendChild(info);
                    output.appendChild(li);
                });
            }

            Promise.all([fetchDataFromSAP(), fetchDataFromLastMonth()])
                .then(([sapData, lastMonthData]) => {
                    updateDOM(sapData, lastMonthData);
                })
                .catch(error => {
                    console.error("Failed to fetch data:", error);
                });
        });
    </script>

</body>

</html>