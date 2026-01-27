document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("sidebar");
  const content = document.getElementById("content");
  const toggleBtn = document.getElementById("sidebarToggle");
  const overlay = document.getElementById("sidebarOverlay");
  const darkModeToggle = document.getElementById("darkModeToggle");
  const darkModeIcon = document.getElementById("darkModeIcon");
  const darkModeToggleDesktop = document.getElementById(
    "darkModeToggleDesktop",
  );
  const darkModeIconDesktop = document.getElementById("darkModeIconDesktop");

  // Check if we're on mobile
  function isMobile() {
    return window.innerWidth <= 768;
  }

  // Dark mode functionality
  function toggleDarkMode() {
    const body = document.body;
    const isDarkMode = body.classList.contains("dark-mode");

    if (isDarkMode) {
      body.classList.remove("dark-mode");
      darkModeIcon.classList.remove("fa-sun");
      darkModeIcon.classList.add("fa-moon");
      if (darkModeIconDesktop) {
        darkModeIconDesktop.classList.remove("fa-sun");
        darkModeIconDesktop.classList.add("fa-moon");
      }
      localStorage.setItem("darkMode", "false");
    } else {
      body.classList.add("dark-mode");
      darkModeIcon.classList.remove("fa-moon");
      darkModeIcon.classList.add("fa-sun");
      if (darkModeIconDesktop) {
        darkModeIconDesktop.classList.remove("fa-moon");
        darkModeIconDesktop.classList.add("fa-sun");
      }
      localStorage.setItem("darkMode", "true");
    }
  }

  // Initialize dark mode from localStorage
  function initDarkMode() {
    const savedDarkMode = localStorage.getItem("darkMode");
    const body = document.body;

    if (savedDarkMode === "true") {
      body.classList.add("dark-mode");
      darkModeIcon.classList.remove("fa-moon");
      darkModeIcon.classList.add("fa-sun");
      if (darkModeIconDesktop) {
        darkModeIconDesktop.classList.remove("fa-moon");
        darkModeIconDesktop.classList.add("fa-sun");
      }
    } else {
      body.classList.remove("dark-mode");
      darkModeIcon.classList.remove("fa-sun");
      darkModeIcon.classList.add("fa-moon");
      if (darkModeIconDesktop) {
        darkModeIconDesktop.classList.remove("fa-sun");
        darkModeIconDesktop.classList.add("fa-moon");
      }
    }
  }

  // Toggle sidebar on mobile
  if (toggleBtn) {
    toggleBtn.addEventListener("click", function () {
      if (isMobile()) {
        sidebar.classList.toggle("show");
        content.classList.toggle("shifted");
        toggleBtn.classList.toggle("active");

        // Change icon when toggled
        const icon = toggleBtn.querySelector("i");
        if (sidebar.classList.contains("show")) {
          icon.classList.remove("fa-bars");
          icon.classList.add("fa-times");
        } else {
          icon.classList.remove("fa-times");
          icon.classList.add("fa-bars");
        }
      }
    });
  }

  // Dark mode toggle (mobile)
  if (darkModeToggle) {
    darkModeToggle.addEventListener("click", toggleDarkMode);
  }

  // Dark mode toggle (desktop)
  if (darkModeToggleDesktop) {
    darkModeToggleDesktop.addEventListener("click", toggleDarkMode);
  }

  // Dark mode toggle (dropdown)
  const darkModeDropdownToggle = document.getElementById(
    "darkModeDropdownToggle",
  );
  const darkModeDropdownIcon = document.getElementById("darkModeDropdownIcon");
  const darkModeDropdownText = document.getElementById("darkModeDropdownText");

  if (darkModeDropdownToggle) {
    darkModeDropdownToggle.addEventListener("click", function (e) {
      e.preventDefault();
      toggleDarkMode();

      // Update dropdown text and icon
      const body = document.body;
      const isDarkMode = body.classList.contains("dark-mode");

      if (isDarkMode) {
        darkModeDropdownIcon.classList.remove("fa-sun");
        darkModeDropdownIcon.classList.add("fa-moon");
        darkModeDropdownText.textContent = "Enable Dark Mode";
      } else {
        darkModeDropdownIcon.classList.remove("fa-moon");
        darkModeDropdownIcon.classList.add("fa-sun");
        darkModeDropdownText.textContent = "Disable Dark Mode";
      }
    });
  }

  // Close sidebar when clicking on overlay
  if (overlay) {
    overlay.addEventListener("click", function () {
      if (isMobile() && sidebar.classList.contains("show")) {
        sidebar.classList.remove("show");
        content.classList.remove("shifted");
        toggleBtn.classList.remove("active");

        // Reset icon to bars
        const icon = toggleBtn.querySelector("i");
        icon.classList.remove("fa-times");
        icon.classList.add("fa-bars");
      }
    });
  }

  // Close sidebar when clicking on any sidebar link
  const sidebarLinks = sidebar.querySelectorAll("a");
  sidebarLinks.forEach(function (link) {
    link.addEventListener("click", function () {
      if (isMobile() && sidebar.classList.contains("show")) {
        sidebar.classList.remove("show");
        content.classList.remove("shifted");
        toggleBtn.classList.remove("active");

        // Reset icon to bars
        const icon = toggleBtn.querySelector("i");
        icon.classList.remove("fa-times");
        icon.classList.add("fa-bars");
      }
    });
  });

  // Handle window resize
  window.addEventListener("resize", function () {
    if (!isMobile()) {
      // On desktop, always show sidebar
      sidebar.classList.remove("show");
      content.classList.add("shifted");
      toggleBtn.classList.remove("active");
      // Reset icon to bars for desktop
      const icon = toggleBtn.querySelector("i");
      icon.classList.remove("fa-times");
      icon.classList.add("fa-bars");
    } else {
      // On mobile, hide sidebar by default
      sidebar.classList.remove("show");
      content.classList.remove("shifted");
      toggleBtn.classList.remove("active");
      // Reset icon to bars for mobile default state
      const icon = toggleBtn.querySelector("i");
      icon.classList.remove("fa-times");
      icon.classList.add("fa-bars");
    }
  });

  // Initialize based on screen size and dark mode
  if (isMobile()) {
    document.querySelector(".main-header").classList.remove("d-none");
    sidebar.classList.remove("show");
    content.classList.remove("shifted");
    toggleBtn.classList.remove("active");
  } else {
    sidebar.classList.remove("show");
    content.classList.add("shifted");
    toggleBtn.classList.remove("active");
  }

  // Initialize dark mode
  initDarkMode();

  // Load PPMP data on page load
  loadPPMPData();

  // AJAX functionality for adding PPMP data
  const saveDataBtn = document.getElementById("saveDataBtn");
  const addDataForm = document.getElementById("addDataForm");

  if (saveDataBtn && addDataForm) {
    saveDataBtn.addEventListener("click", function (e) {
      e.preventDefault();

      // Get form values
      const project = document.getElementById("projectName").value;
      const startProcurement =
        document.getElementById("startProcurement").value;
      const endProcurement = document.getElementById("endProcurement").value;
      const expectedDelivery =
        document.getElementById("expectedDelivery").value;
      const amount = document.getElementById("amount").value;
      const prStatus = document.getElementById("prStatus").value;

      // Validate required fields
      if (
        !project ||
        !startProcurement ||
        !endProcurement ||
        !expectedDelivery ||
        !amount ||
        !prStatus
      ) {
        alert("Please fill in all required fields.");
        return;
      }

      // Prepare data for AJAX request
      const data = {
        project: project,
        start_procurement: startProcurement,
        end_procurement: endProcurement,
        expected_delivery: expectedDelivery,
        amount: parseFloat(amount),
        pr_status: prStatus,
      };

      // Show loading state
      saveDataBtn.disabled = true;
      saveDataBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

      // Make AJAX request
      fetch("add_ppmp_data.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("HTTP error! status: " + response.status);
          }
          return response.text();
        })
        .then(function (text) {
          console.log("Raw response:", text);
          try {
            var result = JSON.parse(text);
          } catch (e) {
            throw new Error("Invalid JSON response: " + text);
          }
          if (result.success) {
            alert("Data added successfully!");
            // Close modal
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("addDataModal"),
            );
            modal.hide();
            // Reset form
            addDataForm.reset();
            // Reload table data
            loadPPMPData();
          } else {
            alert("Error: " + result.message);
          }
        })
        .catch(function (error) {
          console.error("Error:", error);
          alert("An error occurred while saving the data: " + error.message);
        })
        .finally(function () {
          // Reset button state
          saveDataBtn.disabled = false;
          saveDataBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Data';
        });
    });
  }

  // Search functionality
  const tableSearch = document.getElementById("tableSearch");
  if (tableSearch) {
    tableSearch.addEventListener("input", function () {
      searchTable(this.value);
    });
  }

  // Select all checkbox functionality - using event delegation
  const selectAll = document.getElementById("selectAll");

  if (selectAll) {
    selectAll.addEventListener("change", function () {
      // Use event delegation to find checkboxes in the table body
      const tableBody = document.getElementById("ppmpTableBody");
      const rowCheckboxes = tableBody.querySelectorAll(".row-checkbox");

      rowCheckboxes.forEach(function (checkbox) {
        checkbox.checked = selectAll.checked;
      });
    });
  }

  // Delete selected functionality
  const deleteSelectedBtn = document.getElementById("deleteSelectedBtn");

  if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener("click", function () {
      const selectedCheckboxes = document.querySelectorAll(
        ".row-checkbox:checked",
      );

      if (selectedCheckboxes.length === 0) {
        alert("Please select at least one record to delete.");
        return;
      }

      if (
        !confirm(
          `Are you sure you want to delete ${selectedCheckboxes.length} record(s)? This action cannot be undone.`,
        )
      ) {
        return;
      }

      // Get selected IDs
      const selectedIds = Array.from(selectedCheckboxes).map(
        function (checkbox) {
          return checkbox.value;
        },
      );

      // Show loading state
      deleteSelectedBtn.disabled = true;
      deleteSelectedBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...';

      // Make AJAX request
      fetch("delete_ppmp_data.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ ids: selectedIds }),
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("HTTP error! status: " + response.status);
          }
          return response.json();
        })
        .then(function (result) {
          if (result.success) {
            alert(result.message);
            // Reload table data
            loadPPMPData();
            // Reset select all checkbox
            if (selectAll) {
              selectAll.checked = false;
            }
          } else {
            alert("Error: " + result.message);
          }
        })
        .catch(function (error) {
          console.error("Error:", error);
          alert("An error occurred while deleting the data.");
        })
        .finally(function () {
          // Reset button state
          deleteSelectedBtn.disabled = false;
          deleteSelectedBtn.innerHTML =
            '<i class="fas fa-trash me-1"></i>Delete Selected';
        });
    });
  }

  // Edit functionality - handle edit button click
  const editDataModal = document.getElementById("editDataModal");

  if (editDataModal) {
    editDataModal.addEventListener("show.bs.modal", function (event) {
      // Get the button that triggered the modal
      const button = event.relatedTarget;

      // Find the closest row to get the data
      const row = button.closest("tr");
      if (!row) return;

      // Get the checkbox in this row to get the ID
      const checkbox = row.querySelector(".row-checkbox");
      if (!checkbox) return;

      const recordId = checkbox.value;

      // Load the data immediately
      loadEditData(recordId);
    });
  }

  // Update data functionality
  const updateDataBtn = document.getElementById("updateDataBtn");

  if (updateDataBtn) {
    updateDataBtn.addEventListener("click", function (e) {
      e.preventDefault();

      // Collect form data
      const formData = {
        id: document.getElementById("editRecordId").value,
        pr_number: document.getElementById("editPrNo").value,
        po_number: document.getElementById("editPoNo").value,
        project: document.getElementById("editProjectName").value,
        start_procurement: document.getElementById("editStartProcurement")
          .value,
        end_procurement: document.getElementById("editEndProcurement").value,
        expected_delivery: document.getElementById("editExpectedDelivery")
          .value,
        amount: parseFloat(document.getElementById("editAmount").value),
        pr_status: document.getElementById("editPrStatus").value,
        delivery_status: document.getElementById("editDeliveryStatus").value,
        // Checklist data
        pre_submitted: document.getElementById("editSubmitted").checked ? 1 : 0,
        pre_approved: document.getElementById("editApproved").checked ? 1 : 0,
        pre_declined: document.getElementById("editDeclined").checked ? 1 : 0,
        pre_bac_declined: document.getElementById("editBacDeclined").checked
          ? 1
          : 0,
        bac_submitted: document.getElementById("editBacSubmitted").checked
          ? 1
          : 0,
        bac_categorized: document.getElementById("editCategorized").checked
          ? 1
          : 0,
        bac_posted: document.getElementById("editPosted").checked ? 1 : 0,
        bac_bidding: document.getElementById("editBidding").checked ? 1 : 0,
        post_awarded: document.getElementById("editAwarded").checked ? 1 : 0,
        post_approved: document.getElementById("editPoApproved").checked
          ? 1
          : 0,
      };

      // Validate required fields
      if (
        !formData.pr_number ||
        !formData.po_number ||
        !formData.project ||
        !formData.start_procurement ||
        !formData.end_procurement ||
        !formData.expected_delivery ||
        !formData.amount ||
        !formData.pr_status ||
        !formData.delivery_status
      ) {
        alert("Please fill in all required fields.");
        return;
      }

      // Show loading state
      updateDataBtn.disabled = true;
      updateDataBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';

      // Make AJAX request
      fetch("update_ppmp_data.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(formData),
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("HTTP error! status: " + response.status);
          }
          return response.json();
        })
        .then(function (result) {
          if (result.success) {
            alert("Data updated successfully!");
            // Close modal
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("editDataModal"),
            );
            modal.hide();
            // Reload table data
            loadPPMPData();
          } else {
            alert("Error: " + result.message);
          }
        })
        .catch(function (error) {
          console.error("Error:", error);
          alert("An error occurred while updating the data: " + error.message);
        })
        .finally(function () {
          // Reset button state
          updateDataBtn.disabled = false;
          updateDataBtn.innerHTML =
            '<i class="fas fa-save me-2"></i>Update Data';
        });
    });
  }
});

