let currentOfficeId = null;
let supplyItemCount = 1;

// Add new supply item
document.getElementById("addSupplyItem").addEventListener("click", function () {
  supplyItemCount++;
  const container = document.getElementById("suppliesContainer");
  const newItem = container.querySelector(".supply-item").cloneNode(true);

  // Clear values
  newItem.querySelectorAll("input, select, textarea").forEach((input) => {
    if (input.type === "date") {
      input.value = new Date().toISOString().split("T")[0];
    } else {
      input.value = "";
    }
  });

  // Enable remove button
  newItem.querySelector(".remove-supply").disabled = false;

  container.appendChild(newItem);
  updateRemoveButtons();
});

// Remove supply item
document.addEventListener("click", function (e) {
  if (e.target.closest(".remove-supply")) {
    e.target.closest(".supply-item").remove();
    supplyItemCount--;
    updateRemoveButtons();
  }
});

function updateRemoveButtons() {
  const items = document.querySelectorAll(".supply-item");
  items.forEach((item, index) => {
    const removeBtn = item.querySelector(".remove-supply");
    removeBtn.disabled = items.length === 1;
  });
}

// View office details
function viewOfficeDetails(officeId) {
  currentOfficeId = officeId;
  // Load office details via AJAX
  fetch(`Logi_get_office_details.php?id=${officeId}`)
    .then((response) => response.json())
    .then((data) => {
      document.getElementById("officeDetailsTitle").textContent =
        data.office.office_name + " - Details";
      document.getElementById("officeInfo").innerHTML = `
                      <p><strong>Department:</strong><br>${
                        data.office.department || "N/A"
                      }</p>
                      <p><strong>Contact Person:</strong><br>${
                        data.office.contact_person || "N/A"
                      }</p>
                      <p><strong>Email:</strong><br>${
                        data.office.contact_email || "N/A"
                      }</p>
                      <p><strong>Phone:</strong><br>${
                        data.office.contact_phone || "N/A"
                      }</p>
                  `;

      // Load assigned supplies
      let suppliesHtml = "";
      data.supplies.forEach((supply) => {
        const statusBadge = getStatusBadgeClass(supply.status);
        suppliesHtml += `
                          <tr>
                              <td>${supply.item_name}</td>
                              <td>${supply.quantity} ${supply.unit}</td>
                              <td>${supply.po_number}</td>
                              <td>${supply.assigned_date}</td>
                              <td><span class="badge ${statusBadge}">${supply.status}</span></td>
                              <td>
                                  <button class="btn btn-sm btn-outline-primary" onclick="editSupply(${supply.id})">
                                      <i class="fas fa-edit"></i>
                                  </button>
                                  <button class="btn btn-sm btn-outline-danger" onclick="removeSupply(${supply.id})">
                                      <i class="fas fa-trash"></i>
                                  </button>
                              </td>
                          </tr>
                      `;
      });
      document.getElementById("assignedSuppliesTable").innerHTML =
        suppliesHtml ||
        '<tr><td colspan="6" class="text-center text-muted">No supplies assigned</td></tr>';

      new bootstrap.Modal(document.getElementById("officeDetailsModal")).show();
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Error loading office details");
    });
}

function getStatusBadgeClass(status) {
  switch (status) {
    case "Active":
      return "bg-success";
    case "Returned":
      return "bg-info";
    case "Damaged":
      return "bg-warning";
    case "Lost":
      return "bg-danger";
    default:
      return "bg-secondary";
  }
}

// Assign supplies to office
function assignSupplies(officeId) {
  document.getElementById("selectOffice").value = officeId;
  new bootstrap.Modal(document.getElementById("assignSuppliesModal")).show();
}

