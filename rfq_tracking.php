<?php
// Include database connection
include 'db_asset.php';
if (!isset($_SESSION['username'])) {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}
if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}
$query = "SELECT * FROM RFQ ";
$result1 = mysqli_query($conn, $query);

if (!$result1) {
    die("Query failed: " . mysqli_error($conn));
}


if ($_SERVER["REQUEST_METHOD"] == "POST"  && isset($_POST['save_data'])) {
    $rfqno = mysqli_real_escape_string($conn, $_POST['rfqno']);
    $prno = mysqli_real_escape_string($conn, $_POST['prno']);
    $rfqname = mysqli_real_escape_string($conn, $_POST['rfqname']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $requestor = mysqli_real_escape_string($conn, $_POST['requestor']);
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
    $date = date('Y-m-d'); // Current date and time

    // Insert data into the database
    $sql = "INSERT INTO RFQ (rfq_no, pr_no, rfq_name, date,amount, requestor, supplier, Status) 
            VALUES ('$rfqno', '$prno', '$rfqname', '$date','$amount', '$requestor', '$supplier', 'Office Clerk')";

    if (mysqli_query($conn, $sql)) {
        header("Location: rfq_tracking.php");
        exit();
    } else {
        header("Location: rfq_tracking.php");
        exit();
    }

    // Close the connection
    mysqli_close($conn);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customizable Sidebar</title>
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="tracking_rfq_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- jQuery library -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- jQuery UI library -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

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

</head>

<body>

    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Office Clerk</span>
            <span class="user-name1">Poliquit Estelito Jr.</span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="dashboard_asset_tracker.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li class="dropdown">
                <a href="#"><i class="fas fa-map icon-size"></i> Tracking <i class="fas fa-chevron-down dropdown-icon"></i></a>
                <ul class="dropdown-menu">
                    <li><a href="tracking.php">Tent</a></li>
                    <li><a href="transpo.php">Transportation</a></li>
                    <li><a href="pay_track.php">Payables</a></li>
                    <li><a href="rfq_tracking.php">RFQ</a></li>
                </ul>
            </li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>

        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="content">

        <h1>RFQ Tracking</h1>
        <div class="row">
            <div class="container2">
                <h1 class="name1">Office Clerk</h1>
                <div class="add_container">
                    <div class="popup3" id="popup3">
                        <div class="close-btn">&times;</div>
                        <form class="form1" action="" method="POST">
                            <h1 id="label_modal1">RFQ Details</h1>
                            <div class="form-element">
                                <div class="form-element1">
                                    <label for="rfqno" id="rfqno-label">RFQ No.</label>
                                    <input type="text" id="rfqno" name="rfqno" placeholder="" required>
                                </div>
                            </div>
                            <div class="form-element">
                                <div class="form-element2">
                                    <label for="prno" id="prno-label">PR No.</label>
                                    <input type="text" id="prno" name="prno" placeholder="" required>
                                </div>
                            </div>
                            <div class="form-element">
                                <div class="form-element3">
                                    <label for="rfqname" id="rfqname-label">RFQ Name</label>
                                    <input type="text" id="rfqname" name="rfqname" placeholder="" required>
                                </div>
                            </div>
                            <div class="form-element">
                                <div class="form-element4">
                                    <label for="amount" id="amount-label">Amount</label>
                                    <input type="text" id="amount" name="amount" placeholder="" required>
                                </div>
                            </div>
                            <div class="form-element">
                                <div class="form-element5">
                                    <label for="requestor" id="requestor-label">Requestor</label>
                                    <input type="text" id="requestor" name="requestor" placeholder="" required>
                                </div>
                            </div>
                            <div class="form-element">
                                <div class="form-element6">
                                    <label for="supplier" id="supplier-label">Supplier</label>
                                    <input type="text" id="supplier" name="supplier" placeholder="" required>
                                </div>
                            </div>
                            <button class="button-39" role="button" name="save_data">Submit</button>
                        </form>
                    </div>
                </div>
                <div class="search-container">
                    <input type="text" id="search-input2" placeholder="Search..." onkeyup="filterStatus()">
                </div>
                <!-- <button class="button-2" id="lessThanButton" role="button">&lt;</button> -->
                <button class="button-3" id="addButton" role="button">Add</button>
                <button class="button-8" id="EditButton" role="button">Edit</button>
                <button class="button-7" id="DeleteButton" role="button">Delete</button>
                <!-- <button class="button-4" id="greaterThanButton" role="button">&gt;</button> -->
                <div class="table-wrapper">
                    <div class="table-scroll">
                        <table class="custom-table" id="custom-table2">
                            <thead>
                                <tr>
                                    <th>RFQ No.</th>
                                    <th>PR No.</th>
                                    <th>RFQ Name</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Requestor</th>
                                    <th>Supplier</th>
                                    <th style="width: 150px;">Status</th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result1)) { ?>
                                    <tr class="clickable-row" data-rfq-id="<?php echo $row["id"]; ?>">
                                        <td data-column="rfq_no"><?php echo $row["rfq_no"]; ?></td>
                                        <td data-column="pr_no"><?php echo $row["pr_no"]; ?></td>
                                        <td data-column="rfq_name"><?php echo $row["rfq_name"]; ?></td>
                                        <td data-column="date"><?php echo $row["date"]; ?></td>
                                        <td data-column="amount"><?php echo $row["amount"]; ?></td>
                                        <td data-column="requestor"><?php echo $row["requestor"]; ?></td>
                                        <td data-column="supplier"><?php echo $row["supplier"]; ?></td>
                                        <td data-column="Status" class="status-column">
                                            <select onchange="updateStatus(this)">
                                                <option value="<?php echo $row["Status"]; ?>"><?php echo $row["Status"]; ?></option>
                                                <option value="Office Clerk">Office Clerk</option>
                                                <option value="SAP">SAP</option>
                                                <option value="CGSO-Head">CGSO-Head</option>
                                            </select>
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

    <div class="overlay"></div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let selectedRfqIds = [];
            let selectedRow = null;
            let originalData = {};
            $('.clickable-row').on('click', function() {
                // Toggle the selected class on the clicked row
                $(this).toggleClass('selected');

                // Get the RFQ ID of the clicked row
                let rfqId = $(this).data('rfq-id');

                // If the row is selected, add its RFQ ID to the array; otherwise, remove it
                if ($(this).hasClass('selected')) {
                    selectedRfqIds.push(rfqId);
                } else {
                    selectedRfqIds = selectedRfqIds.filter(id => id !== rfqId);
                }

                // Set the selected row
                selectedRow = $(this);
            });


            $('#DeleteButton').on('click', function() {
                if (selectedRfqIds.length > 0) {
                    if (confirm('Are you sure you want to delete the selected rows?')) {
                        $.ajax({
                            url: 'delete_rfq.php',
                            type: 'POST',
                            data: {
                                rfq_ids: selectedRfqIds
                            },
                            success: function(response) {
                                if (response === 'success') {
                                    selectedRfqIds.forEach(id => {
                                        $(`.clickable-row[data-rfq-id=${id}]`).remove();
                                    });
                                    selectedRfqIds = [];
                                    alert('Selected rows deleted successfully.');
                                    location.reload();
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

            $('#EditButton').on('click', function() {
                if (selectedRow) {
                    // Store original data
                    originalData = {};
                    $(selectedRow).find('td').each(function() {
                        const text = $(this).text();
                        const columnName = $(this).data('column');
                        originalData[columnName] = text;
                        $(this).html(`<input type="text" value="${text}" data-column="${columnName}" />`);
                    });
                    // Focus on the first input field
                    $(selectedRow).find('input').first().focus();
                } else {
                    alert('Please select a row to edit.');
                }
            });

            // Handle Enter key press to save changes
            $(document).on('keydown', function(event) {
                if (selectedRow) {
                    if (event.key === 'Enter') {
                        event.preventDefault();

                        const updatedData = {};
                        $(selectedRow).find('td').each(function() {
                            const input = $(this).find('input');
                            if (input.length > 0) {
                                const value = input.val();
                                $(this).text(value); // Replace input with the new text
                                const columnName = input.data('column');
                                updatedData[columnName] = value;
                            }
                        });

                        const rfqId = $(selectedRow).data('rfq-id');
                        updatedData.id = rfqId;

                        // Send updated data to the server
                        $.ajax({
                            url: 'edit_rfq.php',
                            type: 'POST',
                            data: updatedData,
                            success: function(response) {
                                alert('Row updated successfully.');
                                selectedRow = null; // Clear the selected row
                                location.reload();
                            },
                            error: function() {
                                alert('Failed to update the row.');
                            }
                        });
                    } else if (event.key === 'Escape') {
                        // Cancel editing and revert to original data
                        $(selectedRow).find('td').each(function() {
                            const columnName = $(this).data('column');
                            if (originalData[columnName] !== undefined) {
                                $(this).text(originalData[columnName]);
                            }
                        });
                        selectedRow = null; // Clear the selected row
                    }
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get all input fields
            var inputFields = document.querySelectorAll('.form-element input');

            // Add event listener to each input field
            inputFields.forEach(function(input, index) {
                input.addEventListener('keyup', function(event) {
                    if (event.keyCode === 13) { // Check if Enter key is pressed
                        event.preventDefault(); // Prevent default form submission behavior
                        var nextIndex = index + 1;
                        if (nextIndex < inputFields.length) {
                            inputFields[nextIndex].focus(); // Focus on the next input field
                        }
                    }
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to close the popup
            function closePopup() {
                document.querySelector(".popup3").classList.remove("active");
                document.querySelector(".overlay").style.display = "none"; // Hide the overlay
            }

            // Event listener for the Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    // Check if the popup is active
                    if (document.querySelector(".popup3").classList.contains("active")) {
                        closePopup(); // Close the popup
                    }
                }
            });

            // Event listener for the add button
            document.querySelector("#addButton").addEventListener("click", function() {
                document.querySelector(".popup3").classList.add("active");
                document.querySelector(".overlay").style.display = "block"; // Show the overlay
            });

            // Event listener for the close button
            document.querySelector(".popup3 .close-btn").addEventListener("click", function() {
                closePopup(); // Close the popup
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            // Search function for Office Clerk table
            $('#search-input2').on('input', function() {
                var filter = $(this).val().trim().toLowerCase(); // Trim and convert filter text to lowercase
                $('#custom-table2 tbody tr').each(function() {
                    var row = $(this);
                    var rowVisible = false;
                    row.find('td').each(function() {
                        var cellText = $(this).text().trim().toLowerCase(); // Trim and convert cell text to lowercase
                        if (cellText.indexOf(filter) > -1) {
                            rowVisible = true;
                            return false; // Break the loop as we found a match
                        }
                    });
                    row.toggle(rowVisible);
                });
            });
        });

        function filterStatus() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("search-input2");
            filter = input.value.toUpperCase();
            table = document.querySelector("table tbody");
            tr = table.getElementsByTagName("tr");

            for (i = 0; i < tr.length; i++) {
                td = tr[i].getElementsByClassName("status-column")[0];
                if (td) {
                    var select = td.getElementsByTagName("select")[0];
                    txtValue = select.options[select.selectedIndex].text.toUpperCase(); // Convert to uppercase for comparison
                    if (txtValue.indexOf(filter) > -1) {
                        tr[i].style.display = ""; // Show the row if it matches the filter
                    } else {
                        tr[i].style.display = "none"; // Hide the row if it doesn't match the filter
                    }
                }
            }
        }
    </script>


    <script>
        function updateStatus(select) {
            var newValue = select.value;
            var rowId = select.closest('tr').getAttribute('data-rfq-id'); // Assuming 'data-rfq-id' attribute holds the row ID

            $.ajax({
                url: 'update_rfq_status1.php', // Replace 'update_status.php' with the file to handle the update on the server
                method: 'POST',
                data: {
                    newValue: newValue,
                    rowId: rowId
                },
                success: function(response) {
                    console.log(response); // You can handle success response here
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText); // Handle error here
                }
            });
        }
    </script>

</html>