// All code below is moved from tracking.php's <script> blocks

// Dropdown menu logic
$(function () {
  var dropdowns = document.querySelectorAll(".dropdown");
  dropdowns.forEach(function (dropdown) {
    dropdown.addEventListener("click", function (event) {
      this.querySelector(".dropdown-menu").classList.toggle("active");
      this.classList.toggle("open");
    });
  });
  document.addEventListener("click", function (event) {
    if (!event.target.closest(".dropdown")) {
      var activeDropdowns = document.querySelectorAll(".dropdown-menu.active");
      activeDropdowns.forEach(function (activeDropdown) {
        activeDropdown.classList.remove("active");
        activeDropdown.closest(".dropdown").classList.remove("open");
      });
    }
  });
});

// Datepicker initialization
$(function () {
  $("#datepicker").datepicker();
  $("#datepicker1").datepicker();
});

// Popup/modal logic
$(function () {
  $("#addButton").on("click", function () {
    $(".popup").addClass("active");
    $(".overlay").show();
  });
  $(".popup .close-btn").on("click", function () {
    $(".popup").removeClass("active");
    $(".overlay").hide();
  });
  $("#UpdateButton").on("click", function () {
    $(".popup3").addClass("active");
    $(".overlay").show();
  });
  $(".popup3 .close-btn").on("click", function () {
    $(".popup3").removeClass("active");
    $(".overlay").hide();
  });
  $(".popup1 .close-btn").on("click", function () {
    $(".popup1").removeClass("active");
    $(".overlay").hide();
  });
  $(document).on("click", ".viewButton", function () {
    var id = $(this).data("id");
    // Fetch data for the selected row
    $.get("fetch_data.php", { id: id }, function (data) {
      if (typeof data === "string") data = JSON.parse(data);
      // Populate the new Bootstrap 5 modal fields
      $("#viewEditTentNo").val(data.tent_no);
      $("#viewEditDate").val(data.date);
      $("#viewEditRetrievalDate").val(data.retrieval_date);
      $("#viewEditName").val(data.name);
      $("#viewEditContactNo").val(data.Contact_no);
      $("#viewEditNoOfTents").val(data.no_of_tents);
      $("#viewEditPurpose").val(data.purpose);
      $("#viewEditLocation").val(data.location);
      $("#viewEditStatus").val(data.status);
      $("#viewEditAddress").val(data.address);
      // Show the new Bootstrap 5 modal
      var modal = new bootstrap.Modal(document.getElementById("viewEditModal"));
      modal.show();
    });
  });
  // Clear popup inputs on close
  $(".popup .close-btn").on("click", function () {
    $(".popup .form input[type='text']").val("");
    $(".popup").removeClass("active");
  });
  // Overlay click closes popups
  $(".overlay").on("click", function () {
    $(".popup, .popup1, .popup3").removeClass("active");
    $(this).hide();
  });
  // Escape key closes popups
  $(document).on("keydown", function (event) {
    if (event.key === "Escape") {
      $(".popup, .popup1, .popup3").removeClass("active");
      $(".overlay").hide();
    }
  });
});

// Enter key navigation in form
$(function () {
  $('.form input[type="text"]').on("keydown", function (event) {
    if (event.keyCode === 13) {
      event.preventDefault();
      var inputs = $('.form input[type="text"]');
      var idx = inputs.index(this);
      if (idx + 1 < inputs.length) {
        inputs[idx + 1].focus();
      }
    }
  });
});

// AJAX for viewButton to fetch data and populate form
$(function () {
  $(".viewButton").on("click", function () {
    var id = $(this).data("id");
    $("#id").val(id);
    $.get("fetch_data.php", { id: id }, function (data) {
      if (typeof data === "string") data = JSON.parse(data);
      populateForm(data);
    });
  });
});