// Form submissions
document
  .getElementById("addOfficeForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    // Update your fetch URL to include the correct path
    fetch("Logi_add_office.php", {
      // or './Logi_add_office.php'
      method: "POST",
      body: formData,
    })
      .then((response) => {
        // Add this debug line to see what you're actually receiving
        console.log("Response status:", response.status);
        console.log("Response headers:", response.headers);

        // Check if response is ok before parsing JSON
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.text(); // Change to text() first to debug
      })
      .then((data) => {
        console.log("Raw response:", data); // See what you're actually getting

        try {
          const jsonData = JSON.parse(data);
          if (jsonData.success) {
            Swal.fire({
              icon: "success",
              title: "Success",
              text: "Office added successfully!",
              confirmButtonColor: "#3085d6",
            }).then(() => {
              location.reload();
            });
          } else {
            Swal.fire({
              icon: "error",
              title: "Error",
              text: jsonData.message,
              confirmButtonColor: "#d33",
            });
          }
        } catch (e) {
          console.error("JSON Parse Error:", e);
          console.error("Response was:", data);
          Swal.fire({
            icon: "error",
            title: "Server Error",
            text: "Server returned invalid response",
            confirmButtonColor: "#d33",
          });
        }
      })
      .catch((error) => {
        console.error("Fetch Error:", error);
        alert("Error adding office: " + error.message);
      });
  });

document
  .getElementById("assignSuppliesForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch("Logi_assign_supplies.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        console.log("Response status:", response.status);
        console.log("Response headers:", response.headers.get("content-type"));

        if (!response.ok) {
          // For 500 errors, try to get the response text to see the actual error
          return response.text().then((text) => {
            console.error("Server error response:", text);
            throw new Error(
              `HTTP error! status: ${
                response.status
              }. Response: ${text.substring(0, 200)}...`
            );
          });
        }

        // Check if response is actually JSON
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
          return response.text().then((text) => {
            console.error("Expected JSON but received:", text);
            throw new Error(
              "Server returned non-JSON response: " + text.substring(0, 100)
            );
          });
        }

        return response.json();
      })
      .then((data) => {
        console.log("Success response:", data);
        if (data.success) {
          alert("Supplies assigned successfully!");
          location.reload();
        } else {
          alert("Error: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Full error details:", error);
        alert("Error assigning supplies: " + error.message);
      });
  });

function deleteOffice(officeId) {
  // Using SweetAlert2 for better confirmation dialog
  Swal.fire({
    title: "Are you sure?",
    text: "You won't be able to revert this! This will also delete all associated items.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      // Show loading
      Swal.fire({
        title: "Deleting...",
        text: "Please wait while we delete the office.",
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      // Send delete request
      fetch("Logi_delete_office.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `office_id=${encodeURIComponent(officeId)}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            Swal.fire({
              title: "Deleted!",
              text: data.message,
              icon: "success",
              timer: 2000,
              showConfirmButton: false,
            }).then(() => {
              location.reload();
            });
          } else {
            Swal.fire("Error!", data.message, "error");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          Swal.fire(
            "Error!",
            "An error occurred while deleting the office.",
            "error"
          );
        });
    }
  });
}

function fetchAndDisplaySearchItems(searchTerm = "") {
  // Adjust the endpoint and parameters as needed for your backend
  fetch(
    `manage_common_supplies.php?action=search_items&search=${encodeURIComponent(
      searchTerm
    )}`
  )
    .then((response) => response.text())
    .then((text) => {
      const data = JSON.parse(text);
      displaySearchItems(data.items || []);
    })
    .catch((error) => {
      console.error("Error fetching items:", error);
      displaySearchItems([]);
    });
}

function displaySearchItems(items) {
  const tableBody = document.getElementById("searchItemsTable");
  if (!tableBody) return;

  if (items.length === 0) {
    tableBody.innerHTML = `
        <tr>
            <td colspan="6" class="text-center text-muted">
                No items found matching your search
            </td>
        </tr>
    `;
    return;
  }

  tableBody.innerHTML = items
    .map(
      (item) => `
    <tr>
        <td>
            <input type="checkbox" class="item-checkbox" value="${
              item.item_no
            }">
        </td>
        <td>${item.item_no}</td>
        <td>${item.item_name}</td>
        <td>${item.unit}</td>
        <td>
            <span class="badge ${
              item.current_balance > 0 ? "bg-success" : "bg-danger"
            }">
                ${item.current_balance}
            </span>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm quantity-input" 
                   value="1" min="1" max="${item.current_balance}" 
                   ${item.current_balance <= 0 ? 'disabled' : ''}>
        </td>
    </tr>
`
    )
    .join("");
}

// Example: Call this function on page load or when searching
// fetchAndDisplaySearchItems(); // To load all items initially

/// Debounce function to limit API calls
let searchTimeout;

