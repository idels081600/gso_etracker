<?php
require_once 'dbh.php';
require_once 'functions.php';
$result = display_data_tcws();
if (!isset($_SESSION['username'])) {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}

// Check the user role and perform additional redirection if needed
if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Desk Clerk'||$_SESSION['role'] == 'TCWS Employee' ) {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.14.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.4.0/firebase-messaging-compat.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <title>Home</title>
</head>
<style>
    @media screen and (max-width: 767px) {
        #getName {
            margin-left: 160px;
        }

        #pen_label {
            font-size: 25px;
            font-size: 25px;
            margin-left: 97px;
            margin-left: 80px !important;
        }

        .container {
            height: 70%;
            width: 95%;
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
        <a class="navbar-brand" href="index_tcws.php">
            <img src="logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">E-Pass </span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="nav navbar-nav navbar-right">
                <li class="nav-item">
                    <a class="nav-link" href="index_tcws.php">Home <span class="sr-only">(current)</span></a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="add_req.php">Add Request</a>
                </li> -->
                <!-- <li class="nav-item">
                    <a class="nav-link" href="approved_tcws.php">Approved</a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link" href="declined_tcws.php">Declined Request</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="track_emp_tcws.php">Track Employees</a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="register.php">Register</a>
                </li> -->
                <!-- <li class="nav-item">
                    <a class="nav-link" href="qrcode_scanner.php">Scan QRcode</a>
                </li> -->
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

    <script type="text/javascript">
        function loadDoc() {
            setInterval(function () {
                var xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function () {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("table").innerHTML = this.responseText;
                    }
                };
                xhttp.open("GET", "data_tcws.php", true);
                xhttp.send();
            }, 1000);
        }
        loadDoc();

    </script>
    <div class="container">
        <h2 id="pen_label">Pending Request</h2>
        <div class="p-5 rounded shadow">
            <div class="table-responsive">
                <table class="table .table-hover" id="table">
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Position</th>
                        <th scope="col">Destination</th>
                        <th scope="col">Type of Request</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                    <tr>
                        <?php
                        while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                            <td>
                                <?php echo $row["name"]; ?>
                            </td>
                            <td>
                                <?php echo $row["position"]; ?>
                            </td>
                            <td>
                                <?php echo $row["destination"]; ?>
                            </td>
                            <td>
                                <?php echo $row["typeofbusiness"]; ?>
                            </td>
                            <td>
                                <?php echo $row["Status"]; ?>
                            </td>
                            <td> <a href="view_tcws.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm">View</a></td>
                        </tr>
                        <?php
                        }
                        ?>
                </table>
            </div>
        </div>

    </div>
        <script>
        var firebaseConfig = {
            apiKey: "AIzaSyBdJEBddNuHGPyYW_NQ3D8VFpeQdfXOS2M",
            authDomain: "push-notification-4469d.firebaseapp.com",
            databaseURL: "https://push-notification-4469d-default-rtdb.asia-southeast1.firebasedatabase.app",
            projectId: "push-notification-4469d",
            storageBucket: "push-notification-4469d.appspot.com",
            messagingSenderId: "3251430231",
            appId: "1:3251430231:web:aea52a61992765cf511412",
            measurementId: "G-V236DTMQ4E"
        };
        firebase.initializeApp(firebaseConfig);

        // Get FCM token
        firebase.messaging().getToken().then((token) => {
            console.log("FCM Token:", token);

            // Send the token to your server using jQuery AJAX
            $.ajax({
                url: 'store_token.php',
                type: 'POST',
                data: { token: token },
                success: function (response) {
                    console.log('Token stored successfully:', response);
                },
                error: function () {
                    console.error('Error storing token');
                }
            });
        }).catch((error) => {
            console.error("Error getting FCM token:", error);
        });
    </script>
</body>

</html>