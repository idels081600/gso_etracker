// Auto-submit form when filters change
document.addEventListener("DOMContentLoaded", function () {
  const dateInput = document.getElementById("date");
  const statusSelect = document.getElementById("status");
  const officeSelect = document.getElementById("office");

  if (dateInput) {
    dateInput.addEventListener("change", function () {
      this.form.submit();
    });
  }

  if (statusSelect) {
    statusSelect.addEventListener("change", function () {
      this.form.submit();
    });
  }

  if (officeSelect) {
    officeSelect.addEventListener("change", function () {
      this.form.submit();
    });
  }
});

// Function to open approve modal
function openApproveModal(requestId, itemName, requestedQty) {
  // Check if all required elements exist
  const modalRequestId = document.getElementById("modalRequestId");
  const modalItemName = document.getElementById("modalItemName");
  const modalRequestedQty = document.getElementById("modalRequestedQty");
  const approvedQuantity = document.getElementById("approvedQuantity");
  const adminRemarks = document.getElementById("adminRemarks");

  if (
    !modalRequestId ||
    !modalItemName ||
    !modalRequestedQty ||
    !approvedQuantity ||
    !adminRemarks
  ) {
    console.error("Modal elements not found");
    alert("Error: Modal elements not found. Please refresh the page.");
    return;
  }

  // Set values
  modalRequestId.value = requestId;
  modalItemName.textContent = itemName;
  modalRequestedQty.textContent = requestedQty;
  approvedQuantity.value = requestedQty; // Default to requested quantity
  approvedQuantity.max = requestedQty; // Set max to requested quantity
  adminRemarks.value = "";

  // Show the modal
  const modalElement = document.getElementById("approveModal");
  if (modalElement) {
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
  } else {
    console.error("Modal element not found");
    alert("Error: Modal not found. Please refresh the page.");
  }
}

// Function to confirm approval with quantity
function confirmApproval() {
  const requestId = document.getElementById("modalRequestId")?.value;
  const approvedQty = document.getElementById("approvedQuantity")?.value;
  const adminRemarks = document.getElementById("adminRemarks")?.value || "";

  if (!requestId) {
    alert("Error: Request ID not found");
    return;
  }

  if (!approvedQty || approvedQty <= 0) {
    alert("Please enter a valid approved quantity");
    return;
  }

  if (
    confirm(
      `Are you sure you want to approve ${approvedQty} quantity for this request?`
    )
  ) {
    fetch("update_request_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        request_id: requestId,
        status: "Approved",
        approved_quantity: parseInt(approvedQty),
        admin_remarks: adminRemarks,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Hide modal
          const modalElement = document.getElementById("approveModal");
          if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
              modal.hide();
            }
          }
          // Reload page
          location.reload();
        } else {
          alert("Error: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Error updating request status");
      });
  }
}

// Function to update request status (for reject button)
function updateRequestStatus(requestId, status) {
  if (
    confirm(`Are you sure you want to ${status.toLowerCase()} this request?`)
  ) {
    fetch("update_request_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        request_id: requestId,
        status: status,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          location.reload();
        } else {
          alert("Error: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Error updating request status");
      });
  }
}
// Function to filter table by office
function filterByOffice(officeName) {
  // Remove active class from all office cards
  document.querySelectorAll(".office-card").forEach((card) => {
    card.classList.remove("active");
  });

  // Add active class to clicked card
  event.currentTarget.classList.add("active");

  // Update the office filter dropdown
  const officeSelect = document.getElementById("office");
  if (officeSelect) {
    officeSelect.value = officeName;
  }

  // Filter table rows
  filterTableByOffice(officeName);

  // Update the table header to show filtered office
  updateTableHeader(officeName);
}

// Function to filter table rows by office
function filterTableByOffice(officeName) {
  const tableRows = document.querySelectorAll("tbody tr");
  let visibleCount = 0;

  tableRows.forEach((row) => {
    const officeCell = row.querySelector("td:nth-child(2)"); // Office column is 2nd

    if (officeCell) {
      const rowOfficeName = officeCell.textContent.trim();

      if (rowOfficeName === officeName) {
        row.style.display = "";
        visibleCount++;
      } else {
        row.style.display = "none";
      }
    }
  });

  // Show/hide "no results" message
  toggleNoResultsMessage(visibleCount === 0, officeName);
}

