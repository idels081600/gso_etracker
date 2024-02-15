<?php
// Include your database connection code here
include 'dbh.php';
session_start();
if (!isset($_SESSION['username'])) {
    header("location:login_v2.php");
} else if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Department Head') {
    header("location:login_v2.php");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/webrtc-adapter/3.3.3/adapter.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.1.10/vue.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</head>

<style>
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
        margin-left: auto;
        margin-right: auto;
    }

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

    #submit {
        background-color: #4caf50;
        /* Green background color */
        color: white;
        /* White text color */
        padding: 6px 20px;
        /* Padding for better appearance */
        border: none;
        /* No border */
        border-radius: 5px;
        /* Rounded corners */
        cursor: pointer;
        /* Cursor on hover */
        margin-left: 10%;
    }

    #requestCameraPermission {
        background-color: #4caf50;
        /* Green background color */
        color: white;
        /* White text color */
        padding: 10px 20px;
        /* Padding for better appearance */
        border: none;
        /* No border */
        border-radius: 5px;
        /* Rounded corners */
        cursor: pointer;
        /* Cursor on hover */
        margin-top: 10%;
    }

    #textrow {
        margin-top: 30px;
        text-align: center;
    }

    #texthead {
        font-size: 40px;
        /* Adjust font size as needed */
        text-align: center;
        margin-left: 15%;
    }

    #text {
        font-size: 80px;
        text-align: center;
        margin-top: 10%;
        margin-left: -20%;
    }

    .square-video-container {
        position: relative;
        width: 90%;
        padding-bottom: 50%;
        overflow: hidden;
        margin-left: auto;
        margin-right: auto;
    }

    #preview {
        position: absolute;
        width: 70%;
        height: 70%;
        object-fit: cover;
        margin-left: 8%;
    }

    #label {
        font-size: 30px;
        text-align: center;
        margin-top: 10px;
        margin-left: -10%;
        /* Add or adjust margin-top as needed */
    }

    #arrivalButton {
        background-color: #28a745;
        margin-left: 180px;
    }

    #departureButton {
        background-color: #dc3545;
        /* Blue */
    }

    @media screen and (max-width: 600px) {
        #textrow {
            margin-top: 10px;
        }

        #label {
            font-size: 18px;
            text-align: center;
            margin-top: 10px;
        }

        #texthead {
            font-size: 20px;
            /* Adjust font size as needed */
            text-align: center;
            margin-left: 5%;
        }

        #text {
            font-size: 30px;
            margin-top: 10%;
            margin-left: 1%;
        }

        .square-video-container {
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }

        #preview {
            width: 90%;
            height: 90%;
            margin-left: 1%;
        }


    }
</style>


<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <a class="navbar-brand" href="index_r.php">
            <img src="logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">E-Pass</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
    <div class="container">
        <div class="col-md-8 square-video-container">
            <h3 id="label">Place your QR here </h3>
            <video id="preview"></video>

        </div>

        <div class="row" id="textrow">
            <a href="qrcode_scanner_dept_r.php">
                <button type="button" class="btn btn-primary mr-2" id="arrivalButton">In</button>
            </a>
            <a href="qrcode_scanner_desk_r.php">
                <button type="button" class="btn btn-primary" id="departureButton">Out</button>
            </a>
            <h2 id="texthead">Take Care,</h2>
            <h1 name="text" id="text"></h1>
            <form method="post" action="">
                <!-- <button id="submit" name="approve_req_depart" >Submit</button> -->
            </form>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let scanner = new Instascan.Scanner({
                video: document.getElementById('preview')
            });

            function requestCameraPermission() {
                navigator.mediaDevices.getUserMedia({
                        video: true
                    })
                    .then(function(stream) {
                        scanner.start(stream);
                    })
                    .catch(function(error) {
                        console.error('Camera access denied:', error);
                    });
            }

            Instascan.Camera.getCameras().then(function(cameras) {
                if (cameras.length > 0) {
                    scanner.start(cameras[0]);
                } else {
                    alert('No cameras found');
                }
            }).catch(function(e) {
                console.error(e);
            });

            // Listen for form submission
            document.querySelector('form').addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent the default form submission
            });

            scanner.addListener('scan', function(c) {
                // Check if the scanned data exists in the database
                checkScannedData(c);
            });

            function checkScannedData(scannedData) {
                // You can use AJAX to check if the scanned data exists in the database
                // I'll provide a simplified example using jQuery for this purpose

                $.ajax({
                    url: 'code.php', // Create a separate PHP file to handle the database check
                    type: 'POST',
                    data: {
                        scannedData: scannedData
                    },
                    success: function(response) {
                        if (response === 'exists') {
                            // Scanned data exists in the database, proceed with update and display
                            document.getElementById('text').textContent = scannedData;

                            // Play a sound
                            var audio = new Audio('Success.mp3'); // Replace 'path/to/sound.mp3' with the actual path to your sound file
                            audio.play();
                        } else {
                            var audio = new Audio('error.wav'); // Replace 'path/to/sound.mp3' with the actual path to your sound file
                            audio.play();
                            setTimeout(function() {
                                alert('Your Request does not exist in the database');
                            }, 100);
                        }
                    },
                    error: function() {
                        alert('Error checking scanned data');
                    }
                });
            }
        });
    </script>
</body>

</html>