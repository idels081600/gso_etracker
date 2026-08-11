// In your JavaScript file
async function loadFuelRecords() {
  try {
    const tbody = document.getElementById("fuelRecordsBody");
    if (!tbody) return;
    const response = await fetch("get_fuel_data.php?action=budget_deductions");
    if (!response.ok) throw new Error("Network response was not ok");
    const payload = await response.json();
    if (!payload.success) {
      throw new Error(payload.message || "Failed to load budget deductions");
    }
    updateBudgetDeductionTable(payload.data || []);
  } catch (error) {
    console.error("Error loading budget deductions:", error);
    showNotification("Failed to load budget deduction transactions", "danger");
  }
}

// Attach global functions to window object for inline onclick handlers
window.viewRecord = viewRecord;
window.editRecord = editRecord;
window.deleteRecord = deleteRecord;

const debouncedHandleSearchFilters = debounce(handleSearchFilters, 500);

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

document.addEventListener("DOMContentLoaded", async function () {
  prepareBudgetBarAnimation();
  initializeActionButtons();
  initWeeklyFuelPriceControls();

  // Add event listener for saving fuel records
  const saveFuelRecordBtn = document.getElementById("saveFuelRecord");
  if (saveFuelRecordBtn) {
    saveFuelRecordBtn.addEventListener("click", saveFuelRecord);
  }

  const saveBudgetBtn = document.getElementById("saveBudgetBtn");
  if (saveBudgetBtn) {
    saveBudgetBtn.addEventListener("click", saveFuelBudget);
  }
  initFuelBudgetControls();

  await loadDashboardVisuals();

  loadFuelRecords();
  loadFuelStatistics();
});

function prepareBudgetBarAnimation() {
  document.querySelectorAll(".budget-progress-fill").forEach((bar) => {
    bar.dataset.targetWidth = bar.style.width || "0%";
    bar.style.width = "0%";
  });
}
function handleRefreshRecords() {
  loadFuelRecords();
  showNotification("Budget deduction transactions refreshed", "success");
}

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
        "X-Requested-With": "XMLHttpRequest",
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
      filter.removeEventListener("input", debouncedHandleSearchFilters);
      filter.addEventListener("input", debouncedHandleSearchFilters);
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
    refreshBtn.removeEventListener("click", handleRefreshRecords);
    refreshBtn.addEventListener("click", handleRefreshRecords);
  }

  // Initialize row action buttons
  initializeRowActionButtons();
}

// New function to initialize row action buttons
function initializeRowActionButtons() {
  const tbody = document.getElementById("fuelRecordsBody");
  if (!tbody || tbody.dataset.actionsBound === "true") {
    return;
  }

  tbody.addEventListener("click", function (e) {
    const button = e.target.closest(".action-view, .action-edit, .action-delete");
    if (!button) {
      return;
    }

    e.preventDefault();
    const row = button.closest("tr");
    const checkbox = row ? row.querySelector(".row-checkbox") : null;
    if (!row || !checkbox) {
      return;
    }

    const recordId = checkbox.value;
    if (button.classList.contains("action-view")) {
      viewRecord(recordId);
      return;
    }

    if (button.classList.contains("action-edit")) {
      editRecord(recordId, row);
      return;
    }

    if (confirm("Are you sure you want to delete this fuel record?")) {
      deleteRecord(recordId, row);
    }
  });

  tbody.dataset.actionsBound = "true";
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
    params.append("action", "budget_deductions");

    // Add non-empty filters to params
    Object.keys(filters).forEach((key) => {
      if (filters[key]) {
        params.append(key, filters[key]);
      }
    });

    const response = await fetch(`get_fuel_data.php?${params.toString()}`);
    if (!response.ok) throw new Error("Network response was not ok");

    const data = await response.json();
    if (data.success) {
      updateBudgetDeductionTable(data.data || []);
      showNotification(`Showing ${(data.data || []).length} budget deduction transactions`, "info");
    } else {
      throw new Error(data.message || "Failed to load budget deduction transactions");
    }
  } catch (error) {
    console.error("Error loading filtered budget deductions:", error);
    showNotification(error.message, "danger");
  }
}

function escapeHtml(value) {
  return String(value == null ? "" : value).replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  }[character]));
}

