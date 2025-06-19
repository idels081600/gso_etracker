document.addEventListener("DOMContentLoaded", function () {
  // Handle expiry date
  const expiryDateInput = document.getElementById("expiryDate");
  if (expiryDateInput) {
    expiryDateInput.addEventListener("change", function () {
      const expiryAlertSection = document.getElementById("expiryAlertSection");
      if (this.value) {
        expiryAlertSection.style.display = "block";
      } else {
        expiryAlertSection.style.display = "none";
      }
    });

    // Set minimum date to today for expiry date
    expiryDateInput.min = new Date().toISOString().split("T")[0];
  }

  // Handle select all checkbox
  const selectAllCheckbox = document.getElementById("selectAll");
  const rowCheckboxes = document.querySelectorAll(".row-checkbox");
  const deleteBtn = document.getElementById("deleteItemBtn");
  const updateBtn = document.getElementById("updateItemBtn");
  const searchInput = document.getElementById("searchInput");

  selectAllCheckbox.addEventListener("change", function () {
    rowCheckboxes.forEach((checkbox) => {
      checkbox.checked = this.checked;
    });
    toggleActionButtons();
  });

  // Handle individual row checkboxes
  rowCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener("change", function () {
      const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");
      selectAllCheckbox.checked = checkedBoxes.length === rowCheckboxes.length;
      toggleActionButtons();
    });
  });

  // Toggle action buttons based on selection
  function toggleActionButtons() {
    const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");
    deleteBtn.disabled = checkedBoxes.length === 0;
    updateBtn.disabled = checkedBoxes.length !== 1; // Enable only when exactly one item is selected
  }

  // Search functionality
  searchInput.addEventListener("input", function () {
    const searchTerm = this.value.toLowerCase();
    const tableRows = document.querySelectorAll("tbody tr");

    tableRows.forEach((row) => {
      // Skip the "no data" row if it exists
      if (row.cells.length < 9) {
        return;
      }

      let found = false;

      // Loop through all cells (starting from index 1 to skip checkbox)
      for (let i = 1; i < row.cells.length; i++) {
        const cellText = row.cells[i].textContent.toLowerCase();
        if (cellText.includes(searchTerm)) {
          found = true;
          break;
        }
      }

      if (found) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    });
  });

  // Clear search when input is cleared
  searchInput.addEventListener("keyup", function (e) {
    if (e.key === "Escape") {
      this.value = "";
      const tableRows = document.querySelectorAll("tbody tr");
      tableRows.forEach((row) => {
        row.style.display = "";
      });
      this.focus();
    }
  });

  // Handle Add Item Form with AJAX
  document
    .getElementById("addItemForm")
    .addEventListener("submit", function (e) {
      e.preventDefault();

      // Get form data
      const formData = new FormData(this);

      // Console log all form data being passed
      console.log("=== FORM DATA BEING SENT ===");
      for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
      }

      // Also log as an object for easier reading
      const formDataObject = Object.fromEntries(formData);
      console.log("Form data as object:", formDataObject);

      // Log individual form fields including expiry date and alert days
      console.log("Item No:", formData.get("itemNo"));
      console.log("Item Name:", formData.get("itemName"));
      console.log("Rack No:", formData.get("rackNo"));
      console.log("Unit:", formData.get("unit"));
      console.log("Balance:", formData.get("balance"));
      console.log("Expiry Date:", formData.get("expiryDate"));
      console.log("Expiry Alert Days:", formData.get("expiryAlertDays"));
      console.log("Description:", formData.get("description"));

      // Validate expiry date and alert settings if provided
      const expiryDate = formData.get("expiryDate");
      const expiryAlertDays = parseInt(formData.get("expiryAlertDays")) || 30;

      if (expiryDate) {
        const today = new Date();
        const expiry = new Date(expiryDate);

        // Check if expiry date is in the past
        if (expiry < today) {
          const confirmPastExpiry = confirm(
            "⚠️ Warning: The expiry date is in the past. This item is already expired!\n\nDo you want to continue adding this item?"
          );
          if (!confirmPastExpiry) {
            return; // Stop form submission
          }
        }

        // Check if expiry date is within alert period
        const alertDate = new Date();
        alertDate.setDate(today.getDate() + expiryAlertDays);

        if (expiry <= alertDate && expiry >= today) {
          const daysUntilExpiry = Math.ceil(
            (expiry - today) / (1000 * 60 * 60 * 24)
          );
          console.log(
            `⚠️ Item will expire in ${daysUntilExpiry} days (within ${expiryAlertDays}-day alert period)`
          );

          const confirmNearExpiry = confirm(
            `⚠️ Alert: This item will expire in ${daysUntilExpiry} days!\n` +
              `(Alert is set for ${expiryAlertDays} days before expiry)\n\n` +
              `Do you want to continue adding this item?`
          );
          if (!confirmNearExpiry) {
            return; // Stop form submission
          }
        }

        // Log expiry status for debugging
        const daysUntilExpiry = Math.ceil(
          (expiry - today) / (1000 * 60 * 60 * 24)
        );
        console.log(`📅 Expiry Analysis:`);
        console.log(`   - Days until expiry: ${daysUntilExpiry}`);
        console.log(`   - Alert threshold: ${expiryAlertDays} days`);
        console.log(
          `   - Within alert period: ${
            daysUntilExpiry <= expiryAlertDays && daysUntilExpiry >= 0
          }`
        );
      }

      // Show loading state
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.textContent;
      submitBtn.textContent = "Adding...";
      submitBtn.disabled = true;

      console.log("Sending request to: Logi_add_item.php");

      // Send AJAX request
      fetch("Logi_add_item.php", {
        method: "POST",
        body: formData,
        headers: {
          Accept: "application/json",
        },
      })
        .then((response) => {
          // Log the response for debugging
          console.log("=== RESPONSE DETAILS ===");
          console.log("Response status:", response.status);
          console.log("Response status text:", response.statusText);
          console.log("Response headers:", response.headers);
          console.log("Response URL:", response.url);

          // Check if response is ok
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }

          // Get response text first to check what we're receiving
          return response.text();
        })
        .then((text) => {
          console.log("=== RAW RESPONSE ===");
          console.log("Raw response:", text);
          console.log("Response length:", text.length);
          console.log("Response type:", typeof text);

          // Try to parse as JSON
          try {
            // Check if the response is empty
            if (!text.trim()) {
              throw new Error("Empty response received from server");
            }

            const data = JSON.parse(text);
            console.log("=== PARSED JSON RESPONSE ===");
            console.log("Parsed data:", data);
            console.log("Success status:", data.success);
            console.log("Message:", data.message);

            if (data.success) {
              // Success - close modal and add to table
              console.log("✅ Item added successfully!");

              // Show success message with expiry info if applicable
              let successMessage = data.message;
              if (expiryDate) {
                const expiry = new Date(expiryDate);
                const today = new Date();
                const daysUntilExpiry = Math.ceil(
                  (expiry - today) / (1000 * 60 * 60 * 24)
                );

                if (daysUntilExpiry <= 0) {
                  successMessage +=
                    "\n\n⚠️ IMPORTANT: This item has already expired!";
                } else if (daysUntilExpiry <= expiryAlertDays) {
                  successMessage += `\n\n⚠️ ALERT: This item will expire in ${daysUntilExpiry} days.`;
                  successMessage += `\nAlert is set for ${expiryAlertDays} days before expiry.`;
                } else {
                  successMessage += `\n\n📅 Expiry: ${expiry.toLocaleDateString()}`;
                  successMessage += `\nAlert will trigger ${expiryAlertDays} days before expiry.`;
                }
              }

              alert(successMessage);

              // Close modal
              const modal = bootstrap.Modal.getInstance(
                document.getElementById("addItemModal")
              );
              modal.hide();

              // Add item to table dynamically
              console.log(
                "Adding item to table with form data:",
                formDataObject
              );
              addItemToTable(formData);

              // Reset form
              this.reset();

              // Hide expiry alert section after reset
              document.getElementById("expiryAlertSection").style.display =
                "none";

              console.log("Form reset completed");
            } else {
              // Error - show error message
              console.log("❌ Server returned error:", data.message);
              alert("Error: " + data.message);
            }
          } catch (jsonError) {
            console.error("=== JSON PARSE ERROR ===");
            console.error("JSON Parse Error:", jsonError);
            console.error("Response was:", text);
            console.error("First 200 characters:", text.substring(0, 200));
          }
        })
        .catch((error) => {
          console.error("=== FETCH ERROR ===");
          console.error("Fetch Error:", error);
          console.error("Error message:", error.message);
          console.error("Error stack:", error.stack);
          alert("An error occurred while adding the item. Please try again.");
        })
        .finally(() => {
          console.log("=== CLEANUP ===");
          console.log("Resetting button state");
          // Reset button state
          submitBtn.textContent = originalText;
          submitBtn.disabled = false;
          console.log("Request completed");
        });
    });

  // Add event listener to show/hide expiry alert section when expiry date is entered
  document.getElementById("expiryDate").addEventListener("change", function () {
    const expiryAlertSection = document.getElementById("expiryAlertSection");

    if (this.value) {
      // Show the alert section when expiry date is selected
      expiryAlertSection.style.display = "block";
      console.log("📅 Expiry date selected, showing alert options");
    } else {
      // Hide the alert section when expiry date is cleared
      expiryAlertSection.style.display = "none";
      console.log("📅 Expiry date cleared, hiding alert options");
    }
  });

  // Add event listener for expiry alert days change
  document
    .getElementById("expiryAlertDays")
    .addEventListener("change", function () {
      const expiryDate = document.getElementById("expiryDate").value;
      const alertDays = parseInt(this.value);

      if (expiryDate) {
        const today = new Date();
        const expiry = new Date(expiryDate);
        const daysUntilExpiry = Math.ceil(
          (expiry - today) / (1000 * 60 * 60 * 24)
        );

        console.log(`📅 Alert setting changed to ${alertDays} days`);
        console.log(`   - Days until expiry: ${daysUntilExpiry}`);
        console.log(
          `   - Will trigger alert: ${
            daysUntilExpiry <= alertDays && daysUntilExpiry >= 0
          }`
        );

        // Show immediate feedback if item is within new alert period
        if (daysUntilExpiry <= alertDays && daysUntilExpiry >= 0) {
          console.log(
            `⚠️ Item is within the new ${alertDays}-day alert period`
          );
        }
      }
    });

  // Handle Update Item button click
  updateBtn.addEventListener("click", function () {
    const checkedBox = document.querySelector(".row-checkbox:checked");
    if (checkedBox) {
      const row = checkedBox.closest("tr");
      populateUpdateForm(row);
    }
  });

  // Function to add item to table dynamically
  function addItemToTable(formData) {
    const tableBody = document.querySelector("table tbody");
    const newRow = document.createElement("tr");

    const expiryDate = formData.get("expiryDate");
    const expiryAlertDays = parseInt(formData.get("expiryAlertDays")) || 30;
    const balance = parseInt(formData.get("balance"));

    // Determine status based on balance and expiry with custom alert days
    let status = "Available";
    let badgeClass = "bg-success";

    if (balance == 0) {
      status = "Out of Stock";
      badgeClass = "bg-danger";
    } else if (balance <= 10) {
      status = "Low Stock";
      badgeClass = "bg-warning";
    } else if (expiryDate) {
      const today = new Date();
      const expiry = new Date(expiryDate);
      const daysUntilExpiry = Math.ceil(
        (expiry - today) / (1000 * 60 * 60 * 24)
      );

      if (daysUntilExpiry <= 0) {
        status = "Expired";
        badgeClass = "bg-danger";
      } else if (daysUntilExpiry <= expiryAlertDays) {
        status = `Expires in ${daysUntilExpiry}d`;
        badgeClass = "bg-warning";
      }

      console.log(`📅 Item status determined:`);
      console.log(`   - Days until expiry: ${daysUntilExpiry}`);
      console.log(`   - Alert threshold: ${expiryAlertDays} days`);
      console.log(`   - Final status: ${status}`);
    }

    newRow.innerHTML = `
        <td>
            <input type="checkbox" class="form-check-input row-checkbox" value="${formData.get(
              "itemNo"
            )}">
        </td>
        <td>${formData.get("itemNo")}</td>
        <td>#${formData.get("rackNo")}</td>
        <td>${formData.get("itemName")}</td>
        <td>${formData.get("unit")}</td>
        <td>${formData.get("balance")}</td>
        <td>
            <span class="badge ${badgeClass}" title="${
      expiryDate
        ? `Expires: ${new Date(
            expiryDate
          ).toLocaleDateString()}, Alert: ${expiryAlertDays} days before`
        : ""
    }">${status}</span>
        </td>
    `;

    tbody.appendChild(newRow);

    // Add event listener to new checkbox
    const newCheckbox = newRow.querySelector(".row-checkbox");
    newCheckbox.addEventListener("change", function () {
      const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");
      const allCheckboxes = document.querySelectorAll(".row-checkbox");
      selectAllCheckbox.checked = checkedBoxes.length === allCheckboxes.length;
      toggleActionButtons();
    });

    // Update the rowCheckboxes NodeList reference
    updateCheckboxReferences();
  }

  // Function to update checkbox references after adding new items
  function updateCheckboxReferences() {
    const newRowCheckboxes = document.querySelectorAll(".row-checkbox");
    newRowCheckboxes.forEach((checkbox) => {
      // Remove existing event listeners to avoid duplicates
      checkbox.removeEventListener("change", handleCheckboxChange);
      // Add event listener
      checkbox.addEventListener("change", handleCheckboxChange);
    });
  }

  // Separate function for checkbox change handling
  function handleCheckboxChange() {
    const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");
    const allCheckboxes = document.querySelectorAll(".row-checkbox");
    selectAllCheckbox.checked = checkedBoxes.length === allCheckboxes.length;
    toggleActionButtons();
  }

  // Handle delete button
  deleteBtn.addEventListener("click", function () {
    const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");
    const itemIds = Array.from(checkedBoxes).map((cb) => cb.value);
    const itemNames = Array.from(checkedBoxes).map((cb) => {
      const row = cb.closest("tr");
      return row.cells[3].textContent; // Updated to correct cell index for item name
    });

    if (itemIds.length === 0) {
      alert("Please select at least one item to delete.");
      return;
    }

    if (
      confirm(
        `Are you sure you want to delete the following ${
          itemIds.length
        } item(s)?\n\n${itemNames.join("\n")}\n\nThis action cannot be undone!`
      )
    ) {
      deleteItems(itemIds, itemNames);
    }
  });

  // Delete items function
  function deleteItems(itemIds, itemNames) {
    console.log("=== DELETING ITEMS ===");
    console.log("Item IDs to delete:", itemIds);
    console.log("Item Names:", itemNames);

    // Show loading state
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    deleteBtn.disabled = true;

    // Disable all checkboxes during deletion
    const allCheckboxes = document.querySelectorAll(".row-checkbox");
    allCheckboxes.forEach((cb) => (cb.disabled = true));

    // Prepare data for deletion
    const deleteData = {
      item_ids: itemIds,
      item_names: itemNames,
    };

    console.log("Sending delete request:", deleteData);

    fetch("Logi_delete_item.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(deleteData),
    })
      .then((response) => {
        console.log("Delete response status:", response.status);
        console.log("Delete response OK:", response.ok);

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.json();
      })
      .then((data) => {
        console.log("Delete response data:", data);

        if (data.success) {
          console.log("✅ Items deleted successfully");

          // Show success message
          alert(
            `Successfully deleted ${
              data.deleted_count || itemIds.length
            } item(s).`
          );

          // Remove deleted rows from table
          itemIds.forEach((itemId) => {
            const checkbox = document.querySelector(
              `.row-checkbox[value="${itemId}"]`
            );
            if (checkbox) {
              const row = checkbox.closest("tr");
              if (row) {
                console.log(`Removing row for item: ${itemId}`);
                row.remove();
              }
            }
          });

          // Update select all checkbox
          updateSelectAllCheckbox();

          // Update delete button state
          updateDeleteButtonState();

          // Show empty state if no items left
          const remainingRows = document.querySelectorAll("tbody tr");
          if (remainingRows.length === 0) {
            const tbody = document.querySelector("tbody");
            tbody.innerHTML = `
            <tr>
              <td colspan="9" class="text-center">
                <div class="py-4">
                  <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                  <h5 class="text-muted">No inventory items found</h5>
                  <p class="text-muted">Start by adding some items to your inventory.</p>
                </div>
              </td>
            </tr>
          `;
          }

          console.log("Table updated successfully");
        } else {
          console.error("❌ Failed to delete items:", data.message);
          alert("Failed to delete items: " + (data.message || "Unknown error"));

          // Show details if available
          if (data.details) {
            console.error("Delete error details:", data.details);
          }
        }
      })
      .catch((error) => {
        console.error("❌ Delete request failed:", error);
        console.error("Error details:", {
          message: error.message,
          stack: error.stack,
        });

        alert("An error occurred while deleting items. Please try again.");
      })
      .finally(() => {
        // Reset button state
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;

        // Re-enable checkboxes
        allCheckboxes.forEach((cb) => (cb.disabled = false));

        console.log("=== DELETE PROCESS COMPLETED ===");
      });
  }

  // Helper function to update select all checkbox state
  function updateSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById("selectAll");
    const rowCheckboxes = document.querySelectorAll(".row-checkbox");
    const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");

    if (rowCheckboxes.length === 0) {
      selectAllCheckbox.checked = false;
      selectAllCheckbox.indeterminate = false;
    } else if (checkedBoxes.length === rowCheckboxes.length) {
      selectAllCheckbox.checked = true;
      selectAllCheckbox.indeterminate = false;
    } else if (checkedBoxes.length > 0) {
      selectAllCheckbox.checked = false;
      selectAllCheckbox.indeterminate = true;
    } else {
      selectAllCheckbox.checked = false;
      selectAllCheckbox.indeterminate = false;
    }
  }

  // Helper function to update delete button state
  function updateDeleteButtonState() {
    const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");
    const updateBtn = document.getElementById("updateItemBtn");

    if (checkedBoxes.length > 0) {
      deleteBtn.disabled = false;
      if (updateBtn && checkedBoxes.length === 1) {
        updateBtn.disabled = false;
      }
    } else {
      deleteBtn.disabled = true;
      if (updateBtn) updateBtn.disabled = true;
    }
  }

  // Initialize button states
  toggleActionButtons();
}); // Define the update button element first
const updateBtn = document.getElementById("updateItemBtn");