// Function to load PPMP data
function loadPPMPData() {
  const tableBody = document.getElementById("ppmpTableBody");

  // Show loading message
  tableBody.innerHTML =
    '<tr><td colspan="10" class="text-center">Loading data...</td></tr>';

  fetch("fetch_ppmp_data.php")
    .then(function (response) {
      if (!response.ok) {
        throw new Error("HTTP error! status: " + response.status);
      }
      return response.json();
    })
    .then(function (data) {
      if (data.length === 0) {
        tableBody.innerHTML =
          '<tr><td colspan="10" class="text-center">No data available</td></tr>';
        updateStatusCounts([], 0, 0, 0, 0);
        return;
      }

      let html = "";
      data.forEach(function (item, index) {
        const statusBadge = getStatusBadge(item.pr_status);
        const statusDeliver = getStatusDeliver(item.delivery_status);

        const formattedAmount = new Intl.NumberFormat("en-PH", {
          style: "currency",
          currency: "PHP",
        }).format(item.amount);

        html += `
                    <tr>
                        <td>
                            <div class="form-check">
                                <input class="form-check-input row-checkbox" type="checkbox" value="${item.id}" id="row${item.id}">
                                <label class="form-check-label" for="row${item.id}">
                                    <!-- Hidden label for accessibility -->
                                </label>
                            </div>
                        </td>
                        <td>${item.pr_number}</td>
                        <td>${item.po_number}</td>
                        <td>${item.project}</td>
                        <td>${item.start_procurement}</td>
                        <td>${item.end_procurement}</td>
                        <td>${item.expected_delivery}</td>
                        <td>${formattedAmount}</td>
                        <td>${statusDeliver}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary edit-btn" data-bs-toggle="modal" data-bs-target="#editDataModal" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                `;
      });

      tableBody.innerHTML = html;

      // Update status counts after data is loaded
      updateStatusCounts(data);
    })
    .catch(function (error) {
      console.error("Error loading data:", error);
      tableBody.innerHTML =
        '<tr><td colspan="10" class="text-center text-danger">Error loading data. Please try again.</td></tr>';
    });
}