// Keep the existing click event listener
document
  .getElementById("searchItemsBtn")
  .addEventListener("click", function () {
    const searchTerm = document.getElementById("itemSearchInput").value.trim();
    fetchAndDisplaySearchItems(searchTerm);
  });

// Add input event listener with debounce and loading state
document
  .getElementById("itemSearchInput")
  .addEventListener("input", function () {
    const searchTerm = this.value.trim();

    // Clear previous timeout
    clearTimeout(searchTimeout);

    // Show loading state immediately for better UX
    if (searchTerm.length >= 2) {
      showSearchLoading();
    }

    // Only search if user has typed at least 2 characters or cleared the input
    if (searchTerm.length >= 2 || searchTerm.length === 0) {
      searchTimeout = setTimeout(() => {
        fetchAndDisplaySearchItems(searchTerm);
      }, 300);
    } else if (searchTerm.length === 0) {
      // Clear results when input is empty
      displaySearchItems([]);
    }
  });

// Helper function to show loading state
function showSearchLoading() {
  const tableBody = document.getElementById("searchItemsTable");
  if (tableBody) {
    tableBody.innerHTML = `
    <tr>
      <td colspan="5" class="text-center text-muted">
        <i class="fas fa-spinner fa-spin"></i> Searching...
      </td>
    </tr>
  `;
  }
}

// Array to store common use items
let commonUseItems = [];

// Event listener for checkbox changes in search results
document.addEventListener("change", function (e) {
  if (e.target.classList.contains("item-checkbox")) {
    updateAddSelectedItemsButton();
  }
});

// Event listener for "Select All" checkbox in search results
document
  .getElementById("selectAllSearchItems")
  .addEventListener("change", function () {
    const checkboxes = document.querySelectorAll(".item-checkbox");
    checkboxes.forEach((checkbox) => {
      checkbox.checked = this.checked;
    });
    updateAddSelectedItemsButton();
  });

// Event listener for "Select All" checkbox in common items
document
  .getElementById("selectAllCommonItems")
  .addEventListener("change", function () {
    const checkboxes = document.querySelectorAll(".common-item-checkbox");
    checkboxes.forEach((checkbox) => {
      checkbox.checked = this.checked;
    });
    updateCommonItemsButtons();
  });

// Event listener for common items checkboxes
document.addEventListener("change", function (e) {
  if (e.target.classList.contains("common-item-checkbox")) {
    updateCommonItemsButtons();
  }
});

// Main event listener for "Add Selected Items" button
document.getElementById('addSelectedItems').addEventListener('click', function() {
  const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
    
  if (selectedCheckboxes.length === 0) {
      return; // Silently do nothing if no items selected
  }

  // Collect selected items data
  const selectedItems = [];
  let hasErrors = false;
    
  selectedCheckboxes.forEach(checkbox => {
      const row = checkbox.closest('tr');
      const itemNo = checkbox.value;
      const itemName = row.cells[2].textContent;
      const unit = row.cells[3].textContent;
      const availableStock = parseInt(row.cells[4].querySelector('.badge').textContent);
      
      // Check if there's a quantity input field in the row
      const quantityInput = row.querySelector('.quantity-input');
      let quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
      
      // Ensure quantity doesn't exceed available stock
      if (quantity > availableStock) {
          quantity = availableStock;
      }

      // Check if item already exists in common use (silently skip duplicates)
      const existingItem = commonUseItems.find(item => item.item_no === itemNo);
      if (existingItem) {
          return;
      }

      selectedItems.push({
          item_no: itemNo,
          item_name: itemName,
          unit: unit,
          available_stock: availableStock,
          status: 'Active'
      });
  });

  if (selectedItems.length > 0) {
      // Add items to common use array
      commonUseItems.push(...selectedItems);
        
      // Update the common use items table
      updateCommonItemsTable();
      updateCommonItemsButtons();
        
      // Clear selections
      selectedCheckboxes.forEach(checkbox => {
          checkbox.checked = false;
      });
        
      // Update button states
      updateAddSelectedItemsButton();
      updateSelectedItemsCount();
        
      // Debug log
      console.log('Items transferred to common use:', selectedItems.length);
      console.log('Total common use items:', commonUseItems.length);
  }
});

