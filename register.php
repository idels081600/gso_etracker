<?php
require_once 'dbh.php';
require_once 'functions.php';
$result = display_users();
if (!isset($_SESSION['username'])) {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Employee') {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
    header("location:login_v2.php");
}
?>
<!DOCTYPE html>
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
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <title>Register</title>
    <style>
        @media screen and (max-width: 767px) {

            #my_label {
                font-size: 25px;
                margin-left: 97px;

            }

            .card {
                height: 430px;
                width: 240px;
                margin: 0 auto 0 20px !important;
                /* Adjust the margin as needed */
            }

            #left-column {

                width: 80% !important;

                max-height: 490px;
                /* Adjust the maximum height as needed */


                margin-left: 44px !important;
                margin-right: 49px !important;

                background-color: #fff !important;
                /* Set the background color for the container */

                border-radius: 5px !important;
                /* Add rounded corners */
                margin-top: 20px !important;
                /* Add some space from the top */
                box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5) !important;
                /* Add a shadow to the container */
                margin-bottom: 20px !important;
            }

            #right-column {
                margin-top: 20px !important;
                width: 90% !important;
                margin: 0 auto 0 20px !important;
                /* Adjust the margin as needed */
                padding: 20px !important;
                max-height: 600px;
                /* Adjust the maximum height as needed */


            }

            #input-fields {
                height: 100% !important;
                width: 200px;

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

        /* Style for the left column containing the "Add User" form */
        #left-column {
            float: left;
            width: 30%;
            margin-left: 43px;
            margin-right: 40px;
            padding: 20px;
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

        /* Style for the right column containing the table */
        #right-column {
            background-color: white;
            float: left;
            width: 60%;
            padding: 20px;
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
</head>

<body>
    <!-- <script type="text/javascript">
        function loadDoc() {
            setInterval(function () {
                var xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function () {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("table").innerHTML = this.responseText;

                    }
                };
                xhttp.open("GET", "data_users.php", true);
                xhttp.send();
            }, 1000);
        }
        loadDoc();

    </script> -->
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

    <?php
    $conn = mysqli_connect("localhost", "bryanmysql", 'gsotagbilaran', "my_data");
    if (isset($_POST['register_user'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $name = $_POST['name'];
        $role = $_POST['role'];

        $query = "INSERT INTO `logindb` (`username`, `password`, `name`, `role`) VALUES ('$username', '$password', '$name', '$role')";
        $query_run = mysqli_query($conn, $query);
        if ($query_run) {
            ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <strong>Success!</strong> Account added!.
            </div>
            <?php
        } else {
            ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <strong>Unsuccesful!</strong> Please try again.
            </div>
            <?php
        }
    }
    ?>

    <div class="container" id="left-column">
        <div class="p-3 rounded shadow">
            <div class="card">
                <div class="card-body" id="input-fields">
                    <h2 class="card-title">Add User</h2>
                    <form action="register.php" method="POST">
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" class="form-control" id="name" placeholder="Name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" class="form-control" id="position" placeholder="Username" name="username"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="text" class="form-control" id="position" placeholder="Password" name="password"
                                required>
                        </div>
                        <div class="mb-3">
                            <div class="form-group">
                                <label for="sel1">Role:</label>
                                <select class="form-control" id="sel1" name="role">
                                    <option>Admin</option>
                                    <option>Admin2</option>
                                    <option>Employee</option>
                                    <option>TCWS Employee</option>
                                    <option>TCWS Scanner</option>
                                    <option>Desk Clerk</option>
                                    <option>Division Head</option>
                                    <option>TCWS Division Head</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success" name="register_user">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
    <div class="container" id="right-column">
        <div class="p-5 rounded shadow">
            <div class="table-responsive">
                <table class="table .table-hover" id="table">
                    <tr>
                        <th scope="col">Id</th>
                        <th scope="col">Username</th>
                        <th scope="col">Password</th>
                        <th scope="col">Name</th>
                        <th scope="col">Role</th>
                        <th scope="col">Action</th>
                    </tr>
                    <tr>
                        <?php
                        while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                            <td>
                                <?php echo $row["Id"]; ?>
                            </td>
                            <td>
                                <?php echo $row["username"]; ?>
                            </td>
                            <td>
                                <?php echo $row["password"]; ?>
                            </td>
                            <td>
                                <?php echo $row["name"]; ?>
                            </td>
                            <td>
                                <?php echo $row["role"]; ?>
                            </td>
                            <form action="code.php" method="post">
                                <input type="hidden" name="id" value="<?php echo $row['Id'] ?>">
                                <td><input type="submit" name="delete" class="btn btn-danger" value="delete"></td>
                            </form>
                        </tr>

                        <?php
                        }
                        ?>
                </table>
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