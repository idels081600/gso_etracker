<?php

require_once 'dbh.php';
require_once 'functions.php';
$result = display_emp_status_cviraa();
if (!isset($_SESSION['username'])) {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Employee') {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
    header("location:login_v2.php");
}
if (isset($_POST['delete_all'])) {
    // Define the SQL query to delete all data from a specific table
    if ($_POST['confirm'] == 'yes') {
        // Define the SQL query to delete all data from a specific table
        $sql = "DELETE FROM request WHERE Role = 'CVIRAA'"; // Replace 'your_table_name' with the actual table name

        // Execute the query
        if (mysqli_query($conn, $sql)) {

            header("Location: track_emp_cviraa.php");
            exit(0);
        } else {

            header("Location: track_emp_cviraa.php");
            exit(0);
        }
    } else {
        header("Location: track_emp_cviraa.php");
        exit(0);
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="https://www.gstatic.com/firebasejs/7.14.6/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/7.14.6/firebase-messaging.js"></script>
    <title>Employees</title>
</head>
<style>
    @media screen and (max-width: 767px) {

        #my_label {
            font-size: 25px;
            margin-left: 97px;
            margin-left: 100px !important;
            margin-bottom: 0px;

        }

        .container {
            height: 70%;
            width: 95%;
        }

        #btns {
            margin-left: 240px !important;
            flex-direction: row;
            /* Stack items vertically */
            align-items: left;
            padding: 0px;
            margin-bottom: -20px !important;
        }

        /* #getName{
        margin-left: 70px !important; 
  } */
        #search-input {
            flex: 1;
            width: 150px;
            padding: 5px;
            border: 1.5px solid #8f8d8d;
            border-radius: 20px;
            font-size: 16px;
            margin-left: -240px;
            margin-right: 20px;
            /* margin-top: 10%; */
            /* margin-bottom: 10px; */
            transition: border-color 0.3s ease;
            /* Add transition for smoother effect */
        }

    }

    body {
        background-color: #f0f0f0;
        /* Set the background color of the body */
    }

    .navbar-brand {
        display: flex;
        align-items: center;
    }

    /* Style for the logo image */
    .logo-img {
        border-radius: 50%;
        width: 50px;
        height: 50px;
        object-fit: cover;
    }

    /* Style for the "E-Pass Slip" text */
    .logo-text {
        color: white;
        font-weight: bold;
        font-size: 20px;
        margin-left: 10px;
        /* Add some spacing between the logo and text */
    }

    .container {
        background-color: #fff;
        /* Set the background color for the container */
        padding: 20px;
        /* Add some padding to the container */
        border-radius: 5px;
        /* Add rounded corners */
        margin-top: 20px;
        /* Add some space from the top */
        box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
        /* Add a shadow to the container */
    }
