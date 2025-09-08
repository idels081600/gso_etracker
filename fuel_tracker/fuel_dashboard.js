// In your JavaScript file
async function loadFuelRecords() {
  try {
    const tbody = document.getElementById("fuelRecordsBody");
    const response = await fetch("display_fuel_records.php");
    if (!response.ok) throw new Error("Network response was not ok");
    const html = await response.text();
    tbody.innerHTML = html;
    // Reinitialize any event handlers
    initializeActionButtons();
    // Move initializeTableCheckboxes call here, after the DOM is updated
    initializeTableCheckboxes();
  } catch (error) {
    console.error("Error loading records:", error);
    showNotification("Failed to load records", "danger");
  }
}

// Attach global functions to window object for inline onclick handlers
window.viewRecord = viewRecord;
window.editRecord = editRecord;
window.deleteRecord = deleteRecord;

function initializeTableCheckboxes() {
  const selectAllCheckbox = document.querySelector("#selectAll");
  const checkboxes = document.querySelectorAll(".row-checkbox");

  // Always remove existing listeners to prevent duplicates if called multiple times
  // This is good practice when re-initializing elements
  if (selectAllCheckbox) {
    selectAllCheckbox.removeEventListener("change", handleSelectAllChange);
    selectAllCheckbox.addEventListener("change", handleSelectAllChange);
  }

  checkboxes.forEach((checkbox) => {
    checkbox.removeEventListener("change", handleIndividualCheckboxChange);
    checkbox.addEventListener("change", handleIndividualCheckboxChange);
  });
}

// Separate handler functions for better readability and easier removal
function handleSelectAllChange() {
  const isChecked = this.checked;
  const checkboxes = document.querySelectorAll(".row-checkbox"); // Re-query to get latest
  checkboxes.forEach((checkbox) => {
    checkbox.checked = isChecked;
  });
  const selectedIds = getSelectedIds();
  console.log("Selected IDs after select all:", selectedIds);
  updateSelectedCount();
}

function handleIndividualCheckboxChange() {
  updateSelectAllState();
  const selectedIds = getSelectedIds();
  console.log("Selected IDs after individual check:", selectedIds);
  updateSelectedCount();
}

function getSelectedIds() {
  const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");
  return Array.from(checkedBoxes).map((cb) => cb.value);
}

function updateSelectAllState() {
  const selectAllCheckbox = document.querySelector("#selectAll");
  const checkboxes = document.querySelectorAll(".row-checkbox");
  const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");

  if (selectAllCheckbox) {
    selectAllCheckbox.checked = checkboxes.length === checkedBoxes.length;
    // Set indeterminate state only if there are checkboxes
    selectAllCheckbox.indeterminate =
      checkboxes.length > 0 &&
      checkedBoxes.length > 0 &&
      checkedBoxes.length < checkboxes.length;
  }
}

function updateSelectedCount() {
  const selectedIds = getSelectedIds();
  const selectedCount = selectedIds.length;

  // Update UI to show selected count (if you have an element for this)
  const countElement = document.getElementById("selectedCount");
  if (countElement) {
    countElement.textContent = `${selectedCount} selected`;
  }

  // Enable/disable export button based on selection
  const exportBtn = document.getElementById("exportBtn");
  if (exportBtn) {
    exportBtn.disabled = selectedCount === 0;
  }

  console.log("Currently selected IDs:", selectedIds);
  return selectedIds;
}
async function handleExportRecords() {
  const selectedIds = getSelectedIds(); // This function you already have!

  if (selectedIds.length === 0) {
    showNotification("Please select at least one record to export.", "warning");
    return;
  }

  try {
    // Use a form to submit POST data for file download
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "export_fuel_pdf.php"; // Your PHP script for generating the PDF
    form.target = "_blank"; // Open in a new tab

    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "selected_ids";
    input.value = JSON.stringify(selectedIds); // Send IDs as a JSON string

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form); // Clean up the form after submission

    showNotification(
      "Export initiated. Your PDF should download shortly.",
      "success"
    );
  } catch (error) {
    console.error("Error exporting records:", error);
    showNotification("Failed to export records.", "danger");
  }
}

document.addEventListener("DOMContentLoaded", function () {
  // Load fuel records immediately when page loads
  loadFuelRecords();
  loadFuelStatistics(); // Load statistics for the cards
  initializeActionButtons();

  // Add event listener for saving fuel records
  const saveFuelRecordBtn = document.getElementById("saveFuelRecord");
  if (saveFuelRecordBtn) {
    saveFuelRecordBtn.addEventListener("click", saveFuelRecord);
  }
});

