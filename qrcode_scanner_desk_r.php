<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>E-Pass Slip Scanner</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        body {
            background: #e9f5ec;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            font-weight: 600;
        }

        .logo-img {
            border-radius: 50%;
            width: 48px;
            height: 48px;
            object-fit: cover;
            margin-right: 10px;
            border: 2px solid white;
        }

        .logo-text {
            color: white;
            font-size: 1.4rem;
            letter-spacing: 0.04em;
        }

        main.container {
            max-width: 480px;
            background: white;
            padding: 30px 35px;
            margin: 40px auto;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        h3#label {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 25px;
        }

        #barcodeInput {
            font-size: 1.25rem;
            padding: 14px 20px;
            border-radius: 8px;
            border: 1.8px solid #28a745;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: border-color 0.3s ease;
        }

        #barcodeInput:focus {
            border-color: #1e7e34;
            outline: none;
            box-shadow: 0 0 8px rgba(40, 167, 69, 0.6);
        }

        #texthead {
            margin-top: 40px;
            font-size: 2.25rem;
            font-weight: 700;
            color: #2c3e50;
        }

        #text {
            font-size: 3.5rem;
            margin-top: 12px;
            font-weight: 700;
            color: #28a745;
            min-height: 60px;
        }

        #errorMessage {
            margin-top: 18px;
            font-weight: 600;
            font-size: 1.05rem;
            display: none;
            padding: 12px 15px;
            border-radius: 8px;
        }

        @media (max-width: 576px) {
            main.container {
                margin: 20px 15px;
                padding: 25px 20px;
            }

            #texthead {
                font-size: 1.8rem;
            }

            #text {
                font-size: 2.5rem;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <a class="navbar-brand" href="index_r.php" aria-label="Go to home page">
            <img src="logo.png" alt="Logo" class="logo-img" />
            <span class="logo-text">E-Pass Slip</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav" role="menu">
                <li class="nav-item" role="none"><a class="nav-link" href="index_r.php" role="menuitem">Home</a></li>
                <li class="nav-item" role="none"><a class="nav-link" href="add_req_r.php" role="menuitem">Add Request</a></li>
                <li class="nav-item" role="none"><a class="nav-link" href="declined_r.php" role="menuitem">Declined Request</a></li>
                <li class="nav-item" role="none"><a class="nav-link" href="track_emp_r.php" role="menuitem">Track Employees</a></li>
                <li class="nav-item" role="none"><a class="nav-link" href="qrcode_scanner_desk_r.php" role="menuitem">Scanner</a></li>
                <li class="nav-item" role="none"><a class="nav-link" href="logout.php" role="menuitem">Logout</a></li>
            </ul>
        </div>
    </nav>
    <main class="container py-4" role="main" aria-live="polite" aria-atomic="true">
        <!-- Header -->
        <header class="mb-4">
            <h3 id="label" class="text-primary">
                <i class="fas fa-qrcode" aria-hidden="true"></i> Scan QR Code
            </h3>
            <p class="sr-only" id="qr-instructions">Please scan the QR code or enter the barcode manually.</p>
        </header>
        <!-- Input Field -->
        <div class="mb-4">
            <label for="barcodeInput" class="form-label visually-hidden">QR code Input</label>
            <input
                type="text"
                id="barcodeInput"
                class="form-control"
                placeholder="Scan barcode here"
                aria-describedby="qr-instructions"
                autofocus
                autocomplete="off"
                inputmode="numeric"
                aria-label="Scan barcode input" />
        </div>
        <!-- Greeting Text -->
        <div class="text-center mb-3">
            <h2 id="texthead" class="fw-semibold">Stand By</h2>
            <h1 id="text" aria-live="polite" aria-atomic="true" class="display-6"></h1>
        </div>
        <!-- Error Message -->
        <div id="errorMessage" class="alert alert-danger d-none" role="alert" aria-live="assertive"></div>
    </main>
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const barcodeInput = document.getElementById("barcodeInput");
            const errorMessage = document.getElementById("errorMessage");
            const textDisplay = document.getElementById("text");
            const textHead = document.getElementById("texthead");

            // Audio feedback
            const successAudio = new Audio("success.mp3");
            const errorAudio = new Audio("error.wav");

            // Debounce variables for wireless scanner
            let inputTimer = null;
            let isProcessing = false;
            const DEBOUNCE_DELAY = 300; // 300ms delay after last character
            const MIN_BARCODE_LENGTH = 3; // Minimum expected barcode length

            barcodeInput.focus();

            // Handle input with debounce for wireless scanners
            barcodeInput.addEventListener("input", function(event) {
                const scannedData = event.target.value.trim();
                console.log("Input received:", scannedData, "Length:", scannedData.length);

                // Clear existing timer
                if (inputTimer) {
                    clearTimeout(inputTimer);
                }

                // Don't process if already processing or input is too short
                if (isProcessing || scannedData.length < MIN_BARCODE_LENGTH) {
                    return;
                }

                // Set timer to process after delay (when input stops coming)
                inputTimer = setTimeout(() => {
                    if (scannedData !== "" && !isProcessing) {
                        console.log("Processing scanned data:", scannedData);
                        checkScannedData(scannedData);
                    }
                }, DEBOUNCE_DELAY);
            });

            // Alternative: Handle Enter key press (most scanners send Enter after scanning)
            barcodeInput.addEventListener("keypress", function(event) {
                if (event.key === "Enter" || event.keyCode === 13) {
                    event.preventDefault();

                    // Clear any existing timer
                    if (inputTimer) {
                        clearTimeout(inputTimer);
                    }

                    const scannedData = event.target.value.trim();
                    console.log("Enter key pressed, processing:", scannedData);

                    if (scannedData !== "" && !isProcessing) {
                        checkScannedData(scannedData);
                    }
                }
            });

            // Handle paste events (for manual input or some scanners)
            barcodeInput.addEventListener("paste", function(event) {
                setTimeout(() => {
                    const scannedData = event.target.value.trim();
                    console.log("Paste detected:", scannedData);

                    if (scannedData !== "" && !isProcessing) {
                        checkScannedData(scannedData);
                    }
                }, 50);
            });

            function resetDisplay() {
                // Hide error message first
                errorMessage.style.display = "none";
                errorMessage.classList.add("d-none");

                // Clear text displays
                textHead.textContent = "Stand By";
                textDisplay.textContent = "";
            }

            function showSuccess(name, status) {
                // Hide error message
                errorMessage.style.display = "none";
                errorMessage.classList.add("d-none");

                // Play success audio
                successAudio.currentTime = 0;
                successAudio.play().catch(e => console.log("Audio play failed:", e));

                // Set appropriate header text
                if (status && status.startsWith("Arrived")) {
                    textHead.textContent = "Welcome Back!";
                } else if (status === "exists") {
                    textHead.textContent = "Take Care!";
                } else if (status === "done") {
                    textHead.textContent = "Completed!";
                } else {
                    textHead.textContent = "Success!";
                }

                // Display name
                textDisplay.textContent = name || "Unknown";
            }

            function showError(message) {
                // Clear success displays
                textHead.textContent = "";
                textDisplay.textContent = "";

                // Play error audio
                errorAudio.currentTime = 0;
                errorAudio.play().catch(e => console.log("Audio play failed:", e));

                // Show error message
                errorMessage.textContent = message;
                errorMessage.style.display = "block";
                errorMessage.classList.remove("d-none");
            }

            function checkScannedData(scannedData) {
                // Prevent multiple simultaneous requests
                if (isProcessing) {
                    console.log("Already processing, ignoring request");
                    return;
                }

                isProcessing = true;

                // Clear any pending timer
                if (inputTimer) {
                    clearTimeout(inputTimer);
                }

                // Reset display first
                resetDisplay();

                // Show processing indicator
                textHead.textContent = "Processing...";

                $.ajax({
                    url: "code_dept.php",
                    type: "POST",
                    data: {
                        scannedData: scannedData
                    },
                    dataType: "json",
                    timeout: 10000, // 10 second timeout
                    success: function(response) {
                        console.log("Response from server:", response);

                        // Define success statuses explicitly
                        const successStatuses = ["exists", "done"];
                        const isArrivedStatus = response.status && response.status.startsWith("Arrived");
                        const isSuccessStatus = successStatuses.includes(response.status);

                        if (isSuccessStatus || isArrivedStatus) {
                            // Handle success cases
                            const name = response.name || scannedData;
                            showSuccess(name, response.status);
                        } else {
                            // Handle error cases
                            let errorMsg = "An unexpected error occurred.";

                            switch (response.status) {
                                case "not_exists":
                                    errorMsg = "Your request does not exist in the database or is not approved.";
                                    break;
                                case "update_error":
                                    errorMsg = "Error updating the database. Please try again.";
                                    break;
                                case "est_error":
                                    errorMsg = "Error fetching estimated time from the database.";
                                    break;
                                default:
                                    if (response.message) {
                                        errorMsg = response.message;
                                    }
                                    break;
                            }

                            showError(errorMsg);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", error);
                        if (status === "timeout") {
                            showError("Request timeout. Please try again.");
                        } else {
                            showError("Connection error. Please try again.");
                        }
                    },
                    complete: function() {
                        // Reset processing flag and clear input after a delay
                        setTimeout(() => {
                            isProcessing = false;
                            barcodeInput.value = "";
                            barcodeInput.focus();
                        }, 1500); // 1.5 second delay before allowing next scan
                    }
                });
            }

            // Focus input when clicking anywhere on the page
            document.addEventListener("click", function() {
                if (!isProcessing) {
                    barcodeInput.focus();
                }
            });

            // Refocus input when it loses focus (unless processing)
            barcodeInput.addEventListener("blur", function() {
                if (!isProcessing) {
                    setTimeout(() => {
                        barcodeInput.focus();
                    }, 100);
                }
            });
        });
    </script>

</body>

</html>