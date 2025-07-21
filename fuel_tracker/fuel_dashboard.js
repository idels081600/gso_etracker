// Fuel Dashboard JavaScript
document.addEventListener("DOMContentLoaded", function () {
  // Initialize navbar functionality
  initializeNavbar();

  // Initialize fuel record modal (if it exists)
  if (document.getElementById("addFuelRecordModal")) {
    initializeFuelRecordModal();
  }

  // Initialize fuel records table (if it exists)
  if (document.getElementById("fuelRecordsTable")) {
    initializeFuelRecordsTable();
  }

  // Load initial data only if tally elements exist
  if (document.getElementById("unleadedCount")) {
    loadFuelStatistics();
  }

  // Load fuel records only if table exists
  if (document.getElementById("fuelRecordsBody")) {
    loadFuelRecords();
  }

  // Initialize filter dropdown functionality
  const filterDropdown = document.getElementById('filterDropdown');
  if (filterDropdown) {
    const filterItems = document.querySelectorAll('.dropdown-menu [data-filter]');
    filterItems.forEach(item => {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        const filter = this.getAttribute('data-filter');
        applyFuelFilter(filter);
      });
    });
  }

  // Optional: Add search functionality
  // addTableSearch();
});

// Function to initialize navbar functionality
function initializeNavbar() {
  // Add any navbar-specific initialization here
  console.log("Navbar initialized");
  
  // Example: Handle mobile menu toggle if it exists
  const navbarToggler = document.querySelector('.navbar-toggler');
  if (navbarToggler) {
    navbarToggler.addEventListener('click', function() {
      const navbarCollapse = document.querySelector('.navbar-collapse');
      if (navbarCollapse) {
        navbarCollapse.classList.toggle('show');
      }
    });
  }
}

// Function to initialize fuel record modal
function initializeFuelRecordModal() {
  console.log("Fuel record modal initialized");
  
  const modal = document.getElementById("addFuelRecordModal");
  if (!modal) return;

  // Add modal event listeners here
  const saveButton = modal.querySelector('.btn-primary');
  if (saveButton) {
    saveButton.addEventListener('click', function() {
      // Handle save fuel record
      console.log("Save fuel record clicked");
    });
  }
}

// Function to initialize fuel records table
function initializeFuelRecordsTable() {
  console.log("Fuel records table initialized");
  
  const table = document.getElementById("fuelRecordsTable");
  if (!table) return;

  // Add table-specific initialization here
  initializeActionButtons();
  initializeTableCheckboxes();
  
  // Also initialize buttons for existing rows in the table
  const existingButtons = table.querySelectorAll('.btn-outline-primary, .btn-outline-warning, .btn-outline-danger');
  if (existingButtons.length > 0) {
    console.log("Initializing buttons for existing table rows");
    initializeActionButtons();
  }
}

// Function to initialize action buttons in the table
function initializeActionButtons() {
  // Remove existing event listeners to prevent duplicates
  document.querySelectorAll('.btn-outline-primary, .btn-outline-warning, .btn-outline-danger').forEach(btn => {
    btn.replaceWith(btn.cloneNode(true));
  });
  
  // View buttons
  document.querySelectorAll('.btn-outline-primary').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const row = this.closest('tr');
      const recordId = row.querySelector('.row-checkbox')?.value;
      if (recordId) {
        console.log('View record:', recordId);
        viewRecord(recordId);
      } else {
        console.warn('Could not find record ID for view action');
      }
    });
  });

  // Edit buttons
  document.querySelectorAll('.btn-outline-warning').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const row = this.closest('tr');
      const recordId = row.querySelector('.row-checkbox')?.value;
      if (recordId) {
        console.log('Edit record:', recordId);
        editRecord(recordId, row);
      } else {
        console.warn('Could not find record ID for edit action');
      }
    });
  });

  // Delete buttons
  document.querySelectorAll('.btn-outline-danger').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const row = this.closest('tr');
      const recordId = row.querySelector('.row-checkbox')?.value;
      if (recordId) {
        if (confirm('Are you sure you want to delete this record?')) {
          console.log('Delete record:', recordId);
          deleteRecord(recordId, row);
        }
      } else {
        console.warn('Could not find record ID for delete action');
      }
    });
  });
  
  console.log('Action buttons initialized for', document.querySelectorAll('.btn-outline-primary, .btn-outline-warning, .btn-outline-danger').length, 'buttons');
}