// Handle Update Item button click
updateBtn.addEventListener("click", function () {
  const checkedBox = document.querySelector(".row-checkbox:checked");
  if (checkedBox) {
    const row = checkedBox.closest("tr");
    populateUpdateForm(row);
  } else {
    alert("Please select an item to update.");
  }
});

// Updated populateUpdateForm function
function populateUpdateForm(row) {
  // Get item number from the selected row
  const itemNo = row.cells[1].textContent.trim();
  console.log("Fetching item details for:", itemNo);

  // Show the modal first
  const updateModal = new bootstrap.Modal(
    document.getElementById("updateItemModal")
  );
  updateModal.show();

  // Show loading state in form
  const formElements = document.querySelectorAll(
    "#updateItemModal input, #updateItemModal select, #updateItemModal textarea"
  );
  formElements.forEach((element) => {
    element.disabled = true;
  });

  const lastUpdatedInfo = document.getElementById("lastUpdatedInfo");
  if (lastUpdatedInfo) {
    lastUpdatedInfo.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Loading item details...';
  }

  // Fetch item details from database
  const formData = new FormData();
  formData.append("item_no", itemNo);

  fetch("Logi_get_item.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.text())
    .then((text) => {
      console.log("Get item response:", text);

      try {
        const data = JSON.parse(text);

        if (data.success) {
          const item = data.data;

          // Check if all required form elements exist
          const formElementIds = [
            "updateItemId",
            "updateItemNo",
            "updateItemName",
            "updateRackNo",
            "updateUnit",
            "updateBalance",
            "updateStatus",
            "updateDescription",
          ];

          const missingElements = [];
          const elements = {};

          // Check all elements first
          formElementIds.forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
              elements[id] = element;
            } else {
              missingElements.push(id);
            }
          });

          // Log missing elements
          if (missingElements.length > 0) {
            console.warn("Missing form elements:", missingElements);
          }

          // Populate existing elements
          if (elements.updateItemId)
            elements.updateItemId.value = item.id || "";
          if (elements.updateItemNo)
            elements.updateItemNo.value = item.item_no || "";
          if (elements.updateItemName)
            elements.updateItemName.value = item.item_name || "";
          if (elements.updateRackNo)
            elements.updateRackNo.value = item.rack_no || "";
          if (elements.updateUnit) elements.updateUnit.value = item.unit || "";
          if (elements.updateBalance)
            elements.updateBalance.value = item.current_balance || "";
          if (elements.updateStatus)
            elements.updateStatus.value = item.status || "";
          if (elements.updateDescription)
            elements.updateDescription.value = item.description || "";

          // Update last updated info
          if (lastUpdatedInfo) {
            const lastUpdated = item.updated_at
              ? new Date(item.updated_at).toLocaleString()
              : "Never";

            lastUpdatedInfo.innerHTML = `<i class="fas fa-info-circle"></i> Item: ${item.item_no} - ${item.item_name}<br>
               <small>Last updated: ${lastUpdated}</small>`;
          }

          console.log("Form populated successfully with:", item);
          console.log("Populated elements:", Object.keys(elements));
        } else {
          alert("Error: " + data.message);
          updateModal.hide();
        }
      } catch (jsonError) {
        console.error("JSON Parse Error:", jsonError);
        console.error("Response was:", text);
        alert("Error loading item details. Check console for details.");
        updateModal.hide();
      }
    })
    .catch((error) => {
      console.error("Fetch Error:", error);
      alert("An error occurred while loading item details. Please try again.");
      updateModal.hide();
    })
    .finally(() => {
      // Re-enable form elements
      formElements.forEach((element) => {
        element.disabled = false;
      });
    });
}

