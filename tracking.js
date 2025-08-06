
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

                const today = moment(); // Use local time instead of UTC
                const tomorrow = moment().add(1, 'day'); // Add tomorrow for comparison
                console.log("=== DATE CHECKING SESSION STARTED ===");
                console.log("Today's date (Local):", today.format('YYYY-MM-DD HH:mm:ss'));
                console.log("Tomorrow's date (Local):", tomorrow.format('YYYY-MM-DD HH:mm:ss'));
                console.log("Today's date (Local) start of day:", moment().startOf('day').format('YYYY-MM-DD HH:mm:ss'));
                console.log("Tomorrow's date (Local) start of day:", moment().add(1, 'day').startOf('day').format('YYYY-MM-DD HH:mm:ss'));
                
                let redDates = [];
                let orangeDates = [];
                let processedDates = [];

                try {
                    $(".retrieval-date").each(function(index) {
                        try {
                            const $element = $(this);
                            const dateText = $element.text().trim();

                            console.log(`\n--- Processing retrieval date #${index + 1} ---`);
                            console.log("Raw date text:", `"${dateText}"`);

                            // Validate date text is not empty
                            if (!dateText) {
                                console.warn("❌ Empty date text found, skipping element");
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            // Parse and validate date using local time
                            const date = moment(dateText); // Use local time instead of UTC
                            console.log("Parsed date (Local):", date.isValid() ? date.format('YYYY-MM-DD HH:mm:ss') : "INVALID");
                            console.log("Date start of day (Local):", date.isValid() ? moment(dateText).startOf('day').format('YYYY-MM-DD HH:mm:ss') : "INVALID");
                            
                            if (!date.isValid()) {
                                console.warn(`❌ Invalid date format: "${dateText}", skipping element`);
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            const row = $element.closest("tr");

                            // Validate row exists
                            if (row.length === 0) {
                                console.warn("❌ No parent row found for date element, skipping");
                                return true; // Continue to next iteration
                            }

                            const dropdown = row.find("select.status-dropdown");

                            // Validate dropdown exists
                            if (dropdown.length === 0) {
                                console.warn("❌ No status dropdown found in row, skipping");
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            const selectedOption = dropdown.find(":selected");
                            const selectedValue = selectedOption.val();
                            const id = selectedOption.data("id");
                            const tent_no = row.find("td:first").text().trim();

                            console.log("Tent number:", `"${tent_no}"`);
                            console.log("Current status:", `"${selectedValue}"`);
                            console.log("Data ID:", id);

                            // Validate selection exists and has value
                            if (!selectedValue || selectedValue.trim() === "") {
                                console.warn("❌ No status selected or empty status value");
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            // Validate ID exists
                            if (!id || id === undefined) {
                                console.warn("❌ No data-id found for selected option in dropdown, skipping");
                                $element.css("color", "");
                                return true; // Continue to next iteration
                            }

                            const timeDiff = date.valueOf() - today.valueOf();
                            const diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24));

                            // Alternative calculation using start of day (local time)
                            const todayStartOfDay = moment().startOf('day');
                            const tomorrowStartOfDay = moment().add(1, 'day').startOf('day');
                            const dateStartOfDay = moment(dateText).startOf('day');
                            const timeDiffStartOfDay = dateStartOfDay.valueOf() - todayStartOfDay.valueOf();
                            const diffDaysStartOfDay = Math.ceil(timeDiffStartOfDay / (1000 * 3600 * 24));

                            console.log("=== TIME COMPARISON DETAILS (LOCAL TIME) ===");
                            console.log("Today (full):", today.format('YYYY-MM-DD HH:mm:ss'), "valueOf:", today.valueOf());
                            console.log("Tomorrow (full):", tomorrow.format('YYYY-MM-DD HH:mm:ss'), "valueOf:", tomorrow.valueOf());
                            console.log("Date (full):", date.format('YYYY-MM-DD HH:mm:ss'), "valueOf:", date.valueOf());
                            console.log("Time difference (ms):", timeDiff);
                            console.log("Days difference (original):", diffDays);
                            console.log("---");
                            console.log("Today (start of day):", todayStartOfDay.format('YYYY-MM-DD HH:mm:ss'), "valueOf:", todayStartOfDay.valueOf());
                            console.log("Tomorrow (start of day):", tomorrowStartOfDay.format('YYYY-MM-DD HH:mm:ss'), "valueOf:", tomorrowStartOfDay.valueOf());
                            console.log("Date (start of day):", dateStartOfDay.format('YYYY-MM-DD HH:mm:ss'), "valueOf:", dateStartOfDay.valueOf());
                            console.log("Time difference start of day (ms):", timeDiffStartOfDay);
                            console.log("Days difference (start of day):", diffDaysStartOfDay);
                            
                            // Additional checks
                            const isToday = dateStartOfDay.isSame(todayStartOfDay, 'day');
                            const isTomorrow = dateStartOfDay.isSame(tomorrowStartOfDay, 'day');
                            const isPast = dateStartOfDay.isBefore(todayStartOfDay, 'day');
                            console.log("Is today?", isToday);
                            console.log("Is tomorrow?", isTomorrow);
                            console.log("Is in the past?", isPast);
                            console.log("=== END TIME COMPARISON ===");

                            // Define which statuses can be auto-updated
                            const autoUpdateStatuses = ["Installed", "Pending"];
                            const canAutoUpdate = autoUpdateStatuses.includes(selectedValue);
                            console.log("Can auto-update:", canAutoUpdate, "(Status must be 'Installed' or 'Pending')");

                            // Use more precise date comparison methods
                            let finalDiffDays;
                            let colorStatus = "";
                            let actionTaken = "";

                            if (isPast) {
                                // Date is in the past (overdue)
                                finalDiffDays = -1;
                                colorStatus = "🔴 RED (Overdue)";
                                $element.css("color", "red");

                                // Validate tent number and status for auto-update
                                if (tent_no !== "" && canAutoUpdate) {
                                    redDates.push({
                                        tent_no: tent_no,
                                        id: id,
                                        row: row,
                                        dateText: dateText,
                                        selectedValue: selectedValue,
                                    });
                                    actionTaken = "→ Queued for auto-update to 'Retrieved'";
                                } else {
                                    actionTaken = tent_no === "" 
                                        ? "→ No tent number, cannot auto-update" 
                                        : "→ Status cannot be auto-updated";
                                }
                            } else if (isToday) {
                                // Date is today
                                finalDiffDays = 0;
                                colorStatus = "🟠 ORANGE (Due Today)";
                                $element.css("color", "orange");

                                // Validate tent number and status for auto-update
                                if (tent_no !== "" && canAutoUpdate) {
                                    orangeDates.push({
                                        tent_no: tent_no,
                                        id: id,
                                        row: row,
                                        dateText: dateText,
                                        selectedValue: selectedValue,
                                    });
                                    actionTaken = "→ Queued for auto-update to 'For Retrieval'";
                                } else {
                                    actionTaken = tent_no === "" 
                                        ? "→ No tent number, cannot auto-update" 
                                        : "→ Status cannot be auto-updated";
                                }
                            } else if (isTomorrow) {
                                // Date is tomorrow
                                finalDiffDays = 1;
                                colorStatus = "🟡 YELLOW (Due Tomorrow)";
                                $element.css("color", "orange"); // Use orange color for tomorrow as well
                                actionTaken = "→ Due tomorrow, no action needed yet";
                            } else {
                                // Date is in the future (more than 1 day ahead)
                                finalDiffDays = diffDaysStartOfDay;
                                colorStatus = "⚪ NORMAL (Future Date)";
                                $element.css("color", "");
                                actionTaken = "→ No action needed";
                            }

                            console.log("FINAL DAYS DIFFERENCE USED:", finalDiffDays);

                            console.log("Color status:", colorStatus);
                            console.log("Action:", actionTaken);

                            // Store processed date info for summary
                            processedDates.push({
                                index: index + 1,
                                dateText: dateText,
                                parsedDate: date.format('YYYY-MM-DD'),
                                tentNo: tent_no,
                                status: selectedValue,
                                diffDays: finalDiffDays,
                                colorStatus: colorStatus,
                                actionTaken: actionTaken
                            });

                        } catch (elementError) {
                            console.error("❌ Error processing date element:", elementError);
                            // Reset color on error to prevent stale styling
                            $(this).css("color", "");
                        }
                    });

                    console.log("\n=== PROCESSING SUMMARY ===");
                    console.log(`Total dates processed: ${processedDates.length}`);
                    console.log(`Red dates (overdue): ${redDates.length}`);
                    console.log(`Orange dates (due today): ${orangeDates.length}`);
                    
                    if (processedDates.length > 0) {
                        console.log("\n=== DETAILED SUMMARY ===");
                        processedDates.forEach(date => {
                            console.log(`${date.index}. ${date.dateText} | Tent: ${date.tentNo} | Status: ${date.status} | ${date.colorStatus} | ${date.actionTaken}`);
                        });
                    }

                    console.log("=== DATE CHECKING SESSION COMPLETED ===\n");

                    // Process updates with proper error handling
                    processStatusUpdates(redDates, orangeDates);
                } catch (error) {
                    console.error("❌ Error in updateDateColors:", error);
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

                console.log(`🔄 Processing ${totalUpdates} status updates...`);

                // Process red dates (overdue)
                if (Array.isArray(redDates) && redDates.length > 0) {
                    console.log(`📤 Sending ${redDates.length} red dates for update to 'Retrieved'`);
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
                    console.log(`📤 Sending ${orangeDates.length} orange dates for update to 'For Retrieval'`);
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
                        `📡 Making AJAX request (${requestType}), attempt ${retryCount + 1}`
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
                            console.log(`✅ AJAX success (${requestType}):`, response);
                            retryAttempts.delete(requestKey);
                            resolve(response);
                        })
                        .fail(function(xhr, status, error) {
                            console.error(
                                `❌ AJAX error (${requestType}):`,
                                status,
                                error,
                                xhr.responseText
                            );

                            if (retryCount < MAX_RETRY_ATTEMPTS) {
                                const delay = RETRY_DELAY_BASE * Math.pow(2, retryCount); // Exponential backoff

                                console.log(`🔄 Retrying in ${delay}ms...`);
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
                    console.log(`✅ Successfully updated ${dates.length} items to ${newStatus}`);

                    // Validate response
                    if (response && response.error) {
                        console.error("❌ Server returned error:", response.error);
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
                            console.error("❌ Error updating individual item:", itemError, dateItem);
                        }
                    });

                    showUpdateStatus(
                        `Updated ${dates.length} tent(s) to ${newStatus}`,
                        "success"
                    );
                } catch (error) {
                    console.error("❌ Error in handleUpdateSuccess:", error);
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
                        `❌ Failed to update ${requestType} dates after ${MAX_RETRY_ATTEMPTS} attempts:`,
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
                                "❌ Error adding error styling to item:",
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
                    console.error("❌ Error in handleUpdateError:", error);
                }
            }

            /**
             * Checks if all updates are complete and processes queued updates if needed
             * @param {number} completed - Number of completed updates
             * @param {number} total - Total number of updates
             */
            function checkAllUpdatesComplete(completed, total) {
                try {
                    console.log(`📊 Updates completed: ${completed}/${total}`);

                    if (completed >= total) {
                        // Process any queued updates if not already processing
                        if (pendingUpdates.length > 0 && !queueProcessingInProgress) {
                            console.log(`🔄 Processing ${pendingUpdates.length} queued updates`);
                            queueProcessingInProgress = true;
                            const queued = [...pendingUpdates];
                            pendingUpdates = [];

                            setTimeout(() => {
                                processQueuedUpdates(queued);
                            }, 1000);
                        } else {
                            updateInProgress = false;
                            hideUpdateStatus();
                            console.log("✅ All updates completed successfully");
                        }
                    }
                } catch (error) {
                    console.error("❌ Error in checkAllUpdatesComplete:", error);
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
                        console.log(`📋 Queued ${dates.length} failed updates for retry`);
                    }
                } catch (error) {
                    console.error("❌ Error queuing failed update:", error);
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
                                    `⚠️ Queue retry limit reached for ${update.dates.length} ${update.requestType} updates, skipping`
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
                            console.error("❌ Error processing queued update:", updateError, update);
                            processedCount++;
                            if (processedCount >= totalQueued) {
                                queueProcessingInProgress = false;
                            }
                        }
                    });
                } catch (error) {
                    console.error("❌ Error in processQueuedUpdates:", error);
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
                    console.error("❌ Error showing update status:", error);
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
                    console.error("❌ Error hiding update status:", error);
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
                        console.log(`🧹 Cleaned up ${cleanedCount} old retry attempts`);
                    }
                } catch (error) {
                    console.error("❌ Error cleaning up retry attempts:", error);
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
                                    console.log(`🔄 Syncing ${data.length} dropdown statuses...`);
                                    data.forEach((item) => {
                                        try {
                                            if (item && item.id !== undefined) {
                                                const dropdown = $(
                                                    `.status-dropdown option[data-id="${item.id}"]`
                                                ).parent();

                                                if (dropdown.length > 0 && dropdown.val() !== item.status) {
                                                    dropdown.val(item.status);
                                                    console.log(
                                                        `🔄 Synced status for ID ${item.id} to ${item.status}`
                                                    );
                                                }
                                            }
                                        } catch (itemError) {
                                            console.error(
                                                "❌ Error syncing individual dropdown:",
                                                itemError,
                                                item
                                            );
                                        }
                                    });
                                } else {
                                    console.warn("⚠️ Invalid data format received from server:", data);
                                }
                            } catch (processError) {
                                console.error("❌ Error processing sync data:", processError);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("❌ Failed to sync status dropdowns:", status, error);
                            // Don't show user error for sync failures unless it's critical
                            if (status !== "timeout") {
                                console.error("Sync error details:", xhr.responseText);
                            }
                        },
                    });
                } catch (error) {
                    console.error("❌ Error in syncStatusDropdowns:", error);
                }
            }

            // Initialize the system with error handling
            try {
                console.log("🚀 Initializing date color update system...");
                updateDateColors();
            } catch (initError) {
                console.error("❌ Error during initialization:", initError);
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
                    console.error("❌ Error in updateDateColors interval:", error);
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
                    console.error("❌ Error in syncStatusDropdowns interval:", error);
                }
            }, 60000);

            // Set up cleanup interval for old retry attempts
            const cleanupInterval = setInterval(() => {
                try {
                    cleanupOldRetryAttempts();
                } catch (error) {
                    console.error("❌ Error in cleanup interval:", error);
                }
            }, CLEANUP_INTERVAL);

            // Handle page visibility changes
            document.addEventListener("visibilitychange", function() {
                try {
                    if (!document.hidden && !updateInProgress) {
                        console.log("👁️ Page became visible, checking for updates...");
                        updateDateColors();
                        syncStatusDropdowns();
                    }
                } catch (error) {
                    console.error("❌ Error handling visibility change:", error);
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
                    console.log("🧹 Cleaned up intervals and state");
                } catch (error) {
                    console.error("❌ Error during cleanup:", error);
                }
            });

            /**
             * Manual refresh function for debugging
             */
            window.debugUpdateDateColors = function() {
                console.log("🔧 Manual date color update triggered");
                updateInProgress = false; // Reset in case it's stuck
                queueProcessingInProgress = false; // Reset queue processing flag
                updateDateColors();
            };

            console.log("✅ Date color update system initialized successfully");
            $(document).ready(function() {
                updateDateColors();
            });
        });

 
