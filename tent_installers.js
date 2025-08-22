// Auto-refresh functionality
let refreshInterval;
let isAutoRefreshEnabled = true;

// Function to load table data via AJAX
function loadTableData() {
  console.log('Loading table data...');
  $.ajax({
    url: 'get_tent_data.php',
    method: 'GET',
    dataType: 'json',
    success: function(response) {
      console.log('AJAX Response:', response);
      if (response.success) {
        updateTableBody(response.data);
        console.log('Table data refreshed at:', response.timestamp);
        console.log('Total records loaded:', response.data.length);
      } else {
        console.error('Error loading data:', response.error);
        $('#tableBody').html('<tr><td colspan="6" class="text-center text-danger">Error loading data: ' + (response.error || 'Unknown error') + '</td></tr>');
      }
    },
    error: function(xhr, status, error) {
      console.error('AJAX Error:', status, error);
      console.error('Response Text:', xhr.responseText);
      $('#tableBody').html('<tr><td colspan="6" class="text-center text-danger">Failed to load data. Check console for details.</td></tr>');
    }
  });
}

// Function to update table body with new data
function updateTableBody(data) {
  console.log('updateTableBody called with data:', data);
  const tableBody = $('#tableBody');
  tableBody.empty();

  if (data.length === 0) {
    console.log('No data found');
    tableBody.html('<tr><td colspan="6" class="text-center text-muted">No data found</td></tr>');
    return;
  }

  console.log('Processing', data.length, 'records');
  data.forEach(function(row, index) {
    console.log('Processing row', index, ':', row);
    const tableRow = `
      <tr class="${row.row_class}">
        <td>${row.name}</td>
        <td>${row.location}</td>
        <td>${row.no_of_tents}</td>
        <td>${row.date}</td>
        <td>${row.status}</td>
        <td class="text-right">
          <button class="btn btn-primary"
              data-toggle="modal"
              data-target="#editModal"
              data-id="${row.id}"
              data-name="${row.name}"
              data-address="${row.location}"
              data-contact="${row.contact_no}"
              data-no_of_tents="${row.no_of_tents}"
              data-date="${row.date}"
              data-tent_no="${row.tent_no}"
              data-status="${row.status}">
              Edit
          </button>
        </td>
      </tr>
    `;
    tableBody.append(tableRow);
  });

  // Update last updated timestamp
  const now = new Date();
  const timeString = now.toLocaleTimeString();
  $('#lastUpdated').text(`Last updated: ${timeString}`);

  console.log('Table updated with today\'s data only');
}

// Function to start auto-refresh
function startAutoRefresh() {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
  refreshInterval = setInterval(loadTableData, 10000); // Refresh every 10 seconds
  isAutoRefreshEnabled = true;
  console.log('Auto-refresh started (10 seconds interval)');
}

// Function to stop auto-refresh
function stopAutoRefresh() {
  if (refreshInterval) {
    clearInterval(refreshInterval);
    refreshInterval = null;
  }
  isAutoRefreshEnabled = false;
  console.log('Auto-refresh stopped');
}

// Note: Filter functions removed since we only show today's data

$(document).ready(function () {
  console.log('Document ready - initializing tent installers (today\'s data only)...');

  // Load initial data
  loadTableData();

  // Start auto-refresh
  startAutoRefresh();

  // Refresh button handler
  $("#refreshBtn").click(function() {
    console.log('Refresh button clicked');

    // Add visual feedback for refresh
    const originalText = $(this).html();
    $(this).html('<i class="fas fa-spinner fa-spin"></i> Refreshing...');
    $(this).prop('disabled', true);

    // Load fresh data
    $.ajax({
      url: 'get_tent_data.php',
      method: 'GET',
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          updateTableBody(response.data);
          console.log('Manual refresh completed at:', response.timestamp);
        } else {
          console.error('Error loading data:', response.error);
        }
      },
      error: function(xhr, status, error) {
        console.error('Manual refresh failed:', status, error);
      },
      complete: function() {
        // Restore button state
        $("#refreshBtn").html(originalText);
        $("#refreshBtn").prop('disabled', false);
      }
    });
  });

  // Search functionality
  $("#searchInput").on("keyup", function () {
    var value = $(this).val().toLowerCase();
    console.log('Search input:', value);

    $("#tableBody tr").filter(function () {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
  });
});

