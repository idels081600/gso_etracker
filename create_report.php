<?php
require_once "display_data_asset.php";
require_once 'db_asset.php';
session_start();
if (!isset($_SESSION['username'])) {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}
$on_stock = display_tent_status();
$longterm = display_tent_status_Longterm();
$on_field = display_tent_status_Installed();
$on_retrieval = display_tent_status_Retrieval();
$dispatched = display_vehicle_dispatched();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="main_content.css">
    <link rel="stylesheet" href="report.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@0.7.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <title>Document</title>
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
        <h1>Generate Report</h1>
        <div class="container_tent"> 
            <h2 id="tent_label">Tent Installation</h2>
            <button class="button-2" id="addButton" role="button">Get Data</button>
            <div id="datepicker-container" class="datepicker-container">
                <input type="text" id="datepicker1" class="datepicker-input" placeholder="Select Start Date">
                <input type="text" id="datepicker2" class="datepicker-input" placeholder="Select End Date">
            </div>
            <div class="tent_summary">
                <div id="tent_summary_label">
                    Tent Summary
                </div>
                <h2 id="stock">Available</h2>
                <h2 id="field">On Field</h2>
                <h2 id="retrieval">Retrieval</h2>
                <h2 id="long">Long Term</h2>
                <h1 id="on_stocks"><?php echo $on_stock; ?></h1>
                <h1 id="on_fields"><?php echo $on_field; ?></h1>
                <h1 id="on_retrievals"><?php echo $on_retrieval; ?></h1>
                <h1 id="long_term_uses"><?php echo $longterm; ?></h1>
            </div>
        </div>
        <div class="pie_tent">
            <div id="tent_purpose_label">
                Tent Purpose
            </div>
            <div id="chartContainer"></div>
        </div>

        <div class="pie_tent2">
            <div id="tent_location_label">
                Tent Location
            </div>
            <div id="chartContainer2"></div>

        </div>
        <div class="container_vehicle">
            <h2 id="vehicle_label">Transportation </h2>
            <div id="datepicker-container1" class="datepicker-container">
                <input type="text" id="datepicker3" class="datepicker-input1" placeholder="Select Start Date">
                <input type="text" id="datepicker4" class="datepicker-input1" placeholder="Select End Date">
            </div>
            <button class="button-3" id="addButton2" role="button">Get Data</button>
            <div class="transpo_summary">
                <div id="tent_summary_label">
                    Transportation Summary
                </div>
                <h2 id="dispatch">Dispatched</h2>
                <h1 id="dispatched_value"><?php echo $dispatched; ?></h1>

            </div>
            <div class="pie_transpo">
                <div id="tent_purpose_label">
                    Type of Request
                </div>
                <div id="chartContainer3"></div>
            </div>

            <div class="pie_transpo2">
                <div id="tent_location_label">
                    Breakdown of Dispatch
                </div>
                <div id="chartContainer4"></div>

            </div>
        </div>
        <div class="rfq">
            <h2 id="vehicle_label">RFQ </h2>
            <div id="datepicker-container1" class="datepicker-container">
                <input type="text" id="datepicker5" class="datepicker-input1" placeholder="Select Start Date">
                <input type="text" id="datepicker6" class="datepicker-input1" placeholder="Select End Date">
            </div>
            <button class="button-4" id="addButton3" role="button">Get Data</button>
            <div class="rfq_summary">
                <div id="tent_summary_label">
                    RFQ Summary
                </div>
                <h2 id="rfq">Completed</h2>
                <h1 id="rfq_value">30</h1>

            </div>
        </div>
        <div class="payables">
            <h2 id="payables_label">Payables </h2>
            <div class="dropdown_menu">
                <select class="menu" id="sel1" name='typeofbusiness'>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            <div class="dropdown_menu1">
                <input class="menu1" id="sel2" name="typeofbusiness1" type="number" placeholder="Year">
            </div>
            <button class="button-5" id="pay_button" role="button">Get Data</button>
            <div id="bar_payables"></div>
        </div>
    </div>

