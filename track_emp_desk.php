<?php

require_once 'dbh.php';
require_once 'functions.php';
$result = display_emp_status_r();
$data = display_total_pass_slip();
$total_count = $data['count'];
if (!isset($_SESSION['username'])) {
  header("location:login_v2.php");
  exit();
} else if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
  header("location:login_v2.php");
  exit();
}

if (isset($_POST['delete_all'])) {
  if ($_POST['confirm'] == 'yes') {
    // First backup the data
    $backup_sql = "INSERT INTO request_backup SELECT * FROM request WHERE DATE(date) = CURDATE() AND Role = 'Employee'";
    mysqli_query($conn, $backup_sql);

    $sql = "DELETE FROM request WHERE role = 'Employee'";
    if (mysqli_query($conn, $sql)) {
      $_SESSION['show_undo'] = true;
      header("Location: track_emp_r.php");
      exit(0);
    }
  }
}

// Handle undo action
if (isset($_POST['undo_delete'])) {
  $restore_sql = "INSERT INTO request 
  SELECT * FROM request_backup 
  WHERE DATE(date) = CURDATE() 
  AND Role = 'Employee' 
  ORDER BY id DESC";
  mysqli_query($conn, $restore_sql);
  mysqli_query($conn, "TRUNCATE TABLE request_backup");
  unset($_SESSION['show_undo']);
  header("Location: track_emp_r.php");
  exit(0);
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
  <style>
    @media screen and (max-width: 767px) {
      #my_label {
        font-size: 25px;
        margin-left: 97px;
        margin-bottom: 0px;
      }

      .container {
        width: 95%;
      }

      #btns {
        flex-direction: row;
        /* Stack the buttons vertically on smaller screens */
        justify-content: flex-end;
        /* Ensure buttons align to the right */
        margin-left: 0 !important;
        /* Remove fixed margin */
      }

      #btns a,
      #btns button {
        margin: 5px 0;
        /* Add margin for better spacing */
      }

      /* Adjust the font size for smaller screens */
      .table td,
      .table th {
        font-size: 14px;
        /* Adjust font size for better readability */
      }



    }

    .container {
      background-color: #fff;
      padding: 20px;
      border-radius: 5px;
      margin-top: 20px;
      box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
      max-width: 100%;
      /* Ensures the container doesn't overflow */
    }

    #btns {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      margin-left: auto;
      margin-right: 0;
      /* Align buttons to the right side */
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

    .container {
      background-color: #fff;
      padding: 20px;
      border-radius: 5px;
      margin-top: 20px;
      box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
    }

    .navbar-nav .nav-link {
      background-color: transparent !important;
    }

    .navbar-nav .nav-link:hover {
      background-color: transparent !important;
      color: #fff !important;
    }

    .table-container {
      overflow-x: auto;
      max-height: 640px;
      overflow-y: auto;
      position: relative;
    }

    .table thead th {
      white-space: nowrap;
      position: sticky;
      top: 0;
      background-color: #f8f9fa;
      z-index: 1;
      box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
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

  <div class="container">
    <div class="row">
      <div class="col-md-9">
        <h2 id="my_label">My Employees</h2>
        <h5 id="total_tally">Total Pass Slips: <?php echo $total_count; ?></h5>
      </div>
      <div class="col-md-3">
        <div class="input-group mb-4 mt-5" style="display: flex; align-items: left;">
          <div class="input-group-append" id="btns" style="margin-left: 0px; display: flex; align-items: center;">
            <a href="export_r.php" class="btn btn-success btn-sm" style="margin: 5px;">Export</a>
            <form method="post" action="" style="margin-right: 5px;">
              <button type="submit" name="delete_all" class="btn btn-danger btn-sm" onclick="confirmDelete()">Delete</button>
              <input type="hidden" name="confirm" id="confirm" value="no">
            </form>
            <form method="post" action="">
              <button type="submit" name="undo_delete" class="btn btn-warning btn-sm">Undo Delete</button>
            </form>
          </div>
        </div>
      </div>

    </div>

    <div class="p-5 rounded shadow">
      <div class="table-container table-responsive"> <!-- Added table-responsive class -->
        <table class="table table-hover" id="table">
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Destination</th>
              <th scope="col">Status</th>
              <th scope="col">Type of Business</th>
              <th scope="col">Remarks</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody id="showdata">
            <?php
            while ($row = mysqli_fetch_assoc($result)) {
              echo '<tr>';
              echo '<td>' . $row["name"] . '</td>';
              echo '<td>' . $row["destination"] . '</td>';
              echo '<td>' . $row["status1"] . '</td>';
              echo '<td>' . $row["typeofbusiness"] . '</td>';
              echo '<td>' . $row["remarks"] . '</td>';
              echo '<td><a href="view_track_emp_r.php?id=' . $row['id'] . '" class="btn btn-info btn-sm">View</a></td>';
              echo '</tr>';
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <script>
    function confirmDelete() {
      var confirmation = confirm("Are you sure you want to delete all data?");
      if (confirmation) {
        document.getElementById("confirm").value = "yes";
        document.querySelector("form").submit();
      }
    }

    function loadDoc() {
      setInterval(function() {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
          if (this.readyState == 4 && this.status == 200) {
            document.querySelector("#showdata").innerHTML = this.responseText;
          }
        };
        xhttp.open("GET", "live_track_r.php", true);
        xhttp.send();
      }, 1000);
    }

    loadDoc();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc6znpb3zx6V6zPC92Uuqs2CZf+hK/a3p8elgi1Mx" crossorigin="anonymous"></script>
</body>

</html>