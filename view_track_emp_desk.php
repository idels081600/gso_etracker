<?php
session_start();
require_once 'dbh.php'; // Assuming this file contains your database connection logic




if (!isset($_SESSION['username'])) {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'TCWS Employee') {
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
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
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <a class="navbar-brand" href="index_r.php">
            <img src="logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">E-Pass Slip </span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="nav navbar-nav navbar-right">
                <li class="nav-item">
                    <a class="nav-link" href="index_desk.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="track_emp_desk.php">Track Employees</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="qrcode_scanner_desk.php">Scanner</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <style>
        .navbar-nav .nav-link {
            background-color: transparent !important;
        }

        /* Change the color of the text on hover */
        .navbar-nav .nav-link:hover {
            background-color: transparent !important;
            color: #fff !important;
            /* Change the color to your desired hover color */
        }

        .form-control-static {
            margin-left: 10px;
            /* Adjust this value to control the spacing between label and value */
            display: inline-block;
        }

        label {
            margin-bottom: 5px;
            /* Space between the label and the value */
            font-weight: bold;
            /* Make labels bold for better readability */
        }

        .container .mb-3 {
            margin-bottom: 15px;
            /* Adjust the space between different form groups */
            display: flex;
            align-items: center;
        }

        .container .mb-3 label {
            width: 150px;
            /* Fixed width for labels */
            margin-right: 20px;
            /* Space between label and value */
        }

        .container .mb-3 p.form-control-static {
            flex-grow: 1;
            /* Take up the remaining space */
            margin-bottom: 0;
            /* Remove any default margin */
        }

        .container {
            height: 550px;
        }
    </style>

    <div class="container mt-5">
        <div class="p-6 rounded shadow">
            <div class="card">
                <div class="card-header">
                    <h4>Request Details
                        <a href="track_emp_desk.php" id="btn_back" class="btn btn-danger float-end">Back</a>
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
                                            <?php echo $data['dest2']; ?>
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
                                            <?php echo date('g:i A', strtotime($data['timedept'])); ?>
                                        </p>
                                    </div>

                                    <div class="mb-3">
                                        <label>Estimated Time:</label>
                                        <p class="form-control-static">
                                            <?php echo date('g:i A', strtotime($data['esttime'])); ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label>Type of Request:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['typeofbusiness']; ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label for="time_ret">Time of Actual Return: </label>
                                        <p class="form-control-static">
                                            <?php echo date('g:i A', strtotime($data['time_returned'])); ?>

                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label>Confirmed By:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['confirmed_by']; ?>
                                        </p>
                                    </div>
                                    <div class="mb-3">
                                        <label>Status:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['Status']; ?>
                                        </p>
                                    </div>

                                    <div class="mb-3">
                                        <label>Remarks:</label>
                                        <p class="form-control-static">
                                            <?php echo $data['remarks']; ?>
                                        </p>
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>

</html>