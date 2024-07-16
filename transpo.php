<?php

require_once 'db_asset.php'; // Assuming this file contains your database connection code
require_once 'display_data_asset.php';
$result = display_data_transpo();
$Plate = display_data_vehicle();
$Drivers = display_data_driver();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_data'])) {
    $plate_no = mysqli_real_escape_string($conn, $_POST['plate_no']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $driver = mysqli_real_escape_string($conn, $_POST['driver']);
    $date = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_POST['datepicker'])));
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
    $location = "";

    // Check if "other" input is not empty
    if (!empty($_POST['other'])) {
        $location = mysqli_real_escape_string($conn, $_POST['other']); // Use "other" input value
    } else {
        // "other" input is empty, use the value from the dropdown
        $location = mysqli_real_escape_string($conn, $_POST['Location']);
    }

    $query = "INSERT INTO Transportation(Plate_no, Date, Vehicle, Driver, Purpose, Location, Status, Status1) 
              VALUES ('$plate_no', '$date','$name', '$driver', '$purpose', '$location', 'Stand By','Stand By')";

    // Execute the query
    $query_run = mysqli_query($conn, $query);
    if ($query_run) {

        header("Location: transpo.php");
        exit();
    } else {

        header("Location: transpo.php");
        exit();
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_vehicle'])) {
    $plate_no = mysqli_real_escape_string($conn, $_POST['plate_no1']);
    $vehicle = mysqli_real_escape_string($conn, $_POST['type_vehicle']);

    $query = "INSERT INTO Vehicle(Plate_no, Name, Status) 
              VALUES ('$plate_no', '$vehicle','Stand By')";

    // Execute the query
    $query_run = mysqli_query($conn, $query);
    if ($query_run) {

        header("Location: transpo.php");
        exit();
    } else {

        header("Location: transpo.php");
        exit();
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_driver'])) {
    $name = mysqli_real_escape_string($conn, $_POST['driver1']);
    $query = "INSERT INTO Drivers(Name) 
              VALUES ('$name')";

    // Execute the query
    $query_run = mysqli_query($conn, $query);
    if ($query_run) {

        header("Location: transpo.php");
        exit();
    } else {

        header("Location: transpo.php");
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customizable Sidebar</title>
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="transpo.css">
    <!-- <link rel="stylesheet" href="style_box.css"> -->
    <!-- <link rel="stylesheet" href="test.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.1.10/vue.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
    <!-- jQuery -->


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get all dropdown list items
            var dropdowns = document.querySelectorAll('.dropdown');

            // Loop through each dropdown list item
            dropdowns.forEach(function(dropdown) {
                // Add click event listener to toggle the dropdown menu
                dropdown.addEventListener('click', function(event) {
                    // Toggle the 'active' class on the dropdown menu
                    this.querySelector('.dropdown-menu').classList.toggle('active');
                    this.classList.toggle('open'); // Toggle 'open' class on the dropdown item
                });
            });

            // Close dropdown menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.dropdown')) {
                    var activeDropdowns = document.querySelectorAll('.dropdown-menu.active');
                    activeDropdowns.forEach(function(activeDropdown) {
                        activeDropdown.classList.remove('active');
                        activeDropdown.closest('.dropdown').classList.remove('open'); // Remove 'open' class
                    });
                }
            });


        });
    </script>

