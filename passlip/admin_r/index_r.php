<?php
require_once '../dbh.php';
require_once '../functions.php';
$result = display_data_r();
if (!isset($_SESSION['username'])) {
    header("location: ../../login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
}

// Check the user role and perform additional redirection if needed
if ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Desk Clerk' || $_SESSION['role'] == 'TCWS Employee') {
    header("location: ../../login_v2.php");
    exit(); // Ensure that the script stops execution after the redirect
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
    <script src="https://www.gstatic.com/firebasejs/9.14.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.4.0/firebase-messaging-compat.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <title>Home</title>

    <link rel="stylesheet" href="../assets/passlip-modern.css?v=20260603">
    <script defer src="../assets/passlip-modern.js?v=20260603"></script>
</head>
<style>
    @media screen and (max-width: 767px) {
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

    /* Sticky header CSS */
    .table-responsive {
        overflow-y: auto;
    }

    .table thead th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa;
        z-index: 1;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
    }

    .request-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .request-detail-grid > div {
        padding: 12px;
        border: 1px solid #dde7df;
        border-radius: 6px;
        background: #f8fbf8;
    }

    .request-detail-grid .wide {
        grid-column: 1 / -1;
    }

    .request-detail-grid span,
    .request-detail-grid strong {
        display: block;
    }

    .request-detail-grid span,
    .request-modal-fields span {
        margin-bottom: 4px;
        color: #647068;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .request-modal-fields {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .request-modal-fields .wide {
        grid-column: 1 / -1;
    }

    .request-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 18px;
    }

    dialog {
        width: min(760px, calc(100% - 32px));
        max-height: min(86vh, 820px);
        padding: 0;
        border: 0;
        border-radius: 8px;
        background: transparent;
    }

    dialog::backdrop {
        background: rgba(10, 20, 14, .52);
    }

    .modal-card {
        overflow: hidden;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 18px 50px rgba(0, 0, 0, .24);
    }

    .modal-card header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 16px 18px;
        border-bottom: 1px solid #dde7df;
    }

    .modal-card h2,
    .modal-card p {
        margin: 0;
    }

    .eyebrow {
        color: #66746b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .icon-btn {
        min-width: 36px;
        min-height: 36px;
        border: 1px solid #dde7df;
        border-radius: 6px;
        background: #fff;
        color: #17211b;
        font-weight: 800;
    }

    .detail-modal-body {
        max-height: calc(86vh - 86px);
        overflow: auto;
        padding: 18px;
    }

    @media screen and (max-width: 767px) {
        .request-detail-grid,
        .request-modal-fields {
            grid-template-columns: 1fr;
        }

        .request-modal-actions {
            flex-direction: column;
        }

        .request-modal-actions .btn {
            width: 100%;
        }
    }
