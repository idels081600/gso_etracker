function updateMaintenancePredictionChart() {
  const predictionCtx = document
    .getElementById("maintenancePredictionChart")
    .getContext("2d");

  // Get all rows with predictions
  const rows = document.querySelectorAll("tr[data-vehicle-id]");

  // Extract data for chart
  const chartData = [];
  rows.forEach((row) => {
    const plateNo = row.querySelector("td:first-child").textContent;
    const daysUntil = row.querySelector(".days-until").textContent;

    // Only include if days until is a number
    if (!isNaN(daysUntil) && daysUntil !== "N/A") {
      chartData.push({
        plateNo: plateNo,
        daysUntil: parseInt(daysUntil),
        urgency: row.querySelector(".urgency-badge").textContent,
      });
    }
  });

  // Sort by days until (ascending)
  chartData.sort((a, b) => a.daysUntil - b.daysUntil);

  // Take only the top 5 most urgent vehicles
  const topUrgent = chartData.slice(0, 5);

  // Create or update chart
  if (window.maintenanceChart) {
    window.maintenanceChart.data.labels = topUrgent.map((d) => d.plateNo);
    window.maintenanceChart.data.datasets[0].data = topUrgent.map(
      (d) => d.daysUntil
    );
    window.maintenanceChart.update();
  } else {
    window.maintenanceChart = new Chart(predictionCtx, {
      type: "bar",
      data: {
        labels: topUrgent.map((d) => d.plateNo),
        datasets: [
          {
            label: "Days Until",
            data: topUrgent.map((d) => d.daysUntil),
            backgroundColor: [
              "rgba(75, 192, 192, 0.6)",
              "rgba(54, 162, 235, 0.6)",
              "rgba(153, 102, 255, 0.6)",
              "rgba(255, 159, 64, 0.6)",
              "rgba(255, 99, 132, 0.6)",
            ],
            borderColor: [
              "rgba(75, 192, 192, 1)",
              "rgba(54, 162, 235, 1)",
              "rgba(153, 102, 255, 1)",
              "rgba(255, 159, 64, 1)",
              "rgba(255, 99, 132, 1)",
            ],
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
          },
        },
      },
    });
  }
}

document.getElementById("repairSearch").addEventListener("keyup", function () {
  const searchValue = this.value.toLowerCase();
  const tableRows = document.querySelectorAll(".table-group-divider tr");

  tableRows.forEach((row) => {
    let found = false;
    const cells = row.querySelectorAll("td, th");

    cells.forEach((cell) => {
      if (cell.textContent.toLowerCase().includes(searchValue)) {
        found = true;
      }
    });

    row.style.display = found ? "" : "none";
  });
});

// Store loaded vehicle data for lookup
let loadedVehicleData = [];