</body>
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
    // <!-- 
</script>
<script type="text/javascript">
    var myChart;
    var myChart2;
    var finalCount = 0;
    $(document).ready(function() {
        // Initialize datepicker for the first input
        $("#datepicker1").datepicker();
        $("#datepicker2").datepicker();

        // Fetch initial data without specifying start and end dates for the first chart
        initialData('pie_initial_data.php', 'chartContainer');
        initialData('pie_initial_data_location.php', 'chartContainer2');

        // Click event for the button
        $("#addButton").click(function() {
            var startDate = $("#datepicker1").val();
            var endDate = $("#datepicker2").val();

            // Format the startDate and endDate variables to 'YYYY-MM-DD'
            startDate = formatDate(startDate);
            endDate = formatDate(endDate);

            // Call the fetchData function for the first chart
            fetchData(startDate, endDate, 'report_tent.php', 'chartContainer');
            finalCount = 0;
            // Call the fetchData function for the second chart
            fetchData(startDate, endDate, 'report_tent_location.php', 'chartContainer2');
            fetch_on_field(startDate, endDate);
            fetch_for_retrieval(startDate, endDate);
        });

        function formatDate(dateString) {
            if (!dateString) return null; // Return null if the dateString is empty
            var parts = dateString.split('/');
            if (parts.length !== 3) return null; // Ensure the date is in the expected format
            return parts[2] + '-' + parts[0] + '-' + parts[1];
        }

        function initialData(initialUrl, chartContainerId) {
            $.ajax({
                url: initialUrl,
                method: 'GET',
                success: function(response) {
                    var eventCounts = response.event_counts || response.location_count;
                    if (eventCounts) {
                        updateChart(eventCounts, chartContainerId);
                    } else {
                        // document.getElementById(chartContainerId).innerHTML = "No data available.";
                    }
                },
                error: function(error) {
                    console.error('Error fetching initial data:', error);
                }
            });
        }

        function fetchData(startDate, endDate, reportUrl, chartContainerId) {
            if (!startDate || !endDate) {
                document.getElementById(chartContainerId).innerHTML = "Please select valid start and end dates.";
                return;
            }

            $.ajax({
                url: reportUrl,
                method: 'GET',
                data: {
                    start_date: startDate,
                    end_date: endDate,
                },
                success: function(response) {
                    console.log(response);
                    var eventCounts = response.event_counts || response.location_counts; // Corrected 'location_counts'
                    if (eventCounts) {
                        updateChart(eventCounts, chartContainerId);
                    } else {
                        document.getElementById(chartContainerId).innerHTML = "No data available.";
                    }
                },
                error: function(error) {
                    console.error('Error fetching data: ', error); // Corrected parameter name to 'error'
                }
            });
        }

        function updateChart(eventCounts, chartContainerId) {
            var chart;
            if (chartContainerId === 'chartContainer') {
                if (!myChart) {
                    myChart = echarts.init(document.getElementById(chartContainerId));
                }
                chart = myChart;
            } else if (chartContainerId === 'chartContainer2') {
                if (!myChart2) {
                    myChart2 = echarts.init(document.getElementById(chartContainerId));
                }
                chart = myChart2;
            }

            var filteredData = [];
            var totalValue = 0;
            Object.keys(eventCounts).forEach(function(key) {
                totalValue += eventCounts[key];
                if (eventCounts[key] !== 0) {
                    filteredData.push({
                        value: eventCounts[key],
                        name: key
                    });
                }
            });

            if (totalValue === 0) {
                document.getElementById(chartContainerId).innerHTML = "No data available.";
                return;
            }

            var option = {
                tooltip: {
                    trigger: 'item'
                },
                color: [
                    "#e57373", "#ffb74d", "#fff176", "#aed581", "#64b5f6", "#9575cd", "#f06292", "#4db6ac", "#ba68c8", "#90a4ae", "#ff8a65", "#ffab91", "#81c784"
                ],
                series: [{
                    name: 'Purpose',
                    type: 'pie',
                    radius: '50%',
                    data: filteredData,
                    label: {
                        position: 'outside',
                        formatter: '{b}: {c}'
                    },
                    emphasis: {
                        itemStyle: {
                            shadowBlur: 10,
                            shadowOffsetX: 0,
                            shadowColor: 'rgba(0, 0, 0, 0.5)'
                        }
                    }
                }]
            };

            chart.setOption(option);
        }

        function fetch_on_field(startDate, endDate) {
            if (!startDate || !endDate) return;

            $.ajax({
                url: 'fetch_on_field_data.php',
                method: 'GET',
                data: {
                    start_date: startDate,
                    end_date: endDate
                },
                success: function(response) {
                    var totalCount = 0;

                    response.data.forEach(function(row) {
                        if (row.tent_no) {
                            var tentNumbers = row.tent_no.split(',');
                            totalCount += tentNumbers.length;
                        }
                    });

                    console.log('Total Count:', totalCount); // Log the total count

                    $("#on_fields").text(totalCount);

                    finalCount += totalCount;
                    console.log('Final Count:', finalCount); // Log the final count

                    updateOnStockTotal();
                },
                error: function(error) {
                    console.error('Error fetching on field data:', error);
                }
            });
        }

        function fetch_for_retrieval(startDate, endDate) {
            if (!startDate || !endDate) return;

            $.ajax({
                url: 'fetch_for_retrieval_data.php',
                method: 'GET',
                data: {
                    start_date: startDate,
                    end_date: endDate
                },
                success: function(response) {
                    var totalCount = 0;

                    response.data.forEach(function(row) {
                        if (row.tent_no) {
                            var tentNumbers = row.tent_no.split(',');
                            totalCount += tentNumbers.length;
                        }
                    });

                    $("#on_retrievals").text(totalCount);

                    finalCount += totalCount;
                    updateOnStockTotal();
                },
                error: function(error) {
                    console.error('Error fetching for retrieval data:', error);
                }
            });
        }

        function updateOnStockTotal() {
            var on_stock_total = 120 - finalCount - 40;
            $("#on_stocks").text(on_stock_total);
        }
    });
