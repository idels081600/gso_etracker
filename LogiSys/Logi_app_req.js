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
    const dateText = currentDate ? ` - ${dateText}` : "";

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

let itemsToAdd = [];
let currentItemIndex = 0;

// ORIGINAL FUNCTION: Process items one by one
function addAllItemsWithQuantity() {
    const rows = document.querySelectorAll('#itemTable tr');
    itemsToAdd = []; // Reset the items array

    rows.forEach(row => {
        const quantityInput = row.querySelector('.item-quantity');
        if (quantityInput && quantityInput.value > 0) {
            itemsToAdd.push({
                itemNo: quantityInput.dataset.itemNo,
                itemName: quantityInput.dataset.itemName,
                unit: quantityInput.dataset.unit,
                quantity: quantityInput.value
            });
        }
    });

    console.log('Items to be processed:', itemsToAdd); // Log the items being passed

    if (itemsToAdd.length === 0) {
        alert('No items with quantity to add.');
        return;
    }

    currentItemIndex = 0; // Start with the first item

    // Debugging: Log available modals
    const availableModals = Array.from(document.querySelectorAll('.modal')).map(modal => modal.id);
    console.log('Available modals:', availableModals);

    // Check if the modal exists
    const modalElement = document.getElementById("allItemsModal");
    if (!modalElement) {
        alert('Error: Modal with ID "allItemsModal" not found. Available modals: ' + availableModals.join(', '));
        return;
    }

    openItemModalForCurrentItem();
}

// NEW FUNCTION: Display all items in a single modal (Option 2)
function addAllItemsInSingleModal() {
    const rows = document.querySelectorAll('#itemTable tr');
    itemsToAdd = []; // Reset the items array

    rows.forEach(row => {
        const quantityInput = row.querySelector('.item-quantity');
        if (quantityInput && quantityInput.value > 0) {
            itemsToAdd.push({
                itemNo: quantityInput.dataset.itemNo,
                itemName: quantityInput.dataset.itemName,
                unit: quantityInput.dataset.unit,
                quantity: quantityInput.value
            });
        }
    });

    console.log('Items to be processed:', itemsToAdd);

    if (itemsToAdd.length === 0) {
        alert('No items with quantity to add.');
        return;
    }

    openAllItemsModal();
}