function populateForm(data) {
  $("#tentno").val(data.tent_no);
  $("#tent_no1").val(data.no_of_tents);
  $("#datepicker1").val(data.date);
  $("#name1").val(data.name);
  $("#contact1").val(data.Contact_no);
  $("#tent_duration1").val(data.retrieval_date);
  // Purpose dropdown
  var purposeOptions = [
    "Wake",
    "Birthday",
    "Wedding",
    "Baptism",
    "Personal",
    "Private",
    "Church",
    "School",
    "LGU",
    "Province",
    "City Government",
    "Municipalities",
  ];
  var $purposeDropdown = $("#purpose1");
  $purposeDropdown.empty();
  $.each(purposeOptions, function (_, option) {
    $purposeDropdown.append($("<option>", { value: option, text: option }));
  });
  $purposeDropdown.val(data.purpose);
  // Location dropdown
  var locationOptions = [
    "Bool",
    "Booy",
    "Cabawan",
    "Cogon",
    "Dao",
    "Dampas",
    "Manga",
    "Mansasa",
    "Poblacion I",
    "Poblacion II",
    "Poblacion III",
    "San Isidro",
    "Taloto",
    "Tiptip",
    "Ubujan",
    "Outside Tagbilaran",
  ];
  var $locationDropdown = $("#Location1");
  $locationDropdown.empty();
  $.each(locationOptions, function (_, option) {
    $locationDropdown.append($("<option>", { value: option, text: option }));
  });
  $locationDropdown.val(data.location);
  $("#other1").val(data.location);
  var initialTentNo = parseInt(data.no_of_tents) || 0;
  clickLimit = initialTentNo;
  $("#tent_no1").trigger("input");
}

// Box status and click logic
$(function () {
  var clickLimit = 0;
  var dropdown = document.querySelector(".status-dropdown");
  function updateBoxStatus() {
    $.get("fetch_tent_status.php", function (data) {
      data = typeof data === "string" ? JSON.parse(data) : data;
      data.forEach(function (item) {
        // Color boxes in the modal
        var box = $('.modal .box[data-box="' + item.id + '"]');
        if (box.length) {
          box.removeClass("green red orange blue");
          if (item.Status === "On Stock" || item.Status === "Retrieved") {
            box.addClass("green");
          } else if (item.Status === "Installed") {
            box.addClass("red");
          } else if (item.Status === "For Retrieval") {
            box.addClass("orange");
          } else if (item.Status === "Long Term") {
            box.addClass("blue");
          }
        }
      });
    });
  }
  if (dropdown) {
    dropdown.addEventListener("change", function () {
      var selectedStatus = this.value;
      var tentNumbers = $("#tentno").val().split(",").map(Number);
      tentNumbers.forEach(function (tentNumber) {
        $.post(
          "update_tent_status.php",
          { tentno: tentNumber, status: selectedStatus },
          function (resp) {
            // Optionally handle response
          }
        );
      });
      updateBoxStatus();
    });
  }
  $("#tent_no1").on("input", function () {
    clickLimit = parseInt($(this).val()) || 0;
  });
  // Update box status when modal is shown
  $("#viewEditModal").on("shown.bs.modal", function () {
    updateBoxStatus();
  });
  updateBoxStatus();
});

// Set hidden id on edit
$(function () {
  $(".viewButton").on("click", function () {
    var id = $(this).data("id");
    $("#id").val(id);
  });
});

