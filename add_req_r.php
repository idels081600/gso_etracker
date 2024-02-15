<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Admin') {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Desk Clerk') {
    header("location:login_v2.php");
}
$conn = mysqli_connect("localhost", "root", '', "my_data");

if (isset($_POST['save_data2'])) {
    $name = $_POST['name'];
    $position = $_POST['position'];
    $date = date('Y-m-d', strtotime($_POST['date']));
    $destination = $_POST['destination'];
    $purpose = $_POST['purpose'];
    $role = $_SESSION['role'];
    $typeofbusiness = $_POST['typeofbusiness'];

    $query = "INSERT INTO request(name, position, date, destination, purpose, typeofbusiness, time_returned, Status, status1, dest2, ImageName, Role) VALUES ('$name', '$position', '$date', '$destination', '$purpose', '$typeofbusiness', '00:00:00', 'Pending', 'Waiting For Pass Slip Approval', '$destination', 'pending.png', '$role')";
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        require_once 'send_notification.php';
        header("Location: index_emp.php");
        exit(); // Make sure to exit after a header redirect
    } else {
        echo '<div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <strong>Error!</strong> Please try again.
            </div>';
    }
}
?>


<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <title>Add Request</title>
    <style>
        @media screen and (max-width: 767px) {

            .container {
                height: 70%;
                width: 95%;
            }
        }

        body {
            background-color: #f0f0f0;
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
        }


        .logo-img {
            border-radius: 50%;
            width: 50px;
            height: 50px;
            object-fit: cover;
        }


        .logo-text {
            color: white;
            font-weight: bold;
            font-size: 20px;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <script src="script.js"></script>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <a class="navbar-brand" href="index_r.php">
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
                    <a class="nav-link" href="index_r.php">Home <span class="sr-only">(current)</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add_req_r.php">Add Request</a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="approved_tcws.php">Approved</a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link" href="declined_r.php">Declined Request</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="track_emp_r.php">Track Employees</a>
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
                    <a class="nav-link" href="qrcode_scanner_desk_r.php">Scanner</a>
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


        .navbar-nav .nav-link:hover {
            background-color: transparent !important;
            color: #fff !important;
        }
    </style>

    <?php


    ?>
    <div class="container mt-5">
        <div class="p-5 rounded shadow">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">Add Request</h2>
                    <form action="add_req_emp.php" method="POST">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" class="form-control" id="name" placeholder="Name" name="name"
                                value="<?php echo $_SESSION['username']; ?>" readonly required>
                        </div>

                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" class="form-control" id="position" placeholder="Position" name="position"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" class="form-control" id="date" name="date"
                                value="<?php echo date('Y-m-d'); ?>" required readonly>
                        </div>

                        <div class="form-group">
                            <label for="destination">Destination</label>
                            <input type="text" class="form-control" id="destination" placeholder="Destination"
                                name="destination" required>
                        </div>
                        <div class="form-group">
                            <label for="purpose">Purpose</label>
                            <input type="text" class="form-control" id="purpose" placeholder="Purpose" name="purpose"
                                required>
                        </div>
                        <!-- <div class="form-group">
                    <label for="timedept">Time of Departure</label>
                    <input type="time" class="form-control" id="timedept" name="timedept" min="09:00" max="18:00" required>
                </div> -->
                        <div class="mb-3">
                            <div class="form-group">
                                <label for="sel1">Type of Business:</label>
                                <select class="form-control" id="sel1" name='typeofbusiness'>
                                    <option>Personal</option>
                                    <option>Official Business</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success" name="save_data2">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"
        crossorigin="anonymous"></script>
</body>

</html>