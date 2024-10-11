<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script type="text/javascript"
        src="https://cdnjs.cloudflare.com/ajax/libs/webrtc-adapter/3.3.3/adapter.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
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

    #submit {
        background-color: #4caf50;
        color: white;
        padding: 6px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        margin-left: 10%;
    }

    .square-video-container {
        position: relative;
        width: 100%;
        padding-bottom: 50%;
        overflow: hidden;
        margin-left: auto;
        margin-right: auto;
    }

    #preview {
        position: absolute;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .form-group {
        margin-top: 20px;
        text-align: center;
    }

    #textField {
        width: 100%;
        padding: 10px;
        font-size: 18px;
    }

    #texthead {
        margin-left: 10%;
    }

    #text {
        margin-left: 15%;
    }

    #errorMessage {
        color: red;
        font-weight: bold;
        text-align: center;
        display: none;
        margin-top: 10px;
    }

    @media screen and (max-width: 600px) {
        #preview {
            width: 100%;
            height: 100%;
        }
    }
</style>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <a class="navbar-brand" href="qrcode_scanner_dept.php">
            <img src="logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">E-Pass</span>
        </a>
    </nav>
    <div class="container">
        <div class="square-video-container">
            <h3 id="label">Place your QR code here</h3>
            <video id="preview"></video>
        </div>

        <!-- Text Field to display QR Code value -->
        <div class="form-group">
            <label for="textField">Scanned or Input QR Code:</label>
            <input type="text" id="textField" class="form-control">
        </div>

        <div class="row" id="textrow">
            <h2 id="texthead">Welcome Back!</h2>
            <h1 name="text" id="text"></h1>
        </div>
    </div>
    <div id="errorMessage">Your Request does not exist in the database.</div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let scanner = new Instascan.Scanner({
                video: document.getElementById('preview')
            });

            Instascan.Camera.getCameras().then(function(cameras) {
                if (cameras.length > 0) {
                    scanner.start(cameras[0]);
                } else {
                    displayError("No cameras found");
                }
            }).catch(function(e) {
                console.error(e);
                displayError("Error initializing the camera");
            });

            scanner.addListener('scan', function(scannedData) {
                document.getElementById('textField').value = scannedData;
                document.getElementById('text').textContent = scannedData;

                var audio = new Audio('qrcode.mp3');
                audio.play();

                checkQRCode(scannedData);
            });

            document.getElementById('textField').addEventListener('input', function() {
                let inputValue = this.value;
                document.getElementById('text').textContent = inputValue;
                checkQRCode(inputValue);
            });

            function checkQRCode(qrCode) {
                $.ajax({
                    url: 'code_dept.php',
                    type: 'POST',
                    data: {
                        scannedData: qrCode
                    },
                    success: function(response) {
                        if (response !== 'exists') {
                            // Show error message and clear input
                            displayError('Your Request does not exist in the database');
                            document.getElementById('textField').value = '';
                            document.getElementById('text').textContent = '';
                        }
                    },
                    error: function() {
                        displayError('Error checking scanned data');
                    }
                });
            }

            function displayError(message) {
                var errorMessage = document.getElementById('errorMessage');
                errorMessage.textContent = message;
                errorMessage.style.display = 'block';

                // Hide the error message after 3 seconds
                setTimeout(function() {
                    errorMessage.style.display = 'none';
                }, 3000);
            }
        });
    </script>

</body>

</html>