// Function to initialize table checkboxes
function initializeTableCheckboxes() {
  // Select all checkbox functionality
  const selectAllCheckbox = document.querySelector('#selectAll');
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
      const checkboxes = document.querySelectorAll('.row-checkbox');
      checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
      });
    });
  }

  // Individual checkbox change handler
  document.addEventListener('change', function(e) {
    if (e.target.classList.contains('row-checkbox')) {
      const allCheckboxes = document.querySelectorAll('.row-checkbox');
      const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
      const selectAllCheckbox = document.querySelector('#selectAll');
      
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = allCheckboxes.length === checkedBoxes.length;
        selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allCheckboxes.length;
      }
    }
  });
}

// Function to show notifications
function showNotification(message, type = 'info') {
  // Create notification element if it doesn't exist
  let notificationContainer = document.getElementById('notificationContainer');
  if (!notificationContainer) {
    notificationContainer = document.createElement('div');
    notificationContainer.id = 'notificationContainer';
    notificationContainer.className = 'position-fixed top-0 end-0 p-3';
    notificationContainer.style.zIndex = '9999';
    document.body.appendChild(notificationContainer);
  }

  // Create the notification
  const notification = document.createElement('div');
  notification.className = `alert alert-${type} alert-dismissible fade show`;
  notification.innerHTML = `
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;

  notificationContainer.appendChild(notification);

  // Auto-remove after 5 seconds
  setTimeout(() => {
    if (notification.parentNode) {
      notification.remove();
    }
  }, 5000);
}

// Function to load fuel statistics
async function loadFuelStatistics() {
  try {
    const response = await fetch("get_fuel_data.php?action=statistics");

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      updateFuelTalliesFromData(data.data);
    } else {
      showNotification(
        "Error loading fuel statistics: " + data.message,
        "danger"
      );
    }
  } catch (error) {
    handleFetchError(error);
  }
}

// Function to load all fuel records
async function loadFuelRecords() {
  try {
    const response = await fetch("get_fuel_data.php?action=all");

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      populateFuelTable(data.data);
      updateRecordCount(data.count);
    } else {
      showNotification("Error loading fuel records: " + data.message, "danger");
    }
  } catch (error) {
    handleFetchError(error);
  }
}

// Function to load filtered fuel records
async function loadFilteredFuelRecords(filters) {
  try {
    const params = new URLSearchParams();
    params.append("action", "filtered");

    // Add filters to params
    Object.keys(filters).forEach((key) => {
      if (filters[key]) {
        params.append(key, filters[key]);
      }
    });

    const response = await fetch(`get_fuel_data.php?${params.toString()}`);

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success) {
      populateFuelTable(data.data);
      updateRecordCount(data.count);
    } else {
      showNotification(
        "Error loading filtered records: " + data.message,
        "danger"
      );
    }
  } catch (error) {
    handleFetchError(error);
  }
}

// Function to populate the fuel table with data
function populateFuelTable(records) {
  const tbody = document.getElementById("fuelRecordsBody");
  if (!tbody) {
    console.warn("Table body element not found");
    return;
  }

  tbody.innerHTML = "";

  if (records.length === 0) {
    tbody.innerHTML = `
          <tr>
              <td colspan="11" class="text-center text-muted py-4">
                  <i class="fas fa-inbox fa-2x mb-2"></i>
                  <br>No fuel records found
              </td>
          </tr>
      `;
    return;
  }

  records.forEach((record) => {
    const row = createTableRow(record);
    tbody.appendChild(row);
  });

  // Reinitialize action buttons for new rows
  initializeActionButtons();
}

// Function to create a table row from record data
function createTableRow(record) {
  const row = document.createElement("tr");

  // Format date
  const date = new Date(record.date);
  const formattedDate = date.toISOString().split("T")[0];
  const relativeDate = getRelativeDate(date);

  // Get fuel type badge class
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
          <span class="badge bg-light text-dark">${record.office || "-"}</span>
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
              <button type="button" class="btn btn-outline-primary" title="View">
                  <i class="fas fa-eye"></i>
              </button>
              <button type="button" class="btn btn-outline-warning" title="Edit">
                  <i class="fas fa-edit"></i>
              </button>
              <button type="button" class="btn btn-outline-danger" title="Delete">
                  <i class="fas fa-trash"></i>
              </button>
          </div>
      </td>
  `;

  return row;
}

// Helper function to get fuel type badge class
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

// Helper function to get relative date
function getRelativeDate(date) {
  const now = new Date();
  const diffTime = Math.abs(now - date);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays === 1) return "Today";
  if (diffDays === 2) return "Yesterday";
  if (diffDays <= 7) return `${diffDays - 1} days ago`;
  if (diffDays <= 30) return `${Math.ceil(diffDays / 7)} weeks ago`;
  return `${Math.ceil(diffDays / 30)} months ago`;
}