// Function to update status counts
function updateStatusCounts(data) {
  // Initialize counters
  let deliveredCount = 0;
  let ongoingCount = 0;
  let declinedCount = 0;
  let prToPoCount = 0;

  // Count based on status
  data.forEach(function (item) {
    // Delivered count (based on Delivery Status)
    if (
      item.delivery_status &&
      item.delivery_status.toLowerCase() === "delivered"
    ) {
      deliveredCount++;
    }

    // Ongoing count (based on PR Status)
    if (item.pr_status && item.pr_status.toLowerCase() === "pending") {
      ongoingCount++;
    }

    // Declined count (based on PR Status)
    if (item.pr_status && item.pr_status.toLowerCase() === "declined") {
      declinedCount++;
    }

    // PR to PO count (based on PR Status)
    if (item.pr_status && item.pr_status.toLowerCase() === "po") {
      prToPoCount++;
    }
  });

  // Update the DOM elements
  const deliveredElement = document.getElementById("deliveredCount");
  const ongoingElement = document.getElementById("ongoingCount");
  const declinedElement = document.getElementById("declinedCount");
  const prToPoElement = document.getElementById("prToPoCount");

  if (deliveredElement) deliveredElement.textContent = deliveredCount;
  if (ongoingElement) ongoingElement.textContent = ongoingCount;
  if (declinedElement) declinedElement.textContent = declinedCount;
  if (prToPoElement) prToPoElement.textContent = prToPoCount;
}