async function saveFuelRecord() {
  const form = document.getElementById("addFuelRecordForm");
  const formData = new FormData(form);

  // Convert FormData to a plain object
  const data = {};
  formData.forEach((value, key) => {
    data[key] = value;
  });

  // Validate the date field
  if (!data.fuel_date || data.fuel_date.trim() === "") {
    showNotification(
      "Date is required. Please select a valid date.",
      "warning"
    );
    return;
  }

  try {
    // Show loading state
    const saveBtn = document.getElementById("saveFuelRecord");
    const originalHTML = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Send POST request with JSON data
    const response = await fetch("save_fuel_record.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        date: data.fuel_date,
        office: data.office,
        vehicle: data.vehicle,
        plate_no: data.plate_no,
        driver: data.driver,
        purpose: data.purpose,
        fuel_type: data.fuel_type,
        liters_issued: data.liters_issued,
        remarks: data.remarks,
      }),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const responseData = await response.json();

    if (responseData.success) {
      showNotification("Fuel record saved successfully!", "success");

      // Close the modal
      const addModal = bootstrap.Modal.getInstance(
        document.getElementById("addFuelRecordModal")
      );
      addModal.hide();

      // Reload records and statistics
      loadFuelRecords();
      loadFuelStatistics();
    } else {
      throw new Error(responseData.message || "Failed to save fuel record.");
    }
  } catch (error) {
    console.error("Error saving fuel record:", error);

    // Handle invalid JSON response
    if (error instanceof SyntaxError) {
      showNotification(
        "Invalid response from server. Please check the backend.",
        "danger"
      );
    } else {
      showNotification(`Error: ${error.message}`, "danger");
    }
  } finally {
    // Restore button state
    const saveBtn = document.getElementById("saveFuelRecord");
    saveBtn.innerHTML = '<i class="fas fa-save me-1"></i>Save Record';
    saveBtn.disabled = false;
  }
}

function initializeActionButtons() {
  console.log("Initializing action buttons and filters");

  // Date Range Filter
  const dateFilterBtn = document.getElementById("dateFilterBtn");
  if (dateFilterBtn) {
    dateFilterBtn.removeEventListener("click", handleDateFilter);
    dateFilterBtn.addEventListener("click", handleDateFilter);
  }

  // Filter Dropdown
  const filterItems = document.querySelectorAll(".dropdown-menu [data-filter]");
  filterItems.forEach((item) => {
    item.removeEventListener("click", handleFilterClick);
    item.addEventListener("click", handleFilterClick);
  });

  // Add search input handlers
  const officeFilter = document.getElementById("officeFilter");
  const vehicleFilter = document.getElementById("vehicleFilter");
  const driverFilter = document.getElementById("driverFilter");

  [officeFilter, vehicleFilter, driverFilter].forEach((filter) => {
    if (filter) {
      filter.removeEventListener("input", handleSearchFilters);
      filter.addEventListener("input", debounce(handleSearchFilters, 500));
    }
  });

  // Export Button
  const exportBtn = document.getElementById("exportBtn");
  if (exportBtn) {
    exportBtn.removeEventListener("click", handleExportRecords);
    exportBtn.addEventListener("click", handleExportRecords);
  }

  // Refresh Button
  const refreshBtn = document.getElementById("refreshBtn");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      loadFuelRecords();
      showNotification("Records refreshed", "success");
    });
  }

  // Initialize row action buttons
  initializeRowActionButtons();
}

// New function to initialize row action buttons
function initializeRowActionButtons() {
  // View buttons
  const viewButtons = document.querySelectorAll(".action-view");
  viewButtons.forEach((button) => {
    button.addEventListener("click", function(e) {
      e.preventDefault();
      const row = this.closest("tr");
      const recordId = row.querySelector(".row-checkbox").value;
      viewRecord(recordId);
    });
  });

  // Edit buttons
  const editButtons = document.querySelectorAll(".action-edit");
  editButtons.forEach((button) => {
    button.addEventListener("click", function(e) {
      e.preventDefault();
      const row = this.closest("tr");
      const recordId = row.querySelector(".row-checkbox").value;
      editRecord(recordId, row);
    });
  });

  // Delete buttons
  const deleteButtons = document.querySelectorAll(".action-delete");
  deleteButtons.forEach((button) => {
    button.addEventListener("click", function(e) {
      e.preventDefault();
      const row = this.closest("tr");
      const recordId = row.querySelector(".row-checkbox").value;
      if (confirm("Are you sure you want to delete this fuel record?")) {
        deleteRecord(recordId, row);
      }
    });
  });
}