$(function () {
  var dropdowns = document.querySelectorAll(".dropdown");
  dropdowns.forEach(function (dropdown) {
    dropdown.addEventListener("click", function (event) {
      this.querySelector(".dropdown-menu").classList.toggle("active");
      this.classList.toggle("open");
    });
  });
  document.addEventListener("click", function (event) {
    if (!event.target.closest(".dropdown")) {
      var activeDropdowns = document.querySelectorAll(".dropdown-menu.active");
      activeDropdowns.forEach(function (activeDropdown) {
        activeDropdown.classList.remove("active");
        activeDropdown.closest(".dropdown").classList.remove("open");
      });
    }
  });
});

// Datepicker initialization
$(function () {
  $("#datepicker").datepicker();
  $("#datepicker1").datepicker();
});

// Popup/modal logic
$(function () {
  $("#addButton").on("click", function () {
    $(".popup").addClass("active");
    $(".overlay").show();
  });
  $(".popup .close-btn").on("click", function () {
    $(".popup").removeClass("active");
    $(".overlay").hide();
  });
  $("#UpdateButton").on("click", function () {
    $(".popup3").addClass("active");
    $(".overlay").show();
  });
  $(".popup3 .close-btn").on("click", function () {
    $(".popup3").removeClass("active");
    $(".overlay").hide();
  });
  $(".popup1 .close-btn").on("click", function () {
    $(".popup1").removeClass("active");
    $(".overlay").hide();
  });
  $(document).on("click", ".viewButton", function () {
    var id = $(this).data("id");
    // Fetch data for the selected row
    $.get("fetch_data.php", { id: id }, function (data) {
      if (typeof data === "string") data = JSON.parse(data);
      // Populate the new Bootstrap 5 modal fields
      $("#viewEditTentNo").val(data.tent_no);
      $("#viewEditDate").val(data.date);
      $("#viewEditRetrievalDate").val(data.retrieval_date);
      $("#viewEditName").val(data.name);
      $("#viewEditContactNo").val(data.Contact_no);
      $("#viewEditNoOfTents").val(data.no_of_tents);
      $("#viewEditPurpose").val(data.purpose);
      $("#viewEditLocation").val(data.location);
      $("#viewEditStatus").val(data.status);
      $("#viewEditAddress").val(data.address);
      // Show the new Bootstrap 5 modal
      var modal = new bootstrap.Modal(document.getElementById("viewEditModal"));
      modal.show();
    });
  });
  // Clear popup inputs on close
  $(".popup .close-btn").on("click", function () {
    $(".popup .form input[type='text']").val("");
    $(".popup").removeClass("active");
  });
  // Overlay click closes popups
  $(".overlay").on("click", function () {
    $(".popup, .popup1, .popup3").removeClass("active");
    $(this).hide();
  });
  // Escape key closes popups
  $(document).on("keydown", function (event) {
    if (event.key === "Escape") {
      $(".popup, .popup1, .popup3").removeClass("active");
      $(".overlay").hide();
    }
  });
});

