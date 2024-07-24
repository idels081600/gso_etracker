<?php
require_once 'dbh.php';
require_once 'functions.php';
$result = display_users();

session_start();
if (!isset($_SESSION['username'])) {
    header("location:login_v2.php");
    exit;
} else if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
    header("location:login_v2.php");
    exit;
}

if (isset($_POST['register_user'])) {
    $username = $_POST['username'];
    $position = $_POST['position'];
    $password = $_POST['password'];
    $name = $_POST['name'];
    $role = $_POST['role'];

    // Hash the password before storing it in the database
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = "INSERT INTO `logindb` (`username`, `password`, `name`, `role`,`Position`) VALUES ('$username', '$hashed_password', '$name', '$role','$position')";
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
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <strong>Unsuccessful!</strong> Please try again.
        </div>
<?php
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
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
            }

            #left-column {
                width: 80% !important;
                max-height: 490px;
                margin-left: 44px !important;
                margin-right: 49px !important;
                background-color: #fff !important;
                border-radius: 5px !important;
                margin-top: 20px !important;
                box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5) !important;
                margin-bottom: 20px !important;
            }

            #right-column {
                margin-top: 20px !important;
                width: 90% !important;
                margin: 0 auto 0 20px !important;
                padding: 20px !important;
                max-height: 600px;
            }

            #input-fields {
                height: 100% !important;
                width: 200px;
            }
        }

        body {
            background-color: #f0f0f0;
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

        #left-column {
            float: left;
            width: 30%;
            margin-left: 43px;
            margin-right: 40px;
            padding: 20px;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
        }

        #right-column {
            background-color: white;
            float: left;
            width: 60%;
            padding: 20px;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <a class="navbar-brand" href="index.php">
            <img src="logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">E-Pass</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="nav navbar-nav navbar-right">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home <span class="sr-only">(current)</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="approved.php">Approved</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="decline.php">Declined Request</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="track_emp.php">Track Employees</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="register.php">Register</a>
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
                            <input type="text" class="form-control" id="position" placeholder="Username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="Position">Position:</label>
                            <input type="text" class="form-control" id="position" placeholder="Position" name="position" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" id="position" placeholder="Password" name="password" required>
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
                                    <option>SAP</option>
                                    <option>ASSET</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success" name="register_user">Submit</button>
                    </form>
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
                    <?php
                    while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                        <tr>
                            <td><?php echo $row["Id"]; ?></td>
                            <td><?php echo $row["username"]; ?></td>
                            <td><?php echo $row["password"]; ?></td>
                            <td><?php echo $row["name"]; ?></td>
                            <td><?php echo $row["role"]; ?></td>
                            <td>
                                <form action="code.php" method="post">
                                    <input type="hidden" name="id" value="<?php echo $row['Id'] ?>">
                                    <input type="submit" name="delete" class="btn btn-danger" value="delete">
                                </form>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>

</html>