</script>

<script type="text/javascript">
    var myChart3;
    var myChart4;

    $(document).ready(function() {
        // Initialize datepicker for the first input
        $("#datepicker3").datepicker();

        // Initialize datepicker for the second input
        $("#datepicker4").datepicker();

        // Fetch initial data without specifying start and end dates for the first chart
        initialData('pie_initial_data_request.php', 'chartContainer3');

        // Fetch initial data without specifying start and end dates for the second chart
        initialData('pie_initial_data_dispatch.php', 'chartContainer4');

        // Click event for the button
        $("#addButton2").click(function() {
            var startDate = $("#datepicker3").val();
            var endDate = $("#datepicker4").val();

            // Log the startDate and endDate variables
            console.log('Start Date:', startDate);
            console.log('End Date:', endDate);

            // Format the startDate and endDate variables to 'YYYY-MM-DD'
            startDate = formatDate(startDate);
            endDate = formatDate(endDate);
            console.log('Formatted Start Date:', startDate);
            console.log('Formatted End Date:', endDate);

            // Call the fetchData function for the first chart
            fetchData(startDate, endDate, 'report_transpo.php', 'chartContainer3');
            finalCount = 0;
            // Call the fetchData function for the second chart
            fetchData(startDate, endDate, 'report_transpo_location.php', 'chartContainer4');
            fetch_on_field(startDate, endDate);
        });

        function formatDate(dateString) {
            var parts = dateString.split('/');
            var formattedDate = parts[2] + '-' + parts[0] + '-' + parts[1];
            return formattedDate;
        }

        function initialData(initialUrl, chartContainerId) {
            $.ajax({
                url: initialUrl,
                method: 'GET',
                success: function(response) {
                    console.log(response);
                    updateChart(response.event_counts || response.location_count, chartContainerId);
                },
                error: function(error) {
                    console.error('Error fetching initial data:', error);
                }
            });
        }

        function fetchData(startDate, endDate, reportUrl, chartContainerId) {
            $.ajax({
                url: reportUrl,
                method: 'GET',
                data: {
                    start_date: startDate,
                    end_date: endDate,
                    tent_status: 'Arrived'
                },
                success: function(response) {
                    console.log(response);
                    updateChart(response.event_counts || response.location_count, chartContainerId);
                },
                error: function(error) {
                    console.error('Error fetching data:', error);
                }
            });
        }

        function updateChart(eventCounts, chartContainerId) {
            var chart;
            if (chartContainerId === 'chartContainer3') {
                if (!myChart3) {
                    myChart3 = echarts.init(document.getElementById(chartContainerId));
                }
                chart = myChart3;
            } else if (chartContainerId === 'chartContainer4') {
                if (!myChart4) {
                    myChart4 = echarts.init(document.getElementById(chartContainerId));
                }
                chart = myChart4;
            }

            var filteredData = [];
            var totalValue = 0;
            Object.keys(eventCounts).forEach(function(key) {
                totalValue += eventCounts[key];
                if (eventCounts[key] !== 0) {
                    filteredData.push({
                        value: eventCounts[key],
                        name: key
                    });
                }
            });

            if (totalValue === 0) {
                document.getElementById(chartContainerId).innerHTML = "No data available.";
                return;
            }

            var option = {
                tooltip: {
                    trigger: 'item'
                },
                color: [
                    "#e57373", // Light Red
                    "#ffb74d", // Light Orange
                    "#fff176", // Light Yellow
                    "#aed581", // Light Green
                    "#64b5f6", // Light Blue
                    "#9575cd", // Light Purple
                    "#f06292", // Light Pink
                    "#4db6ac", // Light Teal
                    "#ba68c8", // Light Lavender
                    "#90a4ae", // Light Gray-Blue
                    "#ff8a65" // Light Coral
                ],
                series: [{
                    name: 'Purpose',
                    type: 'pie',
                    radius: '50%',
                    data: filteredData,
                    label: {
                        position: 'outside',
                        formatter: '{b}: {c}'
                    },
                    emphasis: {
                        itemStyle: {
                            shadowBlur: 10,
                            shadowOffsetX: 0,
                            shadowColor: 'rgba(0, 0, 0, 0.5)'
                        }
                    }
                }]
            };

            chart.setOption(option);
        }

        function fetch_on_field(startDate, endDate) {
            $.ajax({
                url: 'fetch_on_field_data_transpo.php',
                method: 'GET',
                data: {
                    start_date: startDate,
                    end_date: endDate
                },
                success: function(response) {
                    console.log(response);

                    // Extract the total count from the response
                    var totalCount = response.total_count;

                    console.log('Total Tent Count:', totalCount);

                    // Update the total count in the h1 element
                    $("#dispatched_value").text(totalCount);
                },
                error: function(error) {
                    console.error('Error fetching on field data:', error);
                }
            });
        }

    });