$(document).ready(function () {
  var selectedClientId; // Declare within document ready scope
  var currentStatus; // Store the current status

  // Function to populate status dropdown based on current status
  function populateStatusDropdown(status) {
    var statusDropdown = $("#clientStatus");
    statusDropdown.empty(); // Clear existing options

    if (status === "Pending") {
      statusDropdown.append('<option value="Installed">Install</option>');
    } else if (status === "Installed") {
      statusDropdown.append('<option value="Retrieved">Retrieve</option>');
      statusDropdown.append(
        '<option value="For Retrieval">For Retrieval</option>'
      );
    } else if (status === "For Retrieval") {
      statusDropdown.append('<option value="Retrieved">Retrieved</option>');
    } else if (status === "Retrieved") {
      // For retrieved items, you might want to allow changing back to other statuses
      // or keep it as is. Adjust based on your business logic
      statusDropdown.append(
        '<option value="Retrieved" selected>Retrieved</option>'
      );
    } else {
      // Default fallback - show all options
      statusDropdown.append('<option value="Installed">Installed</option>');
      statusDropdown.append('<option value="Retrieved">Retrieved</option>');
      statusDropdown.append(
        '<option value="For Retrieval">For Retrieval</option>'
      );
      statusDropdown.append('<option value="Long Term">Long Term</option>');
    }
  }

  // Edit button click handler
  $(".btn-primary").click(function () {
    var row = $(this).closest("tr");
    var status = row.find("td:eq(4)").text().trim(); // Status is now in the 5th column (index 4)
    var name = row.find("td:eq(0)").text();
    var address = row.find("td:eq(1)").text();
    var contact = $(this).data("contact"); // Get contact from data attribute
    var noOfTents = $(this).data("no_of_tents"); // Get number of tents from data attribute
    selectedClientId = $(this).data("id"); // Use data() method
    tent_installed = $(this).data("tent_no");
    currentStatus = status; // Store current status

    console.log("Client ID on click:", selectedClientId);
    console.log("Current Status:", currentStatus);
    console.log("No. of Tents:", noOfTents);

    $("#clientName").val(name);
    $("#clientAddress").val(address);
    $("#clientContact").val(contact);
    $("#noOfTents").val(noOfTents); // Set the number of tents field
    $("#tentNumber").val(tent_installed);

    // Populate status dropdown based on current status
    populateStatusDropdown(currentStatus);

    $("#editModal").modal("show");
  });

  // Form submission handler using the same selectedClientId
  $("#editForm").on("submit", function (e) {
    e.preventDefault();

    const statusValue = $("#clientStatus").val();
    const tentValue = $("#tentNumber").val();

    console.log("Form submission - Client ID:", selectedClientId);
    console.log("Status Value:", statusValue);
    console.log("Tent Value:", tentValue);

    $.ajax({
      url: "update_tent_installer.php",
      method: "POST",
      dataType: "json",
      data: {
        clientStatus: statusValue,
        tentNumber: tentValue,
        clientId: selectedClientId, // Use selectedClientId directly
      },
      success: function (result) {
        console.log("Debug Info:", result.debug);
        if (result.success) {
          console.log("Update successful");
          $("#editModal").modal("hide");

          // Refresh the table data immediately to show updated information
          loadTableData();
        } else {
          console.log("Update failed:", result);
          alert("Update failed: " + (result.message || "Unknown error"));
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error:", status, error);
        console.error("Response:", xhr.responseText);
        alert("An error occurred while updating the record.");
      },
    });
  });

  // Box click handler
  $(".box").click(function () {
    if ($(this).css("background-color") === "rgb(40, 167, 69)") {
      var tentNumber = $(this).text();
      var currentValue = $("#tentNumber").val();

      if ($(this).hasClass("selected")) {
        $(this).removeClass("selected");
        var numbers = currentValue.split(",");
        numbers = numbers.filter((num) => num.trim() !== tentNumber);
        $("#tentNumber").val(numbers.join(","));
      } else {
        $(this).addClass("selected");
        if (currentValue) {
          if (!currentValue.split(",").includes(tentNumber)) {
            $("#tentNumber").val(currentValue + "," + tentNumber);
          }
        } else {
          $("#tentNumber").val(tentNumber);
        }
      }
    }
  });

  // Handle dynamic button binding for newly loaded content
  $(document).on("click", ".btn-primary", function () {
    var row = $(this).closest("tr");
    var status = row.find("td:eq(4)").text().trim(); // Status is in the 5th column (index 4)
    var name = row.find("td:eq(0)").text();
    var address = row.find("td:eq(1)").text();
    var contact = $(this).data("contact"); // Get contact from data attribute
    var noOfTents = $(this).data("no_of_tents"); // Get number of tents from data attribute
    selectedClientId = $(this).data("id"); // Use data() method
    tent_installed = $(this).data("tent_no");
    currentStatus = status; // Store current status

    console.log("Client ID on click:", selectedClientId);
    console.log("Current Status:", currentStatus);

    $("#clientName").val(name);
    $("#clientAddress").val(address);
    $("#clientContact").val(contact);
    $("#noOfTents").val(noOfTents); // Set the number of tents field
    $("#tentNumber").val(tent_installed);

    // Populate status dropdown based on current status
    populateStatusDropdown(currentStatus);

    $("#editModal").modal("show");
  });
});

// Global variables
let today = new Date().toISOString().split("T")[0];

// Global functions for message handling
function showNoResultsMessage(message) {
  // Remove existing message if any
  removeNoResultsMessage();

  // Create new row with message
  const tableBody = document.getElementById("tableBody");
  const noResultsRow = document.createElement("tr");
  noResultsRow.id = "noResultsRow";
  noResultsRow.innerHTML = `<td colspan="6" class="text-center text-muted">${message}</td>`;

  // Add to table body
  if (tableBody) {
    tableBody.appendChild(noResultsRow);
  }
}

function removeNoResultsMessage() {
  const existingMessage = document.getElementById("noResultsRow");
  if (existingMessage) {
    existingMessage.remove();
  }
}

// Note: Filter functionality has been integrated into the main jQuery ready function above
// Note: Enhanced search functionality has been integrated into the main jQuery ready function above

// Manage Common Use Supplies Modal functionality
let selectedItemsForCommonUse = [];

// Search functionality - with null checks
$(document).ready(function() {
  const searchItemsBtn = document.getElementById("searchItemsBtn");
  if (searchItemsBtn) {
    searchItemsBtn.addEventListener("click", function () {
      const searchInput = document.getElementById("itemSearchInput");
      if (searchInput) {
        const searchTerm = searchInput.value.trim();
        if (searchTerm) {
          searchItems(searchTerm);
        }
      }
    });
  }
});

$(document).ready(function() {
  const itemSearchInput = document.getElementById("itemSearchInput");
  if (itemSearchInput) {
    itemSearchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        const searchTerm = this.value.trim();
        if (searchTerm) {
          searchItems(searchTerm);
        }
      }
    });
  }

  const clearSearchBtn = document.getElementById("clearSearchBtn");
  if (clearSearchBtn) {
    clearSearchBtn.addEventListener("click", function () {
      const searchInput = document.getElementById("itemSearchInput");
      if (searchInput) {
        searchInput.value = "";
      }
      clearSearchResults();
    });
  }
});