// Event listener for update form submission
document
  .getElementById("updateItemForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();

    // Get form data
    const formData = new FormData(this);

    // Console log the data being sent
    console.log("=== UPDATE FORM DATA ===");
    for (let [key, value] of formData.entries()) {
      console.log(`${key}: ${value}`);
    }

    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    submitBtn.disabled = true;

    // Send AJAX request
    fetch("Logi_update_item.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.text())
      .then((text) => {
        console.log("Update response:", text);

        try {
          const data = JSON.parse(text);

          if (data.success) {
            alert(data.message);
            location.reload(); // Simple reload
          } else {
            alert("Error: " + data.message);
            // Reset button state
            submitBtn.innerHTML = originalHTML;
            submitBtn.disabled = false;
          }
        } catch (jsonError) {
          console.error("JSON Parse Error:", jsonError);
          console.error("Response was:", text);
          alert("Server returned invalid response. Check console for details.");
          // Reset button state
          submitBtn.innerHTML = originalHTML;
          submitBtn.disabled = false;
        }
      })
      .catch((error) => {
        console.error("Update Error:", error);
        alert("An error occurred while updating the item. Please try again.");
        // Reset button state
        submitBtn.innerHTML = originalHTML;
        submitBtn.disabled = false;
      });
  });

// Auto-update status based on balance
document.getElementById("updateBalance").addEventListener("input", function () {
  const balance = parseInt(this.value) || 0;
  const statusSelect = document.getElementById("updateStatus");

  if (balance === 0) {
    statusSelect.value = "Out of Stock";
  } else if (balance <= 10) {
    statusSelect.value = "Low Stock";
  } else {
    statusSelect.value = "Available";
  }
});