// Function to update "Add Selected Items" button state
function updateAddSelectedItemsButton() {
  const selectedCheckboxes = document.querySelectorAll(
    ".item-checkbox:checked"
  );
  const addButton = document.getElementById("addSelectedItems");
  addButton.disabled = selectedCheckboxes.length === 0;
}

// Function to update common items table
function updateCommonItemsTable() {
  const tableBody = document.getElementById("commonItemsTable");
  if (!tableBody) {
    console.error('Common items table not found');
    return;
  }
  
  console.log('Updating common items table with', commonUseItems.length, 'items');

  if (commonUseItems.length === 0) {
    tableBody.innerHTML = `
        <tr>
            <td colspan="6" class="text-center text-muted">
                No common use items added yet
            </td>
        </tr>
    `;
    return;
  }

  tableBody.innerHTML = commonUseItems
    .map(
      (item, index) => `
    <tr>
        <td>
            <input type="checkbox" class="common-item-checkbox" value="${item.item_no}" data-index="${index}">
        </td>
        <td>${item.item_no}</td>
        <td>${item.item_name}</td>
        <td>
            <input type="number" class="form-control form-control-sm quantity-edit" 
                   value="${item.available_stock}" min="1" max="${item.available_stock}" 
                   data-index="${index}">
        </td>
        <td>
            <span class="badge ${item.status === 'Active' ? 'bg-success' : 'bg-secondary'}">${item.status}</span>
        </td>
        <td>
            <button class="btn btn-sm btn-outline-primary" onclick="editCommonItem(${index})">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="removeCommonItem(${index})">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
`
    )
    .join("");

  // Add event listeners for quantity changes
  document.querySelectorAll(".quantity-edit").forEach((input) => {
    input.addEventListener("change", function () {
      const index = parseInt(this.dataset.index);
      const newQuantity = parseInt(this.value);
      const maxQuantity = commonUseItems[index].available_stock;

      if (newQuantity > maxQuantity) {
        this.value = maxQuantity;
        // No alert - just silently correct the value
      }

      commonUseItems[index].available_stock = parseInt(this.value);
    });
  });
}

// Function to update common items buttons state
function updateCommonItemsButtons() {
  const selectedCheckboxes = document.querySelectorAll(
    ".common-item-checkbox:checked"
  );
  const updateButton = document.getElementById("updateCommonItems");
  const removeButton = document.getElementById("removeCommonItems");

  const hasSelection = selectedCheckboxes.length > 0;
  updateButton.disabled = !hasSelection;
  removeButton.disabled = !hasSelection;
}

// Function to update selected items count
function updateSelectedItemsCount() {
  const countElement = document.getElementById("selectedItemsCount");
  if (countElement) {
    countElement.textContent = commonUseItems.length;
  }
}

// Function to edit individual common item (no alert)
function editCommonItem(index) {
  const item = commonUseItems[index];

  Swal.fire({
    title: "Edit Common Use Item",
    html: `
          <div class="text-start">
              <div class="mb-3">
                  <label class="form-label">Item: ${item.item_name}</label>
              </div>
              <div class="mb-3">
                  <label for="editQuantity" class="form-label">Quantity</label>
                  <input type="number" id="editQuantity" class="form-control" 
                         value="${item.available_stock}" min="1" max="${
      item.available_stock
    }">
                  <small class="text-muted">Max: ${item.available_stock}</small>
              </div>
              <div class="mb-3">
                  <label for="editStatus" class="form-label">Status</label>
                  <select id="editStatus" class="form-select">
                      <option value="Active" ${
                        item.status === "Active" ? "selected" : ""
                      }>Active</option>
                      <option value="Inactive" ${
                        item.status === "Inactive" ? "selected" : ""
                      }>Inactive</option>
                  </select>
              </div>
          </div>
      `,
    showCancelButton: true,
    confirmButtonText: "Update",
    cancelButtonText: "Cancel",
    preConfirm: () => {
      const quantity = parseInt(document.getElementById("editQuantity").value);
      const status = document.getElementById("editStatus").value;

      if (quantity > item.available_stock) {
        Swal.showValidationMessage(
          `Quantity cannot exceed ${item.available_stock}`
        );
        return false;
      }

      return { quantity, status };
    },
  }).then((result) => {
    if (result.isConfirmed) {
      commonUseItems[index].available_stock = result.value.quantity;
      commonUseItems[index].status = result.value.status;
      updateCommonItemsTable();
      updateCommonItemsButtons();
      // No success alert - just update silently
    }
  });
}