// Location/Other change AJAX
$(function () {
  var locationSelect = document.getElementById("Location1");
  var otherInput = document.getElementById("other1");
  if (otherInput && locationSelect) {
    otherInput.addEventListener("change", updateRecord);
    locationSelect.addEventListener("change", updateRecord);
  }
  function updateRecord() {
    var id = document.getElementById("id").value;
    var otherValue = otherInput.value;
    var locationValue = locationSelect.value;
    var formData = new FormData();
    formData.append("id", id);
    formData.append("other1", otherValue);
    formData.append("Location1", locationValue);
    fetch("update_data.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.text())
      .then((result) => {
        console.log(result);
      })
      .catch((error) => {
        console.error("Error:", error);
      });
  }
});

// Status dropdown AJAX
$(function () {
  $(".status-dropdown").change(function () {
    var id = $(this).find(":selected").attr("data-id");
    var status = $(this).val();
    $.ajax({
      url: "update_status.php",
      type: "POST",
      data: { id: id, status: status },
      success: function (response) {
        console.log(response);
      },
      error: function (xhr, status, error) {
        console.error(xhr.responseText);
      },
    });
  });
});

// Delete button AJAX
$(document).on("click", ".deleteButton", function () {
  var rowId = $(this).data("id");
  if (confirm("Are you sure you want to delete this record?")) {
    $.ajax({
      url: "delete_data.php",
      type: "POST",
      data: { id: rowId },
      success: function (response) {
        window.location.href = "tracking.php";
      },
      error: function (xhr, status, error) {
        console.error(xhr.responseText);
      },
    });
  }
});

// Table search
$(function () {
  var input = document.getElementById("search-input");
  var table = document.getElementById("table_tent");
  if (!input || !table) return;
  var rows = table.getElementsByTagName("tr");
  input.addEventListener("input", function () {
    var filter = input.value.toLowerCase();
    for (var i = 1; i < rows.length; i++) {
      var cells = rows[i].getElementsByTagName("td");
      var rowVisible = false;
      for (var j = 0; j < cells.length; j++) {
        var cellText = cells[j].textContent.toLowerCase();
        if (cellText.indexOf(filter) > -1) {
          rowVisible = true;
          break;
        }
      }
      rows[i].style.display = rowVisible ? "" : "none";
    }
  });
});

// Box status for .boxs
$(function () {
  var boxesContainer = document.querySelector(".boxs");
  if (!boxesContainer) return;
  function updateBoxStatus() {
    $.get("fetch_tent_status.php", function (data) {
      data = typeof data === "string" ? JSON.parse(data) : data;
      data.forEach(function (item) {
        var box = document.getElementById("box_" + item.id);
        if (box) {
          box.className = "box";
          if (item.Status === "On Stock" || item.Status === "Retrieved") {
            box.classList.add("green");
          } else if (item.Status === "Installed") {
            box.classList.add("red");
          } else if (item.Status === "For Retrieval") {
            box.classList.add("orange");
          } else if (item.Status === "Long Term") {
            box.classList.add("blue");
          }
        }
      });
    });
  }
  for (let i = 1; i <= 100; i++) {
    const box = document.createElement("div");
    box.className = "box";
    box.id = "box_" + i;
    box.textContent = i;
    boxesContainer.appendChild(box);
  }
  updateBoxStatus();
});

// Hide .form-element-other1
$(function () {
  $(".form-element-other1").hide();
});
// Date color logic and update_status_duration AJAX
let updateInProgress = false;
let pendingUpdates = [];
let retryAttempts = new Map();
const MAX_RETRY_ATTEMPTS = 3;
const RETRY_DELAY_BASE = 1000; // 1 second base delay

// Enhanced updateDateColors function with debouncing and error handling
$(function () {
  function updateDateColors() {
    // Prevent overlapping requests
    if (updateInProgress) {
      console.log('Update already in progress, skipping...');
      return;
    }

    updateInProgress = true;
    showUpdateStatus('Checking date statuses...', 'info');

    var today = new Date();
    var redDates = [];
    var orangeDates = [];

    $(".date, .retrieval-date").each(function () {
      var dateText = $(this).text();
      var date = new Date(dateText);
      var row = $(this).closest("tr");
      var dropdown = row.find("select.status-dropdown");
      var selectedOption = dropdown.find(":selected").val();
      var id = dropdown.find(":selected").data("id");

      if (selectedOption) {
        var timeDiff = date.getTime() - today.getTime();
        var diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24));

        // Define which statuses can be auto-updated
        var autoUpdateStatuses = ["Installed", "Pending"];

        if (diffDays < 0) {
          $(this).css("color", "red");
          var tent_no = row.find("td:first").text().trim();

          if (tent_no !== "" && autoUpdateStatuses.includes(selectedOption)) {
            redDates.push({ tent_no: tent_no, id: id, row: row });
          }
        } else if (diffDays === 0) {
          $(this).css("color", "orange");
          var tent_no = row.find("td:first").text().trim();

          if (tent_no !== "" && autoUpdateStatuses.includes(selectedOption)) {
            orangeDates.push({ tent_no: tent_no, id: id, row: row });
          }
        } else {
          $(this).css("color", "");
        }
      } else {
        $(this).css("color", "");
      }
    });

    // Process updates with proper error handling
    processStatusUpdates(redDates, orangeDates);
  }

  function processStatusUpdates(redDates, orangeDates) {
    let totalUpdates = redDates.length + orangeDates.length;
    let completedUpdates = 0;

    if (totalUpdates === 0) {
      updateInProgress = false;
      hideUpdateStatus();
      return;
    }

    // Process red dates (overdue)
    if (redDates.length > 0) {
      makeAjaxRequestWithRetry({
        type: "POST",
        url: "update_status_duration.php",
        data: { redDates: JSON.stringify(redDates) },
        timeout: 10000, // 10 second timeout
        dataType: 'json'
      }, 'red')
      .then(response => {
        handleUpdateSuccess(redDates, 'Retrieved', response);
        completedUpdates++;
        checkAllUpdatesComplete(completedUpdates, totalUpdates);
      })
      .catch(error => {
        handleUpdateError(redDates, error, 'red');
        completedUpdates++;
        checkAllUpdatesComplete(completedUpdates, totalUpdates);
      });
    }

    // Process orange dates (due today)
    if (orangeDates.length > 0) {
      makeAjaxRequestWithRetry({
        type: "POST",
        url: "update_status_duration.php",
        data: { orangeDates: JSON.stringify(orangeDates) },
        timeout: 10000,
        dataType: 'json'
      }, 'orange')
      .then(response => {
        handleUpdateSuccess(orangeDates, 'For Retrieval', response);
        completedUpdates++;
        checkAllUpdatesComplete(completedUpdates, totalUpdates);
      })
      .catch(error => {
        handleUpdateError(orangeDates, error, 'orange');
        completedUpdates++;
        checkAllUpdatesComplete(completedUpdates, totalUpdates);
      });
    }
  }

  function makeAjaxRequestWithRetry(ajaxConfig, requestType, retryCount = 0) {
    return new Promise((resolve, reject) => {
      const requestKey = `${requestType}_${Date.now()}`;
      
      // Add request tracking
      console.log(`Making AJAX request (${requestType}), attempt ${retryCount + 1}`);
      
      $.ajax(ajaxConfig)
        .done(function(response) {
          console.log(`AJAX success (${requestType}):`, response);
          retryAttempts.delete(requestKey);
          resolve(response);
        })
        .fail(function(xhr, status, error) {
          console.error(`AJAX error (${requestType}):`, status, error, xhr.responseText);
          
          if (retryCount < MAX_RETRY_ATTEMPTS) {
            const delay = RETRY_DELAY_BASE * Math.pow(2, retryCount); // Exponential backoff
            
            console.log(`Retrying in ${delay}ms...`);
            showUpdateStatus(`Request failed, retrying in ${delay/1000}s...`, 'warning');
            
            setTimeout(() => {
              makeAjaxRequestWithRetry(ajaxConfig, requestType, retryCount + 1)
                .then(resolve)
                .catch(reject);
            }, delay);
          } else {
            retryAttempts.delete(requestKey);
            reject({ xhr, status, error, requestType });
          }
        });
    });
  }

  function handleUpdateSuccess(dates, newStatus, response) {
    console.log(`Successfully updated ${dates.length} items to ${newStatus}`);
    
    // Update DOM to reflect changes
    dates.forEach(dateItem => {
      if (dateItem.row) {
        const dropdown = dateItem.row.find("select.status-dropdown");
        dropdown.val(newStatus);
        
        // Add visual feedback
        dateItem.row.addClass('status-updated');
        setTimeout(() => {
          dateItem.row.removeClass('status-updated');
        }, 2000);
      }
    });

    showUpdateStatus(`Updated ${dates.length} tent(s) to ${newStatus}`, 'success');
  }

  function handleUpdateError(dates, error, requestType) {
    console.error(`Failed to update ${requestType} dates after ${MAX_RETRY_ATTEMPTS} attempts:`, error);
    
    // Add error styling to affected rows
    dates.forEach(dateItem => {
      if (dateItem.row) {
        dateItem.row.addClass('status-error');
        setTimeout(() => {
          dateItem.row.removeClass('status-error');
        }, 5000);
      }
    });

    showUpdateStatus(`Failed to update ${dates.length} tent(s). Please refresh and try again.`, 'error');
    
    // Queue for retry on next interval
    queueFailedUpdate(dates, requestType);
  }

  function checkAllUpdatesComplete(completed, total) {
    if (completed >= total) {
      updateInProgress = false;
      
      // Process any queued updates
      if (pendingUpdates.length > 0) {
        console.log(`Processing ${pendingUpdates.length} queued updates`);
        const queued = [...pendingUpdates];
        pendingUpdates = [];
        
        setTimeout(() => {
          processQueuedUpdates(queued);
        }, 1000);
      } else {
        hideUpdateStatus();
      }
    }
  }

  function queueFailedUpdate(dates, requestType) {
    pendingUpdates.push({ dates, requestType, timestamp: Date.now() });
  }

  function processQueuedUpdates(queuedUpdates) {
    queuedUpdates.forEach(update => {
      if (update.requestType === 'red') {
        processStatusUpdates(update.dates, []);
      } else if (update.requestType === 'orange') {
        processStatusUpdates([], update.dates);
      }
    });
  }

  function showUpdateStatus(message, type) {
    let statusElement = $('#update-status-indicator');
    
    if (statusElement.length === 0) {
      statusElement = $(`
        <div id="update-status-indicator" class="alert alert-info" style="
          position: fixed;
          top: 20px;
          right: 20px;
          z-index: 9999;
          min-width: 300px;
          display: none;
        ">
          <span class="status-message"></span>
          <div class="spinner-border spinner-border-sm ms-2" role="status" style="display: none;">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      `);
      $('body').append(statusElement);
    }

    statusElement.removeClass('alert-info alert-success alert-warning alert-danger');
    statusElement.addClass(`alert-${type === 'error' ? 'danger' : type}`);
    statusElement.find('.status-message').text(message);
    
    if (type === 'info') {
      statusElement.find('.spinner-border').show();
    } else {
      statusElement.find('.spinner-border').hide();
    }
    
    statusElement.fadeIn();

    // Auto-hide success and warning messages
    if (type === 'success' || type === 'warning') {
      setTimeout(() => {
        hideUpdateStatus();
      }, 3000);
    }
  }

  function hideUpdateStatus() {
    $('#update-status-indicator').fadeOut();
  }

  // Sync status dropdowns with server state
  function syncStatusDropdowns() {
    $.ajax({
      url: 'fetch_all_tent_status.php',
      type: 'GET',
      dataType: 'json',
      success: function(data) {
        data.forEach(item => {
          const dropdown = $(`.status-dropdown option[data-id="${item.id}"]`).parent();
          if (dropdown.length && dropdown.val() !== item.status) {
            dropdown.val(item.status);
            console.log(`Synced status for ID ${item.id} to ${item.status}`);
          }
        });
      },
      error: function(xhr, status, error) {
        console.error('Failed to sync status dropdowns:', error);
      }
    });
  }

  // Initialize the system
  updateDateColors();
  
  // Set up interval with better error handling
  const updateInterval = setInterval(() => {
    try {
      updateDateColors();
    } catch (error) {
      console.error('Error in updateDateColors interval:', error);
      showUpdateStatus('System error occurred. Please refresh the page.', 'error');
    }
  }, 60000);

  // Sync dropdowns every 5 minutes
  setInterval(syncStatusDropdowns, 300000);

  // Handle page visibility changes
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden && !updateInProgress) {
      console.log('Page became visible, checking for updates...');
      updateDateColors();
    }
  });

  // Cleanup on page unload
  window.addEventListener('beforeunload', function() {
    clearInterval(updateInterval);
    updateInProgress = false;
  });
});