// Function to update table header
function updateTableHeader(officeName) {
  const tableHeader = document.querySelector(".card-header h5");
  if (tableHeader) {
    const currentDate =
      '<?= $date_filter ? date("F j, Y", strtotime($date_filter)) : "" ?>';
    const dateText = currentDate ? ` - ${currentDate}` : "";

    tableHeader.innerHTML = `
            <i class="fas fa-list text-secondary"></i> Requests for ${officeName}${dateText}
            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="clearOfficeFilter()">
                <i class="fas fa-times"></i> Clear Filter
            </button>
        `;
  }
}

// Function to clear office filter
function clearOfficeFilter() {
  // Remove active class from all office cards
  document.querySelectorAll(".office-card").forEach((card) => {
    card.classList.remove("active");
  });

  // Reset office filter dropdown
  const officeSelect = document.getElementById("office");
  if (officeSelect) {
    officeSelect.value = "all";
  }

  // Show all table rows
  document.querySelectorAll("tbody tr").forEach((row) => {
    row.style.display = "";
  });

  // Reset table header
  const tableHeader = document.querySelector(".card-header h5");
  if (tableHeader) {
    const currentDate =
      '<?= $date_filter ? date("F j, Y", strtotime($date_filter)) : "" ?>';
    const dateText = currentDate ? ` - ${currentDate}` : "";

    tableHeader.innerHTML = `
            <i class="fas fa-list text-secondary"></i> Requests to Review${dateText}
        `;
  }

  // Hide no results message
  toggleNoResultsMessage(false);
}

// Function to toggle no results message
function toggleNoResultsMessage(show, officeName = "") {
  const tbody = document.querySelector("tbody");
  let noResultsRow = document.getElementById("noResultsRow");

  if (show) {
    if (!noResultsRow) {
      noResultsRow = document.createElement("tr");
      noResultsRow.id = "noResultsRow";
      noResultsRow.innerHTML = `
                <td colspan="7" class="text-center text-muted">
                    <div class="py-4">
                        <i class="fas fa-search fa-2x mb-2"></i>
                        <p>No requests found for ${officeName}</p>
                        <button class="btn btn-outline-secondary btn-sm" onclick="clearOfficeFilter()">
                            <i class="fas fa-times"></i> Clear Filter
                        </button>
                    </div>
                </td>
            `;
      tbody.appendChild(noResultsRow);
    }
  } else {
    if (noResultsRow) {
      noResultsRow.remove();
    }
  }
}

// Update the existing form filter function to sync with office cards
function syncOfficeCardWithFilter() {
  const officeSelect = document.getElementById("office");
  if (officeSelect && officeSelect.value !== "all") {
    const selectedOffice = officeSelect.value;

    // Find and activate the corresponding office card
    document.querySelectorAll(".office-card").forEach((card) => {
      const officeName = card.querySelector("h6").textContent.trim();
      if (officeName === selectedOffice) {
        card.classList.add("active");
      } else {
        card.classList.remove("active");
      }
    });
  }
}

// Call sync function on page load
document.addEventListener("DOMContentLoaded", function () {
  syncOfficeCardWithFilter();

  // Existing filter change handlers...
  const officeSelect = document.getElementById("office");
  if (officeSelect) {
    officeSelect.addEventListener("change", function () {
      if (this.value === "all") {
        clearOfficeFilter();
      } else {
        // Find the office card and trigger click
        document.querySelectorAll(".office-card").forEach((card) => {
          const officeName = card.querySelector("h6").textContent.trim();
          if (officeName === this.value) {
            filterByOffice(officeName);
          }
        });
      }
    });
  }
});
// Function to open reject modal
function openRejectModal(requestId, itemName) {
  // Check if all required elements exist
  const modalRequestId = document.getElementById("rejectModalRequestId");
  const modalItemName = document.getElementById("rejectModalItemName");
  const rejectRemarks = document.getElementById("rejectRemarks");

  if (!modalRequestId || !modalItemName || !rejectRemarks) {
    console.error("Reject modal elements not found");
    alert("Error: Modal elements not found. Please refresh the page.");
    return;
  }

  // Set values
  modalRequestId.value = requestId;
  modalItemName.textContent = itemName;
  rejectRemarks.value = "";

  // Show the modal
  const modalElement = document.getElementById("rejectModal");
  if (modalElement) {
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
  } else {
    console.error("Reject modal element not found");
    alert("Error: Modal not found. Please refresh the page.");
  }
}

