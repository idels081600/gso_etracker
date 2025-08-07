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
    // Prevent duplicate submissions by checking if data already exists
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $contact_no = mysqli_real_escape_string($conn, $_POST['contact']);
    $date = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_POST['datepicker'])));
    $no_of_tents = mysqli_real_escape_string($conn, $_POST['tent_no']);
    $purpose = mysqli_real_escape_string($conn, $_POST['No_tents']);
    $location = "";
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $duration = mysqli_real_escape_string($conn, $_POST['duration']);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (!empty($_POST['other'])) {
            $location = mysqli_real_escape_string($conn, $_POST['other']);
        } else {
            $location = mysqli_real_escape_string($conn, $_POST['Location']);
        }
    }

    // Check for duplicate entry before inserting
    $check_query = "SELECT id FROM tent WHERE name = '$name' AND contact_no = '$contact_no' AND date = '$date' AND location = '$location' LIMIT 1";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        // Duplicate found, redirect without inserting
        header("Location: tracking.php?error=duplicate");
        exit();
    }

    // Calculate retrieval date
    if (!empty($duration) && is_numeric($duration)) {
        $retrieval_date = date('Y-m-d', strtotime($date . ' + ' . $duration . ' days'));
        $retrieval_date_value = "'$retrieval_date'";
    } else {
        $retrieval_date_value = "NULL";
    }

    $query = "INSERT INTO tent(name, contact_no, no_of_tents, purpose, location, address, status, date, retrieval_date) 
              VALUES ('$name', '$contact_no', '$no_of_tents', '$purpose', '$location', '$address', 'Pending', '$date', $retrieval_date_value)";

    $query_run = mysqli_query($conn, $query);
    if ($query_run) {
        header("Location: tracking.php?success=1");
        exit();
    } else {
        header("Location: tracking.php?error=insert_failed");
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
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="sidebar_asset.css">
    <link rel="stylesheet" href="tracking_style.css">
    <link rel="stylesheet" href="style_box.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- jQuery -->
    <script>
        $(function() {
            // Global variables
            let updateInProgress = false;
            let queueProcessingInProgress = false;
            const MAX_RETRY_ATTEMPTS = 3;
            const MAX_QUEUE_RETRY_ATTEMPTS = 2;
            const RETRY_DELAY_BASE = 1000;
            const CLEANUP_INTERVAL = 300000; // 5 minutes
            let retryAttempts = new Map();
            let pendingUpdates = [];

            /**
             * Updates the color of date elements based on their status and triggers automatic status updates
             */
            function updateDateColors() {
                // Prevent overlapping requests
                if (updateInProgress) {
                    console.log("Update already in progress, skipping...");
                    return;
                }

                updateInProgress = true;
                showUpdateStatus("Checking date statuses...", "info");

                const today = moment.utc();
                let redDates = [];
                let orangeDates = [];

                try {
                    $(".retrieval-date").each(function() {
                        try {
                            const $element = $(this);
                            const dateText = $element.text().trim();

                            // Validate date text is not empty
                            if (!dateText) {
                                console.warn("Empty date text found, skipping element");
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            // Parse and validate date
                            const date = moment.utc(dateText);
                            if (!date.isValid()) {
                                console.warn(
                                    `Invalid date format: "${dateText}", skipping element`
                                );
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            const row = $element.closest("tr");

                            // Validate row exists
                            if (row.length === 0) {
                                console.warn("No parent row found for date element, skipping");
                                return true; // Continue to next iteration
                            }

                            const dropdown = row.find("select.status-dropdown");

                            // Validate dropdown exists
                            if (dropdown.length === 0) {
                                console.warn("No status dropdown found in row, skipping");
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            const selectedOption = dropdown.find(":selected");
                            const selectedValue = selectedOption.val();
                            const id = selectedOption.data("id");

                            // Validate selection exists and has value
                            if (!selectedValue || selectedValue.trim() === "") {
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            // Validate ID exists
                            if (!id || id === undefined) {
                                console.warn(
                                    `No data-id found for selected option in dropdown, skipping`
                                );
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            const timeDiff = date.valueOf() - today.valueOf();
                            const diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24));

                            // Define which statuses can be auto-updated
                            const autoUpdateStatuses = ["Installed", "Pending"];

                            if (diffDays < 0) {
                                // Date is in the past (overdue)
                                $element.css("color", "red");
                                const tent_no = row.find("td:first").text().trim();

                                // Validate tent number and status for auto-update
                                if (tent_no !== "" && autoUpdateStatuses.includes(selectedValue)) {
                                    redDates.push({
                                        tent_no: tent_no,
                                        id: id,
                                        row: row,
                                        dateText: dateText,
                                        selectedValue: selectedValue,
                                    });
                                }
                            } else if (diffDays === 0) {
                                // Date is today
                                $element.css("color", "orange");
                                const tent_no = row.find("td:first").text().trim();

                                // Validate tent number and status for auto-update
                                if (tent_no !== "" && autoUpdateStatuses.includes(selectedValue)) {
                                    orangeDates.push({
                                        tent_no: tent_no,
                                        id: id,
                                        row: row,
                                        dateText: dateText,
                                        selectedValue: selectedValue,
                                    });
                                }
                            } else {
                                // Date is in the future
                                $element.css("color", "");
                            }
                        } catch (elementError) {
                            console.error("Error processing date element:", elementError);
                            // Reset color on error to prevent stale styling
                            $(this).css("color", "");
                        }
                    });

                    console.log(
                        `Date processing complete. Red dates: ${redDates.length}, Orange dates: ${orangeDates.length}`
                    );

                    // Process updates with proper error handling
                    processStatusUpdates(redDates, orangeDates);
                } catch (error) {
                    console.error("Error in updateDateColors:", error);
                    showUpdateStatus(
                        "Error checking date statuses. Please refresh the page.",
                        "error"
                    );
                } finally {
                    // Ensure updateInProgress is reset even if an error occurs
                    if (redDates.length === 0 && orangeDates.length === 0) {
                        updateInProgress = false;
                        hideUpdateStatus();
                    }
                }
            }

            /**
             * Processes status updates for red and orange dates
             * @param {Array} redDates - Array of overdue date items
             * @param {Array} orangeDates - Array of due today date items
             */
            function processStatusUpdates(redDates, orangeDates) {
                const totalUpdates = redDates.length + orangeDates.length;
                let completedUpdates = 0;

                if (totalUpdates === 0) {
                    updateInProgress = false;
                    hideUpdateStatus();
                    return;
                }

                console.log(`Processing ${totalUpdates} status updates...`);

                // Process red dates (overdue)
                if (Array.isArray(redDates) && redDates.length > 0) {
                    makeAjaxRequestWithRetry({
                                type: "POST",
                                url: "update_status_duration.php",
                                data: {
                                    redDates: JSON.stringify(redDates),
                                },
                                timeout: 10000,
                                dataType: "json",
                            },
                            "red"
                        )
                        .then((response) => {
                            handleUpdateSuccess(redDates, "Retrieved", response);
                            completedUpdates++;
                            checkAllUpdatesComplete(completedUpdates, totalUpdates);
                        })
                        .catch((error) => {
                            handleUpdateError(redDates, error, "red");
                            completedUpdates++;
                            checkAllUpdatesComplete(completedUpdates, totalUpdates);
                        });
                }

                // Process orange dates (due today)
                if (Array.isArray(orangeDates) && orangeDates.length > 0) {
                    makeAjaxRequestWithRetry({
                                type: "POST",
                                url: "update_status_duration.php",
                                data: {
                                    orangeDates: JSON.stringify(orangeDates),
                                },
                                timeout: 10000,
                                dataType: "json",
                            },
                            "orange"
                        )
                        .then((response) => {
                            handleUpdateSuccess(orangeDates, "For Retrieval", response);
                            completedUpdates++;
                            checkAllUpdatesComplete(completedUpdates, totalUpdates);
                        })
                        .catch((error) => {
                            handleUpdateError(orangeDates, error, "orange");
                            completedUpdates++;
                            checkAllUpdatesComplete(completedUpdates, totalUpdates);
                        });
                }
            }

            /**
             * Makes AJAX requests with automatic retry logic
             * @param {Object} ajaxConfig - jQuery AJAX configuration object
             * @param {string} requestType - Type of request for tracking
             * @param {number} retryCount - Current retry attempt number
             * @returns {Promise} Promise that resolves on success or rejects after max retries
             */
            function makeAjaxRequestWithRetry(ajaxConfig, requestType, retryCount = 0) {
                return new Promise((resolve, reject) => {
                    const requestKey = `${requestType}_${Date.now()}_${retryCount}`;

                    // Add request tracking
                    console.log(
                        `Making AJAX request (${requestType}), attempt ${retryCount + 1}`
                    );

                    // Validate ajaxConfig
                    if (!ajaxConfig || !ajaxConfig.url) {
                        reject({
                            error: "Invalid AJAX configuration",
                            requestType: requestType,
                        });
                        return;
                    }

                    $.ajax(ajaxConfig)
                        .done(function(response) {
                            console.log(`AJAX success (${requestType}):`, response);
                            retryAttempts.delete(requestKey);
                            resolve(response);
                        })
                        .fail(function(xhr, status, error) {
                            console.error(
                                `AJAX error (${requestType}):`,
                                status,
                                error,
                                xhr.responseText
                            );

                            if (retryCount < MAX_RETRY_ATTEMPTS) {
                                const delay = RETRY_DELAY_BASE * Math.pow(2, retryCount); // Exponential backoff

                                console.log(`Retrying in ${delay}ms...`);
                                showUpdateStatus(
                                    `Request failed, retrying in ${Math.ceil(delay / 1000)}s...`,
                                    "warning"
                                );

                                retryAttempts.set(requestKey, {
                                    requestType: requestType,
                                    retryCount: retryCount + 1,
                                    timestamp: Date.now(),
                                });

                                setTimeout(() => {
                                    makeAjaxRequestWithRetry(ajaxConfig, requestType, retryCount + 1)
                                        .then(resolve)
                                        .catch(reject);
                                }, delay);
                            } else {
                                retryAttempts.delete(requestKey);
                                reject({
                                    xhr: xhr,
                                    status: status,
                                    error: error,
                                    requestType: requestType,
                                    responseText: xhr.responseText,
                                });
                            }
                        });
                });
            }

            /**
             * Handles successful update responses
             * @param {Array} dates - Array of date items that were updated
             * @param {string} newStatus - New status value to set
             * @param {Object} response - Server response object
             */
            function handleUpdateSuccess(dates, newStatus, response) {
                try {
                    console.log(`Successfully updated ${dates.length} items to ${newStatus}`);

                    // Validate response
                    if (response && response.error) {
                        console.error("Server returned error:", response.error);
                        handleUpdateError(dates, {
                            error: response.error
                        }, "server");
                        return;
                    }

                    // Update DOM to reflect changes
                    dates.forEach((dateItem) => {
                        try {
                            if (dateItem.row && dateItem.row.length > 0) {
                                const dropdown = dateItem.row.find("select.status-dropdown");

                                if (dropdown.length > 0) {
                                    dropdown.val(newStatus);

                                    // Add visual feedback
                                    dateItem.row.addClass("status-updated");
                                    setTimeout(() => {
                                        if (dateItem.row.length > 0) {
                                            dateItem.row.removeClass("status-updated");
                                        }
                                    }, 2000);
                                }
                            }
                        } catch (itemError) {
                            console.error("Error updating individual item:", itemError, dateItem);
                        }
                    });

                    showUpdateStatus(
                        `Updated ${dates.length} tent(s) to ${newStatus}`,
                        "success"
                    );
                } catch (error) {
                    console.error("Error in handleUpdateSuccess:", error);
                    showUpdateStatus(
                        "Update completed but with errors. Please refresh to verify.",
                        "warning"
                    );
                }
            }

            /**
             * Handles update errors and applies error styling
             * @param {Array} dates - Array of date items that failed to update
             * @param {Object} error - Error object
             * @param {string} requestType - Type of request that failed
             */
            function handleUpdateError(dates, error, requestType) {
                try {
                    console.error(
                        `Failed to update ${requestType} dates after ${MAX_RETRY_ATTEMPTS} attempts:`,
                        error
                    );

                    // Add error styling to affected rows
                    dates.forEach((dateItem) => {
                        try {
                            if (dateItem.row && dateItem.row.length > 0) {
                                dateItem.row.addClass("status-error");
                                setTimeout(() => {
                                    if (dateItem.row.length > 0) {
                                        dateItem.row.removeClass("status-error");
                                    }
                                }, 5000);
                            }
                        } catch (itemError) {
                            console.error(
                                "Error adding error styling to item:",
                                itemError,
                                dateItem
                            );
                        }
                    });

                    showUpdateStatus(
                        `Failed to update ${dates.length} tent(s). Please refresh and try again.`,
                        "error"
                    );

                    // Queue for retry on next interval (only if not a server error)
                    if (requestType !== "server") {
                        queueFailedUpdate(dates, requestType);
                    }
                } catch (error) {
                    console.error("Error in handleUpdateError:", error);
                }
            }

            /**
             * Checks if all updates are complete and processes queued updates if needed
             * @param {number} completed - Number of completed updates
             * @param {number} total - Total number of updates
             */
            function checkAllUpdatesComplete(completed, total) {
                try {
                    console.log(`Updates completed: ${completed}/${total}`);

                    if (completed >= total) {
                        // Process any queued updates if not already processing
                        if (pendingUpdates.length > 0 && !queueProcessingInProgress) {
                            console.log(`Processing ${pendingUpdates.length} queued updates`);
                            queueProcessingInProgress = true;
                            const queued = [...pendingUpdates];
                            pendingUpdates = [];

                            setTimeout(() => {
                                processQueuedUpdates(queued);
                            }, 1000);
                        } else {
                            updateInProgress = false;
                            hideUpdateStatus();
                            console.log("All updates completed successfully");
                        }
                    }
                } catch (error) {
                    console.error("Error in checkAllUpdatesComplete:", error);
                    updateInProgress = false;
                    queueProcessingInProgress = false;
                    hideUpdateStatus();
                }
            }

            /**
             * Queues failed updates for retry with retry count tracking
             * @param {Array} dates - Array of date items that failed
             * @param {string} requestType - Type of request that failed
             */
            function queueFailedUpdate(dates, requestType) {
                try {
                    if (Array.isArray(dates) && dates.length > 0) {
                        pendingUpdates.push({
                            dates: dates,
                            requestType: requestType,
                            timestamp: Date.now(),
                            retryCount: 0,
                        });
                        console.log(`Queued ${dates.length} failed updates for retry`);
                    }
                } catch (error) {
                    console.error("Error queuing failed update:", error);
                }
            }

            /**
             * Processes queued updates with retry limit to prevent infinite recursion
             * @param {Array} queuedUpdates - Array of queued update objects
             */
            function processQueuedUpdates(queuedUpdates) {
                try {
                    if (!Array.isArray(queuedUpdates) || queuedUpdates.length === 0) {
                        queueProcessingInProgress = false;
                        return;
                    }

                    let processedCount = 0;
                    const totalQueued = queuedUpdates.length;

                    queuedUpdates.forEach((update) => {
                        try {
                            // Check retry limit to prevent infinite recursion
                            if (update.retryCount >= MAX_QUEUE_RETRY_ATTEMPTS) {
                                console.warn(
                                    `Queue retry limit reached for ${update.dates.length} ${update.requestType} updates, skipping`
                                );
                                processedCount++;
                                if (processedCount >= totalQueued) {
                                    queueProcessingInProgress = false;
                                }
                                return;
                            }

                            // Increment retry count
                            update.retryCount++;

                            if (update.requestType === "red") {
                                processStatusUpdates(update.dates, []);
                            } else if (update.requestType === "orange") {
                                processStatusUpdates([], update.dates);
                            }

                            processedCount++;
                            if (processedCount >= totalQueued) {
                                queueProcessingInProgress = false;
                            }
                        } catch (updateError) {
                            console.error("Error processing queued update:", updateError, update);
                            processedCount++;
                            if (processedCount >= totalQueued) {
                                queueProcessingInProgress = false;
                            }
                        }
                    });
                } catch (error) {
                    console.error("Error in processQueuedUpdates:", error);
                    queueProcessingInProgress = false;
                }
            }

            /**
             * Shows update status messages to the user
             * @param {string} message - Status message to display
             * @param {string} type - Type of status (info, success, warning, error)
             */
            function showUpdateStatus(message, type) {
                try {
                    let statusElement = $("#update-status-indicator");

                    if (statusElement.length === 0) {
                        statusElement = $(`
          <div id="update-status-indicator" class="alert alert-info" style="
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            display: none;
          ">
            <span class="status-message"></span>
            <div class="spinner-border spinner-border-sm ms-2" role="status" style="display: none;">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
        `);
                        $("body").append(statusElement);
                    }

                    statusElement.removeClass(
                        "alert-info alert-success alert-warning alert-danger"
                    );
                    statusElement.addClass(`alert-${type === "error" ? "danger" : type}`);
                    statusElement.find(".status-message").text(message);

                    if (type === "info") {
                        statusElement.find(".spinner-border").show();
                    } else {
                        statusElement.find(".spinner-border").hide();
                    }

                    statusElement.fadeIn();

                    // Auto-hide success and warning messages
                    if (type === "success" || type === "warning") {
                        setTimeout(() => {
                            hideUpdateStatus();
                        }, 3000);
                    }
                } catch (error) {
                    console.error("Error showing update status:", error);
                    // Fallback to console for critical errors
                    console.log(`STATUS: ${message} (${type})`);
                }
            }

            /**
             * Hides the update status indicator
             */
            function hideUpdateStatus() {
                try {
                    const statusElement = $("#update-status-indicator");
                    if (statusElement.length > 0) {
                        statusElement.fadeOut();
                    }
                } catch (error) {
                    console.error("Error hiding update status:", error);
                }
            }

            /**
             * Cleans up old retry attempts to prevent memory leaks
             */
            function cleanupOldRetryAttempts() {
                try {
                    const tenMinutesAgo = Date.now() - 10 * 60 * 1000;
                    let cleanedCount = 0;

                    for (const [key, attempt] of retryAttempts.entries()) {
                        if (attempt.timestamp < tenMinutesAgo) {
                            retryAttempts.delete(key);
                            cleanedCount++;
                        }
                    }

                    if (cleanedCount > 0) {
                        console.log(`Cleaned up ${cleanedCount} old retry attempts`);
                    }
                } catch (error) {
                    console.error("Error cleaning up retry attempts:", error);
                }
            }

            /**
             * Syncs status dropdowns with server state
             */
            function syncStatusDropdowns() {
                try {
                    $.ajax({
                        url: "fetch_all_tent_status.php",
                        type: "GET",
                        dataType: "json",
                        timeout: 5000,
                        success: function(data) {
                            try {
                                if (Array.isArray(data)) {
                                    data.forEach((item) => {
                                        try {
                                            if (item && item.id !== undefined) {
                                                const dropdown = $(
                                                    `.status-dropdown option[data-id="${item.id}"]`
                                                ).parent();

                                                if (dropdown.length > 0 && dropdown.val() !== item.status) {
                                                    dropdown.val(item.status);
                                                    console.log(
                                                        `Synced status for ID ${item.id} to ${item.status}`
                                                    );
                                                }
                                            }
                                        } catch (itemError) {
                                            console.error(
                                                "Error syncing individual dropdown:",
                                                itemError,
                                                item
                                            );
                                        }
                                    });
                                } else {
                                    console.warn("Invalid data format received from server:", data);
                                }
                            } catch (processError) {
                                console.error("Error processing sync data:", processError);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Failed to sync status dropdowns:", status, error);
                            // Don't show user error for sync failures unless it's critical
                            if (status !== "timeout") {
                                console.error("Sync error details:", xhr.responseText);
                            }
                        },
                    });
                } catch (error) {
                    console.error("Error in syncStatusDropdowns:", error);
                }
            }

            // Initialize the system with error handling
            try {
                console.log("Initializing date color update system...");
                updateDateColors();
            } catch (initError) {
                console.error("Error during initialization:", initError);
                showUpdateStatus(
                    "System initialization failed. Please refresh the page.",
                    "error"
                );
            }

            // Set up interval with better error handling
            const updateInterval = setInterval(() => {
                try {
                    updateDateColors();
                } catch (error) {
                    console.error("Error in updateDateColors interval:", error);
                    showUpdateStatus(
                        "System error occurred. Please refresh the page.",
                        "error"
                    );
                }
            }, 60000);

            // Sync dropdowns every minute (same as date updates)
            const syncInterval = setInterval(() => {
                try {
                    syncStatusDropdowns();
                } catch (error) {
                    console.error("Error in syncStatusDropdowns interval:", error);
                }
            }, 60000);

            // Set up cleanup interval for old retry attempts
            const cleanupInterval = setInterval(() => {
                try {
                    cleanupOldRetryAttempts();
                } catch (error) {
                    console.error("Error in cleanup interval:", error);
                }
            }, CLEANUP_INTERVAL);

            // Handle page visibility changes
            document.addEventListener("visibilitychange", function() {
                try {
                    if (!document.hidden && !updateInProgress) {
                        console.log("Page became visible, checking for updates...");
                        updateDateColors();
                        syncStatusDropdowns();
                    }
                } catch (error) {
                    console.error("Error handling visibility change:", error);
                }
            });

            // Cleanup on page unload
            window.addEventListener("beforeunload", function() {
                try {
                    clearInterval(updateInterval);
                    clearInterval(syncInterval);
                    clearInterval(cleanupInterval);
                    updateInProgress = false;
                    queueProcessingInProgress = false;
                    retryAttempts.clear();
                    pendingUpdates = [];
                    console.log("Cleaned up intervals and state");
                } catch (error) {
                    console.error("Error during cleanup:", error);
                }
            });

            /**
             * Manual refresh function for debugging
             */
            window.debugUpdateDateColors = function() {
                console.log("Manual date color update triggered");
                updateInProgress = false; // Reset in case it's stuck
                queueProcessingInProgress = false; // Reset queue processing flag
                updateDateColors();
            };

            console.log("Date color update system initialized successfully");
            $(document).ready(function() {
                updateDateColors();
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
        <div id="system-status" class="alert alert-info" style="display: none; margin-bottom: 20px;">
            <i class="fas fa-info-circle"></i>
            <span id="system-status-message"></span>
        </div>

        <h1>Tent</h1>
        <ul class="tally_list" id="tallyList">
            <li class="tally" id="on_standby">
                <div class="available">Available </div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="on_stocks_value"><?php echo $on_stock; ?></h1>
                    </div>
                </div>
            </li>
            <li class="tally" id="on_field_item">
                <div class="on_field">On Field</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="on_field_value"><?php echo $on_field; ?></h1>
                    </div>
                </div>
            </li>
            <li class="tally" id="for_retrieval_item">
                <div class="for_retrieval">For Retrieval</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="for_retrieval_value"><?php echo $on_retrieval; ?></h1>
                    </div>
                </div>
            </li>
            <li class="tally" id="long_term_item">
                <div class="long_term">Long Term</div>
                <div class="info">
                    <div class="amount skeleton-loader">
                        <h1 id="long_term_value"><?php echo $longterm; ?></h1>
                    </div>
                </div>
            </li>
        </ul>


        <div class="container_table">
            <div class="container_table_content">
                <div class="d-flex justify-content-between align-items-center mb-3 px-3" style="padding-left:0;padding-right:0;">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-success" id="addButton" role="button" data-bs-toggle="modal" data-bs-target="#detailsModal">Install Tent</button>
                        <button class="btn btn-secondary" id="printButton" data-bs-toggle="modal" data-bs-target="#printModal"><i class="fas fa-print"></i> Print</button>
                    </div>
                    <input type="text" id="search-input" class="form-control w-auto" placeholder="Search...">
                </div>
                <div class="table-container">
                    <div class="table-responsive">

                        <table id="table_tent" class="table table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">Tent No.</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Retrieval Date</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Contact Number</th>
                                    <th scope="col">No. of Tents</th>
                                    <th scope="col">Purpose</th>
                                    <th scope="col">Location</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                    <tr>
                                        <td><?php echo $row["tent_no"]; ?></td>
                                        <td class="date"><?php echo $row["date"]; ?></td>
                                        <td class="retrieval-date">
                                            <?php
                                            echo $row["retrieval_date"];
                                            ?>
                                        </td>

                                        <td><?php echo $row["name"]; ?></td>
                                        <td><?php echo $row["Contact_no"]; ?></td>
                                        <td><?php echo $row["no_of_tents"]; ?></td>
                                        <td><?php echo $row["purpose"]; ?></td>
                                        <td><?php echo $row["location"]; ?></td>
                                        <?php if (empty($row['status'])) : ?>
                                            <td class="dropdown-cell">
                                                <div class="form-element">
                                                    <select class="form-select status-dropdown" name="status" id="drop_status">
                                                        <option value="" data-id="">Select Status</option>
                                                        <option value="Pending" data-id="<?php echo $row['id']; ?>">Pending</option>
                                                        <option value="Installed" data-id="<?php echo $row['id']; ?>">Installed</option>
                                                        <option value="For Retrieval" data-id="<?php echo $row['id']; ?>">For Retrieval</option>
                                                        <option value="Retrieved" data-id="<?php echo $row['id']; ?>">Retrieved</option>
                                                        <option value="Long Term" data-id="<?php echo $row['id']; ?>">Long Term</option>
                                                    </select>
                                                </div>
                                            </td>
                                        <?php else : ?>
                                            <td class="dropdown-cell">
                                                <div class="form-element">
                                                    <select class="form-select status-dropdown" name="status" id="drop_status">
                                                        <option value="" data-id="">Select Status</option>
                                                        <option value="Pending" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="Installed" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'Installed') ? 'selected' : ''; ?>>Installed</option>
                                                        <option value="For Retrieval" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'For Retrieval') ? 'selected' : ''; ?>>For Retrieval</option>
                                                        <option value="Retrieved" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'Retrieved') ? 'selected' : ''; ?>>Retrieved</option>
                                                        <option value="Long Term" data-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 'Long Term') ? 'selected' : ''; ?>>Long Term</option>
                                                    </select>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <button class="btn btn-primary btn-sm viewButton" data-id="<?php echo $row['id']; ?>" role="button" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm deleteButton" data-id="<?php echo $row['id']; ?>" role="button" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="viewEditModal" tabindex="-1" aria-labelledby="viewEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewEditModalLabel">View/Edit Tent Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="viewEditForm" autocomplete="off">
                    <div class="modal-body">
                        <div class="boxContainer">
                            <div class="boxes">
                                <?php for ($i = 1; $i <= 300; $i++): ?>
                                    <div class="box" data-box="<?php echo $i; ?>"><?php echo $i; ?></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="row g-3">
                            <!-- Add this hidden field inside your viewEditModal form -->
                            <input type="hidden" id="id" name="id">

                            <div class="col-md-6">
                                <label for="viewEditTentNo" class="form-label">Tent No.</label>
                                <input type="text" class="form-control" id="viewEditTentNo" name="tent_no" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditDate" class="form-label">Date</label>
                                <input type="text" class="form-control" id="viewEditDate" name="date">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditRetrievalDate" class="form-label">Retrieval Date</label>
                                <input type="text" class="form-control" id="viewEditRetrievalDate" name="retrieval_date">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditName" class="form-label">Name</label>
                                <input type="text" class="form-control" id="viewEditName" name="name">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditContactNo" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="viewEditContactNo" name="contact_no">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditNoOfTents" class="form-label">No. of Tents</label>
                                <input type="number" class="form-control" id="viewEditNoOfTents" name="no_of_tents">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditPurpose" class="form-label">Purpose</label>
                                <input type="text" class="form-control" id="viewEditPurpose" name="purpose">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditLocation" class="form-label">Location</label>
                                <input type="text" class="form-control" id="viewEditLocation" name="location">
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditStatus" class="form-label">Status</label>
                                <select class="form-select" id="viewEditStatus" name="status">
                                    <option value="">Select Status</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Installed">Installed</option>
                                    <option value="For Retrieval">For Retrieval</option>
                                    <option value="Retrieved">Retrieved</option>
                                    <option value="Long Term">Long Term</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="viewEditAddress" class="form-label">Address</label>
                                <input type="text" class="form-control" id="viewEditAddress" name="address">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Install Tent Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Install Tent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="form" action="" method="POST" autocomplete="off">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="tent_no" class="form-label">No. of Tents</label>
                                <input type="number" class="form-control" id="tent_no" name="tent_no" min="0" step="1" pattern="\d+">
                            </div>
                            <div class="col-md-6">
                                <label for="datepicker" class="form-label">Date</label>
                                <input type="text" class="form-control" id="datepicker" placeholder="Select a date" name="datepicker">
                            </div>
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name">
                            </div>
                            <div class="col-md-6">
                                <label for="contact" class="form-label">Contact no.</label>
                                <input type="text" class="form-control" id="contact" name="contact">
                            </div>
                            <div class="col-md-6">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address">
                            </div>
                            <div class="col-md-6">
                                <label for="Location" class="form-label">Barangay</label>
                                <select class="form-select" id="Location" name="Location">
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
                            <div class="col-md-6">
                                <label for="No_tents" class="form-label">Purpose</label>
                                <select class="form-select" id="No_tents" name="No_tents">
                                    <option value="">Select Purpose</option>
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
                                    <option value="Burial">Burial</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="tent_duration" class="form-label">Tent Duration</label>
                                <input type="number" class="form-control" id="tent_duration" name="duration">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-success" role="button" name="save_data">Submit</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Print Modal -->
    <div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printModalLabel">Print Tent Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex flex-column align-items-center gap-3">
                    <button class="btn btn-primary w-100" id="printPendingBtn">Pending</button>
                    <button class="btn btn-warning w-100" id="printRetrievalBtn">For Retrieval</button>
                </div>
            </div>
        </div>
    </div>
</body>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('#printPendingBtn').on('click', function() {
            window.open('print_tent_pending.php', '_blank');
        });
        $('#printRetrievalBtn').on('click', function() {
            window.open('print_tent_for_retrieval.php', '_blank');
        });
    });
</script>

<script src="tracking.js"></script>
<!-- Bootstrap 5 JS Bundle (with Popper) -->


</html>