// Function to update fuel tallies from database data
function updateFuelTalliesFromData(statisticsData) {
  // Check if elements exist before updating
  const unleadedCount = document.getElementById("unleadedCount");
  const unleadedLiters = document.getElementById("unleadedLiters");
  const unleadedMonth = document.getElementById("unleadedMonth");

  const dieselCount = document.getElementById("dieselCount");
  const dieselLiters = document.getElementById("dieselLiters");
  const dieselMonth = document.getElementById("dieselMonth");

  // If elements don't exist, exit the function
  if (
    !unleadedCount ||
    !unleadedLiters ||
    !unleadedMonth ||
    !dieselCount ||
    !dieselLiters ||
    !dieselMonth
  ) {
    console.warn(
      "Tally card elements not found in DOM. Make sure the HTML includes the tally cards."
    );
    return;
  }

  // Reset all tallies
  unleadedCount.textContent = "0";
  unleadedLiters.textContent = "0.00 L";
  unleadedMonth.textContent = "0";

  dieselCount.textContent = "0";
  dieselLiters.textContent = "0.00 L";
  dieselMonth.textContent = "0";

  // Update with actual data
  statisticsData.forEach((stat) => {
    const fuelType = stat.fuel_type.toLowerCase();

    if (fuelType === "unleaded") {
      unleadedCount.textContent = stat.total_records;
      unleadedLiters.textContent =
        parseFloat(stat.total_liters || 0).toFixed(2) + " L";
      unleadedMonth.textContent = stat.month_records;
    } else if (fuelType === "diesel") {
      dieselCount.textContent = stat.total_records;
      dieselLiters.textContent =
        parseFloat(stat.total_liters || 0).toFixed(2) + " L";
      dieselMonth.textContent = stat.month_records;
    }
  });
}

// Add error handling for fetch requests
function handleFetchError(error) {
  console.error("Fetch error:", error);

  if (error.name === "TypeError" && error.message.includes("fetch")) {
    showNotification("Network error: Please check your connection", "danger");
  } else {
    showNotification(
      "An unexpected error occurred: " + error.message,
      "danger"
    );
  }
}

// Function to view a record
async function viewRecord(recordId) {
  try {
    // Fetch the record details from the backend
    const response = await fetch(`get_fuel_data.php?action=single&id=${recordId}`);
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();

    if (!data.success || !data.data) {
      showNotification("Failed to load record details", "danger");
      return;
    }

    const record = data.data;

    // Populate the modal fields
    document.getElementById('viewFuelDate').textContent = record.date || '-';
    document.getElementById('viewOffice').textContent = record.office || '-';
    document.getElementById('viewVehicle').textContent = record.vehicle || '-';
    document.getElementById('viewPlateNo').textContent = record.plate_no || '-';
    document.getElementById('viewDriver').textContent = record.driver || '-';
    document.getElementById('viewPurpose').textContent = record.purpose || '-';
    document.getElementById('viewFuelType').textContent = record.fuel_type || '-';
    document.getElementById('viewLitersIssued').textContent = record.liters_issued || '-';
    document.getElementById('viewRemarks').textContent = record.remarks || '-';

    // Show the view modal (Bootstrap 5)
    const viewModal = new bootstrap.Modal(document.getElementById('viewFuelRecordModal'));
    viewModal.show();
  } catch (error) {
    console.error('Error loading record details:', error);
    showNotification('Failed to load record details', 'danger');
  }
}

// Function to edit a record
function editRecord(recordId, row) {
  // Find the record data from the row's cells
  const cells = row.querySelectorAll('td');

  // Populate the edit modal fields
  document.getElementById('editRecordId').value = recordId;
  document.getElementById('editFuelDate').value = cells[1].querySelector('.fw-medium')?.innerText.trim() || '';
  document.getElementById('editOffice').value = cells[2].innerText.trim();
  document.getElementById('editVehicle').value = cells[3].innerText.trim();
  document.getElementById('editPlateNo').value = cells[4].innerText.trim();
  document.getElementById('editDriver').value = cells[5].innerText.trim();
  document.getElementById('editPurpose').value = cells[6].innerText.trim();
  document.getElementById('editFuelType').value = cells[7].querySelector('.badge')?.innerText.trim() || '';
  document.getElementById('editLitersIssued').value = parseFloat((cells[8].innerText || '').replace(' L', '')) || '';
  document.getElementById('editRemarks').value = cells[9].innerText.trim();

  // Show the edit modal (Bootstrap 5)
  const editModal = new bootstrap.Modal(document.getElementById('editFuelRecordModal'));
  editModal.show();
}