// Function to confirm rejection with remarks
function confirmRejection() {
  const requestId = document.getElementById("rejectModalRequestId")?.value;
  const rejectRemarks = document.getElementById("rejectRemarks")?.value?.trim();

  if (!requestId) {
    alert("Error: Request ID not found");
    return;
  }

  if (!rejectRemarks) {
    alert("Please provide a reason for rejecting this request");
    document.getElementById("rejectRemarks").focus();
    return;
  }

  if (confirm("Are you sure you want to reject this request?")) {
    // Disable the reject button to prevent double submission
    const rejectButton = document.querySelector("#rejectModal .btn-danger");
    const originalText = rejectButton.innerHTML;
    rejectButton.disabled = true;
    rejectButton.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Rejecting...';

    fetch("update_request_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        request_id: requestId,
        status: "Rejected",
        admin_remarks: rejectRemarks,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Hide modal
          const modalElement = document.getElementById("rejectModal");
          if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
              modal.hide();
            }
          }
          // Show success message
          showNotification("Request rejected successfully", "success");
          // Reload page after a short delay
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          alert("Error: " + data.message);
          // Re-enable button on error
          rejectButton.disabled = false;
          rejectButton.innerHTML = originalText;
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Error rejecting request. Please try again.");
        // Re-enable button on error
        rejectButton.disabled = false;
        rejectButton.innerHTML = originalText;
      });
  }
}

// Update the existing updateRequestStatus function to handle the new reject modal
function updateRequestStatus(requestId, status) {
  // If it's a rejection, use the new modal instead
  if (status === "Rejected") {
    console.warn(
      "Direct rejection is deprecated. Use openRejectModal() instead."
    );
    return;
  }

  if (
    confirm(`Are you sure you want to ${status.toLowerCase()} this request?`)
  ) {
    fetch("update_request_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        request_id: requestId,
        status: status,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          location.reload();
        } else {
          alert("Error: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Error updating request status");
      });
  }
}

// Helper function to show notifications
function showNotification(message, type = "info") {
  // Create notification element if it doesn't exist
  let notification = document.getElementById("notification-container");
  if (!notification) {
    notification = document.createElement("div");
    notification.id = "notification-container";
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      max-width: 300px;
    `;
    document.body.appendChild(notification);
  }

  // Create notification alert
  const alert = document.createElement("div");
  alert.className = `alert alert-${
    type === "success" ? "success" : type === "error" ? "danger" : "info"
  } alert-dismissible fade show`;
  alert.innerHTML = `
    <i class="fas fa-${
      type === "success"
        ? "check-circle"
        : type === "error"
        ? "exclamation-circle"
        : "info-circle"
    }"></i>
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;

  notification.appendChild(alert);

  // Auto-remove after 5 seconds
  setTimeout(() => {
    if (alert.parentNode) {
      alert.remove();
    }
  }, 5000);
}

// Clear reject modal when it's hidden
document.addEventListener("DOMContentLoaded", function () {
  const rejectModal = document.getElementById("rejectModal");
  if (rejectModal) {
    rejectModal.addEventListener("hidden.bs.modal", function () {
      document.getElementById("rejectRemarks").value = "";
      document.getElementById("rejectModalRequestId").value = "";
      document.getElementById("rejectModalItemName").textContent = "Loading...";

      // Re-enable reject button if it was disabled
      const rejectButton = document.querySelector("#rejectModal .btn-danger");
      if (rejectButton) {
        rejectButton.disabled = false;
        rejectButton.innerHTML = '<i class="fas fa-times"></i> Reject Request';
      }
    });
  }
});
// Function to set reject reason from quick buttons
function setRejectReason(reason) {
  const rejectRemarks = document.getElementById("rejectRemarks");
  if (rejectRemarks) {
    rejectRemarks.value = reason;
    rejectRemarks.focus();
  }
}

