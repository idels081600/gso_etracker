<?php
session_start();
require_once 'dbh.php'; // Assuming this file contains your database connection logic

$conn = mysqli_connect("localhost", "root", '', "my_data");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (!isset($_SESSION['username'])) {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Employee') {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Desk Clerk'||$_SESSION['role'] == 'TCWS Employee') {
    header("location:login_v2.php");
}
?>

<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

    <title>View Details</title>
    <style>
        @media screen and (max-width: 767px) {
            #btn_back {
                margin-left: 180px !important;
            }
        }

        body {
            background-color: #f0f0f0;
            /* Set the background color of the body */
        }

        #btn_back {
            margin-left: 880px;
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

        .button-row {
            display: flex;
            flex-direction: row;
            margin-top: 10px;

        }

        #declineButtonContainer {
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <a class="navbar-brand" href="index">
            <img src="logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">E-Pass</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="nav navbar-nav navbar-right">
                <li class="nav-item">
                    <a class="nav-link" href="index">Home <span class="sr-only">(current)</span></a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="add_req.php">Add Request</a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link" href="approved">Approved</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="decline">Declined Request</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="track_emp">Track Employees</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="register">Register</a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="qrcode_scanner.php">Scan QRcode</a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link" href="logout">Logout</a>
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
    <div class="container mt-5">
        <div class="p-6 rounded shadow">
            <div class="card">
                <div class="card-header">
                    <h4>Request Details
                        <a href="decline.php" id="btn_back" class="btn btn-danger float-end">Back</a>
                    </h4>
                </div>
                <div class="card-body">
                    <?php
                    if (isset($_GET['id'])) {
                        $data_id = mysqli_real_escape_string($conn, $_GET['id']);
                        $query = "SELECT * FROM request WHERE id='$data_id' ";
                        $query_run = mysqli_query($conn, $query);

                        if (mysqli_num_rows($query_run) > 0) {
                            $data = mysqli_fetch_array($query_run);
                            ?>
                            <form action="code.php" method="POST">
                                <div class="container">
                                    <input type="hidden" name="data_id" value="<?= $data['id']; ?>">
                                    <div class="mb-3">
                                        <label>Name:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['name']; ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label>Position:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['position']; ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label>Date:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['date']; ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label>Destination:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['destination']; ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label>Purpose:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['purpose']; ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label>Time of Departure:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['timedept']; ?>
                                        </p>
                                    </div>
                                    <div class="form-group">
                                        <label for="esttime">Estimated Time</label>
                                        <input type="time" class="form" id="esttime" name="esttime" min="09:00" max="18:00">
                                    </div>
                                    <div class="mb-3">
                                        <label>Type of Request:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['typeofbusiness']; ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label>Reason:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['reason']; ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-group">
                                            <label for="sel1">Status:</label>
                                            <select class="form" id="sel1" name='status'>
                                                <option>
                                                    <?= $data['Status']; ?>
                                                </option>
                                                <option>Approved</option>

                                            </select>
                                            <div id="reason" name="reasons" style="display: none;">
                                                <label for="decline_reason">Decline Reason:</label>
                                                <textarea id="decline_reason" name="decline_reason"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-group">
                                            <label for="sel2">Confirmed By:</label>
                                            <select class="form" id="sel2" name='confirmed_by'>
                                                <option>RUBY CASAS</option>
                                                <option>MA. EMILIA GALAURA</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="button-row">
                                        <div id="approveButtonContainer">
                                            <button type="submit" name="approve_req" class="btn btn-success">Approve</button>
                                        </div>

                                    </div>
                                </div>
                            </form>
                            <?php
                        } else {
                            echo "<h4>No Such Id Found</h4>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>




    </div>

    <script>
        // Add an event listener to the dropdown
        document.getElementById("sel1").addEventListener("change", function () {
            var selectedStatus = this.value;
            var reasonDiv = document.getElementById("reason");

            // Show the textarea if "Declined" is selected, otherwise hide it
            if (selectedStatus === "Declined") {
                reasonDiv.style.display = "block";
            } else {
                reasonDiv.style.display = "none";
            }
        });
    </script>
    <script>
        // Add an event listener to the dropdown
        document.getElementById("sel1").addEventListener("change", function () {
            var selectedStatus = this.value;
            var reasonDiv = document.getElementById("declineButtonContainer");

            // Show the textarea if "Declined" is selected, otherwise hide it
            if (selectedStatus === "Approved") {
                reasonDiv.style.display = "none";
            } else {
                reasonDiv.style.display = "block";
            }
        });
    </script>

    <script>
        // Add an event listener to the dropdown
        document.getElementById("sel1").addEventListener("change", function () {
            var selectedStatus = this.value;
            var approveButtonContainer = document.getElementById("approveButtonContainer");

            // Show or hide the "Approve" button based on the selected status
            if (selectedStatus === "Declined") {
                approveButtonContainer.style.display = "none";
            } else {
                approveButtonContainer.style.display = "block";
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"
        crossorigin="anonymous"></script>
</body>

</html>