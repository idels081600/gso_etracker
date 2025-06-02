document.addEventListener("DOMContentLoaded", function () {
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
      const itemNo = row.cells[1].textContent.toLowerCase();
      const rackNo = row.cells[2].textContent.toLowerCase();
      const itemName = row.cells[3].textContent.toLowerCase();
      const unit = row.cells[4].textContent.toLowerCase();
      const status = row.cells[6].textContent.toLowerCase();

      if (
        itemNo.includes(searchTerm) ||
        rackNo.includes(searchTerm) ||
        itemName.includes(searchTerm) ||
        unit.includes(searchTerm) ||
        status.includes(searchTerm)
      ) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    });
  });

  // Clear search when search button is clicked
  document.getElementById("searchBtn").addEventListener("click", function () {
    searchInput.value = "";
    const tableRows = document.querySelectorAll("tbody tr");
    tableRows.forEach((row) => {
      row.style.display = "";
    });
    searchInput.focus();
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

      // Log individual form fields
      console.log("Item No:", formData.get("itemNo"));
      console.log("Item Name:", formData.get("itemName"));
      console.log("Rack No:", formData.get("rackNo"));
      console.log("Unit:", formData.get("unit"));
      console.log("Balance:", formData.get("balance"));
      console.log("Description:", formData.get("description"));

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
            const data = JSON.parse(text);
            console.log("=== PARSED JSON RESPONSE ===");
            console.log("Parsed data:", data);
            console.log("Success status:", data.success);
            console.log("Message:", data.message);

            if (data.success) {
              // Success - close modal and add to table
              console.log("✅ Item added successfully!");
              alert(data.message);

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
            alert(
              "Server returned invalid response. Check console for details."
            );
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
    const tbody = document.querySelector("tbody");
    const newRow = document.createElement("tr");

    const itemNo = formData.get("itemNo");
    const itemName = formData.get("itemName");
    const rackNo = "#" + formData.get("rackNo");
    const unit = formData.get("unit");
    const balance = formData.get("balance");

    // Determine status badge based on balance
    let badgeClass = "bg-success";
    let status = "Available";

    if (balance == 0) {
      badgeClass = "bg-danger";
      status = "Out of Stock";
    } else if (balance <= 10) {
      badgeClass = "bg-warning";
      status = "Low Stock";
    }

    newRow.innerHTML = `
            <td>
                <input type="checkbox" class="form-check-input row-checkbox" value="${itemNo}">
            </td>
            <td>${itemNo}</td>
            <td>${rackNo}</td>
            <td>${itemName}</td>
            <td>${unit}</td>
            <td>${balance}</td>
            <td><span class="badge ${badgeClass}">${status}</span></td>
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

    if (
      confirm(
        `Are you sure you want to delete the following ${
          itemIds.length
        } item(s)?\n\n${itemNames.join("\n")}`
      )
    ) {
      deleteItems(itemIds);
    }
  });
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

      const emptyRow = document.getElementById("emptyUpdateListRow");
      if (emptyRow) emptyRow.remove();

      const existingRow = updateListTableBody.querySelector(
        `tr[data-item-id="${currentItem.id}"]`
      );
      if (existingRow) existingRow.remove();

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

      // ✅ AJAX to update the database
      fetch("Logi_update_balance.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          item_id: currentItem.id,
          new_balance: newBalance,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data.success) {
            alert("Failed to update balance: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Error updating balance:", error);
          alert("An error occurred while saving balance.");
        });

      const modal = bootstrap.Modal.getInstance(
        document.getElementById("editBalanceModal")
      );
      modal.hide();
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