// Enter key navigation in form
$(function () {
  $('.form input[type="text"]').on("keydown", function (event) {
    if (event.keyCode === 13) {
      event.preventDefault();
      var inputs = $('.form input[type="text"]');
      var idx = inputs.index(this);
      if (idx + 1 < inputs.length) {
        inputs[idx + 1].focus();
      }
    }
  });
});

// AJAX for viewButton to fetch data and populate form
$(function () {
  $(".viewButton").on("click", function () {
    var id = $(this).data("id");
    $("#id").val(id);
    $.get("fetch_data.php", { id: id }, function (data) {
      if (typeof data === "string") data = JSON.parse(data);
      populateForm(data);
    });
  });
});

function populateForm(data) {
  $("#tentno").val(data.tent_no);
  $("#tent_no1").val(data.no_of_tents);
  $("#datepicker1").val(data.date);
  $("#name1").val(data.name);
  $("#contact1").val(data.Contact_no);
  $("#tent_duration1").val(data.retrieval_date);
  // Purpose dropdown
  var purposeOptions = [
    "Wake",
    "Birthday",
    "Wedding",
    "Baptism",
    "Personal",
    "Private",
    "Church",
    "School",
    "LGU",
    "Province",
    "City Government",
    "Municipalities",
  ];
  var $purposeDropdown = $("#purpose1");
  $purposeDropdown.empty();
  $.each(purposeOptions, function (_, option) {
    $purposeDropdown.append($("<option>", { value: option, text: option }));
  });
  $purposeDropdown.val(data.purpose);
  // Location dropdown
  var locationOptions = [
    "Bool",
    "Booy",
    "Cabawan",
    "Cogon",
    "Dao",
    "Dampas",
    "Manga",
    "Mansasa",
    "Poblacion I",
    "Poblacion II",
    "Poblacion III",
    "San Isidro",
    "Taloto",
    "Tiptip",
    "Ubujan",
    "Outside Tagbilaran",
  ];
  var $locationDropdown = $("#Location1");
  $locationDropdown.empty();
  $.each(locationOptions, function (_, option) {
    $locationDropdown.append($("<option>", { value: option, text: option }));
  });
  $locationDropdown.val(data.location);
  $("#other1").val(data.location);
  var initialTentNo = parseInt(data.no_of_tents) || 0;
  clickLimit = initialTentNo;
  $("#tent_no1").trigger("input");
}