// Function to open modal with all items
function openAllItemsModal() {
    if (itemsToAdd.length === 0) {
        alert('No items to process.');
        return;
    }

    // Get or create the items container in the modal
    const itemsContainer = document.getElementById("allItemsContainer");
    if (!itemsContainer) {
        console.error("Items container not found. Make sure you have a div with id 'allItemsContainer' in your modal.");
        alert("Error: Items container not found in modal.");
        return;
    }

    // Clear existing content
    itemsContainer.innerHTML = '';

    // Update item count in modal footer
    const itemCountElement = document.getElementById("itemCount");
    if (itemCountElement) {
        itemCountElement.textContent = itemsToAdd.length;
    }

    // Create form sections for each item
    itemsToAdd.forEach((item, index) => {
        const itemSection = document.createElement('div');
        itemSection.className = 'border rounded p-3 mb-3 item-section';
        itemSection.innerHTML = `
            <div class="row">
                <div class="col-md-5">
                    <h6 class="text-primary mb-2">
                        <i class="fas fa-box"></i> ${item.itemName}
                        <span class="badge bg-secondary ms-2">#${index + 1}</span>
                    </h6>
                    <p class="text-muted mb-2">
                        <strong>Item No:</strong> ${item.itemNo}
                    </p>
                    <p class="text-muted mb-2">
                        <strong>Requested Quantity:</strong> ${item.quantity} ${item.unit}
                    </p>
                    <input type="hidden" class="item-no" value="${item.itemNo}">
                    <input type="hidden" class="item-name" value="${item.itemName}">
                    <input type="hidden" class="item-unit" value="${item.unit}">
                    <input type="hidden" class="item-requested-qty" value="${item.quantity}">
                </div>
                <div class="col-md-7">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-building"></i> Office/Department <span class="text-danger">*</span>
                            </label>
                            <select class="form-select office-select" required>
                                <option value="">Select Office/Department</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select an office/department.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-calculator"></i> Approved Quantity <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control approved-quantity" 
                                       min="1" 
                                       max="${item.quantity}"
                                       value="${item.quantity}" 
                                       required>
                                <span class="input-group-text">${item.unit}</span>
                            </div>
                            <div class="invalid-feedback">
                                Please enter a valid quantity (1-${item.quantity}).
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-calendar-alt"></i> Date Received <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control date-received" required>
                            <div class="invalid-feedback">
                                Please select the date received.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-comment"></i> Remarks
                            </label>
                            <textarea class="form-control item-remarks" 
                                      rows="2" 
                                      placeholder="Optional remarks for this item"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
        itemsContainer.appendChild(itemSection);
    });

    // Load offices for all office selects
    loadOfficesForAllItems();

    // Set default date to today for all date inputs
    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll("#allItemsContainer .date-received").forEach(dateInput => {
        dateInput.value = today;
    });

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById("allItemsModal"));
    modal.show();
}

// Function to load offices for all office selects in the multi-item modal
function loadOfficesForAllItems() {
    const officeSelects = document.querySelectorAll("#allItemsContainer .office-select");

    // Show loading for all selects
    officeSelects.forEach(select => {
        select.innerHTML = '<option value="">Loading offices...</option>';
    });

    // Fetch offices
    fetch("get_offices.php")
        .then((response) => response.json())
        .then((data) => {
            const optionsHTML = '<option value="">Select Office/Department</option>' +
                (data.success && data.offices.length > 0 
                    ? data.offices.map(office => `<option value="${office}">${office}</option>`).join('')
                    : '<option value="">No offices found</option>');

            // Update all office selects
            officeSelects.forEach(select => {
                select.innerHTML = optionsHTML;
            });
        })
        .catch((error) => {
            console.error("Error loading offices:", error);
            officeSelects.forEach(select => {
                select.innerHTML = '<option value="">Error loading offices</option>';
            });
        });
}

// Function to confirm all items selection
function confirmAllItemsSelection(itemsToAdd = null) {
    console.log('confirmAllItemsSelection called');
    console.log('itemsToAdd parameter:', itemsToAdd);

    // If itemsToAdd is provided, use it directly
    if (itemsToAdd && Array.isArray(itemsToAdd) && itemsToAdd.length > 0) {
        console.log('Using itemsToAdd parameter directly');
        console.log('itemsToAdd.length:', itemsToAdd.length);

        // Get common fields
        const commonOffice = document.getElementById("commonOfficeSelect")?.value;
        const commonDate = document.getElementById("commonDateReceived")?.value;

        console.log('Common office:', commonOffice);
        console.log('Common date:', commonDate);

        // Validate common fields if they exist
        if (document.getElementById("commonOfficeSelect") && !commonOffice) {
            alert("Please select an office/department for all items.");
            document.getElementById("commonOfficeSelect").focus();
            return;
        }

        if (document.getElementById("commonDateReceived") && !commonDate) {
            alert("Please select a date received for all items.");
            document.getElementById("commonDateReceived").focus();
            return;
        }

        // Prepare items data with common fields
        const allItemsData = itemsToAdd.map(item => ({
            itemNo: item.itemNo,
            itemName: item.itemName,
            unit: item.unit,
            requestedQuantity: parseInt(item.quantity),
            office: commonOffice || item.office || '',
            approvedQuantity: parseInt(item.quantity), // Default to requested quantity
            dateReceived: commonDate || new Date().toISOString().split('T')[0],
            remarks: item.remarks || ''
        }));

        console.log('Final allItemsData from itemsToAdd:', allItemsData);

        // Show summary before confirmation
        const summary = allItemsData.map((item, index) =>
            `${index + 1}. ${item.itemName} - ${item.approvedQuantity} ${item.unit} to ${item.office}`
        ).join('\n');

        if (confirm(`Are you sure you want to process these ${allItemsData.length} items?\n\n${summary}`)) {
            processAllItemsData(allItemsData);
        }
        return;
    }

    // Fallback to original DOM-based approach
    const itemSections = document.querySelectorAll(".item-section");
    const allItemsData = [];
    let isValid = true;

    console.log('Using DOM-based approach');
    console.log('Number of item sections found:', itemSections.length);
    console.log('Initial allItemsData:', allItemsData);

    // Validate and collect data from all item sections
    itemSections.forEach((section, index) => {
        console.log(`Processing item section ${index + 1}:`, section);

        const itemNo = section.querySelector(".item-no")?.value;
        const itemName = section.querySelector(".item-name")?.value;
        const unit = section.querySelector(".item-unit")?.value;
        const requestedQty = section.querySelector(".item-requested-qty")?.value;
        const officeSelect = section.querySelector(".office-select");
        const approvedQuantity = section.querySelector(".approved-quantity");
        const dateReceived = section.querySelector(".date-received");
        const remarks = section.querySelector(".item-remarks")?.value;

        console.log(`Item ${index + 1} data:`, {
            itemNo,
            itemName,
            unit,
            requestedQty,
            officeSelect: officeSelect ? officeSelect.value : 'NOT FOUND',
            approvedQuantity: approvedQuantity ? approvedQuantity.value : 'NOT FOUND',
            dateReceived: dateReceived ? dateReceived.value : 'NOT FOUND',
            remarks
        });

        // Validation for office selection
        if (!officeSelect?.value) {
            if (officeSelect) officeSelect.classList.add("is-invalid");
            isValid = false;
            console.log(`Item ${index + 1}: Office validation failed`);
        } else {
            if (officeSelect) officeSelect.classList.remove("is-invalid");
        }

        // Validation for approved quantity
        if (!approvedQuantity?.value || approvedQuantity.value <= 0) {
            if (approvedQuantity) approvedQuantity.classList.add("is-invalid");
            isValid = false;
            console.log(`Item ${index + 1}: Approved quantity validation failed`);
        } else {
            if (approvedQuantity) approvedQuantity.classList.remove("is-invalid");
        }

        // Validation for date received
        if (!dateReceived?.value) {
            if (dateReceived) dateReceived.classList.add("is-invalid");
            isValid = false;
            console.log(`Item ${index + 1}: Date received validation failed`);
        } else {
            if (dateReceived) dateReceived.classList.remove("is-invalid");
        }

        // Collect data if valid
        const itemData = {
            itemNo: itemNo || '',
            itemName: itemName || '',
            unit: unit || '',
            requestedQuantity: parseInt(requestedQty) || 0,
            office: officeSelect?.value || '',
            approvedQuantity: parseInt(approvedQuantity?.value) || 0,
            dateReceived: dateReceived?.value || '',
            remarks: remarks || ''
        };

        console.log(`Item ${index + 1} data to be pushed:`, itemData);
        allItemsData.push(itemData);
        console.log(`allItemsData after pushing item ${index + 1}:`, allItemsData);
    });

    console.log('Final validation result - isValid:', isValid);
    console.log('Final allItemsData:', allItemsData);
    console.log('allItemsData.length:', allItemsData.length);

    if (!isValid) {
        console.log('Validation failed, showing error message');
        alert("Please fill in all required fields for all items.");
        // Scroll to first invalid field
        const firstInvalid = document.querySelector(".item-section .is-invalid");
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus();
        }
        return;
    }

    if (allItemsData.length === 0) {
        console.log('allItemsData is empty, showing no items message');
        alert("No valid items to process.");
        return;
    }

    console.log('Validation passed, proceeding with processing');

    // Show summary before confirmation
    const summary = allItemsData.map((item, index) =>
        `${index + 1}. ${item.itemName} - ${item.approvedQuantity} ${item.unit} to ${item.office}`
    ).join('\n');

    if (confirm(`Are you sure you want to process these ${allItemsData.length} items?\n\n${summary}`)) {
        processAllItemsData(allItemsData);
    }
}

// Function to process all items data
function processAllItemsData(allItemsData) {
    console.log("Processing all items:", allItemsData);

    // Show loading state
    const submitButton = document.querySelector("#allItemsModal .btn-primary");
    const processingProgress = document.getElementById("processingProgress");
    let originalText = '';

    if (submitButton) {
        originalText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }

    if (processingProgress) {
        processingProgress.style.display = 'block';
    }

    // Get common fields
    const commonOffice = document.getElementById("commonOfficeSelect")?.value;
    const commonDate = document.getElementById("commonDateReceived")?.value;

    // Prepare items data with common fields
    const itemsToProcess = allItemsData.map(item => ({
        itemNo: item.itemNo,
        itemName: item.itemName,
        requestedQty: item.requestedQuantity,
        unit: item.unit,
        office: commonOffice || item.office,
        dateReceived: commonDate || item.dateReceived
    }));
    console.log("Items to process with common fields:", itemsToProcess);
    // Send all items to server
    fetch('process_all_items.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            items: itemsToProcess
        })
    })
    .then(response => response.json())
    .then(result => {
        console.log("Server response:", result);

        if (result.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById("allItemsModal"));
            if (modal) {
                modal.hide();
            }

            // Show success notification
            showNotification(`Successfully processed ${allItemsData.length} items!`, "success");

            // Reload page after delay
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            alert("Error: " + (result.message || "Failed to process items"));
            // Re-enable button
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
            if (processingProgress) {
                processingProgress.style.display = 'none';
            }
        }
    })
    .catch(error => {
        console.error("Error processing items:", error);
        alert("An error occurred while processing the items.");
        // Re-enable button
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
        if (processingProgress) {
            processingProgress.style.display = 'none';
        }
    });
}

function openItemModalForCurrentItem() {
    if (itemsToAdd.length === 0) {
        alert('No items to process.');
        return;
    }

    console.log('Opening modal for items:', itemsToAdd); // Log all items to be processed

    // Set default date to today for the common date field
    const today = new Date().toISOString().split('T')[0];
    const commonDateReceived = document.getElementById("commonDateReceived");
    if (commonDateReceived) {
        commonDateReceived.value = today;
    }

    // Load offices for the common office select
    const commonOfficeSelect = document.getElementById("commonOfficeSelect");
    if (commonOfficeSelect) {
        // Show loading
        commonOfficeSelect.innerHTML = '<option value="">Loading offices...</option>';

        // Fetch offices
        fetch("get_offices.php")
            .then((response) => response.json())
            .then((data) => {
                const optionsHTML = '<option value="">Select Office/Department</option>' +
                    (data.success && data.offices.length > 0 
                        ? data.offices.map(office => `<option value="${office}">${office}</option>`).join('')
                        : '<option value="">No offices found</option>');
                commonOfficeSelect.innerHTML = optionsHTML;
            })
            .catch((error) => {
                console.error("Error loading offices:", error);
                commonOfficeSelect.innerHTML = '<option value="">Error loading offices</option>';
            });

        // Add event listener to update all items when common office changes
        commonOfficeSelect.addEventListener('change', function() {
            const selectedOffice = this.value;
            const itemSections = document.querySelectorAll(".item-section");
            itemSections.forEach(section => {
                const officeSelect = section.querySelector(".office-select");
                if (officeSelect) {
                    officeSelect.value = selectedOffice;
                }
            });
        });
    }

    // Add event listener to update all items when common date changes
    const commonDateInput = document.getElementById("commonDateReceived");
    if (commonDateInput) {
        commonDateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            const itemSections = document.querySelectorAll(".item-section");
            itemSections.forEach(section => {
                const dateInput = section.querySelector(".date-received");
                if (dateInput) {
                    dateInput.value = selectedDate;
                }
            });
        });
    }

    // Get the allItemsContainer and clear it
    const allItemsContainer = document.getElementById("allItemsContainer");
    if (allItemsContainer) {
        allItemsContainer.innerHTML = '';
        
        // Create sections for all items
        itemsToAdd.forEach((item, index) => {
            const itemSection = document.createElement('div');
            itemSection.className = 'border rounded p-3 mb-3';
            itemSection.innerHTML = `
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary mb-2">
                            <i class="fas fa-box"></i> ${item.itemName}
                            <span class="badge bg-secondary ms-2">#${index + 1}</span>
                        </h6>
                        <div class="row">
                            <div class="col-md-4">
                                <p class="text-muted mb-2">
                                    <strong>Item No:</strong> ${item.itemNo}
                                </p>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-2">
                                    <strong>Quantity:</strong> ${item.quantity}
                                </p>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-2">
                                    <strong>Unit:</strong> ${item.unit}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            allItemsContainer.appendChild(itemSection);
        });

        // Update item count in footer
        const itemCount = document.getElementById("itemCount");
        if (itemCount) {
            itemCount.textContent = itemsToAdd.length;
        }
    }

    // Show the modal
    const modalElement = document.getElementById("allItemsModal");
    const modal = new bootstrap.Modal(modalElement);
    modal.show();

    // Update the confirm button to pass itemsToAdd
    const confirmButton = document.querySelector("#allItemsModal .btn-primary");
    if (confirmButton) {
        confirmButton.onclick = function() {
            confirmAllItemsSelection(itemsToAdd);
        };
    }
}