</style>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <a class="navbar-brand" href="index_cviraa.php">
            <img src="logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">E-Pass </span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="nav navbar-nav navbar-right">
                <li class="nav-item">
                    <a class="nav-link" href="index_cviraa.php">Home <span class="sr-only">(current)</span></a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="add_req_r.php">Add Request</a>
                </li> -->
                <!-- <li class="nav-item">
                    <a class="nav-link" href="approved_tcws.php">Approved</a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link" href="declined_cviraa.php">Declined Request</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="track_emp_cviraa.php">Track Employees</a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="register.php">Register</a>
                </li> -->
                <!-- <li class="nav-item">
                    <a class="nav-link" href="qrcode_scanner.php">Scan QRcode</a>
                </li> -->
                <!-- <li class="nav-item">
                    <a class="nav-link" href="qrcode_scanner_dept_r.php">Arrival</a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link" href="qrcode_scanner_desk_cviraa.php">Scanner</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <style>
        /* Remove the white box on hover */
        .navbar-nav .nav-link {
            background-color: transparent !important;
        }

        /* Change the color of the text on hover */
        .navbar-nav .nav-link:hover {
            background-color: transparent !important;
            color: #fff !important;
            /* Change the color to your desired hover color */
        }
    </style>
    <div class="container">
        <div class="row">
            <div class="col-md-9">
                <h2 id="my_label">My Employees</h2>
            </div>
            <div class="col-md-3">
                <div class="input-group mb-4 mt-5" style="display: flex; align-items: left;">
                    <div class="form-outline">
                        <div class="input-group-append" id="btns" style="margin-left: 100px; display: flex; align-items: center;">
                            <div class="search-container">
                                <input type="text" id="search-input" placeholder="Search...">
                                <!-- <button id="search-button"><i class="fas fa-search"></i></button> -->
                            </div>
                            <!-- Export Button -->
                            <a href="export_cviraa.php" class="btn btn-success btn-sm" style="margin: 5px;">Export</a>
                            <!-- Delete Form -->
                            <form method="post" action="">
                                <button type="submit" name="delete_all" class="btn btn-danger btn-sm" onclick="confirmDelete()">Delete</button>
                                <input type="hidden" name="confirm" id="confirm" value="no">
                                <input type="submit" name="delete" style="display: none;">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </style>
        <!-- <script type="text/javascript">
            function loadDoc() {
                setInterval(function() {
                    var xhttp = new XMLHttpRequest();
                    xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            document.getElementById("table").innerHTML = this.responseText;
                        }
                    };
                    xhttp.open("GET", "live_track_cviraa.php", true);
                    xhttp.send();

                }, 1000);


            }
            loadDoc();
        </script> -->
        <div class="p-5 rounded shadow">
            <div class="table-responsive">
                <table class="table .table-hover" id="table">

                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Destination</th>
                        <th scope="col">Status</th>
                        <th scope="col">Type of Business</th>
                        <th scope="col">Remarks</th>
                        <th scope="col">Action</th>


                    </tr>
                    <tr>
                        <tbody id="showdata">
                            <?php
                            while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                <td>
                                    <?php echo $row["name"]; ?>
                                </td>
                                <td>
                                    <?php echo $row["destination"]; ?>
                                </td>
                                <td>
                                    <?php echo $row["status1"]; ?>
                                </td>
                                <td>
                                    <?php echo $row["typeofbusiness"]; ?>
                                </td>
                                <td>
                                    <?php echo $row["remarks"]; ?>
                                </td>
                                <td> <a href="view_track_emp_cviraa.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm">View</a></td>

                    </tr>

                <?php
                            }
                ?>
                </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // $(document).ready(function () {
        //   // Function to refresh the table with original data
        //   function refreshTable() {
        //     $.ajax({
        //       method: 'GET', // Use GET to retrieve the original data
        //       url: 'refreshdata2.php', // Create a new PHP file for this purpose
        //       success: function (response) {
        //         $("#showdata").html(response);
        //       }
        //     });
        //   }


        //   refreshTable();

        // $('#getName').on("keyup", function () {
        //   var getName = $(this).val();
        //   $.ajax({
        //     method: 'POST',
        //     url: 'searchajax2.php',
        //     data: { name: getName },
        //     success: function (response) {
        //       $("#showdata").html(response);
        //     }
        //   });


        //   if (getName === "") {
        //     refreshTable();
        //   }
        // });
        // });
    </script>
    <script>
        function confirmDelete() {
            var confirmation = confirm("Are you sure you want to delete all data?");
            if (confirmation) {
                document.getElementById("confirm").value = "yes";
                // Submit the form to delete data
                document.querySelector("form").submit();
            }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the input field and table
            var input = document.getElementById('search-input');
            var table = document.getElementById('table');
            var rows = table.getElementsByTagName('tr');

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
                    if (rowVisible) {
                        rows[i].style.display = ''; // Show the row
                    } else {
                        rows[i].style.display = 'none'; // Hide the row
                    }
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>

</html>