// Clear form when modal is hidden
document
  .getElementById("updateItemModal")
  .addEventListener("hidden.bs.modal", function () {
    document.getElementById("updateItemForm").reset();
    document.getElementById("lastUpdatedInfo").textContent =
      "Last updated information will appear here";
  });

// Function to update checkbox references after adding new items
function updateCheckboxReferences() {
  const newRowCheckboxes = document.querySelectorAll(".row-checkbox");
  newRowCheckboxes.forEach((checkbox) => {
    // Remove existing event listeners to avoid duplicates
    checkbox.removeEventListener("change", handleCheckboxChange);
    // Add event listener
    checkbox.addEventListener("change", handleCheckboxChange);
  });
}

// Separate function for checkbox change handling
function handleCheckboxChange() {
  const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");
  const allCheckboxes = document.querySelectorAll(".row-checkbox");
  selectAllCheckbox.checked = checkedBoxes.length === allCheckboxes.length;
  toggleActionButtons();
}

//autogenerate item numbers
// Function to generate next item number
async function generateNextItemNo() {
  try {
    const response = await fetch("Logi_get_next_item_no.php", {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
      },
    });

    const data = await response.json();

    if (data.success) {
      document.getElementById("itemNo").value = data.nextItemNo;
    } else {
      console.error("Error generating item number:", data.error);
      // Fallback to 1 if there's an error
      document.getElementById("itemNo").value = "1";
    }
  } catch (error) {
    console.error("Error fetching next item number:", error);
    // Fallback to 1 if there's an error
    document.getElementById("itemNo").value = "1";
  }
}