function handleFilterClick(e) {
  e.preventDefault();
  handleFilterSelection(this.getAttribute("data-filter"));
}

function handleFilterSelection(filter) {
  const today = new Date();
  let filters = {};

  switch (filter) {
    case "all":
      loadFuelRecords();
      break;

    case "today":
      filters.date_from = today.toISOString().split("T")[0];
      filters.date_to = filters.date_from;
      loadFilteredFuelRecords(filters);
      break;

    case "week":
      const firstDayOfWeek = new Date(today);
      firstDayOfWeek.setDate(today.getDate() - today.getDay());
      filters.date_from = firstDayOfWeek.toISOString().split("T")[0];
      filters.date_to = today.toISOString().split("T")[0];
      loadFilteredFuelRecords(filters);
      break;

    case "month":
      const firstDayOfMonth = new Date(
        today.getFullYear(),
        today.getMonth(),
        1
      );
      filters.date_from = firstDayOfMonth.toISOString().split("T")[0];
      filters.date_to = today.toISOString().split("T")[0];
      loadFilteredFuelRecords(filters);
      break;

    case "unleaded":
      filters.fuel_type = "Unleaded";
      loadFilteredFuelRecords(filters);
      break;

    case "diesel":
      filters.fuel_type = "Diesel";
      loadFilteredFuelRecords(filters);
      break;

    default:
      console.warn("Unknown filter type:", filter);
      loadFuelRecords();
      break;
  }
}

function handleSearchFilters() {
  const filters = {
    office: document.getElementById("officeFilter")?.value || "",
    vehicle: document.getElementById("vehicleFilter")?.value || "",
    driver: document.getElementById("driverFilter")?.value || "",
  };

  // Only apply filters if at least one has a value
  if (Object.values(filters).some((value) => value.length > 0)) {
    loadFilteredFuelRecords(filters);
  }
}

function handleDateFilter() {
  const startDate = document.getElementById("dateFilterStart").value;
  const endDate = document.getElementById("dateFilterEnd").value;

  if (!startDate || !endDate) {
    showNotification("Please select both start and end dates", "warning");
    return;
  }

  if (startDate > endDate) {
    showNotification("Start date cannot be after end date", "warning");
    return;
  }

  loadFilteredFuelRecords({
    date_from: startDate,
    date_to: endDate,
  });
}

// Debounce helper function
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

async function loadFilteredFuelRecords(filters) {
  try {
    const params = new URLSearchParams();
    params.append("action", "filtered");

    // Add non-empty filters to params
    Object.keys(filters).forEach((key) => {
      if (filters[key]) {
        params.append(key, filters[key]);
      }
    });

    const response = await fetch(`get_fuel_data.php?${params.toString()}`);
    if (!response.ok) throw new Error("Network response was not ok");

    const data = await response.json();
    // Load statistics for the current filters
    loadFuelStatistics(filters);

    if (data.success) {
      updateTableWithFilteredData(data);
      showNotification(`Showing ${data.data.length} filtered records`, "info");
    } else {
      throw new Error(data.message || "Failed to load filtered records");
    }
  } catch (error) {
    console.error("Error loading filtered records:", error);
    showNotification(error.message, "danger");
  }
}