// Function to remove individual common item (no alert)
function removeCommonItem(index) {
  const item = commonUseItems[index];

  Swal.fire({
    title: "Remove Item",
    text: `Are you sure you want to remove "${item.item_name}" from common use items?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Yes, remove it!",
  }).then((result) => {
    if (result.isConfirmed) {
      commonUseItems.splice(index, 1);
      updateCommonItemsTable();
      updateCommonItemsButtons();
      updateSelectedItemsCount();
      // No success alert - just remove silently
    }
  });
}

// Event listeners for bulk operations (no alerts)
document
  .getElementById("updateCommonItems")
  .addEventListener("click", function () {
    const selectedCheckboxes = document.querySelectorAll(
      ".common-item-checkbox:checked"
    );

    if (selectedCheckboxes.length === 0) return;

    Swal.fire({
      title: "Bulk Update Status",
      html: `
          <div class="text-start">
              <label for="bulkStatus" class="form-label">New Status for ${selectedCheckboxes.length} selected item(s)</label>
              <select id="bulkStatus" class="form-select">
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
              </select>
          </div>
      `,
      showCancelButton: true,
      confirmButtonText: "Update All",
      cancelButtonText: "Cancel",
    }).then((result) => {
      if (result.isConfirmed) {
        const newStatus = document.getElementById("bulkStatus").value;

        selectedCheckboxes.forEach((checkbox) => {
          const index = parseInt(checkbox.dataset.index);
          commonUseItems[index].status = newStatus;
        });

        updateCommonItemsTable();
        updateCommonItemsButtons();
        // No success alert - just update silently
      }
    });
  });

document
  .getElementById("removeCommonItems")
  .addEventListener("click", function () {
    const selectedCheckboxes = document.querySelectorAll(
      ".common-item-checkbox:checked"
    );

    if (selectedCheckboxes.length === 0) return;

    Swal.fire({
      title: "Remove Selected Items",
      text: `Are you sure you want to remove ${selectedCheckboxes.length} selected item(s) from common use?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Yes, remove them!",
    }).then((result) => {
      if (result.isConfirmed) {
        // Get indices and sort in descending order to avoid index shifting issues
        const indices = Array.from(selectedCheckboxes)
          .map((checkbox) => parseInt(checkbox.dataset.index))
          .sort((a, b) => b - a);

        // Remove items starting from highest index
        indices.forEach((index) => {
          commonUseItems.splice(index, 1);
        });

        updateCommonItemsTable();
        updateCommonItemsButtons();
        updateSelectedItemsCount();
        // No success alert - just remove silently
      }
    });
  });

// Event listener for "Save All Changes" button - THIS IS WHERE THE ALERT SHOWS
document
  .getElementById("saveCommonUseChanges")
  .addEventListener("click", function () {
    if (commonUseItems.length === 0) {
      Swal.fire({
        icon: "info",
        title: "No Changes to Save",
        text: "Please add some items to common use before saving.",
        confirmButtonColor: "#3085d6",
      });
      return;
    }

    // Show loading
    Swal.fire({
      title: "Saving Changes...",
      text: "Please wait while we save your common use items.",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    // Prepare data for saving
    const dataToSave = {
      action: "save_common_items",
      items: commonUseItems,
    };

    // Send to server
    fetch("Logi_save_manage_common_supplies.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(dataToSave),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          Swal.fire({
            icon: "success",
            title: "Changes Saved Successfully!",
            text: `${commonUseItems.length} common use item(s) have been saved to the database.`,
            confirmButtonColor: "#28a745",
            timer: 3000,
            showConfirmButton: true,
          }).then(() => {
            // Optionally reload the page or reset the form
            // location.reload();
          });
        } else {
          throw new Error(data.message || "Failed to save changes");
        }
      })
      .catch((error) => {
        console.error("Error saving changes:", error);
        Swal.fire({
          icon: "error",
          title: "Save Failed",
          text: "An error occurred while saving your changes. Please try again.",
          confirmButtonColor: "#dc3545",
        });
      });
  });

// Initialize the selected items count on page load
document.addEventListener("DOMContentLoaded", function () {
  updateSelectedItemsCount();
});