// Multi-select green boxes up to viewEditNoOfTents value
$(document).on("click", ".modal .box", function () {
  if (!$(this).hasClass("green")) return; // Only allow green boxes
  var clickLimit = parseInt($("#viewEditNoOfTents").val()) || 1;
  var selectedBoxes = $(".modal .box.selected");
  var isSelected = $(this).hasClass("selected");
  if (!isSelected && selectedBoxes.length >= clickLimit) return; // Limit reached
  $(this).toggleClass("selected");
  // Update Tent No. field with comma-separated selected box numbers
  var selectedNumbers = $(".modal .box.selected")
    .map(function () {
      return $(this).data("box");
    })
    .get()
    .join(",");
  $("#viewEditTentNo").val(selectedNumbers);
});

// Deselect boxes if viewEditNoOfTents value is lowered below current selection
$("#viewEditNoOfTents").on("input", function () {
  var clickLimit = parseInt($(this).val()) || 1;
  var selectedBoxes = $(".modal .box.selected");
  if (selectedBoxes.length > clickLimit) {
    // Deselect boxes from the end
    selectedBoxes.slice(clickLimit).removeClass("selected");
    // Update Tent No. field
    var selectedNumbers = $(".modal .box.selected")
      .map(function () {
        return $(this).data("box");
      })
      .get()
      .join(",");
    $("#viewEditTentNo").val(selectedNumbers);
  }
});