function updateTableWithFilteredData(data) {
  const tbody = document.getElementById("fuelRecordsBody");
  if (!tbody) return;

  tbody.innerHTML = "";
  if (data.data.length === 0) {
    tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-muted py-4">
                    <i class="fas fa-search me-2"></i>No records found
                </td>
            </tr>`;
    return;
  }

  data.data.forEach((record) => {
    const row = createTableRow(record);
    tbody.appendChild(row);
  });

  initializeActionButtons();
  initializeTableCheckboxes();
}

function createTableRow(record) {
  const row = document.createElement("tr");

  // Format date
  const date = new Date(record.date);
  const formattedDate = date.toISOString().split("T")[0];
  const relativeDate = getRelativeDateString(date);

  // Get badge class for fuel type
  const badgeClass = getFuelTypeBadgeClass(record.fuel_type);

  row.innerHTML = `
        <td>
            <input type="checkbox" class="form-check-input row-checkbox" value="${
              record.id
            }">
        </td>
        <td>
            <span class="fw-medium">${formattedDate}</span>
            <small class="text-muted d-block">${relativeDate}</small>
        </td>
        <td>
            <span class="badge bg-light text-dark">${
              record.office || "-"
            }</span>
        </td>
        <td>${record.vehicle || "-"}</td>
        <td>
            <span class="font-monospace">${record.plate_no || "-"}</span>
        </td>
        <td>${record.driver || "-"}</td>
        <td>
            <span class="text-truncate d-inline-block" style="max-width: 150px;" 
                  title="${record.purpose || "-"}">
                ${record.purpose || "-"}
            </span>
        </td>
        <td>
            <span class="badge ${badgeClass}">${record.fuel_type || "-"}</span>
        </td>
        <td>
            <span class="fw-bold">${
              record.liters_issued
                ? parseFloat(record.liters_issued).toFixed(2) + " L"
                : "-"
            }</span>
        </td>
        <td>
            <span class="text-muted">${record.remarks || "-"}</span>
        </td>
        <td>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary action-view" title="View">
                    <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="btn btn-outline-warning action-edit" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-outline-danger action-delete" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    `;

  return row;
}

function getFuelTypeBadgeClass(fuelType) {
  if (!fuelType) return "bg-secondary";

  switch (fuelType.toLowerCase()) {
    case "unleaded":
      return "bg-success";
    case "diesel":
      return "bg-warning text-dark";
    case "premium":
      return "bg-primary";
    default:
      return "bg-secondary";
  }
}

function getRelativeDateString(date) {
  const now = new Date();
  const diffTime = Math.abs(now - date);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays === 1) return "Today";
  if (diffDays === 2) return "Yesterday";
  if (diffDays <= 7) return `${diffDays - 1} days ago`;
  if (diffDays <= 30) return `${Math.ceil(diffDays / 7)} weeks ago`;
  return `${Math.ceil(diffDays / 30)} months ago`;
}

// Assuming these functions are defined elsewhere or will be defined
function showNotification(message, type) {
  console.log(`Notification (${type}): ${message}`);
  // Your actual implementation to show a notification to the user
}

async function loadFuelStatistics() {
  try {
    const response = await fetch("get_fuel_data.php?action=statistics");

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      updateFuelStatistics(data.data);
    } else {
      showNotification(
        "Error loading fuel statistics: " + data.message,
        "danger"
      );
    }
  } catch (error) {
    console.error("Error loading fuel statistics:", error);
    showNotification("Failed to load fuel statistics", "danger");
  }
}

function updateFuelStatistics(statistics, filters = null) {
  // Reset all tallies
  document.getElementById("unleadedCount").textContent = "0";
  document.getElementById("unleadedLiters").textContent = "0.00 L";


  document.getElementById("dieselCount").textContent = "0";
  document.getElementById("dieselLiters").textContent = "0.00 L";
  

  // Update statistics labels based on filter period
  updateStatisticsLabels(filters);

  // Update with actual data
  statistics.forEach((stat) => {
    const fuelType = stat.fuel_type.toLowerCase();

    if (fuelType === "unleaded") {
      document.getElementById("unleadedCount").textContent = stat.total_records;
      document.getElementById("unleadedLiters").textContent =
        parseFloat(stat.total_liters || 0).toFixed(2) + " L";


    } else if (fuelType === "diesel") {
      document.getElementById("dieselCount").textContent = stat.total_records;
      document.getElementById("dieselLiters").textContent =
        parseFloat(stat.total_liters || 0).toFixed(2) + " L";


    }
  });
}

// Function to edit a record
function editRecord(recordId, row) {
  // Find the record data from the row's cells
  const cells = row.querySelectorAll("td");

  // Populate the edit modal fields
  document.getElementById("editRecordId").value = recordId;
  document.getElementById("editFuelDate").value =
    cells[1].querySelector(".fw-medium")?.innerText.trim() || "";
  document.getElementById("editOffice").value = cells[2].innerText.trim();
  document.getElementById("editVehicle").value = cells[3].innerText.trim();
  document.getElementById("editPlateNo").value = cells[4].innerText.trim();
  document.getElementById("editDriver").value = cells[5].innerText.trim();
  document.getElementById("editPurpose").value = cells[6].innerText.trim();
  document.getElementById("editFuelType").value =
    cells[7].querySelector(".badge")?.innerText.trim() || "";
  document.getElementById("editLitersIssued").value =
    parseFloat((cells[8].innerText || "").replace(" L", "")) || "";
  document.getElementById("editRemarks").value = cells[9].innerText.trim();

  // Show the edit modal (Bootstrap 5)
  const editModal = new bootstrap.Modal(
    document.getElementById("editFuelRecordModal")
  );
  editModal.show();
}

