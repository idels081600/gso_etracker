<?php
require_once 'db_asset.php';
require_once 'display_data_asset.php';
$on_stock = display_tent_status();
$on_field = display_tent_status_Installed();
$on_retrieval = display_tent_status_Retrieval();
$longterm = display_tent_status_Longterm();
session_start();
if (!isset($_SESSION['username'])) {
    header("location: login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}
$result = display_data();

if (isset($_POST['save_data'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $contact_no = mysqli_real_escape_string($conn, $_POST['contact']);
    $date = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_POST['datepicker'])));
    $no_of_tents = mysqli_real_escape_string($conn, $_POST['tent_no']);
    $purpose = mysqli_real_escape_string($conn, $_POST['No_tents']);
    $location = "";
    $duration = mysqli_real_escape_string($conn, $_POST['duration']);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Check if "other" input is not empty
        if (!empty($_POST['other'])) {
            $location = mysqli_real_escape_string($conn, $_POST['other']); // Use "other" input value
        } else {
            // "other" input is empty, use the value from the dropdown
            $location = mysqli_real_escape_string($conn, $_POST['Location']);
        }
    }

    // Calculate retrieval date
    $retrieval_date = date('Y-m-d', strtotime($date . ' + ' . $duration . ' days'));

    $query = "INSERT INTO tent(name, contact_no, no_of_tents, purpose, location, status, date, retrieval_date) 
              VALUES ('$name', '$contact_no', '$no_of_tents', '$purpose', '$location', 'Pending', '$date', '$retrieval_date')";

    $query_run = mysqli_query($conn, $query);
    if ($query_run) {
        header("Location: tracking.php");
    } else {
        // Handle insert error
        header("Location: tracking.php");
        exit();
    }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tent Tracker</title>
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="tracking_style.css">
    <link rel="stylesheet" href="style_box.css">
    <!-- <link rel="stylesheet" href="test.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                    <li><a href="pay_track.php">Payables</a></li>
                    <li><a href="rfq_tracking.php">RFQ</a></li>
                </ul>
            </li>
            <li><a href="tracking.php"><i class="fas fa-campground icon-size"></i> Tent</a></li>
            <li><a href="transpo.php"><i class="fas fa-truck icon-size"></i> Transportation</a></li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>

    <div class="content">

        <h1>Tent</h1>
        <ul class="tally_list" id="tallyList">
            <li class="tally" id="on_standby">
                <div class="available">Available</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="on_stocks_value"><?php echo $on_stock; ?></h1>
                    </div>
                </div>
            </li>
            <li class="tally" id="tallyList">
                <div class="on_field"> On Field</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="on_field_value"><?php echo $on_field; ?></h1>
                    </div>
                </div>
            </li>
            <li class="tally" id="tallyList">
                <div class="for_retrieval"> For Retrieval</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="for_retrieval_value"><?php echo $on_retrieval; ?></h1>
                    </div>
                </div>
            </li>
            <li class="tally" id="tallyList">
                <div class="long_term"> Long Term</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="long_term_value"><?php echo $longterm; ?></h1>
                    </div>
                </div>
            </li>
        </ul>
        <div class="container_table">
            <div class="column">
                <!-- <button class="button-3" role="button">Scanner</button> -->
                <button class="button-2" id="addButton" role="button">Install Tent</button>
                <!-- <button class="button-4" id="UpdateButton" role="button">Update Status</button> -->
                <!-- <div class="dropdown_menu">
                    <select class="menu" id="sel1" name='typeofbusiness'>
                        <option>Tent</option>
                        <option>Transportation</option>
                    </select>
                </div> -->
                <input type="text" id="search-input" placeholder="Search...">
            </div>

            <div class="container_table_content">

                <div class="popup">
                    <div class="close-btn">&times;</div>
                    <form class="form" action="" method="POST">
                        <h1 id="label_modal">Tent Details</h1>
                        <div class="form-element-tent_no">
                            <label for="tent_no" id="tent_no-label">No. of Tents</label>
                            <input type="number" id="tent_no" placeholder="" name="tent_no" min="0" step="1" pattern="\d+" required>
                        </div>

                        <div class="form-element-datepicker">
                            <label for="datepicker" id="datepicker-label">Date</label>
                            <input type="text" id="datepicker" placeholder="Select a date" name="datepicker" required>
                        </div>

                        <div class="form-element-name">
                            <label for="name" id="name-label">Name</label>
                            <input type="text" id="name" placeholder="" name="name" required>
                        </div>
                        <div class="form-element-contact">
                            <label for="contact" id="contact-label">Contact no.</label>
                            <input type="text" id="contact" placeholder="" name="contact" required>
                        </div>
                        <div class="form-element-No_tents">
                            <label for="No_tents" id="No_tents-label">Purpose</label>
                            <select id="No_tents" name="No_tents" required>
                                <option value="">Select Purpose</option> <!-- Initial blank option -->
                                <option value="Wake">Wake</option>
                                <option value="Fiesta">Fiesta</option>
                                <option value="Birthday">Birthday</option>
                                <option value="Wedding">Wedding</option>
                                <option value="Baptism">Baptism</option>
                                <option value="Personal">Personal</option>
                                <option value="Private">Private</option>
                                <option value="Church">Church</option>
                                <option value="School">School</option>
                                <option value="City Government">City Government</option>
                                <option value="LGU">LGU</option>
                                <option value="Municipalities">Municipalities</option>
                                <option value="Province">Province</option>
                                <!-- Add more options as needed -->
                            </select>
                        </div>


                        <!-- <div class="form-element-Purpose">
                            <label for="Purpose">Purpose</label>
                            <input type="text" id="Purpose" placeholder="">
                        </div> -->

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
                                <option value="Outside_Tagbilaran">Outside Tagbilaran</option>
                            </select>
                        </div>
                        <div class="form-element-duration">
                            <label for="duration" id="duration-label">Tent Duration</label>
                            <input type="number" id="tent_duration" placeholder="" name="duration" required>
                        </div>

                        <!-- <div class="form-element-other">
                            <label for="other" id="other-label">Specify Location (outside Tagbilaran)</label>
                            <input type="text" id="other" placeholder="" name="other">
                        </div> -->
                        <button class="button-37" role="button" name="save_data">Submit</button>
                    </form>
                </div>
                <div class="popup1" id="popup1">
                    <div class="close-btn">&times;</div>
                    <div class="form1">
                        <h1 id="label_modal1">Details</h1>
                        <form action="update_data.php" method="POST">
                            <input type="hidden" name="id" id="id" value="">
                            <div class="boxContainer">
                                <div class="boxes">
                                </div>
                            </div>
                            <div class="form-element-tent_no1">
                                <label for="tent_no1" id="tent_no-label1">No. of Tents</label>
                                <input type="text" id="tent_no1" name="tent_no1" placeholder="" value=" ">
                            </div>

                            <div class="form-element-datepicker1">
                                <label for="datepicker" id="datepicker-label1">Date</label>
                                <input type="text" id="datepicker1" name="datepicker1" placeholder="">
                            </div>
                            <div class="form-element-name1">
                                <label for="name" id="name-label1">Name</label>
                                <input type="text" id="name1" name="name1" placeholder="">
                            </div>
                            <div class="form-element-contact1">
                                <label for="contact" id="contact-label1">Contact no.</label>
                                <input type="text" id="contact1" name="contact1" placeholder="">
                            </div>
                            <div class="form-element-tentno1">
                                <label for="tentno" id="tentno-label">Tent no.</label>
                                <input type="text" id="tentno" name="tentno" placeholder="">
                            </div>
                            <div class="form-element-Location1">
                                <label for="Location" id="Location-label1">Location</label>
                                <select id="Location1" name="Location1">
                                    <option value="">Select Location</option> <!-- Initial blank option -->
                                    <option value="option1">Bool</option>
                                    <option value="option2">Booy</option>
                                    <option value="option3">Cabawan</option>
                                    <option value="option4">Cogon</option>
                                    <option value="option5">Dao</option>
                                    <option value="option6">Dampas</option>
                                    <option value="option7">Manga</option>
                                    <option value="option8">Mansasa</option>
                                    <option value="option9">Poblacion I</option>
                                    <option value="option10">Poblacion II</option>
                                    <option value="option11">Poblacion III</option>
                                    <option value="option12">San Isidro</option>
                                    <option value="option13">Taloto</option>
                                    <option value="option14">Tiptip</option>
                                    <option value="option15">Ubujan</option>


                                    <!-- Add more options as needed -->
                                </select>
                            </div>
                            <div class="form-element-purpose">
                                <label for="purpose" id="purpose-label">Purpose</label>
                                <select id="purpose1" name="purpose1">
                                    <option value="">Select Purpose</option> <!-- Initial blank option -->
                                    <option value="option1">Wake</option>
                                    <option value="option2">Birthday</option>
                                    <option value="option3">Wedding</option>
                                    <option value="option4">Baptism</option>
                                    <option value="option5">Personal</option>
                                    <option value="option6">Private</option>
                                    <option value="option7">Church</option>
                                    <option value="option8">School</option>
                                    <option value="option10">Municipalities</option>
                                    <option value="option11">Province</option>
                                    <option value="option11">Fiesta</option>
                                    <option value="option9">City Government</option>
                                    <option value="option9">LGU</option>
                                </select>
                            </div>
                            <div class="form-element-other1">
                                <label for="other" id="other-label1">Specify Location (outside Tagbilaran)</label>
                                <input type="text" id="other1" name="other1" placeholder="">
                            </div>
                            <div class="form-element-duration1">
                                <label for="duration1" id="duration1-label">Tent Duration</label>
                                <input type="text" id="tent_duration1" placeholder="" name="duration1">
                            </div>
                            <button type="submit" class="button-details" role="button">Submit</button>
                        </form>
                    </div>
                </div>
                <div class="popup3" id="popup3">
                    <div class="close-btn">&times;</div>
                    <div class="form1">
                        <h1 id="label_modal1">Details</h1>
                        <form action="update_data.php" method="POST">
                            <input type="hidden" name="id" id="id" value="">
                            <div class="box_Container">
                                <div class="boxs">
                                </div>
                            </div>
                            <button type="submit" class="button-details" role="button">Submit</button>
                        </form>
                    </div>
                </div>
                <div class="table-container">
                    <table id="table_tent" class="table_tent">
                        <thead>
                            <tr>
                                <th class="tent-no">Tent No.</th>
                                <th class="date">Date</th>
                                <th class="retrieval-date">Retrieval Date</th>
                                <th class="name">Name</th>
                                <th class="contact-number">Contact Number</th>
                                <th class="no-of-tents">No. of Tents</th>
                                <th class="purpose">Purpose</th>
                                <th class="location">Location</th>
                                <th class="status">Status</th>
                                <th class="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                <tr>
                                    <td><?php echo $row["tent_no"]; ?></td>
                                    <td><?php echo $row["date"]; ?></td>
                                    <td class="retrieval-date"><?php echo $row["retrieval_date"]; ?></td>
                                    <td><?php echo $row["name"]; ?></td>
                                    <td><?php echo $row["Contact_no"]; ?></td>
                                    <td><?php echo $row["no_of_tents"]; ?></td>
                                    <td><?php echo $row["purpose"]; ?></td>
                                    <td><?php echo $row["location"]; ?></td>
                                    <?php if (empty($row['status'])) : ?>
                                        <td class="dropdown-cell">
                                            <div class="form-element">
                                                <select class="status-dropdown" name="status" id="drop_status">
                                                    <option value="" data-id="">Select Status</option>
                                                    <option value="Installed" data-id="<?php echo $row['id']; ?>">Installed</option>
                                                    <option value="For Retrieval" data-id="<?php echo $row['id']; ?>">For Retrieval</option>
                                                    <option value="Retrieved" data-id="<?php echo $row['id']; ?>">Retrieved</option>
                                                </select>
                                            </div>
                                        </td>
                                    <?php else : ?>
                                        <td class="dropdown-cell">
                                            <div class="form-element">
                                                <select class="status-dropdown" name="status" id="drop_status">
                                                    <option value="" data-id="">Select Status</option>
                                                    <option value="Installed" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'Installed') ? 'selected' : ''; ?>>Installed</option>
                                                    <option value="For Retrieval" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'For Retrieval') ? 'selected' : ''; ?>>For Retrieval</option>
                                                    <option value="Retrieved" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'Retrieved') ? 'selected' : ''; ?>>Retrieved</option>
                                                </select>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <button class="button-4 viewButton" data-id="<?php echo $row['id']; ?>" role="button">Edit</button>
                                        <button class="button-5 deleteButton" data-id="<?php echo $row['id']; ?>" role="button">Delete</button>
                                    </td>
                                </tr>
                                <script>
                                    // Debugging: log the status for each row to ensure it's correct
                                    console.log("Row ID:", <?php echo $row['id']; ?>, "Status:", "<?php echo $row['status']; ?>");
                                </script>
                            <?php } ?>

                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
    <!-- <script>
        window.onload = function() {
            document.getElementById('sel1').addEventListener('change', function() {
                var selectedOption = this.value;

                if (selectedOption === 'Tent') {
                    document.getElementById('table_tent').style.display = 'table';
                    document.getElementById('table_transportation').style.display = 'none';
                } else if (selectedOption === 'Transportation') {
                    document.getElementById('table_tent').style.display = 'none';
                    document.getElementById('table_transportation').style.display = 'table';
                }
            });
        };
    </script> -->
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
        document.querySelector("#addButton").addEventListener("click", function() {
            document.querySelector(".popup").classList.add("active");
            document.querySelector(".overlay").style.display = "block"; // Show the overlay
        });

        document.querySelector(".popup .close-btn").addEventListener("click", function() {
            document.querySelector(".popup").classList.remove("active");
            document.querySelector(".overlay").style.display = "none"; // Hide the overlay
        });
    </script>
    <script>
        document.querySelector("#UpdateButton").addEventListener("click", function() {
            document.querySelector(".popup3").classList.add("active");
            document.querySelector(".overlay").style.display = "block"; // Show the overlay
        });

        document.querySelector(".popup3 .close-btn").addEventListener("click", function() {
            document.querySelector(".popup3").classList.remove("active");
            document.querySelector(".overlay").style.display = "none"; // Hide the overlay
        });
    </script>
    <script>
        document.querySelectorAll(".viewButton").forEach(function(button) {
            button.addEventListener("click", function() {
                document.querySelector(".popup1").classList.add("active");
                document.querySelector(".overlay").style.display = "block";
            });
        });

        document.querySelector(".popup1 .close-btn").addEventListener("click", function() {
            document.querySelector(".popup1").classList.remove("active");
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
                var activePopup = document.querySelector(".popup.active");
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
            // Function to close the active popup
            function closeActivePopup() {
                var activePopup = document.querySelector(".popup3.active");
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
    <!-- <script>
        // Get all dropdown elements
        var dropdowns = document.querySelectorAll('.status-dropdown');
        // Get the popup element
        var popup = document.querySelector('.popup1');

        // Add event listener to each dropdown
        dropdowns.forEach(function(dropdown) {
            // Listen for changes in the dropdown selection
            dropdown.addEventListener('change', function() {
                // Check if the selected option is "Installed"
                if (dropdown.value === 'Installed') {
                    // If yes, show the popup
                    popup.classList.add('active');
                    document.querySelector(".overlay").style.display = "block";
                } else {
                    // If not, hide the popup
                    popup.classList.remove('active');
                    document.querySelector(".overlay").style.display = "none";
                }
            });
        });
    </script> -->
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
            document.getElementById('tent_duration1').value = data.retrieval_date;
            // Populate the purpose dropdown
            var purposeDropdown = document.getElementById('purpose1');
            purposeDropdown.innerHTML = ''; // Clear existing options
            var purposeOptions = ['Wake', 'Birthday', 'Wedding', 'Baptism', 'Personal', 'Private', 'Church', 'School', 'LGU', 'Province', 'City Government', 'Municipalities']; // Array of purpose options
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
            var locationOptions = ['Bool', 'Booy', 'Cabawan', 'Cogon', 'Dao', 'Dampas', 'Manga', 'Mansasa', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'San Isidro', 'Taloto', 'Tiptip', 'Ubujan', 'Outside Tagbilaran']; // Array of location options
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
            var boxesContainer = document.querySelector('.boxes');
            var tentNoInput = document.getElementById('tentno');
            var tentNoField = document.getElementById('tent_no1');
            var dropdown = document.querySelector('.status-dropdown');

            function updateBoxStatus() {
                // Make an AJAX request to fetch data from tent_status table
                var xhr = new XMLHttpRequest();
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === XMLHttpRequest.DONE) {
                        if (xhr.status === 200) {
                            var data = JSON.parse(xhr.responseText);
                            // Loop through the data and update boxes based on status
                            data.forEach(function(item) {
                                var box = document.querySelector('.box[data-box="' + item.id + '"]');
                                if (box) {
                                    if (item.Status === 'On Stock') {
                                        box.classList.add('green');
                                    } else if (item.Status === 'Installed') {
                                        box.classList.add('red');
                                    } else if (item.Status === 'Retrieved') {
                                        box.classList.add('green');
                                    } else if (item.Status === 'For Retrieval') {
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

            dropdown.addEventListener('change', function() {
                var selectedStatus = this.value;
                var tentNumbers = tentNoInput.value.split(',').map(Number); // Get an array of tent numbers

                // Update the status of each tent number
                tentNumbers.forEach(function(tentNumber) {
                    // Perform an AJAX request to update the status in the database
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'update_tent_status.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            console.log('Status updated for tent number ' + tentNumber);
                        } else {
                            console.error('Error updating status for tent number ' + tentNumber);
                        }
                    };
                    xhr.send('tentno=' + tentNumber + '&status=' + selectedStatus);
                });

                // Call updateBoxStatus after updating the status
                updateBoxStatus();
            });

            // Call updateBoxStatus on page load
            updateBoxStatus();
            $(document).ready(function() {
                var clickLimit = 0; // Default click limit
                var dropdown = document.querySelector('.status-dropdown');

                // Function to toggle box selection
                function toggleBox(box) {
                    if ($(box).hasClass('red')) {
                        $(box).removeClass('red').addClass('green');
                        clickLimit++; // Increase click limit when deselecting a box
                    } else if (clickLimit > 0) {
                        $(box).removeClass('green').addClass('red');
                        clickLimit--; // Decrease click limit when selecting a box
                    } else {
                        alert('You have reached the click limit.');
                        return false; // Return false to indicate click limit reached
                    }
                    return true; // Return true to indicate box toggled successfully
                }

                // Function to handle box click
                function handleBoxClick(event) {
                    // Check if the box is already orange or blue
                    if ($(this).hasClass('orange') || $(this).hasClass('blue') || $(this).hasClass('red')) {
                        return; // Do nothing if the box is orange or blue
                    }

                    // Toggle class to turn box red or back to green
                    var toggled = toggleBox(this);
                    if (!toggled) return; // If toggleBox returned false (click limit reached), do not proceed

                    // Get the number of the clicked box
                    var boxNumber = this.getAttribute('data-box');

                    // Update the tent number input field
                    var tentNumbers = $('#tentno').val().split(',').map(Number);
                    var index = tentNumbers.indexOf(parseInt(boxNumber));
                    if (index !== -1) {
                        // Remove the box number if it's already selected
                        tentNumbers.splice(index, 1);
                    } else {
                        // Add the box number to the selected boxes
                        tentNumbers.push(parseInt(boxNumber));
                    }

                    // Remove leading "0" if present
                    tentNumbers = tentNumbers.filter(function(num) {
                        return num !== 0;
                    });

                    // Update the input field value with the updated tent numbers
                    $('#tentno').val(tentNumbers.join(','));
                }

                // Update click limit when input field changes
                $('#tent_no1').on('input', function() {
                    clickLimit = parseInt($(this).val()) || 0;
                });

                // Dynamically generate 102 boxes
                var boxesContainer = document.querySelector('.boxContainer');
                for (var i = 1; i <= 200; i++) {
                    var box = document.createElement('div');
                    box.classList.add('box');
                    box.setAttribute('data-box', i);
                    box.innerHTML = '<span>' + i + '</span>';
                    box.addEventListener('click', handleBoxClick);
                    boxesContainer.appendChild(box);
                }
            });

            var popup1 = document.getElementById('popup1');
            popup1.addEventListener('click', function() {
                // Call function to update box status
                updateBoxStatus();
            });

            // Call the function initially to update box status
            updateBoxStatus();
        });
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
    <!-- <script>
        document.addEventListener('DOMContentLoaded', function() {
            var dropdown = document.querySelector('.status-dropdown');
            var tentnoInput = document.getElementById('tentno');

            dropdown.addEventListener('change', function() {
                var selectedStatus = this.value;
                var tentNumbers = tentnoInput.value.split(',').map(Number); // Get an array of tent numbers

                // Update the status of each tent number
                tentNumbers.forEach(function(tentNumber) {
                    // Perform an AJAX request to update the status in the database
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'update_tent_status.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            console.log('Status updated for tent number ' + tentNumber);
                        } else {
                            console.error('Error updating status for tent number ' + tentNumber);
                        }
                    };
                    xhr.send('tentno=' + tentNumber + '&status=' + selectedStatus);
                });
            });
        });
    </script> -->
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
                                } else if (item.Status === 'For Retrieval') {
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
    <script>
        $(document).ready(function() {
            // Hide the specified form element
            $('.form-element-other1').hide();
        });
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
<script>
    $(document).ready(function() {
        // Function to check and update date colors and get tent_no and id for red dates
        function updateDateColors() {
            var today = new Date();
            var redDates = [];

            $('.date, .retrieval-date').each(function() {
                var dateText = $(this).text();
                var date = new Date(dateText);

                // Get the row and check if a dropdown value is selected
                var row = $(this).closest('tr');
                var selectedOption = row.find('select.status-dropdown').find(':selected').val();

                if (selectedOption) { // Proceed only if there is a selected value
                    // Calculate difference in days
                    var timeDiff = date.getTime() - today.getTime();
                    var diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24));

                    // Apply color based on difference
                    if (diffDays < 0) {
                        $(this).css('color', 'red'); // Date is passed

                        // Get tent_no and id
                        var tent_no = row.find('td:first').text().trim(); // Assuming tent_no is in the first td
                        var id = row.find('select.status-dropdown').find(':selected').data('id');

                        // Check if tent_no is not empty
                        if (tent_no !== '') {
                            // Add to redDates array
                            redDates.push({
                                tent_no: tent_no,
                                id: id
                            });
                        }
                    } else if (diffDays === 0) {
                        $(this).css('color', 'orange'); // Today
                    } else {
                        $(this).css('color', ''); // Reset color if date is in the future
                    }
                } else {
                    $(this).css('color', ''); // Reset color if no value is selected
                }
            });

            // Log the redDates array
            console.log("Red Dates:", redDates);

            // Send redDates to PHP script
            if (redDates.length > 0) {
                $.ajax({
                    type: 'POST',
                    url: 'update_status_duration.php', // Your PHP script to handle the update
                    data: {
                        redDates: JSON.stringify(redDates)
                    },
                    success: function(response) {
                        console.log("Response from server:", response);
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", status, error);
                    }
                });
            }
        }

        // Initial call to update colors
        updateDateColors();

        // Optional: Refresh colors periodically (e.g., every minute)
        setInterval(updateDateColors, 60000); // Update every 1 minute (60000 milliseconds)
    });
</script>

</script>


</html>