// Function to get status badge HTML
function getStatusBadge(status) {
  let badgeClass = "secondary";
  let statusText = status;

  switch (status.toLowerCase()) {
    case "pending":
      badgeClass = "warning";
      break;
    case "po":
      badgeClass = "success";
      break;
    case "declined":
      badgeClass = "danger";
      break;
    case "completed":
      badgeClass = "info";
      break;
  }

  return `<span class="badge bg-${badgeClass}">${statusText}</span>`;
}
function getStatusDeliver(status) {
  let badgeClass = "secondary";
  let statusText = status;

  switch (status.toLowerCase()) {
    case "pending":
      badgeClass = "warning";
      break;
    case "delivered":
      badgeClass = "success";
      break;
    case "failed":
      badgeClass = "danger";
      break;
  }

  return `<span class="badge bg-${badgeClass}">${statusText}</span>`;
}
// Function to search table
function searchTable(searchTerm) {
  const tableBody = document.getElementById("ppmpTableBody");
  const rows = tableBody.getElementsByTagName("tr");

  searchTerm = searchTerm.toLowerCase();

  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    const cells = row.getElementsByTagName("td");
    let found = false;

    // Search in project name, dates, and status
    for (let j = 1; j < cells.length; j++) {
      if (cells[j].textContent.toLowerCase().indexOf(searchTerm) > -1) {
        found = true;
        break;
      }
    }

    if (found) {
      row.style.display = "";
    } else {
      row.style.display = "none";
    }
  }
}