function confirmItemSelection() {
    const form = document.getElementById("itemSelectionForm");

    // Validate form
    if (!form.checkValidity()) {
        form.classList.add("was-validated");
        return;
    }

    // Get form data
    const itemNo = document.getElementById("selectedItemNo").value;
    const itemName = document.getElementById("selectedItemName").value;
    const unit = document.getElementById("selectedUnit").value;
    const approvedQuantity = document.getElementById("approved_Quantity").value;

    // Check if quantity has a value
    if (!approvedQuantity || approvedQuantity <= 0) {
        alert("Please enter a valid quantity.");
        return;
    }

    const formData = {
        itemNo,
        itemName,
        unit,
        approvedQuantity,
    };

    console.log("Form Data:", formData); // Debug: Log the form data

    // Process the selection
    processItemSelection(formData);

    // Move to the next item
    currentItemIndex++;
    openItemModalForCurrentItem();
}

function openItemModal(itemNo, itemName, unit) {
    // Add the item to the queue
    itemsQueue.push({ itemNo, itemName, unit });

    // If the modal is not already open, process the next item
    if (itemsQueue.length === 1) {
        processNextItemInQueue();
    }
}

function processNextItemInQueue() {
    if (itemsQueue.length === 0) {
        return; // No more items to process
    }

    const currentItem = itemsQueue[0]; // Get the first item in the queue

    // Set hidden field values
    document.getElementById("selectedItemNo").value = currentItem.itemNo;
    document.getElementById("selectedItemName").value = currentItem.itemName;
    document.getElementById("selectedUnit").value = currentItem.unit;

    // Display item information
    document.getElementById("displayItemName").textContent = currentItem.itemName;
    document.getElementById("displayUnit").textContent = currentItem.unit;
    document.getElementById("unitDisplay").textContent = currentItem.unit;

    // Reset form
    const form = document.getElementById("itemSelectionForm");
    form.reset();
    form.classList.remove("was-validated");

    // Reset hidden fields after form reset
    document.getElementById("selectedItemNo").value = currentItem.itemNo;
    document.getElementById("selectedItemName").value = currentItem.itemName;
    document.getElementById("selectedUnit").value = currentItem.unit;

    // Load offices via AJAX
    loadOffices();

    // Show the modal
    const modal = new bootstrap.Modal(
        document.getElementById("allItemsModal")
    );
    modal.show();

    // Add an event listener to handle when the modal is hidden
    const modalElement = document.getElementById("allItemsModal");
    modalElement.addEventListener("hidden.bs.modal", handleModalHidden, { once: true });
}