// Box status and click logic
$(function () {
  var clickLimit = 0;
  var dropdown = document.querySelector(".status-dropdown");
  function updateBoxStatus() {
    $.get("fetch_tent_status.php", function (data) {
      data = typeof data === "string" ? JSON.parse(data) : data;
      data.forEach(function (item) {
        // Color boxes in the modal
        var box = $('.modal .box[data-box="' + item.id + '"]');
        if (box.length) {
          box.removeClass("green red orange blue");
          if (item.Status === "On Stock" || item.Status === "Retrieved") {
            box.addClass("green");
          } else if (item.Status === "Installed") {
            box.addClass("red");
          } else if (item.Status === "For Retrieval") {
            box.addClass("orange");
          } else if (item.Status === "Long Term") {
            box.addClass("blue");
          }
        }
      });
    });
  }
  if (dropdown) {
    dropdown.addEventListener("change", function () {
      var selectedStatus = this.value;
      var tentNumbers = $("#tentno").val().split(",").map(Number);
      tentNumbers.forEach(function (tentNumber) {
        $.post(
          "update_tent_status.php",
          { tentno: tentNumber, status: selectedStatus },
          function (resp) {
            // Optionally handle response
          }
        );
      });
      updateBoxStatus();
    });
  }
  $("#tent_no1").on("input", function () {
    clickLimit = parseInt($(this).val()) || 0;
  });
  // Update box status when modal is shown
  $("#viewEditModal").on("shown.bs.modal", function () {
    updateBoxStatus();
  });
  updateBoxStatus();
});