</style>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <a class="navbar-brand" href="index_r.php">
            <img src="../logo.png" alt="Logo" class="logo-img">
            <span class="logo-text">E-Pass Slip </span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                    <a class="nav-link" href="track_emp_r.php">Track Employees</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="qrcode_scanner_desk_r.php">Scanner</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../logout.php">Logout</a>
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
    <script type="text/javascript">
        let checkboxStates = {};

        function getRequestCheckboxes() {
            return Array.from(document.querySelectorAll('input[name="selected[]"]'));
        }

        function updateBulkSelectionState() {
            const checkboxes = getRequestCheckboxes();
            const selected = checkboxes.filter(checkbox => checkbox.checked);
            const selectAll = document.getElementById('selectAllRequests');
            const selectedIdsInput = document.getElementById('selected_ids_input');

            if (selectedIdsInput) {
                selectedIdsInput.value = JSON.stringify(selected.map(checkbox => checkbox.value));
            }

            if (selectAll) {
                selectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
                selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
            }
        }

        function saveCheckboxStates() {
            checkboxStates = {};
            getRequestCheckboxes().forEach(checkbox => {
                checkboxStates[checkbox.value] = checkbox.checked;
            });
        }

        function restoreCheckboxStates() {
            getRequestCheckboxes().forEach(checkbox => {
                if (checkboxStates[checkbox.value] !== undefined) {
                    checkbox.checked = checkboxStates[checkbox.value];
                }
            });
            updateBulkSelectionState();
        }

        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'selected[]') {
                saveCheckboxStates();
                updateBulkSelectionState();
            }
        }, true);

        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAllRequests');
            if (!selectAll) return;

            selectAll.addEventListener('change', function() {
                getRequestCheckboxes().forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                    checkboxStates[checkbox.value] = selectAll.checked;
                });
                updateBulkSelectionState();
            });

            updateBulkSelectionState();
        });

        function loadDoc() {
            function poll() {
                if (document.hidden) return;

                // Save current checkbox states before AJAX call
                saveCheckboxStates();

                var xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("table-body").innerHTML = this.responseText;
                        restoreCheckboxStates();
                    }
                };
                xhttp.open("GET", "data_r.php", true);
                xhttp.send();
            }

            poll();
            setInterval(poll, 30000);
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) poll();
            });
        }

        loadDoc();
    </script>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 id="pen_label">Pending Request</h2>
            <button id="acceptAllBtn" class="btn btn-success" data-toggle="modal" data-target="#acceptModal">Process Selected</button>
        </div>

        <div class="p-5 rounded shadow">
            <div class="table-responsive">
                <table class="table .table-hover" id="table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Position</th>
                            <th scope="col">Destination</th>
                            <th scope="col">Type of Request</th>
                            <th scope="col">Status</th>
                            <th scope="col">
                                <label class="mb-0">
                                    <input type="checkbox" id="selectAllRequests">
                                    Select all
                                </label>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                            <tr>
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
                                <td>
                                    <button type="button" class="btn btn-info btn-sm" data-view-request="<?= (int) $row['id']; ?>">View</button>
                                    <input type="checkbox" name="selected[]" value="<?= $row['id']; ?>" class="form-check-input ml-2">
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="modal fade" id="acceptModal" tabindex="-1" role="dialog" aria-labelledby="acceptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="acceptModalLabel">Process Selected Requests</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>You are about to process the following requests:</p>
                    <div id="selectedRequestsList" class="mb-4">
                        <!-- Selected requests will be listed here -->
                    </div>

                    <form id="batchApprovalForm" action="" method="POST">
                        <input type="hidden" name="selected_ids" id="selected_ids_input">

                        <div class="card">
                            <div class="card-header">
                                <h4>Batch Approval Details</h4>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3" id="timeInputs">
                                    <label>Fixed Time for Pass Slip:</label><br>
                                    <label for="fix_hours">Hours:</label>
                                    <input type="number" class="form" id="fix_hours" name="fix_hours" min="0" max="4" value="0">
                                    <label for="fix_minutes">Minutes:</label>
                                    <input type="number" class="form" id="fix_minutes" name="fix_minutes" min="0" max="59" value="20">
                                </div>
                                <div class="form-group mb-3">
                                    <label for="sel1">Status:</label>
                                    <select class="form-control" id="sel1" name="status" required>
                                        <option value="Partially Approved">Partially Approved</option>
                                        <option value="Declined">Declined</option>
                                    </select>
                                </div>

                                <div class="form-group mb-3" id="batchDeclineReason" hidden>
                                    <label for="batch_decline_reason">Decline Reason:</label>
                                    <textarea class="form-control" id="batch_decline_reason" name="decline_reason" rows="3" placeholder="Reason applied to all selected requests"></textarea>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="sel2">Confirmed By:</label>
                                    <select class="form-control" id="sel2" name="confirmed_by" required>
                                        <option>CAGULADA RENE ART</option>
                                        <option>CASAS RUBY</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h4>Selected Requests Details</h4>
                            </div>
                            <div class="card-body">
                                <div id="detailedRequestsList">
                                    <!-- Detailed request information will be loaded here -->
                                    <div class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- Update the button to submit the form directly -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" form="batchApprovalForm" name="approve_multiple_req" id="approveAllBtn" class="btn btn-success">
                        <span id="approveButtonText">Approve Selected</span>
                        <span id="loadingSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>


            </div>
        </div>
    </div>
    <dialog id="requestDetailModal">
        <div class="modal-card">
            <header>
                <div>
                    <p class="eyebrow">Request details</p>
                    <h2>Review Request</h2>
                </div>
                <button type="button" class="icon-btn" id="closeDetailModal" aria-label="Close">x</button>
            </header>
            <div id="requestDetailBody" class="detail-modal-body">
                <div class="text-center py-4">Loading request details...</div>
            </div>
        </div>
    </dialog>
    <script>
        document.getElementById('acceptAllBtn').addEventListener('click', function(e) {
            // Get all checked checkboxes
            const selectedCheckboxes = document.querySelectorAll('input[name="selected[]"]:checked');

            if (selectedCheckboxes.length === 0) {
                e.preventDefault(); // Prevent modal from opening
                alert('Please select at least one request to accept.');
                return;
            }

            // Collect all selected IDs and names
            const selectedIds = [];
            const selectedNames = [];

            selectedCheckboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const name = row.cells[0].textContent.trim();
                selectedIds.push(checkbox.value);
                selectedNames.push(name);
            });

            // Display selected requests in the modal
            const listContainer = document.getElementById('selectedRequestsList');
            listContainer.innerHTML = '<ul class="list-group">';

            selectedNames.forEach((name, index) => {
                listContainer.innerHTML += `<li class="list-group-item">${name} (ID: ${selectedIds[index]})</li>`;
            });

            listContainer.innerHTML += '</ul>';

            // Store selected IDs in the hidden input
            document.getElementById('selected_ids_input').value = JSON.stringify(selectedIds);

            // Load detailed information for each selected request
            loadDetailedRequestInfo(selectedIds);
        });

        function loadDetailedRequestInfo(ids) {
            const detailedListContainer = document.getElementById('detailedRequestsList');

            // Create a request to fetch detailed information
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../get_request_details.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function() {
                if (this.readyState === 4 && this.status === 200) {
                    detailedListContainer.innerHTML = this.responseText;

                    // Check Type of Business and adjust inputs
                    const hasOfficial = detailedListContainer.textContent.includes('Official Business');
                    const hasPersonal = detailedListContainer.textContent.includes('Personal');
                    const hoursInput = document.getElementById('fix_hours');
                    const minutesInput = document.getElementById('fix_minutes');

                    if (hasOfficial) {
                        hoursInput.max = 4;
                        hoursInput.disabled = false;
                    } else if (hasPersonal) {
                        hoursInput.disabled = true;
                        hoursInput.value = '';
                        minutesInput.max = 30;
                    }
                }
            };

            xhr.send('ids=' + JSON.stringify(ids));
        }

    </script>
    <!-- Add JavaScript for form validation and submission -->
    <!-- Add JavaScript for form validation and submission -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get references to form elements
            const batchApprovalForm = document.getElementById('batchApprovalForm');
            const approveAllBtn = document.getElementById('approveAllBtn');
            const selectedIdsInput = document.getElementById('selected_ids_input');

            function toggleEstTimeRequirement() {
                const status = document.getElementById('sel1').value;
                const timeContainer = document.getElementById('timeInputs');
                const declineReason = document.getElementById('batchDeclineReason');
                const declineReasonInput = document.getElementById('batch_decline_reason');
                const buttonText = document.getElementById('approveButtonText');
                const isDeclined = status === 'Declined';

                if (timeContainer) {
                    timeContainer.style.display = isDeclined ? 'none' : 'block';
                }
                if (declineReason) declineReason.hidden = !isDeclined;
                if (declineReasonInput) declineReasonInput.required = isDeclined;
                if (buttonText) buttonText.textContent = isDeclined ? 'Decline Selected' : 'Approve Selected';
            }

            // Add event listener to status dropdown
            const statusSelect = document.getElementById('sel1');
            if (statusSelect) {
                statusSelect.addEventListener('change', toggleEstTimeRequirement);
                // Initialize on page load
                toggleEstTimeRequirement();
            }

            // Only add event listeners if elements exist
            if (batchApprovalForm) {
                batchApprovalForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent default form submission

                    // Get form fields
                    const status = document.getElementById('sel1').value;
                    const confirmedBy = document.getElementById('sel2').value;
                    const selectedIds = selectedIdsInput ? selectedIdsInput.value : '[]';

                    console.log("Form submission - Selected IDs:", selectedIds);
                    console.log("Form submission - Status:", status);
                    console.log("Form submission - Confirmed By:", confirmedBy);

                    if (!status) {
                        alert('Please select a status.');
                        return false;
                    }

                    if (!confirmedBy) {
                        alert('Please select who confirmed this request.');
                        return false;
                    }

                    if (!selectedIds || selectedIds === '[]') {
                        alert('No requests selected for approval.');
                        return false;
                    }

                    // Show loading state
                    if (approveAllBtn) {
                        const buttonText = document.getElementById('approveButtonText');
                        const spinner = document.getElementById('loadingSpinner');

                        approveAllBtn.disabled = true;
                        if (buttonText) buttonText.textContent = 'Processing...';
                        if (spinner) spinner.classList.remove('d-none');
                    }

                    // Submit the form using AJAX
                    const formData = new FormData(this);

                    // Log the form data being sent
                    console.log("FormData entries:");
                    for (let pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }

                    fetch('../admin_approver/bulk_accept.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            console.log("Response status:", response.status);
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log("Response data:", data);
                            alert(data.message || 'Requests processed successfully!');
                            if (data.success) {
                                window.location.href = 'index_r.php';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('There was an error processing your request. Please try again.');
                        })
                        .finally(() => {
                            if (!approveAllBtn) return;
                            approveAllBtn.disabled = false;
                            const buttonText = document.getElementById('approveButtonText');
                            const spinner = document.getElementById('loadingSpinner');
                            if (buttonText) {
                                buttonText.textContent = document.getElementById('sel1').value === 'Declined'
                                    ? 'Decline Selected'
                                    : 'Approve Selected';
                            }
                            if (spinner) spinner.classList.add('d-none');
                        });
                });
            }

            // Additional validation when the Approve All button is clicked directly
            if (approveAllBtn) {
                approveAllBtn.addEventListener('click', function() {
                    // This is a backup validation in case the form submission event doesn't trigger
                    const status = document.getElementById('sel1');
                    const confirmedBy = document.getElementById('sel2');

                    if (!status || !status.value) {
                        alert('Please select a status.');
                        return false;
                    }

                    if (!confirmedBy || !confirmedBy.value) {
                        alert('Please select who confirmed this request.');
                        return false;
                    }
                });
            }

            // Add event listener to the Accept All button that opens the modal
            const acceptAllBtn = document.getElementById('acceptAllBtn');
            if (acceptAllBtn) {
                acceptAllBtn.addEventListener('click', function(e) {
                    // Get all checked checkboxes
                    const selectedCheckboxes = document.querySelectorAll('input[name="selected[]"]:checked');
                    console.log("Accept All clicked - Selected checkboxes:", selectedCheckboxes.length);

                    if (selectedCheckboxes.length === 0) {
                        e.preventDefault(); // Prevent modal from opening
                        alert('Please select at least one request to accept.');
                        return;
                    }

                    // Collect all selected IDs and names
                    const selectedIds = [];
                    const selectedNames = [];

                    selectedCheckboxes.forEach(checkbox => {
                        const row = checkbox.closest('tr');
                        if (row && row.cells && row.cells[0]) {
                            const name = row.cells[0].textContent.trim();
                            selectedIds.push(checkbox.value);
                            selectedNames.push(name);
                        }
                    });

                    console.log("Selected IDs:", selectedIds);
                    console.log("Selected Names:", selectedNames);

                    // Display selected requests in the modal
                    const listContainer = document.getElementById('selectedRequestsList');
                    if (listContainer) {
                        listContainer.innerHTML = '<ul class="list-group">';

                        selectedNames.forEach((name, index) => {
                            listContainer.innerHTML += `<li class="list-group-item">${name} (ID: ${selectedIds[index]})</li>`;
                        });

                        listContainer.innerHTML += '</ul>';
                    }

                    // Store selected IDs in the hidden input
                    if (selectedIdsInput) {
                        selectedIdsInput.value = JSON.stringify(selectedIds);
                        console.log("Set selected_ids_input value:", selectedIdsInput.value);
                    }

                    // Load detailed information for each selected request
                    if (typeof loadDetailedRequestInfo === 'function') {
                        loadDetailedRequestInfo(selectedIds);
                    }
                });
            }
        });

        // Function to load detailed request info (defined outside to be accessible)
        function loadDetailedRequestInfo(ids) {
            console.log("Loading detailed info for IDs:", ids);
            const detailedListContainer = document.getElementById('detailedRequestsList');
            if (!detailedListContainer) return;

            // Create a request to fetch detailed information
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../get_request_details.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function() {
                if (this.readyState === 4) {
                    console.log("XHR status:", this.status);
                    if (this.status === 200) {
                        detailedListContainer.innerHTML = this.responseText;
                    } else {
                        console.error("Error loading request details:", this.statusText);
                        detailedListContainer.innerHTML = '<div class="alert alert-danger">Error loading request details</div>';
                    }
                }
            };

            xhr.send('ids=' + JSON.stringify(ids));
        }
    </script>

    <script>
        // More aggressive approach for Bootstrap modal
        document.addEventListener('DOMContentLoaded', function() {
            // Function to focus on the esttime input
            function focusOnEsttime() {
                const esttimeInput = document.getElementById('esttime');
                if (esttimeInput) {
                    console.log('Focusing on esttime input');
                    setTimeout(() => {
                        esttimeInput.focus();
                    }, 500); // Delay to ensure modal is fully rendered
                }
            }

            // Multiple ways to detect when the modal is shown

            // 1. Bootstrap event
            $('#acceptModal').on('shown.bs.modal', focusOnEsttime);

            // 2. Click event on the button that opens the modal
            document.getElementById('acceptAllBtn')?.addEventListener('click', function() {
                console.log('Accept All button clicked');
                setTimeout(focusOnEsttime, 700); // Delay to ensure modal is shown
            });

            // 3. Mutation observer to detect when modal becomes visible
            const modalElement = document.getElementById('acceptModal');
            if (modalElement) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class' &&
                            modalElement.classList.contains('show')) {
                            console.log('Modal shown detected by observer');
                            focusOnEsttime();
                        }
                    });
                });

                observer.observe(modalElement, {
                    attributes: true
                });
            }

            // Global key handler for Enter key when modal is visible
            document.addEventListener('keydown', function(event) {
                const modal = document.getElementById('acceptModal');
                if (modal &&
                    (modal.classList.contains('show') || modal.style.display === 'block') &&
                    event.key === 'Enter') {

                    // Don't trigger for textareas
                    if (event.target.tagName.toLowerCase() === 'textarea') {
                        return;
                    }

                    console.log('Global Enter key handler triggered');
                    event.preventDefault();

                    // Get the approve button and click it
                    const approveBtn = document.getElementById('approveAllBtn');
                    if (approveBtn) {
                        approveBtn.click();
                    }
                }
            });

            console.log('Enhanced Bootstrap modal handlers initialized');
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const detailModal = document.getElementById('requestDetailModal');
            const detailBody = document.getElementById('requestDetailBody');

            async function openRequestDetails(id) {
                detailBody.innerHTML = '<div class="text-center py-4">Loading request details...</div>';
                detailModal.showModal();

                try {
                    const response = await fetch(`request_details_modal.php?id=${encodeURIComponent(id)}`, { cache: 'no-store' });
                    const html = await response.text();
                    detailBody.innerHTML = html || '<div class="alert alert-warning mb-0">Unable to load request details.</div>';
                    if (response.ok) {
                        bindSingleRequestForm();
                    }
                } catch (error) {
                    detailBody.innerHTML = '<div class="alert alert-danger mb-0">Unable to load request details.</div>';
                }
            }

            function bindSingleRequestForm() {
                const status = detailBody.querySelector('[data-single-status]');
                const reason = detailBody.querySelector('[data-single-decline-reason]');
                const reasonInput = reason ? reason.querySelector('textarea') : null;
                const approve = detailBody.querySelector('[data-single-approve]');
                const decline = detailBody.querySelector('[data-single-decline]');
                const timeFields = detailBody.querySelectorAll('[data-single-time]');

                function syncSingleAction() {
                    const isDeclined = status && status.value === 'Declined';
                    if (reason) reason.hidden = !isDeclined;
                    if (reasonInput) reasonInput.required = isDeclined;
                    if (approve) approve.hidden = isDeclined;
                    if (decline) decline.hidden = !isDeclined;
                    timeFields.forEach(field => {
                        field.hidden = isDeclined || field.hasAttribute('data-personal-hidden');
                    });
                }

                if (status) status.addEventListener('change', syncSingleAction);
                detailBody.querySelectorAll('[data-dismiss="modal"], [data-close-detail]').forEach(button => {
                    button.addEventListener('click', () => detailModal.close());
                });
                syncSingleAction();
            }

            document.getElementById('closeDetailModal').addEventListener('click', () => detailModal.close());

            document.addEventListener('click', function(event) {
                const button = event.target.closest('[data-view-request]');
                if (!button) return;

                event.preventDefault();
                openRequestDetails(button.dataset.viewRequest);
            });
        });
    </script>


</body>

</html>