// Function to load edit data for a specific record
function loadEditData(recordId) {
  fetch(`fetch_edit_data.php?id=${recordId}`)
    .then(function (response) {
      if (!response.ok) {
        throw new Error("HTTP error! status: " + response.status);
      }
      return response.text(); // Get raw text first to debug
    })
    .then(function (text) {
      console.log("Raw response from fetch_edit_data.php:", text);

      // Try to parse JSON
      let data;
      try {
        data = JSON.parse(text);
      } catch (jsonError) {
        throw new Error("JSON parsing error: " + jsonError.message);
      }

      if (data.success && data.data) {
        const record = data.data;

        // Populate form fields
        document.getElementById("editRecordId").value = record.id;
        document.getElementById("editPrNo").value = record.pr_number || "";
        document.getElementById("editPoNo").value = record.po_number || "";
        document.getElementById("editProjectName").value = record.project || "";
        document.getElementById("editStartProcurement").value =
          record.start_procurement || "";
        document.getElementById("editEndProcurement").value =
          record.end_procurement || "";
        document.getElementById("editExpectedDelivery").value =
          record.expected_delivery || "";
        document.getElementById("editAmount").value = record.amount || "";
        document.getElementById("editPrStatus").value = record.pr_status || "";
        document.getElementById("editDeliveryStatus").value =
          record.delivery_status || "";

        // Set checklist values (assuming 1 = checked, 0 = unchecked)
        document.getElementById("editSubmitted").checked =
          record.pre_submitted == 1;
        document.getElementById("editApproved").checked =
          record.pre_approved == 1;
        document.getElementById("editDeclined").checked =
          record.pre_declined == 1;
        document.getElementById("editBacDeclined").checked =
          record.pre_bac_declined == 1;
        document.getElementById("editBacSubmitted").checked =
          record.bac_submitted == 1;
        document.getElementById("editCategorized").checked =
          record.bac_categorized == 1;
        document.getElementById("editPosted").checked = record.bac_posted == 1;
        document.getElementById("editBidding").checked =
          record.bac_bidding == 1;
        document.getElementById("editAwarded").checked =
          record.post_awarded == 1;
        document.getElementById("editPoApproved").checked =
          record.post_approved == 1;
      } else {
        alert("Error loading data: " + (data.message || "Unknown error"));
        // Close the modal
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("editDataModal"),
        );
        if (modal) modal.hide();
      }
    })
    .catch(function (error) {
      console.error("Error loading edit data:", error);
      alert("An error occurred while loading the data: " + error.message);
      // Close the modal
      const modal = bootstrap.Modal.getInstance(
        document.getElementById("editDataModal"),
      );
      if (modal) modal.hide();
    });
}