function loadVehicleSelectionTable() {
  const tableBody = document.querySelector("#vehicleSelectionTable tbody");
  // Clear existing table rows
  tableBody.innerHTML = "";
  // Show loading indicator
  tableBody.innerHTML =
    '<tr><td colspan="9" class="text-center">Loading...</td></tr>';
  // Fetch vehicle data from the server
  fetch("get_vehicle_records_motorpool.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Store loaded vehicle data for later lookup
        loadedVehicleData = data.data;
        // Clear loading indicator
        tableBody.innerHTML = "";
        // Check if we have data
        if (data.count === 0) {
          tableBody.innerHTML =
            '<tr><td colspan="9" class="text-center">No vehicles found</td></tr>';
          return;
        }
        // Populate table with vehicle data
        data.data.forEach((vehicle) => {
          const row = document.createElement("tr");
          row.innerHTML = `
                <td>${vehicle.plate_no}</td>
                <td>${vehicle.car_model || "-"}</td>
                <td>${vehicle.office}</td>
                <td>${vehicle.status}</td>
                <td>${vehicle.old_mileage}</td>
                <td>${vehicle.latest_mileage}</td>
                <td>${vehicle.no_of_repairs}</td>
                <td>${vehicle.new_repair_date || "-"}</td>
                <td>${vehicle.date_procured || "-"}</td>
                <td>
                  <div class="d-flex">
                    <button class="btn btn-sm btn-primary select-vehicle me-1"
                            data-plate="${vehicle.plate_no}"
                            data-id="${vehicle.id}"
                            data-model="${vehicle.car_model || ""}"
                            data-office="${vehicle.office}"
                            data-status="${vehicle.status}"
                            data-old-mileage="${vehicle.old_mileage}"
                            data-latest-mileage="${vehicle.latest_mileage}"
                            data-repairs="${vehicle.no_of_repairs}"
                            data-repair-date="${vehicle.new_repair_date || ""}"
                            data-procured="${vehicle.date_procured || ""}"
                            data-dispatch="${vehicle.no_dispatch}">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-vehicle"
                      data-plate="${vehicle.plate_no}">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </div>
                </td>`;
          tableBody.appendChild(row);
        });

        // Add event listeners to select buttons
        document.querySelectorAll(".select-vehicle").forEach((button) => {
          button.addEventListener("click", function () {
            // Fill the update form with vehicle data
            document.getElementById("original_plate_no").value =
              this.dataset.id;
               document.getElementById("update_plate_no").value =
              this.dataset.plate;
            document.getElementById("update_car_model").value =
              this.dataset.model;
            document.getElementById("update_office").value =
              this.dataset.office || "";
            document.getElementById("update_status").value =
              this.dataset.status;
            document.getElementById("update_old_mileage").value =
              this.dataset.oldMileage;
            document.getElementById("update_latest_mileage").value =
              this.dataset.latestMileage;
            document.getElementById("update_no_of_repairs").value =
              this.dataset.repairs;
            document.getElementById("update_latest_repair_date").value =
              this.dataset.repairDate;
            document.getElementById("update_date_procured").value =
              this.dataset.procured;
            document.getElementById("update_no_dispatch").value =
              this.dataset.dispatch;
            // Scroll to the top of the modal
            document.querySelector(
              "#updateVehicleModal .modal-body"
            ).scrollTop = 0;
          });
        });

        // Add event listeners to delete buttons
        document.querySelectorAll(".delete-vehicle").forEach((button) => {
          button.addEventListener("click", function () {
            const plateNo = this.dataset.plate;
            if (
              confirm(`Are you sure you want to delete vehicle ${plateNo}?`)
            ) {
              // Send delete request to server
              fetch("delete_vehicle_record_motorpool.php", {
                method: "POST",
                headers: {
                  "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `plate_no=${encodeURIComponent(plateNo)}`,
              })
                .then((response) => response.json())
                .then((data) => {
                  if (data.status === "success") {
                    alert(data.message || "Vehicle deleted successfully!");
                    // Reload the table to reflect changes
                    loadVehicleSelectionTable();
                  } else {
                    alert(
                      data.message ||
                        "Error deleting vehicle. Please try again."
                    );
                  }
                })
                .catch((error) => {
                  console.error("Error:", error);
                  alert("An error occurred. Please try again.");
                });
            }
          });
        });
      } else {
        // Show error message
        tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error: ${data.error}</td></tr>`;
        console.error("Error loading vehicle data:", data.error);
      }
    })
    .catch((error) => {
      tableBody.innerHTML =
        '<tr><td colspan="9" class="text-center text-danger">Failed to load vehicle data</td></tr>';
      console.error("Error:", error);
    });
}
// Load vehicle data when the update modal is shown
document
  .getElementById("updateVehicleModal")
  .addEventListener("show.bs.modal", function () {
    loadVehicleSelectionTable();
  });

// Handle form submission for updating vehicle
// Track if a form submission is in progress
let isSubmitting = false;

function setupUpdateVehicleForm() {
  const form = document.getElementById("updateVehicleForm");
  if (form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();

      // Prevent multiple submissions
      if (isSubmitting) {
        console.log("Form submission already in progress, ignoring");
        return;
      }

      console.log("Form submission started");
      isSubmitting = true;

      // Show loading state
      const submitButton = document.querySelector(
        '#updateVehicleModal .modal-footer button[type="submit"]'
      );
      const originalButtonText = submitButton.innerHTML;
      submitButton.disabled = true;
      submitButton.innerHTML =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';

      // Get form data
      const formData = new FormData(form);

      // Log form data for debugging
      console.log("Form data being submitted:");
      for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
      }

      // Submit form data via AJAX
      fetch("update_vehicle_record_motorpool.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => {
          console.log("Response status:", response.status);
          return response.text(); // Get raw text first to see what's returned
        })
        .then((text) => {
          console.log("Raw response:", text);

          // Reset submission flag
          isSubmitting = false;

          // Try to parse as JSON
          try {
            const data = JSON.parse(text);
            console.log("Parsed JSON response:", data);

            // Reset button state
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;

            // Handle response
            if (data.status === "success") {
              // Show success message
              console.log("Update successful");
              alert(data.message || "Vehicle updated successfully!");

              // Instead of closing the modal and reloading the page,
              // just refresh the vehicle selection table
              loadVehicleSelectionTable();

              // Optionally clear the form or reset it
              // form.reset();
            } else {
              // Show error message
              console.error("Update failed:", data.message);
              alert(
                data.message || "Error updating vehicle. Please try again."
              );
            }
          } catch (e) {
            console.error("Error parsing JSON response:", e);
            console.error("Raw response was:", text);

            // Reset button state
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;

            // Show error message
            alert(
              "An error occurred processing the server response. Check the console for details."
            );
          }
        })
        .catch((error) => {
          // Reset submission flag
          isSubmitting = false;

          // Reset button state
          submitButton.disabled = false;
          submitButton.innerHTML = originalButtonText;

          // Show error message
          console.error("Fetch error:", error);
          alert("An error occurred. Please try again.");
        });
    });
  } else {
    console.error("Update form not found in the DOM");
  }
}

// Initialize when the document is ready
document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM content loaded, setting up form handlers");

  // Set up the update form submission
  setupUpdateVehicleForm();
});
//line grapgh
document.addEventListener("DOMContentLoaded", function () {
  // Get the data from the hidden div
  const chartDataElement = document.getElementById("repairChartData");
  const dates = JSON.parse(chartDataElement.dataset.dates);
  const counts = JSON.parse(chartDataElement.dataset.counts);

  // Create chart
  const ctx = document.getElementById("dailyRepairsChart").getContext("2d");
  const dailyRepairsChart = new Chart(ctx, {
    type: "line",
    data: {
      labels: dates,
      datasets: [
        {
          label: "Repairs",
          data: counts,
          fill: false,
          borderColor: "rgb(75, 192, 192)",
          tension: 0.1,
          pointBackgroundColor: "rgb(75, 192, 192)",
          pointBorderColor: "#fff",
          pointHoverBackgroundColor: "#fff",
          pointHoverBorderColor: "rgb(75, 192, 192)",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            precision: 0, // Only show whole numbers
          },
        },
      },
      plugins: {
        legend: {
          display: false, // Hide legend to save space
        },
        tooltip: {
          callbacks: {
            title: function (tooltipItems) {
              return tooltipItems[0].label;
            },
            label: function (context) {
              return context.parsed.y + " repairs";
            },
          },
        },
      },
    },
  });
});
document.addEventListener("DOMContentLoaded", function () {
  // Get all status select elements
  const statusSelects = document.querySelectorAll(".status-select");

  // Add change event listener to each select
  statusSelects.forEach((select) => {
    select.addEventListener("change", function () {
      const form = this.closest(".status-form");
      const repairId = form.dataset.repairId;
      const newStatus = this.value;

      // Show loading indicator
      this.disabled = true;

      // Send AJAX request to update status
      fetch("update_repair_status_motorpool.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `repair_id=${repairId}&status=${newStatus}`,
      })
        .then((response) => response.text()) // Change from response.json() to response.text()
        .then((text) => {
          console.log("Raw response:", text); // Log the raw response
          try {
            const data = JSON.parse(text);
            if (data.success) {
              // Show success notification
              alert("Status updated successfully");
            } else {
              // Show error and revert to previous value
              alert("Error updating status: " + data.message);
              this.value = data.currentStatus;
            }
          } catch (e) {
            console.error("Failed to parse JSON:", e);
            alert("Server returned invalid JSON");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("An error occurred while updating the status");
        })
        .finally(() => {
          // Re-enable the select
          this.disabled = false;
        });
    });
  });
});
document.addEventListener("DOMContentLoaded", function () {
  // Function to get URL parameters
  function getUrlParams() {
    let params = {};
    const queryString = window.location.search.substring(1);
    const pairs = queryString.split("&");
    for (let i = 0; i < pairs.length; i++) {
      if (!pairs[i]) continue;
      const pair = pairs[i].split("=");
      params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || "");
    }
    return params;
  }

  // Function to show toast notification
  function showToast(message, isSuccess = true) {
    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector(".toast-container");
    if (!toastContainer) {
      toastContainer = document.createElement("div");
      toastContainer.className =
        "toast-container position-fixed bottom-0 end-0 p-3";
      document.body.appendChild(toastContainer);
    }
    // Create toast element
    const toastEl = document.createElement("div");
    toastEl.className = `toast align-items-center ${
      isSuccess ? "text-bg-success" : "text-bg-danger"
    }`;
    toastEl.setAttribute("role", "alert");
    toastEl.setAttribute("aria-live", "assertive");
    toastEl.setAttribute("aria-atomic", "true");
    // Create toast content
    toastEl.innerHTML = `
          <div class="d-flex">
              <div class="toast-body">
                  ${message}
              </div>
              <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
      `;
    // Add toast to container
    toastContainer.appendChild(toastEl);
    // Initialize and show toast with auto-hide after 2 seconds
    const toast = new bootstrap.Toast(toastEl, {
      delay: 2000, // 2000 milliseconds = 2 seconds
    });
    toast.show();
    // Remove toast after it's hidden
    toastEl.addEventListener("hidden.bs.toast", function () {
      toastEl.remove();
    });
  }

  // Get URL parameters
  const params = getUrlParams();

  // Check for success or error parameters (without requiring message parameter)
  if (params.success === "1") {
    // Show predefined success message
    showToast("Repair record added successfully", true);

    // Remove parameters from URL without reloading the page
    const newUrl = window.location.pathname;
    history.replaceState({}, document.title, newUrl);
  } else if (params.error === "1") {
    // Show predefined error message
    showToast("Error: Failed to add repair record", false);

    // Remove parameters from URL without reloading the page
    const newUrl = window.location.pathname;
    history.replaceState({}, document.title, newUrl);
  }
});

//search vehicles
// Vehicle search functionality
document.addEventListener("DOMContentLoaded", function () {
  const vehicleSearchInput = document.getElementById("vehicleSearchInput");
  const clearVehicleSearchBtn = document.getElementById("clearVehicleSearch");
  const vehicleTable = document.getElementById("vehicleSelectionTable");

  if (vehicleSearchInput && vehicleTable) {
    vehicleSearchInput.addEventListener("keyup", function () {
      const searchTerm = this.value.toLowerCase();
      const rows = vehicleTable.querySelectorAll("tbody tr");

      rows.forEach((row) => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
          row.style.display = "";
        } else {
          row.style.display = "none";
        }
      });
    });

    // Clear search and show all rows
    if (clearVehicleSearchBtn) {
      clearVehicleSearchBtn.addEventListener("click", function () {
        vehicleSearchInput.value = "";
        const rows = vehicleTable.querySelectorAll("tbody tr");
        rows.forEach((row) => {
          row.style.display = "";
        });
        vehicleSearchInput.focus();
      });
    }
  }
});
// Edit and Delete functionality for repair records
document.addEventListener("DOMContentLoaded", function () {
  // Edit repair functionality
  document.addEventListener("click", function (e) {
    if (e.target.closest(".edit-repair-btn")) {
      const button = e.target.closest(".edit-repair-btn");
      const repairId = button.getAttribute("data-repair-id");
      editRepair(repairId);
    }
  });

  // Delete repair functionality
  document.addEventListener("click", function (e) {
    if (e.target.closest(".delete-repair-btn")) {
      const button = e.target.closest(".delete-repair-btn");
      const repairId = button.getAttribute("data-repair-id");
      deleteRepair(repairId);
    }
  });

  // Function to edit repair
  function editRepair(repairId) {
    // Fetch repair data
    fetch("get_repair_data_motorpool.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "repair_id=" + repairId,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Populate edit modal with data
          populateEditModal(data.repair);
          // Show edit modal
          const editModal = new bootstrap.Modal(
            document.getElementById("editRepairModal")
          );
          editModal.show();
        } else {
          alert("Error fetching repair data: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Error fetching repair data");
      });
  }

  // Function to populate edit modal
  function populateEditModal(repair) {
    // Basic fields
    document.getElementById("edit_repair_id").value = repair.id;
    document.getElementById("edit_office").value = repair.office;
    document.getElementById("edit_vehicle_id").value = repair.plate_no;
    document.getElementById("edit_repair_date").value = repair.repair_date;
    document.getElementById("edit_mileage").value = repair.mileage || "";
    document.getElementById("edit_parts_replaced").value =
      repair.parts_replaced || "";
    document.getElementById("edit_cost").value = repair.cost || "";
    document.getElementById("edit_office").value = repair.office || "";
    document.getElementById("edit_notes").value = repair.remarks || "";
    document.getElementById("edit_status").value = repair.status || "";

    // Handle multiple repair types
    // First, uncheck all checkboxes
    const checkboxes = document.querySelectorAll(".edit-repair-type-checkbox");
    checkboxes.forEach((checkbox) => {
      checkbox.checked = false;
    });

    // Then check the appropriate ones based on repair_type
    if (repair.repair_type) {
      // Split repair types by comma and trim whitespace
      const repairTypes = repair.repair_type
        .split(",")
        .map((type) => type.trim());

      repairTypes.forEach((type) => {
        const checkbox = document.querySelector(
          `input[name="edit_repair_type[]"][value="${type}"]`
        );
        if (checkbox) {
          checkbox.checked = true;
        }
      });
    }
  }

  // Function to delete repair
  function deleteRepair(repairId) {
    if (
      confirm(
        "Are you sure you want to delete this repair record? This action cannot be undone."
      )
    ) {
      fetch("delete_repair_motorpool.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "repair_id=" + repairId,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            alert("Repair record deleted successfully");
            // Reload the page to refresh the table
            location.reload();
          } else {
            alert("Error deleting repair record: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("Error deleting repair record");
        });
    }
  }

  // Handle edit form submission
  document
    .getElementById("editRepairForm")
    .addEventListener("submit", function (e) {
      e.preventDefault();

      // Validate that at least one repair type is selected
      const checkedBoxes = document.querySelectorAll(
        ".edit-repair-type-checkbox:checked"
      );
      if (checkedBoxes.length === 0) {
        alert("Please select at least one repair type.");
        return false;
      }

      const formData = new FormData(this);

      fetch("update_repair_motorpool.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            alert("Repair record updated successfully");
            // Close modal
            const editModal = bootstrap.Modal.getInstance(
              document.getElementById("editRepairModal")
            );
            editModal.hide();
            // Reload the page to refresh the table
            location.reload();
          } else {
            alert("Error updating repair record: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("Error updating repair record");
        });
    });
});

// Add validation for edit repair form
document.addEventListener("DOMContentLoaded", function () {
  const editForm = document.getElementById("editRepairForm");
  const editCheckboxes = document.querySelectorAll(
    ".edit-repair-type-checkbox"
  );

  if (editForm) {
    editForm.addEventListener("submit", function (e) {
      const checkedBoxes = document.querySelectorAll(
        ".edit-repair-type-checkbox:checked"
      );
      if (checkedBoxes.length === 0) {
        e.preventDefault();
        alert("Please select at least one repair type.");
        return false;
      }
    });
  }
});
// Repair Type Dropdown with Input functionality
document.addEventListener("DOMContentLoaded", function () {
  const repairTypeInput = document.getElementById("edit_repair_type");
  const repairTypeDropdown = document.getElementById(
    "edit_repair_type_dropdown"
  );
  const repairTypeMenu = document.getElementById("edit_repair_type_menu");

  // Toggle dropdown menu
  repairTypeDropdown.addEventListener("click", function (e) {
    e.preventDefault();
    repairTypeMenu.classList.toggle("show");
  });

  // Handle dropdown item selection
  repairTypeMenu.addEventListener("click", function (e) {
    if (e.target.classList.contains("dropdown-item")) {
      e.preventDefault();
      const value = e.target.getAttribute("data-value");
      repairTypeInput.value = value;
      repairTypeMenu.classList.remove("show");
    }
  });

  // Filter dropdown items based on input
  repairTypeInput.addEventListener("input", function () {
    const filter = this.value.toLowerCase();
    const items = repairTypeMenu.querySelectorAll(".dropdown-item");

    items.forEach(function (item) {
      const text = item.textContent.toLowerCase();
      if (text.includes(filter)) {
        item.style.display = "block";
      } else {
        item.style.display = "none";
      }
    });

    // Show dropdown when typing
    if (this.value.length > 0) {
      repairTypeMenu.classList.add("show");
    }
  });

  // Hide dropdown when clicking outside
  document.addEventListener("click", function (e) {
    if (
      !e.target.closest("#edit_repair_type") &&
      !e.target.closest("#edit_repair_type_dropdown")
    ) {
      repairTypeMenu.classList.remove("show");
    }
  });

  // Show all items when input is focused
  repairTypeInput.addEventListener("focus", function () {
    const items = repairTypeMenu.querySelectorAll(".dropdown-item");
    items.forEach(function (item) {
      item.style.display = "block";
    });
  });
});

document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector("form");
  const checkboxes = document.querySelectorAll(".repair-type-checkbox");

  form.addEventListener("submit", function (e) {
    const checkedBoxes = document.querySelectorAll(
      ".repair-type-checkbox:checked"
    );
    if (checkedBoxes.length === 0) {
      e.preventDefault();
      alert("Please select at least one repair type.");
      return false;
    }
  });
});