// Reset box selection and Tent No. field when modal is closed
$("#viewEditModal").on("hidden.bs.modal", function () {
  $(".modal .box").removeClass("selected");
  $("#viewEditTentNo").val("");
});
// Add this form submission handler to your tracking.js file
$(function () {
  $("#viewEditForm").on("submit", function (e) {
    e.preventDefault();
    var formData = new FormData();
    formData.append("id", $("#id").val());
    formData.append("tent_no1", $("#viewEditNoOfTents").val());
    formData.append("datepicker1", $("#viewEditDate").val());
    formData.append("name1", $("#viewEditName").val());
    formData.append("contact1", $("#viewEditContactNo").val());
    formData.append("tentno", $("#viewEditTentNo").val());
    formData.append("Location1", $("#viewEditLocation").val());
    formData.append("purpose1", $("#viewEditPurpose").val());
    formData.append("status", $("#viewEditStatus").val());
    formData.append("duration1", $("#viewEditRetrievalDate").val());
    formData.append("address1", $("#viewEditAddress").val()); // Added address field

    $.ajax({
      url: "update_data.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        console.log("Update successful:", response);
        $("#viewEditModal").modal("hide");
        location.reload();
      },
      error: function (xhr, status, error) {
        console.error("Update failed:", error);
        console.error("XHR Response:", xhr.responseText);
        alert("Failed to update tent data. Please try again.");
      },
    });
  });
});