// Set hidden id on edit
$(function () {
  $(".viewButton").on("click", function () {
    var id = $(this).data("id");
    $("#id").val(id);
  });
});

// Location/Other change AJAX
$(function () {
  var locationSelect = document.getElementById("Location1");
  var otherInput = document.getElementById("other1");
  if (otherInput && locationSelect) {
    otherInput.addEventListener("change", updateRecord);
    locationSelect.addEventListener("change", updateRecord);
  }
  function updateRecord() {
    var id = document.getElementById("id").value;
    var otherValue = otherInput.value;
    var locationValue = locationSelect.value;
    var formData = new FormData();
    formData.append("id", id);
    formData.append("other1", otherValue);
    formData.append("Location1", locationValue);
    fetch("update_data.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.text())
      .then((result) => {
        console.log(result);
      })
      .catch((error) => {
        console.error("Error:", error);
      });
  }
});

// Status dropdown AJAX
$(function () {
  $(".status-dropdown").change(function () {
    var id = $(this).find(":selected").attr("data-id");
    var status = $(this).val();
    $.ajax({
      url: "update_status.php",
      type: "POST",
      data: { id: id, status: status },
      success: function (response) {
        console.log(response);
      },
      error: function (xhr, status, error) {
        console.error(xhr.responseText);
      },
    });
  });
});

// Delete button AJAX
$(document).on("click", ".deleteButton", function () {
  var rowId = $(this).data("id");
  if (confirm("Are you sure you want to delete this record?")) {
    $.ajax({
      url: "delete_data.php",
      type: "POST",
      data: { id: rowId },
      success: function (response) {
        window.location.href = "tracking.php";
      },
      error: function (xhr, status, error) {
        console.error(xhr.responseText);
      },
    });
  }
});

// Table search
$(function () {
  var input = document.getElementById("search-input");
  var table = document.getElementById("table_tent");
  if (!input || !table) return;
  var rows = table.getElementsByTagName("tr");
  input.addEventListener("input", function () {
    var filter = input.value.toLowerCase();
    for (var i = 1; i < rows.length; i++) {
      var cells = rows[i].getElementsByTagName("td");
      var rowVisible = false;
      for (var j = 0; j < cells.length; j++) {
        var cellText = cells[j].textContent.toLowerCase();
        if (cellText.indexOf(filter) > -1) {
          rowVisible = true;
          break;
        }
      }
      rows[i].style.display = rowVisible ? "" : "none";
    }
  });
});

// Box status for .boxs
$(function () {
  var boxesContainer = document.querySelector(".boxs");
  if (!boxesContainer) return;
  function updateBoxStatus() {
    $.get("fetch_tent_status.php", function (data) {
      data = typeof data === "string" ? JSON.parse(data) : data;
      data.forEach(function (item) {
        var box = document.getElementById("box_" + item.id);
        if (box) {
          box.className = "box";
          if (item.Status === "On Stock" || item.Status === "Retrieved") {
            box.classList.add("green");
          } else if (item.Status === "Installed") {
            box.classList.add("red");
          } else if (item.Status === "For Retrieval") {
            box.classList.add("orange");
          } else if (item.Status === "Long Term") {
            box.classList.add("blue");
          }
        }
      });
    });
  }
  for (let i = 1; i <= 300; i++) {
    const box = document.createElement("div");
    box.className = "box";
    box.id = "box_" + i;
    box.textContent = i;
    boxesContainer.appendChild(box);
  }
  updateBoxStatus();
});

// Hide .form-element-other1
$(function () {
  $(".form-element-other1").hide();
});

// Multi-select green boxes up to viewEditNoOfTents value
$(document).on("click", ".modal .box", function () {
  if (!$(this).hasClass("green")) return; // Only allow green boxes
  var clickLimit = parseInt($("#viewEditNoOfTents").val()) || 1;
  var selectedBoxes = $(".modal .box.selected");
  var isSelected = $(this).hasClass("selected");
  if (!isSelected && selectedBoxes.length >= clickLimit) return; // Limit reached
  $(this).toggleClass("selected");
  // Update Tent No. field with comma-separated selected box numbers
  var selectedNumbers = $(".modal .box.selected")
    .map(function () {
      return $(this).data("box");
    })
    .get()
    .join(",");
  $("#viewEditTentNo").val(selectedNumbers);
});

// Deselect boxes if viewEditNoOfTents value is lowered below current selection
$("#viewEditNoOfTents").on("input", function () {
  var clickLimit = parseInt($(this).val()) || 1;
  var selectedBoxes = $(".modal .box.selected");
  if (selectedBoxes.length > clickLimit) {
    // Deselect boxes from the end
    selectedBoxes.slice(clickLimit).removeClass("selected");
    // Update Tent No. field
    var selectedNumbers = $(".modal .box.selected")
      .map(function () {
        return $(this).data("box");
      })
      .get()
      .join(",");
    $("#viewEditTentNo").val(selectedNumbers);
  }
});

// Reset box selection and Tent No. field when modal is closed
$("#viewEditModal").on("hidden.bs.modal", function () {
  $(".modal .box").removeClass("selected");
  $("#viewEditTentNo").val("");
});
// Add this form submission handler to your tracking.js file
$(function () {
  $("#viewEditForm").on("submit", function (e) {
    e.preventDefault();
    var formData = new FormData();
    formData.append("id", $("#id").val());
    formData.append("tent_no1", $("#viewEditNoOfTents").val());
    formData.append("datepicker1", $("#viewEditDate").val());
    formData.append("name1", $("#viewEditName").val());
    formData.append("contact1", $("#viewEditContactNo").val());
    formData.append("tentno", $("#viewEditTentNo").val());
    formData.append("Location1", $("#viewEditLocation").val());
    formData.append("purpose1", $("#viewEditPurpose").val());
    formData.append("status", $("#viewEditStatus").val());
    formData.append("duration1", $("#viewEditRetrievalDate").val());
    formData.append("address1", $("#viewEditAddress").val()); // Added address field

    $.ajax({
      url: "update_data.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        console.log("Update successful:", response);
        $("#viewEditModal").modal("hide");
        location.reload();
      },
      error: function (xhr, status, error) {
        console.error("Update failed:", error);
        console.error("XHR Response:", xhr.responseText);
        alert("Failed to update tent data. Please try again.");
      },
    });
  });
});
