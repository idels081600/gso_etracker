<?php
require_once 'db.php'; // Assuming this file contains your database connection code
require_once 'display_data.php';
session_start();
if (!isset($_SESSION['username'])) {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}
// if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
//     header("location: login_v2.php");
//     exit(); // Ensure that the script stops execution after the redirect
// }

$result = display_data_BQ();
$result2 = display_data_bq_print();
$total_amount = 0;
$display_payment = display_data_BQ_payments();
while ($row = mysqli_fetch_assoc($result)) {
    $total_amount += $row["amount"];
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_data'])) {
    $sr_no = mysqli_real_escape_string($conn, $_POST['sr_no']);
    $date = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_POST['date'])));
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $activity = mysqli_real_escape_string($conn, $_POST['activity']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $office = mysqli_real_escape_string($conn, $_POST['office']);
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
    // $total = $quantity * $amount;
    $query = "INSERT INTO bq(`SR_DR`, `date`, `supplier`, `requestor`, `activity`,`description`, `quantity`, `amount`) 
              VALUES ('$sr_no', '$date', '$supplier','$office','$activity','$description', '$quantity', ' $amount ')";

    $query_run = mysqli_query($conn, $query);
    if ($query_run) {
        header("Location: BQ.php");
        exit();
    } else {
        header("Location: BQ.php");
        exit();
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
    <link rel="stylesheet" href="bq.css">
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
                <form action="BQ.php" method="POST">
                    <div class="form-element-sr_no">
                        <label for="sr_no" id="sr_no1">SR/DR</label>
                        <input type="text" id="sr_no" name="sr_no" placeholder="" value=" ">
                    </div>
                    <div class="form-element-date">
                        <label for="date" id="date2">Date</label>
                        <input type="text" id="date1" name="date" placeholder="" value=" ">
                    </div>
                    <div class="form-element-activity">
                        <label for="activity" id="activity1">Activity</label>
                        <input type="text" id="activity" name="activity" placeholder="" value=" ">
                    </div>
                    <div class="form-element-description">
                        <label for="description" id="activity1">Description</label>
                        <input type="text" id="description1" name="description" placeholder="" value=" ">
                    </div>
                    <div class="form-element-quantity">
                        <label for="quantity" id="quantity1">Quantity</label>
                        <input type="text" id="quantity" name="quantity" placeholder="" value=" ">
                    </div>

                    <div class="form-element-amount">
                        <label for="amount" id="amount1">Amount</label>
                        <input type="number" id="amount" name="amount" placeholder="" value="" min="0" step="any">
                    </div>
                    <div class="form-element-office">
                        <label for="office" id="office1">Department/Requestor</label>
                        <input type="text" id="office" name="office" placeholder="" value=" ">
                    </div>
                    <div class="form-element-supplier">
                        <label for="supplier" id="supplier1">Supplier</label>
                        <select id="supplierDropdown" name="supplier">
                            <option value="">Select a supplier</option>
                            <!-- Options will be dynamically populated here -->
                        </select>
                    </div>
                    <!-- <div class="form-element-plate">
                        <label for="plate" id="plate1">Plate</label>
                        <input type="text" id="plate" name="plate" placeholder="" value=" ">
                    </div> -->
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
                        <label for="payment_name" class="payment-label">P.O/Payment Details:</label>
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
            <button id="delete_print_all" class="button-48">Delete All</button>
            <div class="payment_container">
                <table id="table_print" class="table_print">
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

                    </tbody>

                </table>
            </div>
        </div>
    </div>
    <div class="container_table">
        <input type="text" id="date4" name="date4" placeholder="Start..">
        <input type="text" id="date3" name="date3" placeholder="End..">
        <button class="button-50" role="button" name="add_sup" id="add_supplier">Add Supplier</button>
        <button class="button-51" id="filter">Filter</button>
        <input type="text" id="search-input" placeholder="Search...">
        <select id="supplier-select" name="supplier">
            <option value="">Select Supplier</option>
            <?php
            // Assuming you have a list of suppliers from the database
            $suppliers_query = "SELECT name FROM Supplier"; // Update with your actual query and table name
            $suppliers_result = mysqli_query($conn, $suppliers_query);

            while ($row = mysqli_fetch_assoc($suppliers_result)) {
                echo '<option value="' . $row['name'] . '">' . $row['name'] . '</option>';
            }
            ?>
        </select>
        <div class="table-container1">
            <table id="table_tent1" class="table_tent1">
                <thead>
                    <tr>
                        <th>SR/DR</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Department/Requestor</th>
                        <th>Activity</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Reset the result pointer and re-fetch the rows to display them
                    mysqli_data_seek($result, 0);
                    while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                        <tr class="clickable-row" data-rfq-id="<?php echo $row['id']; ?>">
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
        document.getElementById("filter").addEventListener("click", function() {
            // Get the selected date range
            var startDateInput = document.getElementById('date4').value;
            var endDateInput = document.getElementById('date3').value;

            // Convert input values to Date objects if they are set
            var startDate = startDateInput ? new Date(startDateInput) : null;
            var endDate = endDateInput ? new Date(endDateInput) : null;

            // Get the selected supplier
            var selectedSupplier = document.getElementById("supplier-select").value.toLowerCase();

            // Get all table rows
            var tableRows = document.querySelectorAll("#table_tent1 tbody tr");

            // Loop through each row and filter
            tableRows.forEach(function(row) {
                // Get the row's date and supplier values
                var rowDate = new Date(row.cells[1].textContent); // Assuming date is in the 2nd column
                var rowSupplier = row.cells[2].textContent.toLowerCase(); // Assuming supplier is in the 3rd column

                // Check if the row date falls within the range
                var dateInRange = (!startDate || rowDate >= startDate) && (!endDate || rowDate <= endDate);

                // Check if the supplier matches or if no supplier is selected
                var supplierMatch = !selectedSupplier || rowSupplier === selectedSupplier;

                // Display the row only if both conditions are met
                if (dateInRange && supplierMatch) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            // Input elements for date filtering
            const date4Input = document.getElementById('date4');
            const date3Input = document.getElementById('date3');
            const input = document.getElementById('search-input');
            const deductionsInput = document.getElementById('deductions');
            const totalAmountElement = document.getElementById('total-amount');
            const table = document.getElementById('table_tent1');
            const rows = table ? table.getElementsByTagName('tr') : [];

            // Check if date4Input, date3Input, input, deductionsInput exist before adding event listeners
            if (date4Input) {
                date4Input.addEventListener('change', filterTable);
            }
            if (date3Input) {
                date3Input.addEventListener('change', filterTable);
            }
            if (input) {
                input.addEventListener('input', filterTableBySearch);
            }
            if (deductionsInput) {
                deductionsInput.addEventListener('input', updateTotalAmount);
            }

            // Filter table based on date range
            function filterTable() {
                if (!date4Input || !date3Input || !rows.length) return;

                const start = new Date(date4Input.value);
                const end = new Date(date3Input.value);

                Array.from(rows).forEach(function(row) {
                    const rowDate = new Date(row.cells[1].textContent); // Assuming date is in the second column
                    row.style.display = (rowDate >= start && rowDate <= end) ? '' : 'none';
                });

                updateTotalAmount();
            }

            // Search filter function for the table
            function filterTableBySearch() {
                if (!input || !rows.length) return;

                const filter = input.value.toLowerCase(); // For case-insensitive search

                Array.from(rows).forEach(function(row) {
                    const cells = row.getElementsByTagName('td');
                    let isVisible = false;

                    Array.from(cells).forEach(function(cell) {
                        if (cell.textContent.toLowerCase().includes(filter)) {
                            isVisible = true;
                        }
                    });

                    row.style.display = isVisible ? '' : 'none';
                });

                updateTotalAmount();
            }

            // Function to update the total amount in the table
            function updateTotalAmount() {
                if (!rows.length || !totalAmountElement) return;

                let totalAmount = 0;

                Array.from(rows).forEach(function(row) {
                    if (row.style.display !== 'none') {
                        const amountCell = row.getElementsByTagName('td')[7]; // Assuming amount is in the 8th column
                        const amount = parseFloat(amountCell.textContent.replace('₱', '').replace(/,/g, '')) || 0;
                        totalAmount += amount;
                    }
                });

                const deductions = parseFloat(deductionsInput ? deductionsInput.value.replace(/,/g, '') : 0) || 0;
                totalAmount -= deductions;

                totalAmountElement.textContent = `Total Amount: ${formatCurrency(totalAmount)}`;
            }

            // Currency formatting function
            function formatCurrency(value) {
                return new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP',
                    minimumFractionDigits: 2
                }).format(value);
            }

            // Handle adding all displayed data to print
            $("#addtoprint").on("click", function() {
                const tableData = [];

                if (!table) return;

                $("#table_tent1 tbody tr:visible").each(function() {
                    const rowData = {
                        SR_DR: $(this).find('td:eq(0)').text(),
                        Date: $(this).find('td:eq(1)').text(),
                        Supplier: $(this).find('td:eq(2)').text(),
                        Requestor: $(this).find('td:eq(3)').text(),
                        Activity: $(this).find('td:eq(4)').text(),
                        Description: $(this).find('td:eq(5)').text(),
                        Quantity: $(this).find('td:eq(6)').text(),
                        Amount: $(this).find('td:eq(7)').text()
                    };
                    tableData.push(rowData);
                });

                if (tableData.length === 0) {
                    alert("No data to send.");
                    return;
                }

                // Log the table data for debugging
                console.log("Table Data:", tableData);

                // AJAX post request to send the table data
                $.ajax({
                    type: "POST",
                    url: "insert_print_data_bq.php",
                    data: {
                        data: JSON.stringify(tableData)
                    },
                    dataType: "json",
                    success: function(response) {
                        console.log("Server Response:", response);
                        alert(response.message);
                        // location.reload(); // Uncomment this if you want to reload after success
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("AJAX Error:", textStatus, errorThrown);
                        alert("An error occurred while adding data to print.");
                    }
                });
            });

            // Open the PDF generation in a new window
            document.getElementById("submitBtn")?.addEventListener("click", function() {
                window.open("generate_bq_pdf.php", "_blank");
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            // Variable to store the IDs of the selected rows
            var selectedRowIds4 = [];

            function clearInputFields() {
                $('#sr_no').val('');
                $('#date1').val('');
                $('#quantity').val('');
                $('#activity').val('');
                $('#description1').val('');
                $('#office').val('');
                $('#amount').val('');
                $('#supplierDropdown').val('');
            }

            // Add event listener for single click on table rows
            $('#table_tent1 tbody').on('click', 'tr', function() {
                // Get the ID from the data-rfq-id attribute of the clicked row
                var rowId = $(this).data('rfq-id');

                // Check if the clicked row is already selected
                if ($(this).hasClass('selected')) {
                    // If it is selected, remove the 'selected' class and clear input fields
                    $(this).removeClass('selected');
                    selectedRowIds4 = selectedRowIds4.filter(id => id !== rowId);
                    if (selectedRowIds4.length === 0) {
                        clearInputFields();
                    }
                } else {
                    // Add 'selected' class to the clicked row
                    $(this).addClass('selected');
                    selectedRowIds4.push(rowId);

                    // Get the data from the clicked row
                    var rowData = $(this).find('td').map(function() {
                        return $(this).text();
                    }).get();

                    // Fill the input fields with the data of the first selected row
                    console.log(rowData); // Debug: Print rowData to ensure it contains the expected values

                    $('#sr_no').val(rowData[0]);
                    $('#date1').val(rowData[1]);
                    $('#supplierDropdown').val(rowData[2]);
                    $('#quantity').val(rowData[6]);
                    $('#activity').val(rowData[4]);

                    // Debug: Print the value of rowData[6] before and after processing
                    console.log('Original Amount:', rowData[6]);

                    // Remove the currency symbol and commas
                    var amountValue = rowData[7].replace('₱', '').replace(/,/g, '');
                    console.log('Processed Amount String:', amountValue);

                    // Convert the cleaned string to a float
                    var amountNumber = parseFloat(amountValue);
                    console.log('Processed Amount Number:', amountNumber);

                    // Ensure the amountNumber is a valid number
                    if (!isNaN(amountNumber)) {
                        $('#amount').val(amountNumber); // Set the processed amount value
                    } else {
                        console.error('Invalid number:', amountValue);
                    }

                    $('#office').val(rowData[3]);
                    $('#description1').val(rowData[5]);
                }
            });

            // Add event listener for the Edit button
            $('[name="save_data2"]').click(function(event) {
                event.preventDefault(); // Prevent form submission

                // Get the data from the input fields
                var srNo = $('#sr_no').val();
                var date = $('#date1').val();
                var quantity = $('#quantity').val();
                var activity = $('#activity').val();
                var amount = $('#amount').val();
                var requestor = $('#office').val();
                var description = $('#description1').val();
                var supplier = $('#supplierDropdown').val();
                console.log('SR No:', srNo);
                console.log('Date:', date);
                console.log('Quantity:', quantity);
                console.log('Description:', activity);
                console.log('Amount:', amount);
                console.log('Office:', requestor);
                console.log('Vehicle:', description);
                console.log('Supplier:', supplier);
                // Check if there's a selected row ID
                if (selectedRowIds4.length === 1) {
                    var selectedRowId4 = selectedRowIds4[0];
                    // Use AJAX to update the row in the database
                    $.ajax({
                        url: 'update_row_bq.php', // Change this to the URL of your PHP script that handles the update
                        type: 'POST',
                        data: {
                            id: selectedRowId4, // Pass the selected row ID
                            sr_no: srNo,
                            date: date,
                            quantity: quantity,
                            activity: activity,
                            amount: amount,
                            requestor: requestor,
                            description: description,
                            supplier: supplier
                        },
                        success: function(response) {
                            // Handle success response
                            alert('Row updated successfully.');

                            // Optionally update the row in the table to reflect the changes without reloading
                            var selectedRow = $('#table_tent1 tbody tr[data-rfq-id="' + selectedRowId4 + '"]');
                            selectedRow.find('td:eq(0)').text(srNo);
                            selectedRow.find('td:eq(1)').text(date);
                            selectedRow.find('td:eq(2)').text(supplier);
                            selectedRow.find('td:eq(6)').text(quantity);
                            selectedRow.find('td:eq(4)').text(activity);
                            selectedRow.find('td:eq(7)').text('₱' + parseFloat(amount).toFixed(2));
                            selectedRow.find('td:eq(3)').text(requestor);
                            selectedRow.find('td:eq(5)').text(description);
                        },
                        error: function() {
                            // Handle error response
                            alert('Failed to update row.');
                        }
                    });
                } else {
                    // If no row is selected, show an error message
                    alert('No row selected for update or multiple rows selected.');
                }
            });

            // Add event listener for the Delete button
            $('[name="delete_data"]').click(function(event) {
                event.preventDefault(); // Prevent form submission

                // Check if there are selected row IDs
                if (selectedRowIds4.length > 0) {
                    if (confirm('Are you sure you want to delete the selected rows?')) {
                        // Use AJAX to delete the rows in the database
                        $.ajax({
                            url: 'delete_row_bq.php', // Change this to the URL of your PHP script that handles the delete
                            type: 'POST',
                            data: {
                                ids: selectedRowIds4 // Pass the selected row IDs
                            },
                            success: function(response) {
                                // Handle success response
                                alert('Rows deleted successfully.');
                                // Clear input fields
                                clearInputFields();

                                // Optionally remove the rows from the table
                                selectedRowIds4.forEach(function(id) {
                                    $('#table_tent1 tbody tr[data-rfq-id="' + id + '"]').remove();
                                });
                                selectedRowIds4 = []; // Reset the selectedRowIds
                            },
                            error: function() {
                                // Handle error response
                                alert('Failed to delete rows.');
                            }
                        });
                    }
                } else {
                    // If no rows are selected, show an error message
                    alert('No rows selected for deletion.');
                }
            });
        });
    </script>

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
                    url: 'delete_print_bq.php',
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
                                url: 'refresh_table_bq.php', // Specify the URL to fetch the updated table content
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
    document.getElementById('delete_print_all').addEventListener('click', function() {
        if (confirm('Are you sure you want to delete all records?')) {
            // Send request to server to delete all records
            fetch('delete_all_print_BQ.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=delete_all'
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        alert('All records deleted successfully.');
                        location.reload(); // Reload the page to refresh the table
                    } else {
                        alert('Error deleting records.');
                    }
                })
                .catch(error => console.error('Error:', error));
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
                    url: 'delete_payment_bq.php',
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
            xhr.open("POST", "save_payment_bq.php"); // Replace "save_payment.php" with your server-side script URL
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
                url: 'refresh_payment_table_bq.php',
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
    $(document).ready(function() {
        // Trigger to open popup5 and fetch data
        $("#view_print").on("click", function() {
            // Show the popup immediately
            $("#popup5").show();

            // Introduce a delay of 1 second before fetching the data
            setTimeout(function() {
                // Fetch data from the server after 1 second
                $.ajax({
                    url: 'fetch_print_data_bq.php', // URL to fetch data
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        // Clear the existing table body
                        $("#table_print tbody").empty();

                        // Loop through the data and append rows to the table
                        data.forEach(function(row) {
                            $("#table_print tbody").append(`
                        <tr class="clickable-row3" data-rfq-id="${row.id}">
                            <td>${row.SR_DR}</td>
                            <td>${row.date}</td>
                            <td>${row.supplier}</td>
                            <td>${row.requestor}</td>
                            <td>${row.activity}</td>
                            <td>${row.description}</td>
                            <td>${row.quantity}</td>
                            <td>₱${parseFloat(row.amount).toFixed(2)}</td>
                            
                        </tr>
                    `);
                        });
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("Error fetching data:", textStatus, errorThrown);
                        alert("Failed to load data.");
                    }
                });
            }, 1000); // 1 second delay
        });

        // Close the popup
        $(".close-btn").on("click", function() {
            $("#popup5").hide();
        });
    });
</script>

</html>