</script>

<script type="text/javascript">
    var myChart3;
    var myChart4;

    $(document).ready(function() {
        // Initialize datepicker for the first input
        $("#datepicker5").datepicker();

        // Initialize datepicker for the second input
        $("#datepicker6").datepicker();

        // Fetch initial data without specifying start and end dates for the first chart
        initialData('pie_initial_data_rfq.php');

        // Click event for the button
        $("#addButton3").click(function() {
            var startDate = $("#datepicker5").val();
            var endDate = $("#datepicker6").val();

            // Log the startDate and endDate variables
            console.log('Start Date:', startDate);
            console.log('End Date:', endDate);

            // Format the startDate and endDate variables to 'YYYY-MM-DD'
            startDate = formatDate(startDate);
            endDate = formatDate(endDate);
            console.log('Formatted Start Date:', startDate);
            console.log('Formatted End Date:', endDate);
            fetch_on_field(startDate, endDate);
        });

        function formatDate(dateString) {
            var parts = dateString.split('/');
            var formattedDate = parts[2] + '-' + parts[0] + '-' + parts[1];
            return formattedDate;
        }

        function initialData(initialUrl) {
            $.ajax({
                url: initialUrl,
                method: 'GET',
                success: function(response) {
                    console.log(response);
                    // Update the total count in the h1 element
                    $("#rfq_value").text(response.total_count);
                },
                error: function(error) {
                    console.error('Error fetching initial data:', error);
                }
            });
        }

        function fetch_on_field(startDate, endDate) {
            $.ajax({
                url: 'fetch_rfq_data_complete.php',
                method: 'GET',
                data: {
                    start_date: startDate,
                    end_date: endDate
                },
                success: function(response) {
                    console.log(response);

                    // Extract the total count from the response
                    var totalCount = response.total_count;

                    console.log('Total Tent Count:', totalCount);

                    // Update the total count in the h1 element
                    $("#rfq_value").text(totalCount);
                },
                error: function(error) {
                    console.error('Error fetching on field data:', error);
                }
            });
        }

    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        function fetchData(url) {
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error("Error:", data.error);
                        return;
                    }
                    if (!Array.isArray(data)) {
                        console.error("Unexpected data format:", data);
                        return;
                    }

                    let suppliersMap = new Map();

                    data.forEach(item => {
                        if (typeof item === 'object') {
                            let supplier = item.supplier.toUpperCase().trim();
                            let total_amount = parseFloat(item.total_amount);

                            if (suppliersMap.has(supplier)) {
                                let currentAmount = suppliersMap.get(supplier);
                                suppliersMap.set(supplier, currentAmount + total_amount);
                            } else {
                                suppliersMap.set(supplier, total_amount);
                            }
                        } else {
                            let supplier = item.toUpperCase().trim();

                            if (!suppliersMap.has(supplier)) {
                                suppliersMap.set(supplier, 0);
                            }
                        }
                    });

                    let uniqueSuppliers = Array.from(suppliersMap, ([supplier, total_amount]) => ({
                        supplier,
                        total_amount
                    })).sort((a, b) => b.total_amount - a.total_amount);

                    let chartData = uniqueSuppliers.map(item => ({
                        name: item.supplier,
                        amount: item.total_amount
                    }));

                    var chartDom = document.getElementById('bar_payables');
                    var myChart = echarts.init(chartDom);

                    var option = {
                        dataset: [{
                            dimensions: ['name', 'amount'],
                            source: chartData
                        }],
                        xAxis: {
                            type: 'category',
                            axisLabel: {
                                interval: 0,
                                rotate: 30
                            }
                        },
                        yAxis: {},
                        series: [{
                            type: 'bar',
                            encode: {
                                x: 'name',
                                y: 'amount'
                            },
                            label: {
                                show: true,
                                position: 'top',
                                formatter: function(params) {
                                    return new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'PHP'
                                    }).format(params.value.amount);
                                }
                            }
                        }]
                    };

                    myChart.setOption(option);

                    function resizeContainer() {
                        var chartWidth = myChart.getWidth();
                        chartDom.style.width = chartWidth + 'px';
                    }

                    resizeContainer();
                    window.addEventListener('resize', resizeContainer);
                })
                .catch(error => console.error("Fetch error:", error));
        }

        fetchData('data_sap.php');

        document.getElementById("pay_button").addEventListener("click", function() {
            let month = document.getElementById("sel1").value;
            let year = document.getElementById("sel2").value;
            fetchData(`data_sap_filtered.php?month=${month}&year=${year}`);
        });
    });
</script>

</html>