function formatMoney(value) {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatLiters(value) {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDateTime(value) {
  if (!value) return "-";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return escapeHtml(value);
  return date.toLocaleString(undefined, {
    year: "numeric",
    month: "short",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatPeriod(startDate, endDate) {
  if (!startDate && !endDate) return "All dates";
  if (startDate && endDate && startDate !== endDate) {
    return `${startDate} to ${endDate}`;
  }
  return startDate || endDate || "All dates";
}

function updateBudgetDeductionTable(records) {
  const tbody = document.getElementById("fuelRecordsBody");
  if (!tbody) return;
  const totalRecordsElement = document.getElementById("totalRecords");

  tbody.innerHTML = "";
  if (!records.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" class="text-center text-muted py-4">
          <i class="fas fa-receipt me-2"></i>No budget deduction transactions found
        </td>
      </tr>`;
    if (totalRecordsElement) totalRecordsElement.textContent = "0";
    updateSelectedCount();
    return;
  }

  records.forEach((record) => {
    const row = document.createElement("tr");
    const ref = record.summary_group_hash
      ? String(record.summary_group_hash).slice(0, 10).toUpperCase()
      : "-";
    row.innerHTML = `
      <td><span class="fw-medium">${formatDateTime(record.created_at)}</span></td>
      <td><span class="font-monospace fw-semibold">${escapeHtml(record.ib_no || "-")}</span></td>
      <td>
        <span class="fw-semibold">${escapeHtml(record.office || "All Offices")}</span>
        <small class="text-muted d-block">${escapeHtml(formatPeriod(record.start_date, record.end_date))}</small>
      </td>
      <td class="text-end">
        <span class="fw-bold text-warning">${formatPeso(record.diesel_amount)}</span>
        <small class="text-muted d-block">${formatLiters(record.diesel_liters)} L</small>
      </td>
      <td class="text-end">
        <span class="fw-bold text-success">${formatPeso(record.unleaded_amount)}</span>
        <small class="text-muted d-block">${formatLiters(record.unleaded_liters)} L</small>
      </td>
      <td class="text-end"><span class="fw-bold text-primary">${formatPeso(record.total_amount)}</span></td>
      <td>${escapeHtml(record.created_by || "-")}</td>
      <td><span class="badge bg-light text-dark font-monospace">${escapeHtml(ref)}</span></td>
    `;
    tbody.appendChild(row);
  });

  if (totalRecordsElement) totalRecordsElement.textContent = String(records.length);
  updateSelectedCount();
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

async function loadFuelBudgetSummary() {
  try {
    updateFuelBudgetSummary(await fetchFuelBudgetSummary());
  } catch (error) {
    console.error("Error loading fuel budget summary:", error);
  }
}

async function fetchFuelBudgetSummary() {
  const response = await fetch("get_fuel_data.php?action=budget_summary");
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  const payload = await response.json();
  if (!payload.success) {
    throw new Error(payload.message || "Unable to load budget summary.");
  }

  return payload.data || {};
}

function formatPeso(value) {
  return "\u20b1" + Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

let dashboardBudgetSummary = {};
let dashboardDraftIssuances = {
  diesel_liters: 0,
  unleaded_liters: 0,
  diesel_records: 0,
  unleaded_records: 0,
};
let dashboardFuelPriceHistory = [];
let fuelPriceTrendChart = null;
let weeklyBudgetDeductionChart = null;

async function fetchDraftBudgetIssuances() {
  const response = await fetch("get_fuel_data.php?action=draft_budget_issuances");
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  const payload = await response.json();
  if (!payload.success) {
    throw new Error(payload.message || "Unable to load draft budget issuances.");
  }

  return payload.data || {};
}

async function fetchWeeklyFuelPrices() {
  const response = await fetch("get_fuel_data.php?action=weekly_fuel_prices");
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  const payload = await response.json();
  if (!payload.success) {
    throw new Error(payload.message || "Unable to load weekly fuel prices.");
  }

  return payload.data || { latest: null, history: [] };
}

async function fetchWeeklyBudgetDeductions() {
  const response = await fetch("get_fuel_data.php?action=weekly_budget_deductions");
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  const payload = await response.json();
  if (!payload.success) {
    throw new Error(payload.message || "Unable to load weekly budget deductions.");
  }

  return Array.isArray(payload.data) ? payload.data : [];
}

function draftBudgetPercent(remaining, total) {
  if (!total || total <= 0) {
    return 0;
  }

  return Math.max(0, Math.min(100, (remaining / total) * 100));
}

function renderDraftFuelBudget() {
  const dieselPriceInput = document.getElementById("dashboardDieselPumpPrice");
  const unleadedPriceInput = document.getElementById("dashboardUnleadedPumpPrice");
  const dieselPrice = Number(dieselPriceInput?.value || 0) || 0;
  const unleadedPrice = Number(unleadedPriceInput?.value || 0) || 0;
  const dieselLiters = Number(dashboardDraftIssuances.diesel_liters || 0) || 0;
  const unleadedLiters = Number(dashboardDraftIssuances.unleaded_liters || 0) || 0;
  const dieselCost = dieselLiters * dieselPrice;
  const unleadedCost = unleadedLiters * unleadedPrice;
  const dieselTotal = Number(dashboardBudgetSummary.total_diesel_budget || 0) || 0;
  const unleadedTotal = Number(dashboardBudgetSummary.total_unleaded_budget || 0) || 0;
  const dieselLeft = (Number(dashboardBudgetSummary.remaining_diesel_budget || 0) || 0) - dieselCost;
  const unleadedLeft = (Number(dashboardBudgetSummary.remaining_unleaded_budget || 0) || 0) - unleadedCost;
  const dieselPercent = draftBudgetPercent(dieselLeft, dieselTotal);
  const unleadedPercent = draftBudgetPercent(unleadedLeft, unleadedTotal);

  const dieselRemaining = document.getElementById("estimatedBudgetDieselRemaining");
  const unleadedRemaining = document.getElementById("estimatedBudgetUnleadedRemaining");
  const dieselBar = document.getElementById("estimatedBudgetDieselBar");
  const unleadedBar = document.getElementById("estimatedBudgetUnleadedBar");
  const dieselPercentText = document.getElementById("estimatedBudgetDieselPercent");
  const unleadedPercentText = document.getElementById("estimatedBudgetUnleadedPercent");
  const dieselTotalText = document.getElementById("estimatedBudgetDieselTotal");
  const unleadedTotalText = document.getElementById("estimatedBudgetUnleadedTotal");
  const estimatedCost = document.getElementById("estimatedBudgetCost");
  const estimatedLeft = document.getElementById("estimatedBudgetTotalLeft");
  const note = document.getElementById("budgetDraftNote");

  if (dieselRemaining) dieselRemaining.textContent = formatPeso(dieselLeft);
  if (unleadedRemaining) unleadedRemaining.textContent = formatPeso(unleadedLeft);
  if (dieselBar) {
    dieselBar.style.width = `${dieselPercent.toFixed(2)}%`;
    dieselBar.parentElement?.setAttribute("aria-valuenow", Math.round(dieselPercent).toString());
  }
  if (unleadedBar) {
    unleadedBar.style.width = `${unleadedPercent.toFixed(2)}%`;
    unleadedBar.parentElement?.setAttribute("aria-valuenow", Math.round(unleadedPercent).toString());
  }
  if (dieselPercentText) dieselPercentText.textContent = `${Math.round(dieselPercent)}% left`;
  if (unleadedPercentText) unleadedPercentText.textContent = `${Math.round(unleadedPercent)}% left`;
  if (dieselTotalText) dieselTotalText.textContent = `of ${formatPeso(dieselTotal)}`;
  if (unleadedTotalText) unleadedTotalText.textContent = `of ${formatPeso(unleadedTotal)}`;
  if (estimatedCost) estimatedCost.textContent = formatPeso(dieselCost + unleadedCost);
  if (estimatedLeft) estimatedLeft.textContent = formatPeso(dieselLeft + unleadedLeft);
  if (note) {
    note.textContent = `Reserved: ${dieselLiters.toFixed(2)} L diesel, ${unleadedLiters.toFixed(2)} L unleaded.`;
  }

}

function initWeeklyFuelPriceControls() {
  ["dashboardFuelPriceWeek", "dashboardDieselPumpPrice", "dashboardUnleadedPumpPrice"].forEach((inputId) => {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener("input", renderDraftFuelBudget);
  });

  const saveButton = document.getElementById("saveWeeklyFuelPriceBtn");
  if (saveButton) {
    saveButton.addEventListener("click", saveWeeklyFuelPrices);
  }
}

function setWeeklyFuelPriceInputs(latest) {
  const weekInput = document.getElementById("dashboardFuelPriceWeek");
  const dieselInput = document.getElementById("dashboardDieselPumpPrice");
  const unleadedInput = document.getElementById("dashboardUnleadedPumpPrice");
  const sourceInput = document.getElementById("dashboardFuelPriceSource");

  if (!latest) {
    if (weekInput && !weekInput.value) weekInput.value = currentTuesdayDate();
    renderDraftFuelBudget();
    return;
  }

  if (weekInput) weekInput.value = latest.week_start || currentTuesdayDate();
  if (dieselInput) dieselInput.value = Number(latest.diesel_price || 0).toFixed(2);
  if (unleadedInput) unleadedInput.value = Number(latest.unleaded_price || 0).toFixed(2);
  if (sourceInput) sourceInput.value = latest.source_note || "";
  renderDraftFuelBudget();
}

function currentTuesdayDate() {
  const date = new Date();
  const day = date.getDay() === 0 ? 7 : date.getDay();
  date.setDate(date.getDate() + (2 - day));
  return date.toISOString().slice(0, 10);
}

function formatShortDate(dateString) {
  if (!dateString) return "";
  return new Date(`${dateString}T00:00:00`).toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
  });
}

function formatFullDate(dateString) {
  if (!dateString) return "-";
  return new Date(`${dateString}T00:00:00`).toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatDateTime(dateTimeString) {
  if (!dateTimeString) return "-";
  const normalized = dateTimeString.includes("T")
    ? dateTimeString
    : dateTimeString.replace(" ", "T");
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) return "-";
  return date.toLocaleString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

async function saveWeeklyFuelPrices() {
  const button = document.getElementById("saveWeeklyFuelPriceBtn");
  const weekInput = document.getElementById("dashboardFuelPriceWeek");
  const dieselInput = document.getElementById("dashboardDieselPumpPrice");
  const unleadedInput = document.getElementById("dashboardUnleadedPumpPrice");
  const sourceInput = document.getElementById("dashboardFuelPriceSource");

  const originalHtml = button?.innerHTML || "";
  if (button) {
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
  }

  try {
    const response = await fetch("fuel_price_save.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        week_start: weekInput?.value || currentTuesdayDate(),
        diesel_price: dieselInput?.value || 0,
        unleaded_price: unleadedInput?.value || 0,
        source_note: sourceInput?.value || "",
      }),
    });

    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Unable to save weekly fuel prices.");
    }

    renderWeeklyFuelPrices({
      latest: payload.latest || null,
      history: payload.history || [],
    });
    const [summaryResult, weeklyDeductionResult] = await Promise.allSettled([
      fetchFuelBudgetSummary(),
      fetchWeeklyBudgetDeductions(),
    ]);
    if (summaryResult.status === "fulfilled") {
      updateFuelBudgetSummary(summaryResult.value);
    }
    if (weeklyDeductionResult.status === "fulfilled") {
      renderWeeklyBudgetDeductions(weeklyDeductionResult.value);
    }
    showNotification(payload.message || "Weekly fuel prices saved.", "success");
  } catch (error) {
    showNotification(error.message || "Unable to save weekly fuel prices.", "danger");
  } finally {
    if (button) {
      button.disabled = false;
      button.innerHTML = originalHtml;
    }
  }
}

function updateFuelBudgetSummary(summary) {
  dashboardBudgetSummary = summary || {};
  renderActualFuelBudget();
  renderDraftFuelBudget();
  renderBudgetManagementTable();
}

function initFuelBudgetControls() {
  const coverage = document.getElementById("budgetFuelCoverage");
  const openAddButton = document.getElementById("openAddBudgetBtn");
  const manageAddButton = document.getElementById("manageAddBudgetBtn");
  const managementBody = document.getElementById("budgetManagementBody");

  coverage?.addEventListener("change", updateBudgetCoverageFields);
  openAddButton?.addEventListener("click", resetFuelBudgetForm);
  manageAddButton?.addEventListener("click", () => {
    resetFuelBudgetForm();
    showAddBudgetModalAfterManage();
  });
  managementBody?.addEventListener("click", (event) => {
    const editButton = event.target.closest("[data-edit-budget-id]");
    if (!editButton) return;
    editFuelBudget(Number(editButton.dataset.editBudgetId || 0));
  });

  updateBudgetCoverageFields();
}

function showAddBudgetModalAfterManage() {
  const manageModalElement = document.getElementById("manageBudgetModal");
  const addModalElement = document.getElementById("addBudgetModal");
  if (!manageModalElement || !addModalElement) return;

  manageModalElement.addEventListener(
    "hidden.bs.modal",
    () => bootstrap.Modal.getOrCreateInstance(addModalElement).show(),
    { once: true }
  );
  bootstrap.Modal.getOrCreateInstance(manageModalElement).hide();
}

function resetFuelBudgetForm() {
  const form = document.getElementById("addBudgetForm");
  form?.reset();
  document.getElementById("addBudgetModalLabel").innerHTML =
    '<i class="fas fa-wallet me-2"></i>Add IB Budget';
  document.getElementById("budgetIbNo").readOnly = false;
  document.getElementById("budgetIbHelp").textContent =
    "Enter a new IB number or edit an existing IB from Manage IBs.";
  document.getElementById("budgetFuelCoverage").value = "both";
  document.getElementById("budgetDieselAllocation").value = "0";
  document.getElementById("budgetUnleadedAllocation").value = "0";
  document.getElementById("budgetDieselUsedHelp").textContent = "";
  document.getElementById("budgetUnleadedUsedHelp").textContent = "";
  updateBudgetCoverageFields();
}

function updateBudgetCoverageFields() {
  const coverage = document.getElementById("budgetFuelCoverage")?.value || "both";
  const dieselSelected = coverage === "diesel" || coverage === "both";
  const unleadedSelected = coverage === "unleaded" || coverage === "both";
  const dieselGroup = document.getElementById("budgetDieselAllocationGroup");
  const unleadedGroup = document.getElementById("budgetUnleadedAllocationGroup");
  const dieselInput = document.getElementById("budgetDieselAllocation");
  const unleadedInput = document.getElementById("budgetUnleadedAllocation");

  dieselGroup?.classList.toggle("d-none", !dieselSelected);
  unleadedGroup?.classList.toggle("d-none", !unleadedSelected);
  if (dieselInput) {
    dieselInput.required = dieselSelected;
    dieselInput.disabled = !dieselSelected;
  }
  if (unleadedInput) {
    unleadedInput.required = unleadedSelected;
    unleadedInput.disabled = !unleadedSelected;
  }
}

function budgetFuelCoverageLabel(budget) {
  const hasDiesel = Number(budget.diesel_allocation || 0) > 0;
  const hasUnleaded = Number(budget.unleaded_allocation || 0) > 0;
  if (hasDiesel && hasUnleaded) return "Diesel and Unleaded";
  if (hasDiesel) return "Diesel Only";
  if (hasUnleaded) return "Unleaded Only";
  return "No Allocation";
}

function renderBudgetManagementTable() {
  const tbody = document.getElementById("budgetManagementBody");
  if (!tbody) return;
  const budgets = Array.isArray(dashboardBudgetSummary.budgets)
    ? dashboardBudgetSummary.budgets
    : [];

  if (!budgets.length) {
    tbody.innerHTML =
      '<tr><td colspan="8" class="text-center text-muted py-4">No IB budgets saved yet.</td></tr>';
    return;
  }

  tbody.innerHTML = budgets
    .map(
      (budget) => `
        <tr>
          <td><span class="font-monospace fw-semibold">${escapeHtml(budget.ib_no || "-")}</span></td>
          <td>${escapeHtml(budget.description || "-")}</td>
          <td><span class="badge text-bg-light border">${budgetFuelCoverageLabel(budget)}</span></td>
          <td class="text-end">${formatPeso(budget.diesel_allocation)}</td>
          <td class="text-end text-warning fw-semibold">${formatPeso(budget.remaining_diesel_amount)}</td>
          <td class="text-end">${formatPeso(budget.unleaded_allocation)}</td>
          <td class="text-end text-success fw-semibold">${formatPeso(budget.remaining_unleaded_amount)}</td>
          <td class="text-end">
            <button type="button" class="btn btn-outline-primary btn-sm" data-edit-budget-id="${Number(budget.id || 0)}">
              <i class="fas fa-pen me-1"></i>Edit
            </button>
          </td>
        </tr>
      `
    )
    .join("");
}

function editFuelBudget(budgetId) {
  const budgets = Array.isArray(dashboardBudgetSummary.budgets)
    ? dashboardBudgetSummary.budgets
    : [];
  const budget = budgets.find((item) => Number(item.id || 0) === budgetId);
  if (!budget) {
    showNotification("Unable to find the selected IB budget.", "danger");
    return;
  }

  document.getElementById("addBudgetModalLabel").innerHTML =
    '<i class="fas fa-pen me-2"></i>Edit IB Budget';
  const ibInput = document.getElementById("budgetIbNo");
  ibInput.value = budget.ib_no || "";
  ibInput.readOnly = true;
  document.getElementById("budgetIbHelp").textContent =
    "IB numbers cannot be renamed. Choose the fuel allocation you want to update.";
  document.getElementById("budgetDescription").value = budget.description || "";
  document.getElementById("budgetDieselAllocation").value = Number(
    budget.diesel_allocation || 0
  ).toFixed(2);
  document.getElementById("budgetUnleadedAllocation").value = Number(
    budget.unleaded_allocation || 0
  ).toFixed(2);
  document.getElementById("budgetDieselUsedHelp").textContent =
    `Already deducted: ${formatPeso(budget.used_diesel_amount)}`;
  document.getElementById("budgetUnleadedUsedHelp").textContent =
    `Already deducted: ${formatPeso(budget.used_unleaded_amount)}`;

  const hasDiesel = Number(budget.diesel_allocation || 0) > 0;
  const hasUnleaded = Number(budget.unleaded_allocation || 0) > 0;
  document.getElementById("budgetFuelCoverage").value =
    hasDiesel && hasUnleaded ? "both" : hasDiesel ? "diesel" : "unleaded";
  document.getElementById("budgetAddAnother").checked = false;
  updateBudgetCoverageFields();
  showAddBudgetModalAfterManage();
}

function renderActualFuelBudget() {
  const dieselRemaining = document.getElementById("budgetDieselRemaining");
  const unleadedRemaining = document.getElementById("budgetUnleadedRemaining");
  const dieselBar = document.getElementById("budgetDieselBar");
  const unleadedBar = document.getElementById("budgetUnleadedBar");
  const dieselPercentText = document.getElementById("budgetDieselPercent");
  const unleadedPercentText = document.getElementById("budgetUnleadedPercent");
  const dieselTotalText = document.getElementById("budgetDieselTotal");
  const unleadedTotalText = document.getElementById("budgetUnleadedTotal");
  const total = document.getElementById("budgetTotal");
  const used = document.getElementById("budgetUsed");
  const actualUsedTotal = document.getElementById("actualUsedTotalAmount");
  const actualDieselAmount = document.getElementById("actualUsedDieselAmount");
  const actualDieselLitersText = document.getElementById("actualUsedDieselLiters");
  const actualUnleadedAmount = document.getElementById("actualUsedUnleadedAmount");
  const actualUnleadedLitersText = document.getElementById("actualUsedUnleadedLiters");
  const actualMissingPrices = document.getElementById("actualUsedMissingPrices");
  const dieselTotal = Number(dashboardBudgetSummary.total_diesel_budget || 0) || 0;
  const dieselLeft = Number(dashboardBudgetSummary.remaining_diesel_budget || 0) || 0;
  const unleadedTotal = Number(dashboardBudgetSummary.total_unleaded_budget || 0) || 0;
  const unleadedLeft = Number(dashboardBudgetSummary.remaining_unleaded_budget || 0) || 0;
  const dieselPercent = draftBudgetPercent(dieselLeft, dieselTotal);
  const unleadedPercent = draftBudgetPercent(unleadedLeft, unleadedTotal);

  if (dieselRemaining) dieselRemaining.textContent = formatPeso(dieselLeft);
  if (unleadedRemaining) unleadedRemaining.textContent = formatPeso(unleadedLeft);
  if (dieselBar) {
    dieselBar.style.width = `${dieselPercent.toFixed(2)}%`;
    dieselBar.parentElement?.setAttribute("aria-valuenow", Math.round(dieselPercent).toString());
  }
  if (unleadedBar) {
    unleadedBar.style.width = `${unleadedPercent.toFixed(2)}%`;
    unleadedBar.parentElement?.setAttribute("aria-valuenow", Math.round(unleadedPercent).toString());
  }
  if (dieselPercentText) dieselPercentText.textContent = `${Math.round(dieselPercent)}% left`;
  if (unleadedPercentText) unleadedPercentText.textContent = `${Math.round(unleadedPercent)}% left`;
  const dieselUsed = Number(dashboardBudgetSummary.used_diesel_budget || 0) || 0;
  const unleadedUsed = Number(dashboardBudgetSummary.used_unleaded_budget || 0) || 0;
  const dieselLiters = Number(dashboardBudgetSummary.actual_diesel_liters || 0) || 0;
  const unleadedLiters = Number(dashboardBudgetSummary.actual_unleaded_liters || 0) || 0;
  const missing = Number(dashboardBudgetSummary.actual_missing_price_count || 0) || 0;
  if (dieselTotalText) dieselTotalText.textContent = `used ${formatPeso(dieselUsed)} of ${formatPeso(dieselTotal)}`;
  if (unleadedTotalText) unleadedTotalText.textContent = `used ${formatPeso(unleadedUsed)} of ${formatPeso(unleadedTotal)}`;
  if (total) total.textContent = formatPeso(dashboardBudgetSummary.total_budget || 0);
  if (used) used.textContent = formatPeso(dashboardBudgetSummary.used_budget || 0);
  if (actualUsedTotal) actualUsedTotal.textContent = formatPeso(dashboardBudgetSummary.used_budget || 0);
  if (actualDieselAmount) actualDieselAmount.textContent = formatPeso(dieselUsed);
  if (actualDieselLitersText) actualDieselLitersText.textContent = `${dieselLiters.toFixed(2)} L`;
  if (actualUnleadedAmount) actualUnleadedAmount.textContent = formatPeso(unleadedUsed);
  if (actualUnleadedLitersText) actualUnleadedLitersText.textContent = `${unleadedLiters.toFixed(2)} L`;
  if (actualMissingPrices) {
    actualMissingPrices.classList.toggle("d-none", missing === 0);
    actualMissingPrices.textContent = missing
      ? `${missing} used record(s) need weekly pump prices before they can be fully costed.`
      : "";
  }

  const note = document.getElementById("actualBudgetPriceNote");
  if (note) {
    note.textContent = `Actual used: ${dieselLiters.toFixed(2)} L diesel and ${unleadedLiters.toFixed(2)} L unleaded, priced by the weekly pump price saved for each used date.${missing ? ` ${missing} used record(s) have no matching weekly price yet.` : ""}`;
  }
}

let officeConsumptionChart = null;
let vehicleConsumptionChart = null;

async function loadConsumptionRankings() {
  try {
    renderConsumptionRankings(await fetchConsumptionRankings());
  } catch (error) {
    console.error("Error loading consumption rankings:", error);
  }
}

async function fetchConsumptionRankings() {
  const response = await fetch("get_fuel_data.php?action=consumption_rankings");
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  const payload = await response.json();
  if (!payload.success) {
    throw new Error(payload.message || "Unable to load consumption rankings.");
  }

  return payload.data || {};
}

async function loadDashboardVisuals() {
  const [summaryResult, draftResult, rankingsResult, fuelPriceResult, weeklyDeductionResult] = await Promise.allSettled([
    fetchFuelBudgetSummary(),
    fetchDraftBudgetIssuances(),
    fetchConsumptionRankings(),
    fetchWeeklyFuelPrices(),
    fetchWeeklyBudgetDeductions(),
  ]);

  requestAnimationFrame(() => {
    if (fuelPriceResult.status === "fulfilled") {
      renderWeeklyFuelPrices(fuelPriceResult.value);
    } else {
      console.error("Error loading weekly fuel prices:", fuelPriceResult.reason);
      setWeeklyFuelPriceInputs(null);
      renderFuelPriceTrend([]);
    }

    if (draftResult.status === "fulfilled") {
      dashboardDraftIssuances = draftResult.value || {};
    } else {
      console.error("Error loading draft budget issuances:", draftResult.reason);
    }

    if (summaryResult.status === "fulfilled") {
      updateFuelBudgetSummary(summaryResult.value);
    } else {
      console.error("Error loading fuel budget summary:", summaryResult.reason);
    }

    if (rankingsResult.status === "fulfilled") {
      renderConsumptionRankings(rankingsResult.value);
    } else {
      console.error("Error loading consumption rankings:", rankingsResult.reason);
    }

    if (weeklyDeductionResult.status === "fulfilled") {
      renderWeeklyBudgetDeductions(weeklyDeductionResult.value);
    } else {
      console.error("Error loading weekly budget deductions:", weeklyDeductionResult.reason);
      renderWeeklyBudgetDeductions([]);
    }
  });
}

function renderWeeklyFuelPrices(priceData) {
  const latest = priceData?.latest || null;
  dashboardFuelPriceHistory = Array.isArray(priceData?.history) ? priceData.history : [];
  setWeeklyFuelPriceInputs(latest);
  renderFuelPriceTrend(dashboardFuelPriceHistory);
  renderWeeklyFuelPriceHistoryModal(dashboardFuelPriceHistory);
}

function renderWeeklyFuelPriceHistoryModal(history) {
  const tbody = document.getElementById("weeklyFuelPriceHistoryBody");
  const summary = document.getElementById("weeklyFuelPriceHistorySummary");
  if (!tbody) return;

  const rows = (history || [])
    .map((row) => ({
      week_start: row.week_start || "",
      diesel_price: Number(row.diesel_price || 0),
      unleaded_price: Number(row.unleaded_price || 0),
      source_note: row.source_note || "",
      updated_at: row.updated_at || "",
    }))
    .filter((row) => row.week_start)
    .sort((a, b) => b.week_start.localeCompare(a.week_start));

  tbody.replaceChildren();

  if (summary) {
    summary.textContent = rows.length
      ? `Showing ${rows.length} saved weekly pump price ${rows.length === 1 ? "entry" : "entries"}.`
      : "No saved weekly pump prices yet.";
  }

  if (rows.length === 0) {
    const emptyRow = document.createElement("tr");
    const emptyCell = document.createElement("td");
    emptyCell.colSpan = 5;
    emptyCell.className = "text-center text-muted py-4";
    emptyCell.textContent = "No weekly fuel prices saved yet.";
    emptyRow.appendChild(emptyCell);
    tbody.appendChild(emptyRow);
    return;
  }

  rows.forEach((row) => {
    const tr = document.createElement("tr");
    const updatedAt = row.updated_at ? formatDateTime(row.updated_at) : "";
    const cells = [
      { text: formatFullDate(row.week_start), className: "fw-semibold" },
      { text: formatPeso(row.diesel_price), className: "text-end fuel-price-history-amount" },
      { text: formatPeso(row.unleaded_price), className: "text-end fuel-price-history-amount" },
      { text: row.source_note || "-", className: "text-muted weekly-price-history-note" },
      { text: updatedAt || "-", className: "text-muted weekly-price-history-updated" },
    ];

    cells.forEach((cell) => {
      const td = document.createElement("td");
      td.className = cell.className;
      td.textContent = cell.text;
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
}

function renderFuelPriceTrend(history) {
  const canvas = document.getElementById("fuelPriceTrendChart");
  const empty = document.getElementById("fuelPriceTrendEmpty");
  if (!canvas || typeof Chart === "undefined") {
    return;
  }

  const rows = (history || [])
    .map((row) => ({
      week_start: row.week_start || "",
      diesel_price: Number(row.diesel_price || 0),
      unleaded_price: Number(row.unleaded_price || 0),
    }))
    .filter((row) => row.week_start && (row.diesel_price > 0 || row.unleaded_price > 0));

  canvas.classList.toggle("d-none", rows.length === 0);
  if (empty) empty.classList.toggle("d-none", rows.length > 0);

  if (fuelPriceTrendChart) {
    fuelPriceTrendChart.destroy();
    fuelPriceTrendChart = null;
  }

  if (rows.length === 0) {
    return;
  }

  fuelPriceTrendChart = new Chart(canvas, {
    type: "line",
    data: {
      labels: rows.map((row) => formatShortDate(row.week_start)),
      datasets: [
        {
          label: "Diesel",
          data: rows.map((row) => row.diesel_price),
          borderColor: "#f5b301",
          backgroundColor: "rgba(245, 179, 1, 0.12)",
          borderWidth: 2,
          pointRadius: 2.5,
          pointHoverRadius: 4,
          tension: 0.25,
        },
        {
          label: "Unleaded",
          data: rows.map((row) => row.unleaded_price),
          borderColor: "#198754",
          backgroundColor: "rgba(25, 135, 84, 0.12)",
          borderWidth: 2,
          pointRadius: 2.5,
          pointHoverRadius: 4,
          tension: 0.25,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : 650,
        easing: "easeOutQuart",
      },
      plugins: {
        legend: {
          display: true,
          position: "bottom",
          labels: {
            boxWidth: 10,
            boxHeight: 10,
            usePointStyle: true,
          },
        },
        tooltip: {
          callbacks: {
            label: (context) => `${context.dataset.label}: ${formatPeso(context.raw)}`,
          },
        },
      },
      scales: {
        x: {
          grid: {
            display: false,
          },
          ticks: {
            maxRotation: 0,
            autoSkip: true,
            maxTicksLimit: 5,
          },
        },
        y: {
          beginAtZero: false,
          ticks: {
            callback: (value) => `\u20b1${Number(value).toFixed(0)}`,
            maxTicksLimit: 5,
          },
          grid: {
            color: "rgba(15, 23, 42, 0.06)",
          },
        },
      },
    },
  });
}

function renderWeeklyBudgetDeductions(rows) {
  const canvas = document.getElementById("weeklyBudgetDeductionChart");
  const empty = document.getElementById("weeklyBudgetDeductionEmpty");
  const summary = document.getElementById("weeklyDeductionChartSummary");
  if (!canvas || typeof Chart === "undefined") {
    return;
  }

  const chartRows = (rows || [])
    .map((row) => ({
      week_start: row.week_start || "",
      diesel_amount: Number(row.diesel_amount || 0),
      unleaded_amount: Number(row.unleaded_amount || 0),
      total_amount: Number(row.total_amount || 0),
      diesel_liters: Number(row.diesel_liters || 0),
      unleaded_liters: Number(row.unleaded_liters || 0),
      diesel_price: Number(row.diesel_price || 0),
      unleaded_price: Number(row.unleaded_price || 0),
      missing_price_count: Number(row.missing_price_count || 0),
    }))
    .filter((row) => row.week_start && (row.total_amount > 0 || row.missing_price_count > 0));

  canvas.classList.toggle("d-none", chartRows.length === 0);
  if (empty) empty.classList.toggle("d-none", chartRows.length > 0);

  if (weeklyBudgetDeductionChart) {
    weeklyBudgetDeductionChart.destroy();
    weeklyBudgetDeductionChart = null;
  }

  const totalAmount = chartRows.reduce((sum, row) => sum + row.total_amount, 0);
  const missingCount = chartRows.reduce((sum, row) => sum + row.missing_price_count, 0);
  if (summary) {
    summary.textContent = chartRows.length
      ? `${chartRows.length} week${chartRows.length === 1 ? "" : "s"} shown · ${formatPeso(totalAmount)} total${missingCount ? ` · ${missingCount} missing price` : ""}`
      : "No used gas issuance deductions yet.";
  }

  if (chartRows.length === 0) {
    return;
  }

  weeklyBudgetDeductionChart = new Chart(canvas, {
    type: "bar",
    data: {
      labels: chartRows.map((row) => formatShortDate(row.week_start)),
      datasets: [
        {
          label: "Diesel Deduction",
          data: chartRows.map((row) => row.diesel_amount),
          backgroundColor: "rgba(245, 179, 1, 0.86)",
          borderColor: "#d99a00",
          borderWidth: 1,
          stack: "weekly-deductions",
          order: 2,
        },
        {
          label: "Unleaded Deduction",
          data: chartRows.map((row) => row.unleaded_amount),
          backgroundColor: "rgba(25, 135, 84, 0.84)",
          borderColor: "#198754",
          borderWidth: 1,
          stack: "weekly-deductions",
          order: 2,
        },
        {
          label: "Diesel Price/L",
          type: "line",
          data: chartRows.map((row) => row.diesel_price || null),
          borderColor: "#a36b00",
          backgroundColor: "rgba(163, 107, 0, 0.12)",
          borderDash: [5, 4],
          borderWidth: 2,
          pointRadius: 2.5,
          pointHoverRadius: 4,
          spanGaps: true,
          tension: 0.25,
          yAxisID: "price",
          order: 1,
        },
        {
          label: "Unleaded Price/L",
          type: "line",
          data: chartRows.map((row) => row.unleaded_price || null),
          borderColor: "#0f6840",
          backgroundColor: "rgba(15, 104, 64, 0.12)",
          borderDash: [5, 4],
          borderWidth: 2,
          pointRadius: 2.5,
          pointHoverRadius: 4,
          spanGaps: true,
          tension: 0.25,
          yAxisID: "price",
          order: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : 700,
        easing: "easeOutQuart",
      },
      plugins: {
        legend: {
          display: true,
          position: "bottom",
          labels: {
            boxWidth: 10,
            boxHeight: 10,
            usePointStyle: true,
            pointStyle: "rectRounded",
          },
        },
        tooltip: {
          callbacks: {
            title: (items) => {
              const index = items[0]?.dataIndex ?? 0;
              return formatFullDate(chartRows[index]?.week_start || "");
            },
            label: (context) => {
              const row = chartRows[context.dataIndex] || {};
              if (context.dataset.yAxisID === "price") {
                return `${context.dataset.label}: ${formatPeso(context.raw)}`;
              }
              const isDiesel = context.dataset.label.includes("Diesel");
              const liters = isDiesel ? row.diesel_liters : row.unleaded_liters;
              const price = isDiesel ? row.diesel_price : row.unleaded_price;
              return `${context.dataset.label}: ${formatPeso(context.raw)} (${Number(liters || 0).toFixed(2)} L @ ${formatPeso(price)}/L)`;
            },
            footer: (items) => {
              const row = chartRows[items[0]?.dataIndex ?? 0] || {};
              return `Week total: ${formatPeso(row.total_amount || 0)}`;
            },
          },
        },
      },
      scales: {
        x: {
          stacked: true,
          grid: {
            display: false,
          },
          ticks: {
            maxRotation: 0,
            autoSkip: true,
            maxTicksLimit: 8,
          },
        },
        y: {
          stacked: true,
          beginAtZero: true,
          position: "left",
          ticks: {
            callback: (value) => `\u20b1${Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 })}`,
            maxTicksLimit: 6,
          },
          grid: {
            color: "rgba(15, 23, 42, 0.06)",
          },
        },
        price: {
          beginAtZero: false,
          grid: {
            drawOnChartArea: false,
          },
          position: "right",
          ticks: {
            callback: (value) => `\u20b1${Number(value).toFixed(0)}`,
            maxTicksLimit: 5,
          },
        },
      },
    },
  });
}

function renderConsumptionRankings(rankings) {
  renderRankingChart("officeConsumptionChart", "officeChartEmpty", rankings?.offices || [], "office");
  renderRankingChart("vehicleConsumptionChart", "vehicleChartEmpty", rankings?.vehicles || [], "vehicle");
}

const rankingDataLabelPlugin = {
  id: "rankingDataLabelPlugin",
  afterDatasetsDraw(chart) {
    const { ctx, scales } = chart;
    const xScale = scales.x;
    const yScale = scales.y;
    if (!xScale || !yScale) return;

    ctx.save();
    ctx.fillStyle = "#334155";
    ctx.font = "700 11px Arial, sans-serif";
    ctx.textAlign = "left";
    ctx.textBaseline = "middle";

    chart.data.labels.forEach((_, index) => {
      const total = chart.data.datasets.reduce(
        (sum, dataset) => sum + Number(dataset.data[index] || 0),
        0
      );
      if (total <= 0) return;

      const x = Math.min(xScale.getPixelForValue(total) + 8, chart.chartArea.right - 44);
      const y = yScale.getPixelForValue(index);
      ctx.fillText(`${total.toFixed(2)} L`, x, y);
    });
    ctx.restore();
  },
};

function renderRankingChart(canvasId, emptyId, rows, chartType) {
  const canvas = document.getElementById(canvasId);
  const empty = document.getElementById(emptyId);
  if (!canvas || typeof Chart === "undefined") {
    return;
  }

  const cleanRows = rows
    .map((row) => ({
      label: row.label || "Unknown",
      total_liters: Number(row.total_liters || 0),
      diesel_liters: Number(row.diesel_liters || 0),
      unleaded_liters: Number(row.unleaded_liters || 0),
    }))
    .filter((row) => row.total_liters > 0);

  canvas.classList.toggle("d-none", cleanRows.length === 0);
  if (empty) empty.classList.toggle("d-none", cleanRows.length > 0);

  const existingChart =
    chartType === "office" ? officeConsumptionChart : vehicleConsumptionChart;
  if (existingChart) {
    existingChart.destroy();
  }

  if (cleanRows.length === 0) {
    if (chartType === "office") officeConsumptionChart = null;
    else vehicleConsumptionChart = null;
    return;
  }

  const maxLiters = Math.max(...cleanRows.map((row) => row.total_liters), 0);
  const chart = new Chart(canvas, {
    type: "bar",
    data: {
      labels: cleanRows.map((row) => row.label),
      datasets: [
        {
          label: "Diesel",
          data: cleanRows.map((row) => row.diesel_liters),
          backgroundColor: "#f5b301",
          borderColor: "#d99a00",
          borderWidth: 1,
          borderRadius: 5,
          barThickness: 12,
          maxBarThickness: 12,
        },
        {
          label: "Unleaded",
          data: cleanRows.map((row) => row.unleaded_liters),
          backgroundColor: "#198754",
          borderColor: "#146c43",
          borderWidth: 1,
          borderRadius: 5,
          barThickness: 12,
          maxBarThickness: 12,
        },
      ],
    },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : 850,
        easing: "easeOutQuart",
      },
      plugins: {
        legend: {
          display: true,
          position: "bottom",
          labels: {
            boxWidth: 10,
            boxHeight: 10,
            usePointStyle: true,
            pointStyle: "rectRounded",
          },
        },
        tooltip: {
          callbacks: {
            label: (context) => `${context.dataset.label}: ${Number(context.raw || 0).toFixed(2)} L`,
            footer: (items) => {
              const total = items.reduce((sum, item) => sum + Number(item.raw || 0), 0);
              return `Total: ${total.toFixed(2)} L`;
            },
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          stacked: true,
          suggestedMax: maxLiters > 0 ? maxLiters * 1.12 : undefined,
          grace: "12%",
          border: {
            display: false,
          },
          ticks: {
            callback: (value) => `${value} L`,
            padding: 8,
            maxTicksLimit: 6,
          },
          grid: {
            color: "rgba(15, 23, 42, 0.06)",
          },
        },
        y: {
          stacked: true,
          ticks: {
            autoSkip: false,
            padding: 10,
            font: {
              size: 11,
              weight: "600",
            },
          },
          grid: {
            display: false,
          },
        },
      },
    },
    plugins: [rankingDataLabelPlugin],
  });

  if (chartType === "office") officeConsumptionChart = chart;
  else vehicleConsumptionChart = chart;
}

async function saveFuelBudget() {
  const form = document.getElementById("addBudgetForm");
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const button = document.getElementById("saveBudgetBtn");
  const originalHtml = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

  try {
    const response = await fetch("fuel_budget_save.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        ib_no: document.getElementById("budgetIbNo").value,
        description: document.getElementById("budgetDescription").value,
        fuel_coverage: document.getElementById("budgetFuelCoverage").value,
        diesel_allocation: document.getElementById("budgetDieselAllocation").value,
        unleaded_allocation: document.getElementById("budgetUnleadedAllocation").value,
      }),
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Unable to save IB budget.");
    }

    updateFuelBudgetSummary(payload.budget_summary || {});
    const addAnother = document.getElementById("budgetAddAnother").checked;
    if (addAnother) {
      resetFuelBudgetForm();
      document.getElementById("budgetAddAnother").checked = true;
      document.getElementById("budgetIbNo").focus();
    } else {
      const modal = bootstrap.Modal.getInstance(document.getElementById("addBudgetModal"));
      if (modal) modal.hide();
    }
    showNotification(payload.message || "IB budget saved.", "success");
  } catch (error) {
    showNotification(error.message, "danger");
  } finally {
    button.disabled = false;
    button.innerHTML = originalHtml;
  }
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
            "X-Requested-With": "XMLHttpRequest",
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
        "X-Requested-With": "XMLHttpRequest",
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

  if (!uploadCsvBtn || !csvUploadInput) {
    return;
  }

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
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
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
const searchBtn = document.getElementById("searchBtn");
const searchBar = document.getElementById("searchBar");

if (searchBtn && searchBar) {
  searchBtn.addEventListener("click", function () {
    const searchValue = searchBar.value.toLowerCase();
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
  searchBar.addEventListener("keypress", function (e) {
    if (e.key === "Enter") {
      searchBtn.click();
    }
  });
}
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

  // Initial statistics are loaded by the primary startup flow after the budget animation.
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