function handleModalHidden() {
    // Remove the processed item from the queue
    itemsQueue.shift();

    // Process the next item in the queue
    processNextItemInQueue();
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
}

function confirmItemSelection() {
  const form = document.getElementById("itemSelectionForm");
  
  if (!form) {
    alert("Error: Form not found.");
    return;
  }

  // Validate form
  if (!form.checkValidity()) {
    form.classList.add("was-validated");
    return;
  }

  // Get form elements with null checks
  const selectedItemNo = document.getElementById("selectedItemNo");
  const selectedItemName = document.getElementById("selectedItemName");
  const selectedUnit = document.getElementById("selectedUnit");
  const approvedQuantityElement = document.getElementById("approved_Quantity");

  if (!selectedItemNo || !selectedItemName || !selectedUnit || !approvedQuantityElement) {
    console.error("Required form elements not found:", {
      selectedItemNo: !!selectedItemNo,
      selectedItemName: !!selectedItemName,
      selectedUnit: !!selectedUnit,
      approvedQuantityElement: !!approvedQuantityElement
    });
    alert("Error: Required form elements not found.");
    return;
  }

  // Get form data
  const itemNo = selectedItemNo.value;
  const itemName = selectedItemName.value;
  const unit = selectedUnit.value;
  const approvedQuantity = approvedQuantityElement.value;

  // Check if quantity has a value
  if (!approvedQuantity || approvedQuantity <= 0) {
    alert("Please enter a valid quantity.");
    return;
  }

  const formData = {
    itemNo,
    itemName,
    unit,
    approvedQuantity,
  };

  console.log("Form Data:", formData); // Debug: Log the form data

  // Process the selection
  processItemSelection(formData);

  // Close modal
  const modalElement = document.getElementById("allItemsModal");
  if (modalElement) {
    const modal = bootstrap.Modal.getInstance(modalElement);
    if (modal) {
      modal.hide();
    }
  }
}

function processItemSelection(data) {
  // This function handles what happens after the user confirms the selection
  console.log("Item selected:", data);

  // Example AJAX call (uncomment and modify as needed):
  fetch('process_item_selection.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams(data)
  })
  .then(response => response.json())
  .then(result => {
    console.log("Server response:", result);
    alert(`Item "${data.itemName}" added successfully!`);
  })
  .catch(error => {
    console.error("Error processing item selection:", error);
    alert("An error occurred while processing the item selection.");
  });
}
