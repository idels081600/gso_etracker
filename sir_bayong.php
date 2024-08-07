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

// Fetch data for the page
$result = display_data_sir_bayong();
$result2 = display_data_sir_bayong_print();
$total_amount = 0;
$display_payment = display_data_sir_bayong_payments();
while ($row = mysqli_fetch_assoc($result)) {
    $total_amount += $row["Amount"];
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_data'])) {
    // Escape user inputs for security
    $sr_no = mysqli_real_escape_string($conn, $_POST['sr_no']);
    $date = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_POST['date'])));
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // Check if amount is empty, if so, assign 0
    $amount = isset($_POST['amount']) && $_POST['amount'] !== '' ? mysqli_real_escape_string($conn, $_POST['amount']) : 0;

    $office = mysqli_real_escape_string($conn, $_POST['office']);
    $vehicle = mysqli_real_escape_string($conn, $_POST['vehicle']);
    $plate = mysqli_real_escape_string($conn, $_POST['plate']);
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);

    // Prepare the SQL query
    $query = "INSERT INTO sir_bayong(`SR_DR`, `Date`, `Supplier`, `Quantity`, `Description`, `Amount`, `Office`, `Vehicle`, `Plate` ) 
              VALUES ('$sr_no', '$date', '$supplier', '$quantity', '$description', '$amount', '$office', '$vehicle', '$plate')";

    // Execute the query and check for success
    if (mysqli_query($conn, $query)) {
        header("Location: sir_bayong.php");
        exit();
    } else {
        // Output any SQL error
        echo "Error: " . mysqli_error($conn);
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="sir_bayong.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> <!-- Corrected path for jQuery UI CSS -->

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
            <li><a href="payables_dashboard.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li class="dropdown">
                <a href="#"><i class="fas fa-map icon-size"></i> Tracking <i class="fas fa-chevron-down dropdown-icon"></i></a>
                <ul class="dropdown-menu">
                    <li><a href="sir_bayong.php">Ulysess Dela Cruz </a></li>
                    <li><a href="maam_maricris.php">Maricres Cornell</a></li>
                    <li><a href="BQ.php">March Christine Igang </a></li>
                </ul>
            </li>
            <!-- <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li> -->
        </ul>
        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>
    <div class="container">
        <div class="column">
            <div class="form_container">
                <form action="sir_bayong.php" method="POST">
                    <div class="form-element-sr_no">
                        <label for="sr_no" id="sr_no1">SR/DR</label>
                        <input type="text" id="sr_no" name="sr_no" placeholder="" value=" ">
                    </div>
                    <div class="form-element-date">
                        <label for="date" id="date2">Date</label>
                        <input type="text" id="date1" name="date" placeholder="" value=" ">
                    </div>
                    <div class="form-element-quantity">
                        <label for="quantity" id="quantity1">Quantity</label>
                        <input type="number" id="quantity" name="quantity" placeholder="" value="" min="0" step="any">
                    </div>
                    <div class="form-element-description">
                        <label for="description" id="description1">Description</label>
                        <input type="text" id="description" name="description" placeholder="" value=" ">
                    </div>
                    <div class="form-element-amount">
                        <label for="amount" id="amount1">Amount</label>
                        <input type="number" id="amount" name="amount" placeholder="" value="" min="0" step="any">
                    </div>
                    <div class="form-element-office">
                        <label for="office" id="office1">Department/Requestor</label>
                        <input type="text" id="office" name="office" placeholder="" value=" ">
                    </div>
                    <div class="form-element-vehicle">
                        <label for="vehicle" id="vehicle1">Vehicle</label>
                        <input type="text" id="vehicle" name="vehicle" placeholder="" value=" ">
                    </div>
                    <div class="form-element-plate">
                        <label for="plate" id="plate1">Plate No.</label>
                        <input type="text" id="plate" name="plate" placeholder="" value=" ">
                    </div>
                    <div class="form-element-supplier">
                        <label for="supplier" id="supplier1">Supplier</label>
                        <select id="supplierDropdown" name="supplier">
                            <option value="">Select a supplier</option>
                            <!-- Options will be dynamically populated here -->
                        </select>
                    </div>

                    <button class="button-37" role="button" name="save_data">Add Data</button>
                    <button class="button-38" role="button" name="save_data2">Edit</button>
                    <button class="button-39" role="button" name="delete_data">Delete</button>
                    <button class="button-40" role="button" name="generate_pdf" id="generate_pdf">Review PDF</button>
                    <button class="button-43" role="button" name="add_print" id="addtoprint">Add to Print</button>
                </form>
                <!-- <input type="text" id="search-input" placeholder="Search...">
                <input type="number" id="deductions" name="deductions" placeholder=" Deductions.." value=" "> -->
                <div id="total-amount">Total Amount: ₱<?php echo number_format($total_amount, 2); ?></div>
            </div>
        </div>
        <div class="popup3" id="popup3">
            <div class="close-btn">&times;</div>
            <form id="payment_form" action="" method="POST">
                <h1 id="label_modal1">Add Payment</h1>
                <div class="form-element">
                    <div class="form-element1">
                        <label for="payment_name" class="payment-label">Name:</label>
                        <input type="text" id="payment" name="payment_name" placeholder="">
                    </div>
                </div>
                <div class="form-element">
                    <div class="form-element2">
                        <label for="payment_amount" id="amount-label">Amount:</label>
                        <input type="number" id="amount2" name="payment_amount" placeholder="">
                    </div>
                </div>
                <button id="viewpayment" class="button-44">View Payment</button>
                <button id="view_print" class="button-46">View Print</button>
                <button id="submitBtn" class="button-41">Generate PDF</button>
                <button type="button" class="button-42" id="save_payment">Save Payment</button>

            </form>
        </div>
        <div class="popup4" id="popup4">
            <div class="close-btn">&times;</div>
            <button id="delete_payment" class="button-45">Delete</button>
            <div class="payment_container">
                <table id="table_payment" class="table_payment">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Reset the result pointer and re-fetch the rows to display them
                        mysqli_data_seek($display_payment, 0);
                        while ($row = mysqli_fetch_assoc($display_payment)) { ?>
                            <tr class="clickable-row2" data-rfq-id="<?php echo $row['id']; ?>"> <!-- Add data-rfq-id attribute with the row's ID -->
                                <td><?php echo $row["name"]; ?></td>
                                <td><?php echo $row["amount"]; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="popup6" id="popup6">
            <div class="close-btn">&times;</div>
            <h1 id="label_modal1">Add Supplier</h1>
            <div class="form-element">
                <div class="form-element_sup">
                    <label for="supplier_name" class="supplier-label">Name:</label>
                    <input type="text" id="supplier_name" placeholder="">
                </div>
                <button class="button-49" role="button" name="save_sup" id="save_supplier">Save</button>
            </div>
        </div>
        <div class="popup5" id="popup5">
            <div class="close-btn">&times;</div>
            <button id="delete_print" class="button-47">Delete</button>
            <div class="payment_container">
                <table id="table_print" class="table_print">
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
                        mysqli_data_seek($result2, 0);
                        while ($row = mysqli_fetch_assoc($result2)) { ?>
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
            </div>
        </div>
    </div>
    <div class="container_table">
        <input type="text" id="date4" name="date4" placeholder="Start.." value=" ">
        <input type="text" id="date3" name="date3" placeholder="End.." value=" ">
        <button class="button-50" id="add_supplier">Add Supplier</button>
        <input type="text" id="search-input" placeholder="Search...">
        <input type="number" id="deductions" name="deductions" placeholder=" Payments.." value=" ">
        <div class="table-container1">
            <table id="table_tent1" class="table_tent1">
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
                    mysqli_data_seek($result, 0);
                    while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr class="clickable-row" data-rfq-id="<?php echo $row['id']; ?>"> <!-- Add data-rfq-id attribute with the row's ID -->
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
        </div>
    </div>
    <div class="overlay"></div>
    <!-- jQuery and jQuery UI -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

    <script>
        $(function() {
            // Initialize the datepicker
            $("#date1").datepicker({
                dateFormat: "yy-mm-dd" // Set the desired date format
            });


        });
    </script>


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
            // Function to perform search
            var date4Input = document.getElementById('date4');
            var date3Input = document.getElementById('date3');

            date4Input.addEventListener('change', filterTable);
            date3Input.addEventListener('change', filterTable);

            function filterTable() {
                var start = new Date(date4Input.value);
                var end = new Date(date3Input.value);

                var tableRows = document.querySelectorAll('#table_tent1 tbody tr');

                tableRows.forEach(function(row) {
                    var rowDate = new Date(row.cells[1].textContent); // Assuming date is in the second column
                    if (rowDate >= start && rowDate <= end) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                updateTotalAmount();
            }


            function loadInitialData() {
                // AJAX request to fetch initial data of the table
                $.ajax({
                    type: "GET",
                    url: "initial_data_mariecris.php", // Update with the file that fetches initial data
                    success: function(response) {
                        $("#table_tent1 tbody").html(response);
                        updateTotalAmount(); // Update the total amount after loading initial data
                        addRowClickEventListeners(); // Add row click event listeners
                    }
                });
            }

            var input = document.getElementById('search-input');
            var table = document.getElementById('table_tent1');
            var totalAmountElement = document.getElementById('total-amount');
            var deductionsInput = document.getElementById('deductions'); // Get the deductions input field
            var rows = table.getElementsByTagName('tr');

            function updateTotalAmount() {
                let totalAmount = 0;

                // Loop through all visible table rows (excluding the first row which contains <th> elements)
                for (var i = 1; i < rows.length; i++) {
                    if (rows[i].style.display !== 'none') { // Check if the row is visible
                        var amountCell = rows[i].getElementsByTagName('td')[8]; // Get the cell containing the amount
                        var amount = parseFloat(amountCell.textContent.replace('₱', '').replace(/,/g, '')) || 0; // Parse the amount
                        totalAmount += amount; // Add the amount to the total
                    }
                }

                // Subtract deductions
                const deductions = parseFloat(deductionsInput.value.replace(/,/g, '')) || 0; // Get the deductions value from the input field
                totalAmount -= deductions;

                // Format the total amount with currency format
                const formattedTotalAmount = new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(totalAmount);

                // Update the total amount element
                totalAmountElement.textContent = `Total Amount: ${formattedTotalAmount}`;
            }
            // Add event listener to the search input
            input.addEventListener('input', function() {
                var filter = input.value.toLowerCase(); // Convert input to lowercase for case-insensitive search

                // Loop through all table rows (excluding the first row which contains <th> elements)
                for (var i = 1; i < rows.length; i++) {
                    var cells = rows[i].getElementsByTagName('td'); // Get all cells in the current row

                    // Hide the row if the search input doesn't match any cell value
                    var rowVisible = false; // Assume the row is hidden by default
                    for (var j = 0; j < cells.length; j++) {
                        var cellText = cells[j].textContent.toLowerCase(); // Get the cell text in lowercase
                        if (cellText.indexOf(filter) > -1) { // Check if the search input is found in the cell text
                            rowVisible = true; // Set rowVisible to true if the search input is found
                            break; // Exit the loop since the input is found in this row
                        }
                    }

                    // Toggle the row's display property based on rowVisible
                    rows[i].style.display = rowVisible ? '' : 'none'; // Show the row if rowVisible is true, otherwise hide it
                }

                // Update the total amount after filtering
                updateTotalAmount();
            });

            // Add event listener to the deductions input field to update the total amount
            deductionsInput.addEventListener('input', updateTotalAmount);

            // Listen to changes in date fields
            $("#date4, #date3").on('change input', function() {
                filterTable();
            });

            function getSelectedIDs() {
                var selectedIDs = [];

                // Loop through all visible and selected table rows (excluding the first row which contains <th> elements)
                for (var i = 1; i < rows.length; i++) {
                    if ($(rows[i]).hasClass('selected-row')) { // Check if the row is selected
                        var id = $(rows[i]).data('rfq-id'); // Get the ID of the selected row
                        selectedIDs.push(id); // Add the ID to the selectedIDs array
                    }
                }

                return selectedIDs;
            }

            $("#addtoprint").on("click", function() {
                var selectedIDs = getSelectedIDs(); // Get the IDs of the currently selected rows

                // Log the selected IDs to the console
                console.log("Selected IDs:", selectedIDs);

                // Check if selectedIDs has data
                if (selectedIDs.length === 0) {
                    console.log("No data to send.");
                    alert("No data to send.");
                    return;
                }

                // Send the IDs to the server via AJAX
                $.post("insert_print_data.php", {
                        ids: selectedIDs
                    })
                    .done(function(response) {
                        console.log("Server Response:", response); // Log the server response
                        // Check if the response indicates success
                        if (response.success) {
                            alert(response.message); // Display success message
                            location.reload(); // Reload the page
                        } else {
                            alert(response.message); // Display failure message
                        }
                    })
                    .fail(function(jqXHR, textStatus, errorThrown) {
                        console.error("AJAX Error:", textStatus, errorThrown); // Log detailed error information
                        alert("An error occurred while adding data to print.");
                    });
            });



            // Function to add click event listeners to table rows
            let selectedRfqIds = [];

            function addRowClickEventListeners() {
                $(".clickable-row").on("click", function() {
                    // Toggle the selected class on the clicked row
                    $(this).toggleClass('selected-row'); // Toggle the selected-row class on click

                    // Get the RFQ ID of the clicked row
                    let rfqId = $(this).data('rfq-id');

                    // If the row is selected, add its RFQ ID to the array; otherwise, remove it
                    if ($(this).hasClass('selected-row')) {
                        selectedRfqIds.push(rfqId);
                    } else {
                        selectedRfqIds = selectedRfqIds.filter(id => id !== rfqId);
                    }
                });
            }
            document.getElementById("submitBtn").addEventListener("click", function() {
                window.open("generate_pdf.php", "_blank");
            });

            // Initial setup to add row click event listeners
            addRowClickEventListeners();
        });
    </script>
    <script>
        $(document).ready(function() {
            // Variable to store the IDs of the selected rows
            var selectedRowIds4 = [];

            function clearInputFields() {
                $('#sr_no').val('');
                $('#date1').val('');
                $('#supplierDropdown').val('');
                $('#quantity').val('');
                $('#description').val('');
                $('#amount').val('');
                $('#office').val('');
                $('#vehicle').val('');
                $('#plate').val('');
            }

            // Add event listener for single click on table rows
            $('#table_tent1 tbody').on('click', 'tr', function() {
                var rowId = $(this).data('rfq-id');
                if ($(this).hasClass('selected')) {
                    $(this).removeClass('selected');
                    selectedRowIds4 = selectedRowIds4.filter(id => id !== rowId);
                    if (selectedRowIds4.length === 0) {
                        clearInputFields();
                    }
                } else {
                    $(this).addClass('selected');
                    selectedRowIds4.push(rowId);

                    var rowData = $(this).find('td').map(function() {
                        return $(this).text();
                    }).get();

                    if (selectedRowIds4.length === 1) {
                        $('#sr_no').val(rowData[0]);
                        $('#date1').val(rowData[1]);
                        $('#supplierDropdown').val(rowData[2]);
                        $('#quantity').val(rowData[7]);
                        $('#description').val(rowData[4]);

                        var amountValue = rowData[8].replace('₱', '').replace(/,/g, '');
                        var amountNumber = parseFloat(amountValue);
                        if (!isNaN(amountNumber)) {
                            $('#amount').val(amountNumber);
                        }

                        $('#office').val(rowData[3]);
                        $('#vehicle').val(rowData[5]);
                        $('#plate').val(rowData[6]);

                        $('#amount').change(function() {
                            console.log('Amount input value after change:', $(this).val());
                        });
                    }
                }
            });

            // Add event listener for the Edit button
            $('[name="save_data2"]').click(function(event) {
                event.preventDefault();

                var srNo = $('#sr_no').val();
                var date = $('#date1').val();
                var quantity = $('#quantity').val();
                var description = $('#description').val();
                var amount = $('#amount').val();
                var office = $('#office').val();
                var vehicle = $('#vehicle').val();
                var plate = $('#plate').val();
                var supplier = $('#supplierDropdown').val();
                console.log('SR No:', srNo);
                console.log('Date:', date);
                console.log('Quantity:', quantity);
                console.log('Description:', description);
                console.log('Amount:', amount);
                console.log('Office:', office);
                console.log('Vehicle:', vehicle);
                console.log('Plate:', plate);
                console.log('Supplier:', supplier);
                if (selectedRowIds4.length === 1) {
                    var selectedRowId4 = selectedRowIds4[0];
                    $.ajax({
                        url: 'update_row.php',
                        type: 'POST',
                        data: {
                            id: selectedRowId4,
                            sr_no: srNo,
                            date: date,
                            quantity: quantity,
                            description: description,
                            amount: amount,
                            office: office,
                            vehicle: vehicle,
                            plate: plate,
                            supplier: supplier
                        },
                        success: function(response) {
                            alert('Row updated successfully.');
                            var selectedRow = $('#table_tent1 tbody tr[data-rfq-id="' + selectedRowId4 + '"]');
                            selectedRow.find('td:eq(0)').text(srNo);
                            selectedRow.find('td:eq(1)').text(date);
                            selectedRow.find('td:eq(2)').text(supplier);
                            selectedRow.find('td:eq(7)').text(quantity);
                            selectedRow.find('td:eq(4)').text(description);
                            selectedRow.find('td:eq(8)').text('₱' + parseFloat(amount).toFixed(2));
                            selectedRow.find('td:eq(3)').text(office);
                            selectedRow.find('td:eq(5)').text(vehicle);
                            selectedRow.find('td:eq(6)').text(plate);
                        },
                        error: function() {
                            alert('Failed to update row.');
                        }
                    });
                } else {
                    alert('No row selected for update or multiple rows selected.');
                }
            });

            // Add event listener for the Delete button
            $('[name="delete_data"]').click(function(event) {
                event.preventDefault();

                if (selectedRowIds4.length > 0) {
                    $.ajax({
                        url: 'delete_row.php',
                        type: 'POST',
                        data: {
                            ids: selectedRowIds4
                        },
                        success: function(response) {
                            alert('Rows deleted successfully.');
                            clearInputFields();
                            selectedRowIds4.forEach(function(id) {
                                $('#table_tent1 tbody tr[data-rfq-id="' + id + '"]').remove();
                            });
                            selectedRowIds4 = [];
                        },
                        error: function() {
                            alert('Failed to delete rows.');
                        }
                    });
                } else {
                    alert('No rows selected for deletion.');
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to close the popup
            function closePopup() {
                console.log("Closing popup");
                document.querySelector(".popup3").classList.remove("active");
                document.querySelector(".overlay").style.display = "none"; // Hide the overlay
            }

            // Event listener for the Escape key
            document.addEventListener('keydown', function(event) {
                console.log("Key pressed: ", event.key);
                if (event.key === 'Escape') {
                    // Check if the popup is active
                    if (document.querySelector(".popup3").classList.contains("active")) {
                        closePopup(); // Close the popup
                    }
                }
            });

            // Event listener for the add button
            document.querySelector("#generate_pdf").addEventListener("click", function(event) {
                console.log("Opening popup");
                event.preventDefault(); // Prevent default behavior to avoid accidental form submissions
                document.querySelector(".popup3").classList.add("active");
                document.querySelector(".overlay").style.display = "block"; // Show the overlay
            });

            // Event listener for the close button
            document.querySelector(".popup3 .close-btn").addEventListener("click", function() {
                closePopup(); // Close the popup
            });

            // Debug log for form submission to ensure it doesn't close the popup
            document.querySelector(".form1").addEventListener("submit", function(event) {
                console.log("Form submitted");
                event.preventDefault(); // Prevent default form submission for debugging purposes
                // You can handle form submission here if necessary
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            $("#date3,#date4").datepicker({
                dateFormat: "yy-mm-dd" // Set the date format
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to close the popup
            function closePopup() {
                console.log("Closing popup");
                document.querySelector(".popup4").classList.remove("active");

            }

            // Event listener for the Escape key
            document.addEventListener('keydown', function(event) {
                console.log("Key pressed: ", event.key);
                if (event.key === 'Escape') {
                    // Check if the popup is active
                    if (document.querySelector(".popup4").classList.contains("active")) {
                        closePopup(); // Close the popup
                    }
                }
            });

            // Event listener for the add button
            document.querySelector("#viewpayment").addEventListener("click", function(event) {
                console.log("Opening popup");
                event.preventDefault(); // Prevent default behavior to avoid accidental form submissions
                document.querySelector(".popup4").classList.add("active");
                document.querySelector(".overlay").style.display = "block"; // Show the overlay
            });

            // Event listener for the close button
            document.querySelector(".popup4 .close-btn").addEventListener("click", function() {
                closePopup(); // Close the popup
            });

            // Debug log for form submission to ensure it doesn't close the popup
            document.querySelector(".form1").addEventListener("submit", function(event) {
                console.log("Form submitted");
                event.preventDefault(); // Prevent default form submission for debugging purposes
                // You can handle form submission here if necessary
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to close the popup
            function closePopup() {
                console.log("Closing popup");
                document.querySelector(".popup5").classList.remove("active");

            }

            // Event listener for the Escape key
            document.addEventListener('keydown', function(event) {
                console.log("Key pressed: ", event.key);
                if (event.key === 'Escape') {
                    // Check if the popup is active
                    if (document.querySelector(".popup5").classList.contains("active")) {
                        closePopup(); // Close the popup
                    }
                }
            });

            // Event listener for the add button
            document.querySelector("#view_print").addEventListener("click", function(event) {
                console.log("Opening popup");
                event.preventDefault(); // Prevent default behavior to avoid accidental form submissions
                document.querySelector(".popup5").classList.add("active");
                document.querySelector(".overlay").style.display = "block"; // Show the overlay
            });

            // Event listener for the close button
            document.querySelector(".popup5 .close-btn").addEventListener("click", function() {
                closePopup(); // Close the popup
            });

            // Debug log for form submission to ensure it doesn't close the popup
            document.querySelector(".form1").addEventListener("submit", function(event) {
                console.log("Form submitted");
                event.preventDefault(); // Prevent default form submission for debugging purposes
                // You can handle form submission here if necessary
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to close the popup
            function closePopup() {
                console.log("Closing popup");
                document.querySelector(".popup6").classList.remove("active");
                document.querySelector(".overlay").style.display = "none"; // Hide the overlay
                location.reload(); // Reload the page
            }

            // Event listener for the Escape key
            document.addEventListener('keydown', function(event) {
                console.log("Key pressed: ", event.key);
                if (event.key === 'Escape') {
                    // Check if the popup is active
                    if (document.querySelector(".popup6").classList.contains("active")) {
                        closePopup(); // Close the popup
                    }
                }
            });

            // Event listener for the add button
            document.querySelector("#add_supplier").addEventListener("click", function(event) {
                console.log("Opening popup");
                event.preventDefault(); // Prevent default behavior to avoid accidental form submissions
                document.querySelector(".popup6").classList.add("active");
                document.querySelector(".overlay").style.display = "block"; // Show the overlay
            });

            // Event listener for the close button
            document.querySelector(".popup6 .close-btn").addEventListener("click", function() {
                closePopup(); // Close the popup
            });

            // Debug log for form submission to ensure it doesn't close the popup
            document.querySelector(".form1").addEventListener("submit", function(event) {
                console.log("Form submitted");
                event.preventDefault(); // Prevent default form submission for debugging purposes
                // You can handle form submission here if necessary
            });
        });
    </script>

</body>
<script>
    let selectedRfqIds2 = [];

    function addRowClickEventListeners() {
        $(".clickable-row3").on("click", function() {
            // Toggle the selected class on the clicked row
            $(this).toggleClass('selected-row3'); // Toggle the selected-row class on click

            // Get the RFQ ID of the clicked row
            let rfqId = $(this).data('rfq-id');

            // If the row is selected, add its RFQ ID to the array; otherwise, remove it
            if ($(this).hasClass('selected-row3')) {
                selectedRfqIds2.push(rfqId);
            } else {
                selectedRfqIds2 = selectedRfqIds2.filter(id => id !== rfqId);
            }
        });
    }

    // Call the function to add event listeners initially
    addRowClickEventListeners();

    $('#delete_print').on('click', function() {
        if (selectedRfqIds2.length > 0) {
            if (confirm('Are you sure you want to delete the selected rows?')) {
                $.ajax({
                    url: 'delete_print.php',
                    type: 'POST',
                    data: {
                        rfq_ids: selectedRfqIds2
                    },
                    success: function(response) {
                        if (response === 'success') {
                            selectedRfqIds2.forEach(id => {
                                $(`.clickable-row3[data-rfq-id=${id}]`).remove();
                            });
                            selectedRfqIds2 = [];
                            alert('Selected rows deleted successfully.');

                            // Refresh the table content after successful deletion
                            $.ajax({
                                url: 'refresh_table.php', // Specify the URL to fetch the updated table content
                                type: 'GET', // Use GET or POST depending on your server-side logic
                                success: function(data) {
                                    $('#table_print tbody').html(data); // Replace the table content with the updated data

                                    // Call the function to add event listeners again after table refresh
                                    addRowClickEventListeners();
                                },
                                error: function() {
                                    alert('Failed to refresh the table.');
                                }
                            });
                        } else {
                            alert('Failed to delete the selected rows.');
                        }
                    },
                    error: function() {
                        alert('Failed to delete the selected rows.');
                    }
                });
            }
        } else {
            alert('Please select at least one row.');
        }
    });
</script>
<script>
    let selectedRfqIds1 = [];

    function addRowClickEventListeners() {
        $(".clickable-row2").on("click", function() {
            // Toggle the selected class on the clicked row
            $(this).toggleClass('selected-row2'); // Toggle the selected-row class on click

            // Get the RFQ ID of the clicked row
            let rfqId = $(this).data('rfq-id');

            // If the row is selected, add its RFQ ID to the array; otherwise, remove it
            if ($(this).hasClass('selected-row2')) {
                selectedRfqIds1.push(rfqId);
            } else {
                selectedRfqIds1 = selectedRfqIds1.filter(id => id !== rfqId);
            }
        });
    }

    // Call the function to add event listeners
    addRowClickEventListeners();

    $('#delete_payment').on('click', function() {
        if (selectedRfqIds1.length > 0) {
            if (confirm('Are you sure you want to delete the selected rows?')) {
                $.ajax({
                    url: 'delete_payment.php',
                    type: 'POST',
                    data: {
                        rfq_ids: selectedRfqIds1
                    },
                    success: function(response) {
                        if (response === 'success') {
                            selectedRfqIds1.forEach(id => {
                                $(`.clickable-row2[data-rfq-id=${id}]`).remove();
                            });
                            selectedRfqIds1 = [];
                            alert('Selected rows deleted successfully.');

                            // Refresh the table content after successful deletion
                            refreshTableContent();
                        } else {
                            alert('Failed to delete the selected rows.');
                        }
                    },
                    error: function() {
                        alert('Failed to delete the selected rows.');
                    }
                });
            }
        } else {
            alert('Please select at least one row.');
        }
    });
    document.addEventListener("DOMContentLoaded", function() {
        var savePaymentBtn = document.getElementById("save_payment");
        var selectedRfqIds1 = [];

        savePaymentBtn.addEventListener("click", function(event) {
            event.preventDefault(); // Prevent default form submission behavior

            var form = document.getElementById("payment_form");
            var paymentName = form.elements["payment_name"].value;
            var paymentAmount = form.elements["payment_amount"].value;

            // Perform validation if needed
            if (!paymentName || !paymentAmount) {
                alert("Please fill in all fields.");
                return;
            }

            var paymentData = {
                payment_name: paymentName,
                payment_amount: paymentAmount
            };

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "save_payment.php"); // Replace "save_payment.php" with your server-side script URL
            xhr.setRequestHeader("Content-Type", "application/json");
            xhr.onload = function() {
                if (xhr.status === 200) {
                    console.log("Payment saved successfully");
                    form.reset();
                    refreshTable();
                } else {
                    console.error("Error saving payment:", xhr.statusText);
                }
            };
            xhr.onerror = function() {
                console.error("Network error occurred");
            };
            xhr.send(JSON.stringify(paymentData));
        });

        function refreshTable() {
            $.ajax({
                url: 'refresh_payment_table.php',
                type: 'GET',
                success: function(data) {
                    $('#table_payment tbody').html(data);
                    console.log("Table refreshed successfully.");
                    addRowClickEventListeners();
                },
                error: function() {
                    alert('Failed to refresh the table.');
                }
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector("#save_supplier").addEventListener("click", function(event) {
            event.preventDefault(); // Prevent default form submission

            // Get the supplier name input value
            var supplierName = document.getElementById("supplier_name").value;

            // Send AJAX request to save the supplier name
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "save_supplier.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
                    // Handle response from server
                    var response = xhr.responseText.trim(); // Trim to remove any extra whitespace

                    if (response === "exists") {
                        alert("Supplier already exists."); // Display message if supplier exists
                        // Optionally, fetch and display existing data here
                        document.getElementById("supplier_name").value = '';
                        fetchExistingSupplierData(supplierName);
                    } else if (response === "saved") {
                        alert("Supplier name saved successfully!"); // Display success message
                        document.getElementById("supplier_name").value = ''; // Clear input field
                    } else {
                        alert("Error saving supplier."); // Display error message if any
                    }
                }
            };
            xhr.send("supplier_name=" + encodeURIComponent(supplierName));
        });

        // Function to fetch and display existing supplier data
        function fetchExistingSupplierData(supplierName) {
            // Example: You can add code here to fetch and display existing supplier data
            // Replace with your implementation to fetch and display existing data
            console.log("Fetching existing data for supplier: " + supplierName);
            // Example: AJAX request to fetch and display existing data
            // var xhrFetch = new XMLHttpRequest();
            // xhrFetch.open("GET", "fetch_existing_supplier_data.php?supplier_name=" + encodeURIComponent(supplierName), true);
            // xhrFetch.onreadystatechange = function() {
            //     if (xhrFetch.readyState === XMLHttpRequest.DONE && xhrFetch.status === 200) {
            //         var existingData = xhrFetch.responseText;
            //         // Example: Display existing data in a modal or alert
            //         alert("Existing Data:\n" + existingData);
            //     }
            // };
            // xhrFetch.send();
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to populate suppliers dropdown
        function populateSuppliersDropdown() {
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "get_suppliers.php", true);
            xhr.setRequestHeader("Content-Type", "application/json");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    var suppliers = response.suppliers;

                    var dropdown = document.getElementById("supplierDropdown");
                    dropdown.innerHTML = ""; // Clear existing options

                    // Add default option
                    var defaultOption = document.createElement("option");
                    defaultOption.value = "";
                    defaultOption.text = "Select a supplier";
                    dropdown.appendChild(defaultOption);

                    // Add suppliers options
                    suppliers.forEach(function(supplier) {
                        var option = document.createElement("option");
                        option.value = supplier;
                        option.text = supplier;
                        dropdown.appendChild(option);
                    });
                }
            };
            xhr.send();
        }

        // Call the function to populate dropdown on page load
        populateSuppliersDropdown();
    });
</script>



</html>