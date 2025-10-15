// Function to show notification
function showNotification(message, type = "success") {
  const toast = $(`
    <div class="toast align-items-center text-white bg-${
      type == "success" ? "success" : "danger"
    } border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  `);

  $("#notification-container").append(toast);
  const bsToast = new bootstrap.Toast(toast);
  bsToast.show();

  toast.on("hidden.bs.toast", function () {
    toast.remove();
  });
}

$(document).ready(function () {
  let itemCount = 0;

  // Handle Add Multiple Items button click
  $("#addItemBtn").on("click", function () {
    // Auto-populate shared fields from the main form
    const selectedStore = $("#exampleModal .form-select[name='Store']").val();
    const selectedDate = $("#exampleModal #date").val();
    const selectedInvoice = $("#exampleModal #invoice_number").val();

    // Set the shared values in the multiple items modal
    $("#sharedStore").val(selectedStore);
    $("#sharedDate").val(selectedDate);
    $("#sharedInvoiceNumber").val(selectedInvoice);

    // Show the modal
    $("#multipleItemsModal").modal("show");
  });

  // Handle Add Item button in multiple items modal
  $("#addAnotherItemBtn").on("click", function () {
    addNewItemRow();
  });

  // Handle Save Multiple Items
  $("#saveMultipleItemsBtn").on("click", function () {
    const formData = collectFormData();

    if (formData.length === 0) {
      showNotification("Please add at least one item.", "error");
      return;
    }

    // Disable the button to prevent multiple submissions
    $("#saveMultipleItemsBtn").prop("disabled", true);
    $("#saveMultipleItemsBtn").text("Saving...");

    // Process the collected data via AJAX
    $.ajax({
      url: "insert_advance_po.php",
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        items: formData,
      }),
      success: function (response) {
        try {
          if (response.success) {
            showNotification(response.message, "success");
            $("#multipleItemsModal").modal("hide");
            resetMultipleItemsForm();
          } else {
            showNotification(
              response.message || "Failed to save items",
              "error"
            );
          }
        } catch (e) {
          showNotification("Invalid response from server", "error");
        }
      },
      error: function (xhr, status, error) {
        let errorMessage = "An error occurred while saving items";
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = xhr.responseJSON.message;
        }
        showNotification(errorMessage, "error");
        console.error("AJAX Error:", status, error);
      },
      complete: function () {
        // Re-enable the button
        $("#saveMultipleItemsBtn").prop("disabled", false);
        $("#saveMultipleItemsBtn").text("Save Items");
      },
    });
  });

  // Handle Remove Item
  $(document).on("click", ".remove-item-row", function () {
    $(this).closest(".item-row").remove();
    updateItemNumbers();
  });

  function addNewItemRow() {
    itemCount++;
    const sharedInvoice = $("#sharedInvoiceNumber").val();
    const sharedDate = $("#sharedDate").val();
    const sharedStore = $("#sharedStore").val();
    const itemRow = `
            <div class="item-row border rounded p-3 mb-3 position-relative" data-item-id="${itemCount}">
                <div class="row g-3">
                    <div class="col-12">
                        <h6 class="text-primary mb-2">Item ${itemCount}</h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input type="text" class="form-control item-date" name="item_date_${itemCount}" value="${sharedDate}" readonly>
                        <small class="text-muted">Shared date - auto-filled</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Store</label>
                        <input type="text" class="form-control item-store" name="item_store_${itemCount}" value="${sharedStore}" readonly>
                        <small class="text-muted">Shared store - auto-filled</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Invoice Number</label>
                        <input type="text" class="form-control item-invoice" name="item_invoice_${itemCount}" value="${sharedInvoice}" readonly>
                        <small class="text-muted">Shared invoice number - auto-filled</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control item-description" name="item_description_${itemCount}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pieces</label>
                        <input type="number" class="form-control item-pcs" name="item_pcs_${itemCount}" min="0" step="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unit Price</label>
                        <input type="number" class="form-control item-price" name="item_price_${itemCount}" min="0" step="0.01">
                    </div>
                </div>
                <button type="button" class="btn btn-danger btn-sm position-absolute remove-item-row" style="top: 10px; right: 10px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

    $("#multipleItemsContainer").append(itemRow);
  }

  function updateItemNumbers() {
    $("#multipleItemsContainer .item-row").each(function (index, element) {
      const newNumber = index + 1;
      $(element).find("h6").text(`Item ${newNumber}`);
      $(element).attr("data-item-id", newNumber);
    });
  }

  function collectFormData() {
    const itemsData = [];

    $("#multipleItemsContainer .item-row").each(function () {
      const itemId = $(this).data("item-id");
      const itemData = {
        store: $(this).find(".item-store").val(),
        date: $(this).find(".item-date").val(),
        invoice_number: $(this).find(".item-invoice").val(),
        description: $(this).find(".item-description").val(),
        pcs: $(this).find(".item-pcs").val(),
        unit_price: $(this).find(".item-price").val(),
      };

      // Only add if description, pcs, or unit_price has data (as required fields validation will be handled on server)
      if (itemData.description || itemData.pcs || itemData.unit_price) {
        itemsData.push(itemData);
      }
    });

    return itemsData;
  }

  function resetMultipleItemsForm() {
    $("#multipleItemsContainer").empty();
    itemCount = 0;
  }

  // Handle modal close
  $("#multipleItemsModal").on("hidden.bs.modal", function () {
    resetMultipleItemsForm();
  });

  // Handle single item save
  $("#saveSingleItemBtn").on("click", function () {
    // Get form data
    const singleItemData = {
      store: $("#addDataForm select[name='Store']").val(),
      date: $("#date").val(),
      invoice_number: $("#invoice_number").val(),
      description: $("#description").val(),
      pcs: $("#pcs").val(),
      unit_price: $("#unit_price").val(),
    };

    // Validate required fields
    if (
      !singleItemData.store ||
      !singleItemData.date ||
      !singleItemData.invoice_number ||
      !singleItemData.description ||
      !singleItemData.pcs ||
      !singleItemData.unit_price
    ) {
      showNotification("Please fill in all required fields.", "error");
      return;
    }

    // Disable the button to prevent multiple submissions
    $("#saveSingleItemBtn").prop("disabled", true);
    $("#saveSingleItemBtn").text("Saving...");

    // Send AJAX request
    $.ajax({
      url: "insert_advance_po.php",
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        single_item: true,
        item: singleItemData,
      }),
      success: function (response) {
        try {
          if (response.success) {
            showNotification(response.message, "success");
            $("#exampleModal").modal("hide");
            // Reset form
            $("#addDataForm")[0].reset();
            window.location.reload();
          } else {
            showNotification(
              response.message || "Failed to save item",
              "error"
            );
          }
        } catch (e) {
          showNotification("Invalid response from server", "error");
        }
      },
      error: function (xhr, status, error) {
        let errorMessage = "An error occurred while saving the item";
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = xhr.responseJSON.message;
        }
        showNotification(errorMessage, "error");
        console.error("AJAX Error:", status, error);
      },
      complete: function () {
        // Re-enable the button
        $("#saveSingleItemBtn").prop("disabled", false);
        $("#saveSingleItemBtn").text("Save changes");
      },
    });
  });

  // Handle multiple items modal show
  $("#multipleItemsModal").on("shown.bs.modal", function () {
    if ($("#multipleItemsContainer .item-row").length === 0) {
      addNewItemRow(); // Auto-add first item
    }
  });
  $("#date").datepicker({
    dateFormat: "yy-mm-dd", // Format: 2025-10-13
    changeMonth: true,
    changeYear: true,
    yearRange: "2000:2030",
    showAnim: "slideDown",
    beforeShow: function (input, inst) {
      // Adjust z-index for proper display over modal
      setTimeout(function () {
        inst.dpDiv.css({
          zIndex: 1055,
        });
      }, 0);
    },
  });

  // Initialize datepicker for shared date field in multiple items modal
  $("#sharedDate").datepicker({
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true,
    yearRange: "2000:2030",
    showAnim: "slideDown",
    beforeShow: function (input, inst) {
      setTimeout(function () {
        inst.dpDiv.css({
          zIndex: 1065, // Higher z-index for nested modal
        });
      }, 0);
    },
  });

  // Optional: Add date formatting and validation
  $("#date").on("change", function () {
    var selectedDate = $(this).val();
    if (selectedDate) {
      // Ensure the date is in the correct format
      var dateParts = selectedDate.split("-");
      if (dateParts.length === 3 && dateParts[0].length === 4) {
        // Valid format
        $(this).removeClass("is-invalid");
      } else {
        $(this).addClass("is-invalid");
      }
    }
  });

  // AJAX functions for edit modal
  function loadEditData(searchTerm = '') {
    let url = "fetch_edit_data.php";
    if (searchTerm) {
      url += "?search=" + encodeURIComponent(searchTerm);
    }

    $.ajax({
      url: url,
      type: "GET",
      success: function (response) {
        $("#editDataTable").html(response);
      },
      error: function () {
        $("#editDataTable").html("<tr><td colspan='10' class='text-center'>Error loading data</td></tr>");
      }
    });
  }

  // Handle edit modal show
  $("#editModal").on("show.bs.modal", function () {
    loadEditData();
    // Initialize datepicker for edit modal
    $("#edit_date").datepicker({
      dateFormat: "yy-mm-dd",
      changeMonth: true,
      changeYear: true,
      yearRange: "2000:2030",
      showAnim: "slideDown",
      beforeShow: function (input, inst) {
        setTimeout(function () {
          inst.dpDiv.css({
            zIndex: 1065,
          });
        }, 0);
      },
    });
  });

  // Handle edit button click
  $(document).on("click", ".edit-btn", function () {
    var id = $(this).data("id");
    $.ajax({
      url: "get_single_record.php",
      type: "GET",
      data: { id: id },
      success: function (response) {
        try {
          var result = JSON.parse(response);
          if (result.success) {
            var data = result.data;
            $("#edit_row_id").val(data.id);
            $("#edit_store").val(data.store);
            $("#edit_date").val(data.date);
            $("#edit_invoice_number").val(data.invoice_number);
            $("#edit_description").val(data.description);
            $("#edit_pcs").val(data.pcs);
            $("#edit_unit_price").val(data.unit_price);
          } else {
            showNotification(result.message, "error");
          }
        } catch (e) {
          showNotification("Error parsing response", "error");
        }
      },
      error: function () {
        showNotification("Error loading record", "error");
      }
    });
  });

  // Handle save edit
  $("#saveEditBtn").on("click", function () {
    var formData = $("#editRowForm").serialize();

    $("#saveEditBtn").prop("disabled", true).text("Saving...");

    $.ajax({
      url: "update_record.php",
      type: "POST",
      data: formData,
      success: function (response) {
        try {
          var result = JSON.parse(response);
          if (result.success) {
            showNotification(result.message, "success");
            $("#editRowModal").modal("hide");
            loadEditData(); // Refresh the edit table
            window.location.reload(); // Refresh main dashboard data
          } else {
            showNotification(result.message, "error");
          }
        } catch (e) {
          showNotification("Error parsing response", "error");
        }
      },
      error: function () {
        showNotification("Error updating record", "error");
      },
      complete: function () {
        $("#saveEditBtn").prop("disabled", false).text("Save Changes");
      }
    });
  });

  // Handle delete button click
  $(document).on("click", ".delete-btn", function () {
    if (!confirm("Are you sure you want to delete this record?")) {
      return;
    }

    var id = $(this).data("id");
    var button = $(this);

    button.prop("disabled", true).text("Deleting...");

    $.ajax({
      url: "delete_record.php",
      type: "POST",
      data: { id: id },
      success: function (response) {
        try {
          var result = JSON.parse(response);
          if (result.success) {
            showNotification(result.message, "success");
            loadEditData(); // Refresh the edit table
            window.location.reload(); // Refresh main dashboard data
          } else {
            showNotification(result.message, "error");
          }
        } catch (e) {
          showNotification("Error parsing response", "error");
        }
      },
      error: function () {
        showNotification("Error deleting record", "error");
      },
      complete: function () {
        button.prop("disabled", false).text("Delete");
      }
    });
  });

  // Handle refresh data button
  $("#refreshDataBtn").on("click", function () {
    loadEditData();
    showNotification("Data refreshed", "success");
  });

  // Handle search in edit modal
  let searchTimeout;
  $("#editSearchInput").on("input", function () {
    clearTimeout(searchTimeout);
    var searchTerm = $(this).val().trim();

    searchTimeout = setTimeout(function() {
      loadEditData(searchTerm);
    }, 300); // Debounce search by 300ms
  });

  // Initialize datepickers for filter inputs
  $("#start_date").datepicker({
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true,
    yearRange: "2000:2030",
    showAnim: "slideDown",
    beforeShow: function (input, inst) {
      setTimeout(function () {
        inst.dpDiv.css({
          zIndex: 1055,
        });
      }, 0);
    },
  });

  $("#end_date").datepicker({
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true,
    yearRange: "2000:2030",
    showAnim: "slideDown",
    beforeShow: function (input, inst) {
      setTimeout(function () {
        inst.dpDiv.css({
          zIndex: 1055,
        });
      }, 0);
    },
  });
});