</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name">Rene Art Cagulada</span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="dashboard_asset_tracker.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li class="dropdown">
                <a href="#"><i class="fas fa-map icon-size"></i> Tracking <i class="fas fa-chevron-down dropdown-icon"></i></a>
                <ul class="dropdown-menu">
                    <li><a href="tracking.php">Tent</a></li>
                    <li><a href="transpo.php">Transportation</a></li>
                    <li><a href="pay_track.php">Payables</a></li>
                    <li><a href="rfq_tracking.php">RFQ</a></li>
                </ul>
            </li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="content">

        <h1>Transportation</h1>
        <?php if (isset($_SESSION['toastMsg'])) { ?>
            <div id="showMsg"> <?= $_SESSION['toastMsg']; ?></div>
        <?php } ?>
        <div class="container_table">
            <div class="column">
                <!-- <button class="button-3" role="button">Scanner</button> -->
                <button class="button-2" id="addButton" role="button">Add</button>
                <button class="button-4" id="Scanner" role="button">Scanner</button>
                <button class="button-3" id="addVehicle" role="button">Add Data</button>
                <!-- <div class="dropdown_menu">
                    <select class="menu" id="sel1" name='typeofbusiness'>
                        <option>Tent</option>
                        <option>Transportation</option>
                    </select>
                </div> -->
                <input type="text" id="search-input" placeholder="Search...">
            </div>

            <div class="container_table_content">
                <div class="popup_container">
                    <div class="popup">
                        <div class="close-btn">&times;</div>
                        <form class="form" action="" method="POST">
                            <h1 id="label_modal">Transportation Details</h1>
                            <div class="form-element-tent_no">
                                <label for="plate_no" id="plate_no-label">Plate No.</label>
                                <select id="plate_no" name="plate_no" required>
                                    <option value="" disabled selected>Select Plate.No</option> <!-- Initial blank option -->
                                    <?php
                                    foreach ($Plate as $plate) {
                                        echo '<option value="' . $plate['Plate_No'] . '">' . $plate['Plate_No'] . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>


                            <div class="form-element-datepicker">
                                <label for="datepicker" id="datepicker-label">Date</label>
                                <input type="text" id="datepicker" placeholder="Select a date" name="datepicker" required>
                            </div>

                            <div class="form-element-name">
                                <label for="name" id="name-label">Type of Vehicle</label>
                                <select id="name" name="name" required>
                                    <option value="" disabled selected>Select Type</option>
                                </select>
                            </div>


                            <!-- <div class="form-element-contact">
                                <label for="driver" id="driver-label">Driver</label>
                                <input type="text" id="driver" placeholder="" name="driver" required>
                            </div> -->
                            <div class="form-element-contact">
                                <label for="driver" id="driver-label">Driver</label>
                                <select id="driver" name="driver" required>
                                    <option value="" disabled selected>Select Driver</option> <!-- Initial blank option -->
                                    <?php
                                    foreach ($Drivers as $drivers) {
                                        echo '<option value="' . $drivers['Name'] . '">' . $drivers['Name'] . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-element-purpose">
                                <label for="purpose" id="purpose-label">Purpose</label>
                                <select id="purpose" name="purpose" required>
                                    <option value="">Select Purpose</option>
                                    <option value="Burial Services">Burial Services</option>
                                    <option value="Office Services">Office Services</option>
                                    <option value="Cargo Services">Cargo Services</option>
                                    <option value="Other Services">Other Services</option>
                                    <option value="Travel Services">Travel Services</option>
                                </select>
                            </div>


                            <!-- <div class="form-element-Purpose">
                            <label for="Purpose">Purpose</label>
                            <input type="text" id="Purpose" placeholder="">
                        </div>  -->

                            <div class="form-element-Location">
                                <label for="Location" id="Location-label">Location</label>
                                <select id="Location" name="Location">
                                    <option value="">Select Location</option>
                                    <option value="Bool">Bool</option>
                                    <option value="Booy">Booy</option>
                                    <option value="Cabawan">Cabawan</option>
                                    <option value="Cogon">Cogon</option>
                                    <option value="Dao">Dao</option>
                                    <option value="Dampas">Dampas</option>
                                    <option value="Manga">Manga</option>
                                    <option value="Mansasa">Mansasa</option>
                                    <option value="Poblacion I">Poblacion I</option>
                                    <option value="Poblacion II">Poblacion II</option>
                                    <option value="Poblacion III">Poblacion III</option>
                                    <option value="San Isidro">San Isidro</option>
                                    <option value="Taloto">Taloto</option>
                                    <option value="Tiptip">Tiptip</option>
                                    <option value="Ubujan">Ubujan</option>
                                    <option value="Outside Tagbilaran">Outside Tagbilaran</option>
                                    <option value="Within Tagbilaran">Within Tagbilaran</option>
                                </select>
                            </div>
                            <!-- <div class="form-element-other">
                                <label for="other" id="other-label">Specify Location (outside Tagbilaran)</label>
                                <input type="text" id="other" placeholder="" name="other">
                            </div> -->
                            <button class="button-39" role="button" name="save_data">Submit</button>
                        </form>
                    </div>
                </div>

                <div class="container_table_content">
                    <div class="popup_container">
                        <div class="popup1">
                            <div class="close-btn">&times;</div>
                            <form class="form" action="" method="POST">
                                <h1 id="label_modal">Add Details</h1>
                                <div class="form-element-tent_no">
                                    <label for="plate_no1" id="plate_no_label">Plate No.</label>
                                    <input type="text" id="plate_no1" placeholder="" name="plate_no1">
                                </div>
                                <!-- 
                                <div class="form-element-datepicker">
                                    <label for="datepicker" id="datepicker-label">Date</label>
                                    <input type="text" id="datepicker" placeholder="Select a date" name="datepicker" required>
                                </div> -->

                                <div class="form-element-name">
                                    <label for="type_vehicle" id="type_vehicle_label">Type of Vehicle</label>
                                    <input type="text" id="type_vehicle" placeholder="" name="type_vehicle">
                                </div>

                                <div class="form-element-contact">
                                    <label for="driver1" id="driver1-label">Driver</label>
                                    <input type="text" id="driver1" placeholder="" name="driver1">
                                </div>
                                <!-- <div class="form-element-purpose">
                                    <label for="purpose" id="purpose-label">Purpose</label>
                                    <select id="purpose" name="purpose" required>
                                        <option value="">Select Purpose</option>
                                        <option value="Burial Services">Burial Services</option>
                                        <option value="Office Services">Office Services</option>
                                        <option value="Cargo Services">Cargo Services</option>
                                        <option value="Other Services">Other Services</option>
                                        <option value="Travel Services">Travel Services</option>
                                    </select>
                                </div> -->


                                <!-- <div class="form-element-Purpose">
                            <label for="Purpose">Purpose</label>
                            <input type="text" id="Purpose" placeholder="">
                        </div>  -->

                                <!-- <div class="form-element-other">
                                    <label for="other" id="other-label">Specify Location (outside Tagbilaran)</label>
                                    <input type="text" id="other" placeholder="" name="other">
                                </div> -->
                                <button class="button-37" role="button" name="save_vehicle">Add Vehicle</button>
                                <button class="button-38" role="button" name="save_driver">Add Driver</button>
                            </form>
                        </div>
                    </div>
                    <div class="scanner_container">
                        <div class="popup3" id="popup3">
                            <div class="close-btn">&times;</div>
                            <div class="form1">
                                <h1 id="label_modal1">Scan Qr Code</h1>
                                <video id="preview"></video>
                                <h1 name="text" id="text"></h1>
                            </div>
                        </div>
                    </div>
                    <div class="table-container">
                        <table id="table_tent" class="table_tent">
                            <thead>
                                <tr>
                                    <th>Plate No.</th>
                                    <th>Date</th>
                                    <th>Type of Vehicle</th>
                                    <th>Driver</th>
                                    <th>Purpose</th>
                                    <th>Location</th>
                                    <th>Departure</th>
                                    <th>Arrival</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                    <tr>
                                        <td><?php echo $row["Plate_no"]; ?></td>
                                        <td><?php echo $row["Date"]; ?></td>
                                        <td><?php echo $row["Vehicle"]; ?></td>
                                        <td><?php echo $row["Driver"]; ?></td>
                                        <td><?php echo $row["Purpose"]; ?></td>
                                        <td><?php echo $row["Location"]; ?></td>
                                        <td><?php echo $row["Departure"]; ?></td>
                                        <td><?php echo $row["Arrival"]; ?></td>
                                        <td><?php echo $row["Status"]; ?></td>
                                    </tr>
                                <?php } ?>

                            </tbody>

                        </table>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
        <script>
            // Fetch the select elements
            var plateSelect = document.getElementById('plate_no');
            var nameSelect = document.getElementById('name');

            // Add event listener to plate number select element
            plateSelect.addEventListener('change', function() {
                var selectedPlate = this.value;
                // Send an AJAX request to fetch the name of the vehicle based on the selected plate number
                $.ajax({
                    url: 'fetch_vehicle_name.php', // Your PHP script to fetch vehicle name based on plate number
                    type: 'POST',
                    data: {
                        plate_no: selectedPlate
                    },
                    success: function(response) {
                        // Clear previous options
                        nameSelect.innerHTML = '';
                        // Add fetched name as an option
                        nameSelect.innerHTML += '<option value="' + response + '" selected>' + response + '</option>';
                    },
                    error: function() {
                        console.log('Error fetching vehicle name');
                    }
                });
            });
        </script>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                let scanner;

                // Function to start the scanner
                function startScanner() {
                    Instascan.Camera.getCameras().then(function(cameras) {
                        if (cameras.length > 0) {
                            scanner = new Instascan.Scanner({
                                video: document.getElementById('preview')
                            });
                            scanner.addListener('scan', function(c) {
                                // Check if the scanned data exists in the database
                                checkScannedData(c);
                            });
                            scanner.start(cameras[0]);
                        } else {
                            alert('No cameras found');
                        }
                    }).catch(function(e) {
                        console.error(e);
                    });
                }

                // Function to handle camera permission request
                function requestCameraPermission() {
                    navigator.mediaDevices.getUserMedia({
                        video: true
                    }).then(function(stream) {
                        scanner.start(stream);
                    }).catch(function(error) {
                        console.error('Camera access denied:', error);
                    });
                }

                // Start the scanner when the scanner button is clicked
                document.getElementById('Scanner').addEventListener('click', function() {
                    if (!scanner) {
                        startScanner();
                    } else {
                        requestCameraPermission();
                    }
                });

                // Function to close the active popup and stop the scanner
                function closeActivePopup() {
                    var activePopup = document.querySelector(".popup3.active");
                    if (activePopup) {
                        // Stop the scanner
                        if (scanner && typeof scanner.stop === "function") {
                            scanner.stop();
                        }

                        // Stop the video stream
                        const video = document.getElementById('preview');
                        if (video && video.srcObject) {
                            const stream = video.srcObject;
                            const tracks = stream.getTracks();
                            tracks.forEach(function(track) {
                                track.stop();
                            });
                            video.srcObject = null;
                        }

                        // Remove active class and hide overlay
                        activePopup.classList.remove("active");
                        document.querySelector(".overlay").style.display = "none";
                    }
                }

                // Add event listener for the Escape key
                document.addEventListener('keydown', function(event) {
                    if (event.key === "Escape") { // Check if Escape key is pressed
                        closeActivePopup(); // Close the active popup
                    }
                });

                // Add event listener to the overlay to close popups when clicked outside
                document.querySelector(".overlay").addEventListener("click", function() {
                    closeActivePopup(); // Close the active popup
                });

                // Add event listener to the close buttons in popups
                document.querySelectorAll(".close-btn").forEach(function(closeBtn) {
                    closeBtn.addEventListener("click", function() {
                        closeActivePopup(); // Close the active popup
                    });
                });

                // Function to handle form submission
                function handleFormSubmission() {
                    // Stop the scanner
                    if (scanner && typeof scanner.stop === "function") {
                        scanner.stop();
                    }
                }

                // Listen for form submission
                document.querySelector('form').addEventListener('submit', function(e) {
                    // Call function to handle form submission
                    handleFormSubmission();
                });

                // Function to handle scanned data
                function checkScannedData(scannedData) {
                    $.ajax({
                        url: 'scan_data.php',
                        type: 'POST',
                        data: {
                            scannedData: scannedData
                        },
                        success: function(response) {
                            if (response === 'exists' || response === 'Arrived') {
                                document.getElementById('text').textContent = scannedData;
                                var audio = new Audio('success.mp3');
                                audio.play();
                            } else {
                                var audio = new Audio('error.wav');
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


        <script>
            // Function to initialize datepicker
            $(function() {
                $("#datepicker").datepicker();
            });
        </script>
        <script>
            // Function to initialize datepicker
            $(function() {
                $("#datepicker1").datepicker();
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Function to close the popup
                function closePopup() {
                    document.querySelector(".popup").classList.remove("active");
                    document.querySelector(".overlay").style.display = "none"; // Hide the overlay
                }

                // Event listener for the Escape key
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        // Check if the popup is active
                        if (document.querySelector(".popup").classList.contains("active")) {
                            closePopup(); // Close the popup
                        }
                    }
                });

                // Event listener for the add button
                document.querySelector("#addButton").addEventListener("click", function() {
                    document.querySelector(".popup").classList.add("active");
                    document.querySelector(".overlay").style.display = "block"; // Show the overlay
                });

                // Event listener for the close button
                document.querySelector(".popup .close-btn").addEventListener("click", function() {
                    closePopup(); // Close the popup
                });
            });
        </script>
        <script>
            document.querySelector("#addVehicle").addEventListener("click", function() {
                document.querySelector(".popup1").classList.add("active");
                document.querySelector(".overlay").style.display = "block"; // Show the overlay
            });

            document.querySelector(".popup1 .close-btn").addEventListener("click", function() {
                document.querySelector(".popup1").classList.remove("active");
                document.querySelector(".overlay").style.display = "none"; // Hide the overlay
            });
        </script>
        <script>
            document.querySelector("#Scanner").addEventListener("click", function() {
                document.querySelector(".popup3").classList.add("active");
                document.querySelector(".overlay").style.display = "block"; // Show the overlay
            });

            document.querySelector(".popup3 .close-btn").addEventListener("click", function() {
                document.querySelector(".popup3").classList.remove("active");
                document.querySelector(".overlay").style.display = "none"; // Hide the overlay
            });
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Get all input fields
                var inputFields = document.querySelectorAll('.form input[type="text"]');

                // Add event listener to each input field
                inputFields.forEach(function(input, index) {
                    input.addEventListener('keydown', function(event) {
                        if (event.keyCode === 13) { // Check if Enter key is pressed
                            event.preventDefault(); // Prevent default form submission behavior
                            var nextIndex = index + 1;
                            if (nextIndex < inputFields.length) {
                                inputFields[nextIndex].focus(); // Focus on the next input field
                            }
                        }
                    });
                });
            });
        </script>
        <script>
            document.querySelector(".popup .close-btn").addEventListener("click", function() {
                var inputs = document.querySelectorAll('.popup .form input[type="text"]');
                inputs.forEach(function(input) {
                    input.value = ''; // Clear the value of each input field
                });
                document.querySelector(".popup").classList.remove("active"); // Hide the modal
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Function to close the active popup
                function closeActivePopup() {
                    var activePopup = document.querySelector(".popup1.active");
                    if (activePopup) {
                        activePopup.classList.remove("active");
                        document.querySelector(".overlay").style.display = "none"; // Hide the overlay
                    }
                }

                // Add event listener for the Escape key
                document.addEventListener('keydown', function(event) {
                    if (event.key === "Escape") { // Check if Escape key is pressed
                        closeActivePopup(); // Close the active popup
                    }
                });

                // Add event listener to the overlay to close popups when clicked outside
                document.querySelector(".overlay").addEventListener("click", function() {
                    closeActivePopup(); // Close the active popup
                });

                // Add event listener to the close buttons in popups
                document.querySelectorAll(".close-btn").forEach(function(closeBtn) {
                    closeBtn.addEventListener("click", function() {
                        closeActivePopup(); // Close the active popup
                    });
                });
            });
        </script>


        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var buttons = document.querySelectorAll('.viewButton');

                buttons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        var id = button.getAttribute('data-id');
                        var xhr = new XMLHttpRequest();
                        var url = 'fetch_data.php?id=' + id;

                        xhr.onreadystatechange = function() {
                            if (xhr.readyState == 4 && xhr.status == 200) {
                                var data = JSON.parse(xhr.responseText);
                                populateForm(data);
                            }
                        };

                        xhr.open('GET', url, true);
                        xhr.send();
                    });
                });

            });

            function populateForm(data) {
                // Populate form fields with data
                document.getElementById('tentno').value = data.tent_no;
                document.getElementById('tent_no1').value = data.no_of_tents;
                document.getElementById('datepicker1').value = data.date;
                document.getElementById('name1').value = data.name;
                document.getElementById('contact1').value = data.Contact_no;
                // Populate the purpose dropdown
                var purposeDropdown = document.getElementById('purpose1');
                purposeDropdown.innerHTML = ''; // Clear existing options
                var purposeOptions = ['Wake', 'Birthday', 'Wedding', 'Baptism', 'Personal', 'Private', 'Church', 'School', 'LGU', 'Province']; // Array of purpose options
                purposeOptions.forEach(function(option) {
                    var optionElement = document.createElement('option');
                    optionElement.value = option;
                    optionElement.textContent = option;
                    purposeDropdown.appendChild(optionElement);
                });
                // Set the selected value
                purposeDropdown.value = data.purpose;

                var locationDropdown = document.getElementById('Location1');
                locationDropdown.innerHTML = ''; // Clear existing options
                var locationOptions = ['Bool', 'Booy', 'Cabawan', 'Cogon', 'Dao', 'Dampas', 'Manga', 'Mansasa', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'San Isidro', 'Taloto', 'Tiptip', 'Ubujan']; // Array of location options
                locationOptions.forEach(function(option) {
                    var optionElement = document.createElement('option');
                    optionElement.value = option;
                    optionElement.textContent = option;
                    locationDropdown.appendChild(optionElement);
                });
                // Set the selected value
                locationDropdown.value = data.location;

                document.getElementById('other1').value = data.location;
                var initialTentNo = parseInt(data.no_of_tents) || 0;
                clickLimit = initialTentNo;

                // Trigger the input event on #tent_no1 to update the click limit
                $('#tent_no1').trigger('input');

                // // Update the tent number input field with the initial value
                // var tentNumbers = $('#tentno').val().split(',').map(Number);
                // tentNumbers = Array.from({
                //     length: initialTentNo
                // }, (_, i) => i + 1); // Populate with numbers 1 to initialTentNo
                // $('#tentno').val(tentNumbers.join(','));

                // Set initial click limit
                clickLimit = initialTentNo;
            }
        </script>


        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var buttons = document.querySelectorAll('.viewButton');

                buttons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        var id = button.getAttribute('data-id');
                        document.getElementById('id').value = id; // Store the ID in the hidden input field
                    });
                });
            });
        </script>
        <script>
            // Get the input elements
            var otherInput = document.getElementById('other1');
            var locationSelect = document.getElementById('Location1');

            // Add event listeners to both input elements
            otherInput.addEventListener('change', function() {
                // Call a function to handle the change
                updateRecord();
            });

            locationSelect.addEventListener('change', function() {
                // Call a function to handle the change
                updateRecord();
            });

            // Function to update the record
            function updateRecord() {
                // Get the ID from a hidden input field, assuming you have one
                var id = document.getElementById('id').value;

                // Get the values of the input fields 
                var otherValue = otherInput.value;
                var locationValue = locationSelect.value;

                // Create a new FormData object to send the data to the server
                var formData = new FormData();
                formData.append('id', id);
                formData.append('other1', otherValue);
                formData.append('Location1', locationValue);

                // Send a POST request to the update_data.php file
                fetch('update_data.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(result => {
                        // Display the result
                        console.log(result);
                    })
                    .catch(error => {
                        // Handle errors
                        console.error('Error:', error);
                    });
            }
        </script>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <script>
            $(document).ready(function() {
                // Listen for change in dropdown selection
                $('.status-dropdown').change(function() {
                    var id = $(this).find(':selected').attr('data-id');
                    var status = $(this).val();

                    // Execute AJAX call to update_status.php
                    $.ajax({
                        url: 'update_status.php',
                        type: 'POST',
                        data: {
                            id: id,
                            status: status
                        },
                        success: function(response) {
                            console.log(response); // Log response to console
                            // Handle success response here
                        },
                        error: function(xhr, status, error) {
                            console.error(xhr.responseText); // Log error response to console
                            // Handle error here
                        }
                    });
                });
            });
        </script>
        <script>
            // Assuming you're using jQuery for simplicity
            $(document).on('click', '.deleteButton', function() {
                var rowId = $(this).data('id');
                if (confirm("Are you sure you want to delete this record?")) {
                    $.ajax({
                        url: 'delete_data.php', // PHP file to handle delete operation
                        type: 'POST',
                        data: {
                            id: rowId
                        },
                        success: function(response) {

                            window.location.href = 'tracking.php';

                        },
                        error: function(xhr, status, error) {
                            // Handle error
                            console.error(xhr.responseText);
                        }
                    });
                }
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Get the input field and table
                var input = document.getElementById('search-input');
                var table = document.getElementById('table_tent');
                var rows = table.getElementsByTagName('tr');

                // Add event listener to the search input
                input.addEventListener('input', function() {
                    var filter = input.value.toLowerCase(); // Convert input to lowercase for case-insensitive search

                    // Loop through all table rows (excluding the first row which contains <th> elements)
                    for (var i = 1; i < rows.length; i++) {
                        var cells = rows[i].getElementsByTagName('td'); // Get all cells in the current row

                        // Hide the row if the search input doesn't match any cell value
                        var rowVisible = false; // Assume the row is hidden by default
                        for (var j = 0; j < cells.length; j++) {
                            var cellText = cells[j].textContent.toLowerCase(); // Get the cell text in lowercase
                            if (cellText.indexOf(filter) > -1) { // Check if the search input is found in the cell text
                                rowVisible = true; // Set rowVisible to true if the search input is found
                                break; // Exit the loop since the input is found in this row
                            }
                        }

                        // Toggle the row's display property based on rowVisible
                        if (rowVisible) {
                            rows[i].style.display = ''; // Show the row
                        } else {
                            rows[i].style.display = 'none'; // Hide the row
                        }
                    }
                });
            });
        </script>
        <script>
            // Get the div element with class "boxs"
            const boxesContainer = document.querySelector('.boxs');

            function updateBoxStatus() {
                // Make an AJAX request to fetch data from tent_status table
                var xhr = new XMLHttpRequest();
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === XMLHttpRequest.DONE) {
                        if (xhr.status === 200) {
                            var data = JSON.parse(xhr.responseText);
                            // Loop through the data and update boxes based on status
                            data.forEach(function(item) {
                                var box = document.getElementById('box_' + item.id); // Assuming item.id is the unique ID of each box
                                if (box) {
                                    // Remove all existing classes
                                    box.className = 'box';
                                    // Add class based on status
                                    if (item.Status === 'On Stock') {
                                        box.classList.add('green');
                                    } else if (item.Status === 'Installed') {
                                        box.classList.add('red');
                                    } else if (item.Status === 'Retrieved') {
                                        box.classList.add('green');
                                    } else if (item.Status === 'Retrieval') {
                                        box.classList.add('orange');
                                    } else if (item.Status === 'Long Term') {
                                        box.classList.add('blue');
                                    }
                                }
                            });
                        } else {
                            console.error('Error fetching data:', xhr.status);
                        }
                    }
                };
                xhr.open('GET', 'fetch_tent_status.php', true);
                xhr.send();
            }

            // Loop to create 100 boxes
            for (let i = 1; i <= 100; i++) {
                // Create a new div element for each box
                const box = document.createElement('div');

                // Set a class for the box
                box.className = 'box';

                // Set a unique id for each box (optional)
                box.id = 'box_' + i;

                // Put the number inside the box
                box.textContent = i;

                // Append the box to the container
                boxesContainer.appendChild(box);
            }
            updateBoxStatus();
        </script>

        <div class="overlay"></div>
</body>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<!-- jQuery UI -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script>
    $(document).ready(function() {
        $("#datepicker").datepicker();
    });
</script>

</html>