// Handle update button click in the edit modal
if (document.getElementById('updateFuelRecord')) {
  document.getElementById('updateFuelRecord').addEventListener('click', async function() {
    const form = document.getElementById('editFuelRecordForm');
    const formData = new FormData(form);
    const recordId = formData.get('id');
    const payload = {};
    formData.forEach((value, key) => {
      payload[key] = value;
    });

    try {
      // Show loading state
      const updateBtn = document.getElementById('updateFuelRecord');
      const originalHTML = updateBtn.innerHTML;
      updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      updateBtn.disabled = true;

      // Send update request (adjust endpoint as needed)
      const response = await fetch(`update_fuel_record.php?id=${recordId}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      if (data.success) {
        showNotification(`Record ${recordId} updated successfully`, 'success');
        // Hide modal
        const editModalEl = document.getElementById('editFuelRecordModal');
        const editModal = bootstrap.Modal.getInstance(editModalEl);
        editModal.hide();
        // Reload records
        loadFuelRecords();
        // Reload statistics if needed
        if (document.getElementById('unleadedCount')) {
          loadFuelStatistics();
        }
      } else {
        throw new Error(data.message || 'Failed to update record');
      }
    } catch (error) {
      showNotification(`Failed to update record: ${error.message}`, 'danger');
    } finally {
      // Restore button state
      const updateBtn = document.getElementById('updateFuelRecord');
      updateBtn.innerHTML = '<i class="fas fa-save me-1"></i>Update Record';
      updateBtn.disabled = false;
    }
  });
}

// Function to delete a record
async function deleteRecord(recordId, row) {
  try {
    console.log('Deleting record ID:', recordId);
    
    // Show loading state
    const deleteBtn = row.querySelector('.btn-outline-danger');
    const originalHTML = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    // Make API call to delete record
    const response = await fetch(`delete_fuel_record.php?id=${recordId}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json'
      }
    });
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    
    if (data.success) {
      // Remove the row from the table
      row.remove();
      showNotification(`Record ${recordId} deleted successfully`, 'success');
      
      // Update record count
      updateRecordCount();
      
      // Reload statistics if elements exist
      if (document.getElementById("unleadedCount")) {
        loadFuelStatistics();
      }
    } else {
      throw new Error(data.message || 'Failed to delete record');
    }
    
  } catch (error) {
    console.error('Error deleting record:', error);
    showNotification(`Failed to delete record: ${error.message}`, 'danger');
    
    // Restore button state
    const deleteBtn = row.querySelector('.btn-outline-danger');
    deleteBtn.innerHTML = originalHTML;
    deleteBtn.disabled = false;
  }
}

// Update record count display
function updateRecordCount(count = null) {
  const recordsShowingElement = document.getElementById("recordsShowing");
  const totalRecordsElement = document.getElementById("totalRecords");

  if (count !== null) {
    if (recordsShowingElement) recordsShowingElement.textContent = count;
    if (totalRecordsElement) totalRecordsElement.textContent = count;
  } else {
    const tbody = document.getElementById("fuelRecordsBody");
    if (tbody) {
      const rowCount = tbody.querySelectorAll("tr").length;
      if (recordsShowingElement) recordsShowingElement.textContent = rowCount;
      if (totalRecordsElement) totalRecordsElement.textContent = rowCount;
    }
  }
}

function applyFuelFilter(filter) {
  const today = new Date();
  let filters = {};

  switch (filter) {
    case 'all':
      loadFuelRecords();
      break;
    case 'today':
      filters.date_from = today.toISOString().split('T')[0];
      filters.date_to = filters.date_from;
      loadFilteredFuelRecords(filters);
      break;
    case 'week': {
      const firstDayOfWeek = new Date(today);
      firstDayOfWeek.setDate(today.getDate() - today.getDay()); // Sunday
      filters.date_from = firstDayOfWeek.toISOString().split('T')[0];
      filters.date_to = today.toISOString().split('T')[0];
      loadFilteredFuelRecords(filters);
      break;
    }
    case 'month': {
      const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
      filters.date_from = firstDayOfMonth.toISOString().split('T')[0];
      filters.date_to = today.toISOString().split('T')[0];
      loadFilteredFuelRecords(filters);
      break;
    }
    case 'unleaded':
      filters.fuel_type = 'Unleaded';
      loadFilteredFuelRecords(filters);
      break;
    case 'diesel':
      filters.fuel_type = 'Diesel';
      loadFilteredFuelRecords(filters);
      break;
    default:
      loadFuelRecords();
      break;
  }
}