// Auto-generate item number when the modal is opened
document
  .getElementById("addItemModal")
  .addEventListener("show.bs.modal", function () {
    generateNextItemNo();
  });

// Handle edit balance button clickdocument.addEventListener("DOMContentLoaded", function () {
const updateListTableBody = document.getElementById("updateListTableBody");
const updateListCount = document.getElementById("updateListCount");

// Store item info temporarily when opening the modal
let currentItem = {};

// When clicking "Update" button to open the modal
document.querySelectorAll(".update-btn").forEach(function (btn) {
  btn.addEventListener("click", function () {
    const row = this.closest("tr");
    currentItem = {
      id: row.getAttribute("data-item-id"),
      name: row.getAttribute("data-item-name"),
      current: parseFloat(row.getAttribute("data-current-balance")) || 0,
    };

    // Pre-fill new balance field
    document.getElementById("editNewBalance").value = currentItem.current;
    document.getElementById("editItemName").value = currentItem.name;

    const editModal = new bootstrap.Modal(
      document.getElementById("editBalanceModal")
    );
    editModal.show();
  });
});

// update modal submit
document.addEventListener("DOMContentLoaded", function () {
  const updateListTableBody = document.getElementById("updateListTableBody");
  const updateListCount = document.getElementById("updateListCount");

  // Declare currentItem globally so it can be accessed in both event handlers
  let currentItem = {};

  // Handle update button click (open modal)
  document.querySelectorAll(".update-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const row = this.closest("tr");
      currentItem = {
        id: row.getAttribute("data-item-id"),
        name: row.getAttribute("data-item-name"),
        current: parseFloat(row.getAttribute("data-current-balance")) || 0,
      };

      document.getElementById("editNewBalance").value = currentItem.current;

      const editModal = new bootstrap.Modal(
        document.getElementById("editBalanceModal")
      );
      editModal.show();
    });
  });

  // Handle form submit (add to table)
  document
    .getElementById("editBalanceForm")
    .addEventListener("submit", function (e) {
      e.preventDefault();

      const newBalance =
        parseFloat(document.getElementById("editNewBalance").value) || 0;
      const amountDiff = newBalance - currentItem.current;
      const action =
        amountDiff > 0 ? "Add" : amountDiff < 0 ? "Deduct" : "No Change";

      // Console log the item being updated
      console.log("=== UPDATING ITEM BALANCE ===");
      console.log("Item ID:", currentItem.id);
      console.log("Item Name:", currentItem.name);
      console.log("Current Balance:", currentItem.current);
      console.log("New Balance:", newBalance);
      console.log("Amount Difference:", amountDiff);
      console.log("Action:", action);
      console.log("Current Item Object:", currentItem);
      console.log("===============================");

      const emptyRow = document.getElementById("emptyUpdateListRow");
      if (emptyRow) emptyRow.remove();

      const existingRow = updateListTableBody.querySelector(
        `tr[data-item-id="${currentItem.id}"]`
      );
      if (existingRow) {
        console.log("Removing existing row for item:", currentItem.id);
        existingRow.remove();
      }

      const newRow = document.createElement("tr");
      newRow.setAttribute("data-item-id", currentItem.id);
      newRow.setAttribute("data-amount-diff", amountDiff); // store the diff for later
      newRow.setAttribute("data-original-balance", currentItem.current);
      newRow.innerHTML = `
      <td>${currentItem.id}</td>
      <td>${currentItem.name}</td>
      <td>${currentItem.current}</td>
      <td>${action}</td>
      <td>${Math.abs(amountDiff)}</td>
      <td>${newBalance}</td>
      <td><button class="btn btn-sm btn-danger remove-row-btn"><i class="fas fa-trash"></i></button></td>
    `;

      updateListTableBody.appendChild(newRow);
      updateListCount.textContent =
        updateListTableBody.querySelectorAll("tr").length;

      console.log("Added new row to update list table");
      console.log("Total items in update list:", updateListCount.textContent);

      // ✅ AJAX to update the database
      const updateData = {
        item_id: currentItem.id,
        new_balance: newBalance,
      };

      console.log("Sending AJAX request to update database:");
      console.log("Request Data:", updateData);

      fetch("Logi_update_balance.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(updateData),
      })
        .then((response) => {
          console.log("AJAX Response Status:", response.status);
          console.log("AJAX Response OK:", response.ok);
          return response.json();
        })
        .then((data) => {
          console.log("AJAX Response Data:", data);
          if (data.success) {
            console.log("✅ Balance updated successfully in database");
            console.log("Server Message:", data.message);
          } else {
            console.error("❌ Failed to update balance:", data.message);
            alert("Failed to update balance: " + data.message);
          }
        })
        .catch((error) => {
          console.error("❌ AJAX Error:", error);
          console.error("Error details:", {
            message: error.message,
            stack: error.stack,
          });
          alert("An error occurred while saving balance.");
        });

      const modal = bootstrap.Modal.getInstance(
        document.getElementById("editBalanceModal")
      );

      console.log("Hiding modal...");
      modal.hide();

      console.log("=== UPDATE PROCESS COMPLETED ===");
    });

  // ✅ Handle remove and rollback
  updateListTableBody.addEventListener("click", function (e) {
    if (e.target.closest(".remove-row-btn")) {
      const row = e.target.closest("tr");

      const itemId = parseInt(row.getAttribute("data-item-id"));
      const originalBalance = parseFloat(
        row.getAttribute("data-original-balance")
      );
      const amountDiff = parseFloat(row.getAttribute("data-amount-diff"));
      const rollbackBalance = originalBalance; // revert to original

      // AJAX to rollback the balance
      fetch("Logi_update_balance.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          item_id: itemId,
          new_balance: rollbackBalance,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data.success) {
            alert("Rollback failed: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Rollback error:", error);
          alert("An error occurred while rolling back balance.");
        });

      row.remove();

      const rowsLeft = updateListTableBody.querySelectorAll("tr").length;
      updateListCount.textContent = rowsLeft;

      if (rowsLeft === 0) {
        updateListTableBody.innerHTML = `
        <tr id="emptyUpdateListRow">
          <td colspan="7" class="text-center text-muted py-3">
            <i class="fas fa-inbox fa-2x mb-2"></i><br>
            No items added for update yet
          </td>
        </tr>`;
      }
    }
  });
});
document.querySelectorAll(".modal").forEach((modal) => {
  modal.addEventListener("hidden.bs.modal", () => {
    document.body.classList.remove("modal-open");
    const backdrop = document.querySelector(".modal-backdrop");
    if (backdrop) backdrop.remove();
  });
});
// ... existing code ...
document.addEventListener("DOMContentLoaded", function () {
  // Existing code...

  // Print button click handler
  document.getElementById("printBtn").addEventListener("click", function () {
    // Open the print options modal
    var printOptionsModal = new bootstrap.Modal(
      document.getElementById("printOptionsModal")
    );
    printOptionsModal.show();
  });

  // Print Selected button click handler
  document
    .getElementById("printSelectedBtn")
    .addEventListener("click", function () {
      // Collect selected statuses
      var selectedStatuses = [];
      if (document.getElementById("printAvailable").checked)
        selectedStatuses.push("Available");
      if (document.getElementById("printLowStock").checked)
        selectedStatuses.push("Low Stock");
      if (document.getElementById("printOutOfStock").checked)
        selectedStatuses.push("Out of Stock");
      if (document.getElementById("printDiscontinued").checked)
        selectedStatuses.push("Discontinued");

      // Close the modal
      var printOptionsModal = bootstrap.Modal.getInstance(
        document.getElementById("printOptionsModal")
      );
      printOptionsModal.hide();

      // Send POST request to generate and preview PDF
      var formData = new FormData();
      selectedStatuses.forEach(function (status) {
        formData.append("statuses[]", status);
      });

      fetch("Logi_print_data_stock.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.blob())
        .then((blob) => {
          var url = window.URL.createObjectURL(blob);
          window.open(url, "_blank");
        })
        .catch((error) => {
          alert("Failed to generate PDF.");
          console.error(error);
        });
    });
});