// Handle update button click in the edit modal
if (document.getElementById("updateFuelRecord")) {
  document
    .getElementById("updateFuelRecord")
    .addEventListener("click", async function () {
      console.log("=== UPDATE FUEL RECORD - START ===");

      const form = document.getElementById("editFuelRecordForm");
      const formData = new FormData(form);
      const recordId = formData.get("id");

      // Log initial form data
      console.log("Form element found:", !!form);
      console.log("Record ID:", recordId);

      const payload = {};
      formData.forEach((value, key) => {
        payload[key] = value;
      });

      console.log("Form payload:", payload);
      console.log("Payload keys:", Object.keys(payload));
      console.log("Payload size:", Object.keys(payload).length);

      try {
        // Show loading state
        const updateBtn = document.getElementById("updateFuelRecord");
        const originalHTML = updateBtn.innerHTML;
        console.log("Button original HTML:", originalHTML);

        updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        updateBtn.disabled = true;
        console.log("Button loading state applied");

        // Log request details
        const requestUrl = `update_fuel_record.php?id=${recordId}`;
        console.log("Request URL:", requestUrl);
        console.log("Request method: POST");
        console.log("Request headers:", {
          "Content-Type": "application/json",
        });
        console.log("Request body:", JSON.stringify(payload));

        // Send update request (adjust endpoint as needed)
        console.log("Sending fetch request...");
        const response = await fetch(requestUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        });

        console.log("Response received:");
        console.log("- Status:", response.status);
        console.log("- Status Text:", response.statusText);
        console.log("- OK:", response.ok);
        console.log(
          "- Headers:",
          Object.fromEntries(response.headers.entries())
        );

        if (!response.ok) {
          const errorDetails = {
            status: response.status,
            statusText: response.statusText,
            url: response.url,
            headers: Object.fromEntries(response.headers.entries()),
          };

          console.error("HTTP Error Details:", errorDetails);

          // Try to get error response body if available
          let errorBody = null;
          try {
            errorBody = await response.text();
            console.error("Error response body:", errorBody);
          } catch (bodyError) {
            console.error("Could not read error response body:", bodyError);
          }

          throw new Error(
            `HTTP error! status: ${response.status} - ${response.statusText}${
              errorBody ? "\nResponse: " + errorBody : ""
            }`
          );
        }

        console.log("Parsing response as JSON...");
        const data = await response.json();
        console.log("Response data:", data);
        console.log("Response data type:", typeof data);
        console.log("Response success:", data.success);

        if (data.success) {
          console.log("✅ Update successful");
          console.log("Success message:", data.message);

          showNotification(
            `Record ${recordId} updated successfully`,
            "success"
          );

          // Hide modal
          console.log("Hiding modal...");
          const editModalEl = document.getElementById("editFuelRecordModal");
          console.log("Modal element found:", !!editModalEl);

          if (editModalEl) {
            const editModal = bootstrap.Modal.getInstance(editModalEl);
            console.log("Modal instance:", editModal);

            if (editModal) {
              editModal.hide();
              console.log("Modal hidden successfully");
            } else {
              console.warn("Bootstrap modal instance not found");
            }
          } else {
            console.warn("Edit modal element not found");
          }

          // Reload records
          console.log("Reloading fuel records...");
          if (typeof loadFuelRecords === "function") {
            await loadFuelRecords();
            console.log("Fuel records reloaded");
          } else {
            console.error("loadFuelRecords function not available");
          }

          // Reload statistics if needed
          console.log("Reloading fuel statistics...");
          if (typeof loadFuelStatistics === "function") {
            await loadFuelStatistics();
            console.log("Fuel statistics reloaded");
          } else {
            console.error("loadFuelStatistics function not available");
          }
        } else {
          console.error("❌ Update failed - Server returned success: false");
          console.error("Server error message:", data.message);
          console.error("Full server response:", data);

          throw new Error(
            data.message ||
              "Failed to update record - server returned success: false"
          );
        }
      } catch (error) {
        console.error("=== UPDATE FUEL RECORD - ERROR ===");
        console.error("Error type:", error.constructor.name);
        console.error("Error message:", error.message);
        console.error("Error stack:", error.stack);

        // Additional error context
        console.error("Error occurred at:", new Date().toISOString());
        console.error("Record ID being updated:", recordId);
        console.error("Payload that failed:", payload);

        // Check for specific error types
        if (error.name === "TypeError" && error.message.includes("fetch")) {
          console.error("🌐 Network error detected - possible causes:");
          console.error("- Server is down");
          console.error("- Incorrect URL");
          console.error("- CORS issues");
          console.error("- Network connectivity problems");
        } else if (
          error.name === "SyntaxError" &&
          error.message.includes("JSON")
        ) {
          console.error("📄 JSON parsing error detected - possible causes:");
          console.error("- Server returned non-JSON response");
          console.error("- Response body is empty");
          console.error("- Server returned HTML error page");
        }

        showNotification(`Failed to update record: ${error.message}`, "danger");
      } finally {
        console.log("=== UPDATE FUEL RECORD - CLEANUP ===");

        // Restore button state
        const updateBtn = document.getElementById("updateFuelRecord");
        if (updateBtn) {
          updateBtn.innerHTML = '<i class="fas fa-save me-1"></i>Update Record';
          updateBtn.disabled = false;
          console.log("Button state restored");
        } else {
          console.error("Update button not found during cleanup");
        }

        console.log("=== UPDATE FUEL RECORD - END ===");
      }
    });
}