document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("itemSearchInput");
  const tableBody = document.getElementById("itemTable");

  // Get only data rows (tr elements), excluding any header rows
  const getDataRows = () => {
    return Array.from(tableBody.querySelectorAll("tr")).filter((row) => {
      const cells = row.getElementsByTagName("td");
      return cells.length > 0; // Only rows with td elements (data rows)
    });
  };

  const performSearch = () => {
    const searchTerm = searchInput.value.toLowerCase().trim();
    const dataRows = getDataRows();

    // If search term is empty, show all rows
    if (searchTerm === "") {
      dataRows.forEach((row) => {
        row.style.display = "";
      });
      return;
    }

    // Search through data rows
    dataRows.forEach((row) => {
      const cells = row.getElementsByTagName("td");
      let found = false;

      // Ensure there are enough cells in the row
      if (cells.length >= 2) {
        const itemName = cells[0].textContent.toLowerCase().trim();
        const unit = cells[1].textContent.toLowerCase().trim();

        // Search in item name and unit
        if (itemName.includes(searchTerm) || unit.includes(searchTerm)) {
          found = true;
        }
      }

      // Show or hide the row based on search result
      row.style.display = found ? "" : "none";
    });
  };

  // Add event listeners
  searchInput.addEventListener("input", performSearch);
  searchInput.addEventListener("keyup", performSearch);

  // Optional: Add search on paste event
  searchInput.addEventListener("paste", () => {
    // Small delay to ensure pasted content is processed
    setTimeout(performSearch, 10);
  });
});
function openItemModal(itemNo, itemName, unit) {
  // Set hidden field values
  document.getElementById("selectedItemNo").value = itemNo;
  document.getElementById("selectedItemName").value = itemName;
  document.getElementById("selectedUnit").value = unit;

  // Display item information
  document.getElementById("displayItemName").textContent = itemName;
  document.getElementById("displayUnit").textContent = unit;
  document.getElementById("unitDisplay").textContent = unit;

  // Reset form
  const form = document.getElementById("itemSelectionForm");
  form.reset();
  form.classList.remove("was-validated");

  // Reset hidden fields after form reset
  document.getElementById("selectedItemNo").value = itemNo;
  document.getElementById("selectedItemName").value = itemName;
  document.getElementById("selectedUnit").value = unit;

  // Load offices via AJAX
  loadOffices();

  // Show the modal
  const modal = new bootstrap.Modal(
    document.getElementById("itemSelectionModal")
  );
  modal.show();
}

function loadOffices() {
  const officeSelect = document.getElementById("officeSelect");

  // Show loading
  officeSelect.innerHTML = '<option value="">Loading offices...</option>';

  // Fetch offices
  fetch("get_offices.php")
    .then((response) => response.json())
    .then((data) => {
      officeSelect.innerHTML =
        '<option value="">Select Office/Department</option>';

      if (data.success && data.offices.length > 0) {
        data.offices.forEach((office) => {
          const option = document.createElement("option");
          option.value = office;
          option.textContent = office;
          officeSelect.appendChild(option);
        });
      } else {
        officeSelect.innerHTML = '<option value="">No offices found</option>';
      }
    })
    .catch((error) => {
      console.error("Error loading offices:", error);
      officeSelect.innerHTML =
        '<option value="">Error loading offices</option>';
    });
}function confirmItemSelection() {
  const form = document.getElementById("itemSelectionForm");
  const officeSelect = document.getElementById("officeSelect");
  const dateReceivedInput = document.getElementById("dateReceived");

  // Validate form
  if (!form.checkValidity()) {
    form.classList.add("was-validated");
    return;
  }

  // Get form data
  const formData = {
    itemNo: document.getElementById("selectedItemNo").value,
    itemName: document.getElementById("selectedItemName").value,
    unit: document.getElementById("selectedUnit").value,
    office: officeSelect.value,
    approved_Quantity: document.getElementById("approved_Quantity").value,
    dateReceived: dateReceivedInput.value,
  };
   console.log("Form Data:", formData); // Debug: Log the form data

  // Process the selection
  processItemSelection(formData);

  // Close modal
  const modal = bootstrap.Modal.getInstance(
    document.getElementById("itemSelectionModal")
  );
  modal.hide();
}

function processItemSelection(data) {
  // This function handles what happens after the user confirms the selection
  // You can modify this based on your specific requirements
  console.log("Item selected:", data);
  // Example: Add to a table, send to server, etc.
  // You might want to:
  // 1. Add the item to a request list
  // 2. Send data to server via AJAX
  // 3. Update UI to show selected items

  // Show success message
  alert(
    `Item "${data.itemName}" added successfully!\nOffice: ${data.office}\nQuantity: ${data.approved_Quantity} ${data.unit}`
  );
  const formData = new URLSearchParams();
    for (const key in data) {
       formData.append(key, data[key]);
    }
  // Example AJAX call (uncomment and modify as needed):
  fetch('process_item_selection.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body:  formData
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      window.location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while processing the request.');
  });
}