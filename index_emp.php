<?php
require_once 'dbh.php';
require_once 'functions.php';
$result = display_data_emp();

if (!isset($_SESSION['username'])) {
  header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Admin') {
  header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Desk Clerk') {
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
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
  <script src="https://www.gstatic.com/firebasejs/7.14.6/firebase-app.js"></script>
  <script src="https://www.gstatic.com/firebasejs/7.14.6/firebase-messaging.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"
    integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm"
    crossorigin="anonymous"></script>
  <title>Home</title>
  <style>
    @media screen and (min-width: 321px) and (max-width: 767px) {
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

      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        background: #e3edf7;
        height: 100vh;
        /* display: flex;
            align-items: center;
            justify-content: center; */
      }

      #status {
        text-align: center;
        margin-top: 20px;
        font-size: 20px;
        color: #999090;
        position: fixed;
        top: 55%;
        /* Adjust to center vertically */
        left: 50%;
        transform: translate(-50%, 10%);
        width: 100%;
      }

      #icon {
        width: 120px;
        /* Set the desired width */
        height: 120px;
        /* Set the desired height */
        margin: auto;
        /* Center horizontally */
        position: absolute;
        /* Position absolutely within the parent */
        top: 40%;
        /* Center vertically */
        left: 50%;
        /* Center horizontally */
        transform: translate(-50%, -50%);
        /* Center both horizontally and vertically */
      }

    }

    @media screen and (min-width: 200px) and (max-width: 320px) {
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

      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        background: #e3edf7;
        height: 100vh;
        /* display: flex;
            align-items: center;
            justify-content: center; */
      }

      #status {
        text-align: center;
        margin-top: 17px;
        font-size: 20px;
        color: #999090;
        position: fixed;
        top: 30%;
        /* Adjust to center vertically */
        left: 50%;
        transform: translate(-50%, -10%);
        width: 100%;
      }

      #icon {
        width: 140px;
        /* Set the desired width */
        height: 140px;
        /* Set the desired height */
        margin: auto;
        /* Center horizontally */
        position: absolute;
        /* Position absolutely within the parent */
        top: 45%;
        /* Center vertically */
        left: 50%;
        /* Center horizontally */
        transform: translate(-50%, -50%);
        /* Center both horizontally and vertically */
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

    #icon {
      width: 230px;
      /* Set the desired width */
      height: 230px;
      /* Set the desired height */
      margin: auto;
      /* Center horizontally */
      position: absolute;
      /* Position absolutely within the parent */
      top: 36%;
      /* Center vertically */
      left: 50%;
      /* Center horizontally */
      transform: translate(-50%, -50%);
      /* Center both horizontally and vertically */
    }

    #status {
      text-align: center;
      margin-top: 120px;
      font-size: 26px;
      color: #999090;
      position: fixed;
      top: 55%;
      /* Adjust to center vertically */
      left: 50%;
      transform: translate(-50%, -110%);
      width: 100%;
      color: black;
    }
  </style>
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
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <a class="navbar-brand" href="index_emp.php">
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
          <a class="nav-link" href="index_emp.php">Home <span class="sr-only">(current)</span></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="add_req_emp.php">Add Request</a>
        </li>
        <!-- <li class="nav-item">
                    <a class="nav-link" href="approved_emp.php">Approved</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="decline_emp.php">Declined Request</a>
                </li> -->
        <li class="nav-item">
          <a class="nav-link" href="login_v2.php">Logout</a>
        </li>
      </ul>
    </div>
  </nav>
  <script>
    // Function to load status from data_emp.php
    function loadStatus() {
      var xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
          document.getElementById("status").innerHTML = this.responseText;
        }
      };
      xhttp.open("GET", "data_emp.php", true);
      xhttp.send();
    }

    // Function to update image from update.php
    function updateImage() {
      $.ajax({
        url: 'update.php',
        type: 'GET',
        dataType: 'json',
        success: function (data) {
          if (data.success) {
            $('#icon').attr('src', data.image);
          } else {
            console.error(data.message);
          }
        },
        error: function (xhr, status, error) {
          console.error('AJAX Error:', status, error);
        }
      });
    }

    // Initial update
    loadStatus();
    updateImage();

    // Set intervals for periodic updates
    setInterval(loadStatus, 1000); // Status update every 5 seconds
    setInterval(updateImage, 10000); // Image update every 10 seconds
  </script>
  <div id="icons">
    <img id="icon" src="pending.png" alt="Logo" class="check-img">
  </div>
  <div id="status">
    <?php
    $row = mysqli_fetch_assoc($result);
    if ($row) {
      echo $row["Status"];
      echo '<br>';
    } else {
      echo "No REQUEST.";
    }
    ?>
  </div>
  <div class="text">
    Note: Please Scan Your QR to Complete The Process <br>
    Personal Request will show After 9:30 Am and 1:30 Pm
  </div>
</body>

</html>