// Function to view a record
async function viewRecord(recordId) {
  try {
    // Fetch the record details from the backend
    const response = await fetch(
      `get_fuel_data.php?action=single&id=${recordId}`
    );
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();

    if (!data.success || !data.data) {
      showNotification("Failed to load record details", "danger");
      return;
    }

    const record = data.data;

    // Populate the modal fields
    document.getElementById("viewFuelDate").textContent = record.date || "-";
    document.getElementById("viewOffice").textContent = record.office || "-";
    document.getElementById("viewVehicle").textContent = record.vehicle || "-";
    document.getElementById("viewPlateNo").textContent = record.plate_no || "-";
    document.getElementById("viewDriver").textContent = record.driver || "-";
    document.getElementById("viewPurpose").textContent = record.purpose || "-";
    document.getElementById("viewRemarks").textContent = record.remarks || "-";
    const viewModal = new bootstrap.Modal(
      document.getElementById("viewFuelRecordModal")
    );
    viewModal.show();
  } catch (error) {
    console.error("Error loading record details:", error);
    showNotification("Failed to load record details", "danger");
  }
}

// Function to delete a record
async function deleteRecord(recordId, row) {
  try {
    console.log("Deleting record ID:", recordId);

    // Show loading state
    const deleteBtn = row.querySelector(".btn-outline-danger");
    const originalHTML = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;

    // Make API call to delete record
    const response = await fetch(`delete_fuel_record.php?id=${recordId}`, {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      // Remove the row from the table
      row.remove();
      showNotification(`Record ${recordId} deleted successfully`, "success");

      // Update record count
      updateRecordCount();

      // Reload statistics if elements exist
      loadFuelStatistics();
    } else {
      throw new Error(data.message || "Failed to delete record");
    }
  } catch (error) {
    console.error("Error deleting record:", error);
    showNotification(`Failed to delete record: ${error.message}`, "danger");

    // Restore button state
    const deleteBtn = row.querySelector(".btn-outline-danger");
    deleteBtn.innerHTML = originalHTML;
    deleteBtn.disabled = false;
  }
}

// Update record count display
function updateRecordCount() {
  const recordsShowingElement = document.getElementById("recordsShowing");
  const totalRecordsElement = document.getElementById("totalRecords");

  const tbody = document.getElementById("fuelRecordsBody");
  if (tbody) {
    const rowCount = tbody.querySelectorAll("tr").length;
    if (recordsShowingElement) recordsShowingElement.textContent = rowCount;
    if (totalRecordsElement) totalRecordsElement.textContent = rowCount;
  }
}
document.addEventListener("DOMContentLoaded", function () {
  const uploadCsvBtn = document.getElementById("uploadCsvBtn");
  const csvUploadInput = document.getElementById("csvUploadInput");

  uploadCsvBtn.addEventListener("click", function () {
    csvUploadInput.click();
  });

  csvUploadInput.addEventListener("change", function () {
    if (csvUploadInput.files.length > 0) {
      const selectedFile = csvUploadInput.files[0];
      console.log("Selected file:", selectedFile.name);

      const formData = new FormData();
      formData.append("csvFile", selectedFile);

      fetch("upload_bulk.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => {
          // Check if the response's content type is JSON before attempting to parse it
          const contentType = response.headers.get("content-type");
          if (!contentType || !contentType.includes("application/json")) {
            // If it's not JSON, read the response as text and create a more informative error
            return response.text().then((text) => {
              throw new Error(
                `Upload failed: Expected JSON response but received: ${contentType}.  Response text: ${text}`
              );
            });
          }

          // If it is JSON, parse it as usual
          return response.json().then((data) => {
            if (!response.ok) {
              // If response is not OK, construct an error with the JSON data
              throw new Error(
                `Upload failed with status ${response.status}: ${
                  data.message || "Unknown error"
                }`,
                { cause: data }
              );
            }
            return data; // If OK, proceed with success data
          });
        })
        .then((data) => {
          console.log("Upload successful:", data);
          // Add logic to inform the user, refresh data, etc.
          let successMessage = "CSV file uploaded successfully!";
          if (data.successful_rows) {
            successMessage += `\n${data.successful_rows} rows were successfully uploaded.`;
          }
          alert(successMessage); // Simple alert for demo
        })
        .catch((error) => {
          console.error("Upload failed:", error);
          let errorMessage = "Error uploading CSV file. Please try again.";

          // Check if the error has a 'cause' (our custom error data)
          if (error.cause && error.cause.message) {
            errorMessage = `Upload failed: ${error.cause.message}`;
            // If there are failed rows, provide more detail
            if (error.cause.failed_rows_count > 0) {
              errorMessage += `\n${error.cause.failed_rows_count} rows had issues:\n`;
              error.cause.failed_rows.forEach((row) => {
                errorMessage += `  - Row ${row.row}: ${row.errors.join(
                  ", "
                )}\n`;
              });
              console.error("Failed rows details:", error.cause.failed_rows);
            }
          } else {
            // Fallback for generic HTTP errors or non-JSON responses
            errorMessage = `Upload failed: ${error.message}`;
          }

          alert(errorMessage);
        });
    }
  });
});
document.getElementById("searchBtn").addEventListener("click", function () {
  const searchValue = document.getElementById("searchBar").value.toLowerCase();
  const rows = document.querySelectorAll("#fuelRecordsBody tr");

  rows.forEach((row) => {
    const rowText = row.textContent.toLowerCase();
    if (rowText.includes(searchValue)) {
      row.style.display = ""; // Show row
    } else {
      row.style.display = "none"; // Hide row
    }
  });
});