// Select all checkboxes handlers
$(document).ready(function() {
  const selectAllSearchItems = document.getElementById("selectAllSearchItems");
  if (selectAllSearchItems) {
    selectAllSearchItems.addEventListener("change", function () {
      const checkboxes = document.querySelectorAll(
        "#searchItemsTable .item-checkbox"
      );
      checkboxes.forEach((checkbox) => {
        checkbox.checked = this.checked;
        handleItemSelection(checkbox);
      });
    });
  }

  const selectAllCommonItems = document.getElementById("selectAllCommonItems");
  if (selectAllCommonItems) {
    selectAllCommonItems.addEventListener("change", function () {
      const checkboxes = document.querySelectorAll(
        "#commonItemsTable .common-checkbox"
      );
      checkboxes.forEach((checkbox) => {
        checkbox.checked = this.checked;
      });
      updateCommonItemsButtons();
    });
  }

  // Add selected items to common use table
  const addSelectedItems = document.getElementById("addSelectedItems");
  if (addSelectedItems) {
    addSelectedItems.addEventListener("click", function () {
      if (selectedItemsForCommonUse.length > 0) {
        addItemsToCommonUse();
      }
    });
  }
});

function searchItems(searchTerm) {
  fetch(
    `manage_common_supplies.php?action=search_items&search=${encodeURIComponent(
      searchTerm
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displaySearchResults(data.items);
      } else {
        console.error("Error searching items:", data.message);
        Swal.fire("Error", data.message, "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      Swal.fire("Error", "Failed to search items", "error");
    });
}

function displaySearchResults(items) {
  const tableBody = document.getElementById("searchItemsTable");

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
                }" 
                       data-item='${JSON.stringify(
                         item
                       )}' onchange="handleItemSelection(this)">
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
                       data-item-no="${item.item_no}" ${
        item.current_balance <= 0 ? "disabled" : ""
      }>
            </td>
        </tr>
    `
    )
    .join("");
}

