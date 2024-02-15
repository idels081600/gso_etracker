<?php

require_once 'dbh.php';
require_once 'functions.php';
$result = display_data_declined();

if (!isset($_SESSION['username'])) {
  header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Admin') {
  header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Employee') {
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
  <title>Decline Request</title>
  <style>
    @media screen and (max-width: 767px) {
      #pen_label {
        font-size: 25px;
        font-size: 25px;
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
    <a class="navbar-brand" href="index_desk.php">
      <img src="logo.png" alt="Logo" class="logo-img">
      <span class="logo-text">E-Pass Slip </span>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="nav navbar-nav navbar-right">
        <li class="nav-item">
          <a class="nav-link" href="index_desk.php">Home <span class="sr-only">(current)</span></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="add_req_desk.php">Add Request</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="approved_desk.php">Approved</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="decline_desk.php">Declined Request</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="qrcode_scanner_desk.php">Scan QrCode</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="login_v2.php">Logout</a>
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
        xhttp.open("GET", "data_declined_desk.php", true);
        xhttp.send();

      }, 1000);


    }
    loadDoc();
  </script>
  <div class="container">
    <h2 id="pen_label">Declined Request</h2>
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
              <td> <a href="view_decline_desk.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm">View</a></td>
            </tr>

            <?php
            }
            ?>
        </table>
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