// Optional: Trigger search when pressing Enter key
document.getElementById("searchBar").addEventListener("keypress", function (e) {
  if (e.key === "Enter") {
    document.getElementById("searchBtn").click();
  }
});
async function loadFuelStatistics(filters = {}) {
  try {
    // Build query string based on filters
    let queryParams = new URLSearchParams();

    // Determine which action to use based on whether date filters are provided
    if (filters.date_from || filters.date_to) {
      queryParams.append("action", "filtered_statistics");

      // Add date filter parameters to query
      if (filters.date_from) queryParams.append("date_from", filters.date_from);
      if (filters.date_to) queryParams.append("date_to", filters.date_to);
    } else {
      queryParams.append("action", "statistics");
    }

    const response = await fetch(`get_fuel_data.php?${queryParams.toString()}`);

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      updateFuelStatistics(data.data, filters);
    } else {
      showNotification(
        "Error loading fuel statistics: " + data.message,
        "danger"
      );
    }
  } catch (error) {
    console.error("Error loading fuel statistics:", error);
    showNotification("Failed to load fuel statistics", "danger");
  }
}
function applyDateFilter() {
  const dateFrom = document.getElementById("dateFilterStart")?.value;
  const dateTo = document.getElementById("dateFilterEnd")?.value;

  if (dateFrom || dateTo) {
    loadFilteredFuelStatistics({
      date_from: dateFrom,
      date_to: dateTo,
    });
  } else {
    // Load regular statistics if no date filter
    loadFuelStatistics();
  }
}
function clearFilters() {
  // Clear form inputs if they exist
  const dateFromInput = document.getElementById("date_from");
  const dateToInput = document.getElementById("date_to");

  if (dateFromInput) dateFromInput.value = "";
  if (dateToInput) dateToInput.value = "";

  // Reload all statistics
  loadFuelStatistics();
}
document.addEventListener("DOMContentLoaded", function () {
  // If you have a filter form, attach the event listener
  const filterForm = document.getElementById("filterForm");
  if (filterForm) {
    filterForm.addEventListener("submit", function (e) {
      e.preventDefault();
      applyDateFilter();
    });
  }

  // If you have filter buttons
  const applyFilterBtn = document.getElementById("applyFilter");
  if (applyFilterBtn) {
    applyFilterBtn.addEventListener("click", applyDateFilter);
  }

  const clearFilterBtn = document.getElementById("clearFilter");
  if (clearFilterBtn) {
    clearFilterBtn.addEventListener("click", clearFilters);
  }
});
function updateStatisticsLabels(filters) {
  // Find the period label elements (you may need to adjust selectors based on your HTML)
  const periodLabels = document.querySelectorAll(
    ".period-label, .stats-period"
  );

  let periodText = "All Time";

  if (filters && (filters.date_from || filters.date_to)) {
    if (filters.date_from && filters.date_to) {
      // Format dates for display
      const fromDate = new Date(filters.date_from).toLocaleDateString();
      const toDate = new Date(filters.date_to).toLocaleDateString();
      periodText = `${fromDate} - ${toDate}`;
    } else if (filters.date_from) {
      const fromDate = new Date(filters.date_from).toLocaleDateString();
      periodText = `From ${fromDate}`;
    } else if (filters.date_to) {
      const toDate = new Date(filters.date_to).toLocaleDateString();
      periodText = `Until ${toDate}`;
    }
  }

  // Update period labels if they exist
  periodLabels.forEach((label) => {
    label.textContent = periodText;
  });

  // If you have specific elements for "This Month" labels, update them too
  const monthLabels = document.querySelectorAll(".month-label");
  monthLabels.forEach((label) => {
    label.textContent =
      filters && (filters.date_from || filters.date_to)
        ? "Filtered Period"
        : "This Month";
  });
}
function onDateFilterChange() {
  // Get current filter values from your form/inputs
  const filters = getCurrentFilters();

  // Reload statistics with new filters
  loadFuelStatistics(filters);

  // Optionally, also reload the main fuel records table
  loadFuelData(filters);
}
function getCurrentFilters() {
  const filters = {};

  // Get date filter values (adjust selectors based on your HTML)
  const dateFrom = document.getElementById("dateFilterStart")?.value;
  const dateTo = document.getElementById("dateFilterEnd")?.value;

  if (dateFrom) filters.date_from = dateFrom;
  if (dateTo) filters.date_to = dateTo;

  return filters;
}
document.addEventListener("DOMContentLoaded", function () {
  // Add event listeners to date inputs only
  const dateFromInput = document.getElementById("dateFrom");
  const dateToInput = document.getElementById("dateTo");

  if (dateFromInput) {
    dateFromInput.addEventListener("change", onDateFilterChange);
  }

  if (dateToInput) {
    dateToInput.addEventListener("change", onDateFilterChange);
  }

  // Load initial statistics
  loadFuelStatistics();
});
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Function to reset filters and reload all data
function resetFilters() {
  // Clear date filter inputs only
  const dateFromInput = document.getElementById("dateFrom");
  const dateToInput = document.getElementById("dateTo");

  if (dateFromInput) dateFromInput.value = "";
  if (dateToInput) dateToInput.value = "";

  // Reload statistics and data without filters
  loadFuelStatistics();
  loadFuelData();
}

// Enhanced loadFuelData function to work with filters
async function loadFuelData(filters = {}) {
  try {
    let queryParams = new URLSearchParams();

    if (Object.keys(filters).length > 0) {
      queryParams.append("action", "filtered");

      // Add filter parameters to query
      Object.keys(filters).forEach((key) => {
        if (filters[key]) {
          queryParams.append(key, filters[key]);
        }
      });
    } else {
      queryParams.append("action", "all");
    }

    const response = await fetch(`get_fuel_data.php?${queryParams.toString()}`);

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      updateFuelTable(data.data);
      // Also update statistics with the same filters
      loadFuelStatistics(filters);
    } else {
      showNotification("Error loading fuel data: " + data.message, "danger");
    }
  } catch (error) {
    console.error("Error loading fuel data:", error);
    showNotification("Failed to load fuel data", "danger");
  }
}