function handleItemSelection(checkbox) {
  const itemData = JSON.parse(checkbox.dataset.item);
  const quantityInput = document.querySelector(
    `input[data-item-no="${itemData.item_no}"]`
  );
  const quantity = parseInt(quantityInput.value) || 1;

  if (checkbox.checked) {
    // Add to selected items
    const existingIndex = selectedItemsForCommonUse.findIndex(
      (item) => item.item_no === itemData.item_no
    );
    if (existingIndex === -1) {
      selectedItemsForCommonUse.push({
        ...itemData,
        quantity: quantity,
      });
    } else {
      selectedItemsForCommonUse[existingIndex].quantity = quantity;
    }
  } else {
    // Remove from selected items
    selectedItemsForCommonUse = selectedItemsForCommonUse.filter(
      (item) => item.item_no !== itemData.item_no
    );
  }

  updateSelectedItemsCount();
  document.getElementById("addSelectedItems").disabled =
    selectedItemsForCommonUse.length === 0;
}

function updateSelectedItemsCount() {
  document.getElementById("selectedItemsCount").textContent =
    selectedItemsForCommonUse.length;
}

function addItemsToCommonUse() {
  const formData = new FormData();
  formData.append("action", "add_common_items");
  formData.append("items", JSON.stringify(selectedItemsForCommonUse));

  fetch("manage_common_supplies.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        Swal.fire(
          "Success",
          "Items added to common use successfully!",
          "success"
        );
        // Refresh common items table
        loadCommonItems();
        // Clear selections
        selectedItemsForCommonUse = [];
        updateSelectedItemsCount();
        clearSearchResults();
      } else {
        Swal.fire("Error", data.message, "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      Swal.fire("Error", "Failed to add items to common use", "error");
    });
}

function loadCommonItems() {
  fetch("manage_common_supplies.php?action=get_common_items")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displayCommonItems(data.items);
      } else {
        console.error("Error loading common items:", data.message);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
    });
}

function displayCommonItems(items) {
  const tableBody = document.getElementById("commonItemsTable");

  if (items.length === 0) {
    tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    No common use items added yet
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
                <input type="checkbox" class="common-checkbox" value="${
                  item.id
                }" 
                       onchange="updateCommonItemsButtons()">
            </td>
            <td>${item.item_no}</td>
            <td>${item.item_name}</td>
            <td>${item.quantity}</td>
            <td>
                <span class="badge ${getStatusBadgeClass(item.status)}">${
        item.status
      }</span>
            </td>
            <td>${item.date_added}</td>
            <td>
                <button class="btn btn-sm btn-outline-danger" onclick="removeCommonItem(${
                  item.id
                })">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `
    )
    .join("");
}

function updateCommonItemsButtons() {
  const checkedBoxes = document.querySelectorAll(
    "#commonItemsTable .common-checkbox:checked"
  );
  const hasSelection = checkedBoxes.length > 0;

  document.getElementById("updateCommonItems").disabled = !hasSelection;
  document.getElementById("removeCommonItems").disabled = !hasSelection;
}

function clearSearchResults() {
  document.getElementById("searchItemsTable").innerHTML = `
        <tr>
            <td colspan="6" class="text-center text-muted">
                Use the search bar above to find items
            </td>
        </tr>
    `;
  selectedItemsForCommonUse = [];
  updateSelectedItemsCount();
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

// Load common items when modal opens
$(document).ready(function() {
  const manageSuppliesModal = document.getElementById("manageSuppliesModal");
  if (manageSuppliesModal) {
    manageSuppliesModal.addEventListener("shown.bs.modal", function () {
      loadCommonItems();
    });
  }
});
