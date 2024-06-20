<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <style>
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
            margin-left: auto;
            margin-right: auto;
        }

        .navbar-nav .nav-link {
            background-color: transparent !important;
        }

        .navbar-nav .nav-link:hover {
            background-color: transparent !important;
            color: #fff !important;
        }

        #submit {
            background-color: #4caf50;
            color: white;
            padding: 6px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10%;
        }

        #requestCameraPermission {
            background-color: #4caf50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10%;
        }

        #textrow {
            margin-top: 30px;
            text-align: center;
        }

        #texthead {
            font-size: 40px;
            text-align: center;
            margin-left: 15%;
        }

        #text {
            font-size: 80px;
            text-align: center;
            margin-top: 10%;
            margin-left: -20%;
        }

        #label {
            font-size: 30px;
            text-align: center;
            margin-top: 10px;
            margin-left: -10%;
        }

        #arrivalButton {
            background-color: #28a745;
            margin-left: 180px;
        }

        #departureButton {
            background-color: #dc3545;
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
                text-align: center;
                margin-left: 5%;
            }

            #text {
                font-size: 30px;
                margin-top: 10%;
                margin-left: 1%;
            }
        }
    </style>
</head>

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
                <li class="nav-item">
                    <a class="nav-link" href="declined_r.php">Declined Request</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="track_emp_r.php">Track Employees</a>
                </li>
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
            <h3 id="label">Scan your barcode here </h3>
            <input type="text" id="barcodeInput" placeholder="Scan barcode here" class="form-control" autofocus>
        </div>

        <div class="row" id="textrow">
            <a href="qrcode_scanner_dept_r.php">
                <button type="button" class="btn btn-primary mr-2" id="arrivalButton">In</button>
            </a>
            <a href="qrcode_scanner_desk_r.php">
                <button type="button" class="btn btn-primary" id="departureButton">Out</button>
            </a>
            <h2 id="texthead">Take Care</h2>
            <h1 name="text" id="text"></h1>
            <form method="post" action="">
                <!-- <button id="submit" name="approve_req_depart" >Submit</button> -->
            </form>
        </div>
        <!-- Error message container -->
        <div id="errorMessage" class="alert alert-danger" style="display: none;"></div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const barcodeInput = document.getElementById('barcodeInput');
            const errorMessage = document.getElementById('errorMessage');
            let lastScannedData = '';
            let timeoutId; // Variable to hold the timeout ID

            // Autofocus on the input field when the page loads
            barcodeInput.focus();

            // Listen for input event on the barcode input field
            barcodeInput.addEventListener('input', function(event) {
                const scannedData = event.target.value;
                // Avoid duplicating the scanned data
                if (scannedData !== lastScannedData) {
                    lastScannedData = scannedData;
                    clearTimeout(timeoutId); // Clear any existing timeout
                    timeoutId = setTimeout(function() {
                        checkScannedData(scannedData); // Call after debounce delay
                    }, 500); // Adjust debounce delay as needed (e.g., 500 milliseconds)
                }
            });

            function checkScannedData(scannedData) {
                // Hide error message before making the AJAX call
                errorMessage.style.display = 'none';

                // Use AJAX to check if the scanned data exists in the database
                $.ajax({
                    url: 'code.php', // Create a separate PHP file to handle the database check
                    type: 'POST',
                    data: {
                        scannedData: scannedData
                    },
                    success: function(response) {
                        console.log('Response from server:', response); // Debug log

                        if (response.trim() === 'exists') {
                            // Scanned data exists in the database, proceed with update and display
                            var successAudio = new Audio('success.mp3'); // Replace 'success.mp3' with the actual path to your success sound file
                            successAudio.play();
                            document.getElementById('text').textContent = scannedData;

                            // Play a success sound

                        } else {
                            // Play an error sound
                            var errorAudio = new Audio('error.wav'); // Replace 'error.wav' with the actual path to your error sound file
                            errorAudio.play();

                            // Display error message
                            errorMessage.textContent = 'Your request does not exist in the database.';
                            errorMessage.style.display = 'block';
                        }
                        // Clear the input field after scanning
                        barcodeInput.value = '';
                        // Autofocus back to the input field
                        barcodeInput.focus();
                    },
                    error: function() {
                        alert('Error checking scanned data');
                        // Clear the input field after error
                        barcodeInput.value = '';
                        // Autofocus back to the input field
                        barcodeInput.focus();
                    }
                });
            }
        });
    </script>

</body>

</html>