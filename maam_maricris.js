$(document).ready(function () {
  add_payment;
  $(".datepicker").datepicker({
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true,
    showAnim: "slideDown",
  });
});

// JavaScript
$(document).ready(function () {
  // Use event delegation for delete buttons
  $(document).on("click", ".delete-btn", function () {
    var id = $(this).data("id");
    console.log("Attempting to delete record with ID:", id);

    if (confirm("Are you sure you want to delete this record?")) {
      $.ajax({
        url: "delete_record_maricris.php",
        type: "POST",
        data: { id: id },
        dataType: "json",
        success: function (response) {
          console.log("Server response:", response);
          if (response.status === "success") {
            // Trigger the filter again instead of page reload
            $("#filter_button").click();
          } else {
            alert("Error deleting record: " + response.message);
          }
        },
        error: function (xhr, status, error) {
          console.log("Full error details:", { xhr, status, error });
          alert("AJAX Error: " + error);
        },
      });
    }
  });
});

$(document).ready(function () {
  // Update button click handler
  $('.btn-warning[name="save_data2"]').on("click", function (e) {
    e.preventDefault();

    // Get the ID directly from the hidden input value
    var currentId = $("#record_id").val();

    // Get the current PO value - if dropdown is empty, use a hidden field value
    var poValue = $("#paymentDropdown").val() || $("#current_po").val();

    $.ajax({
      url: "update_record_maricris.php",
      type: "POST",
      data: {
        id: currentId,
        sr_no: $("#sr_no").val(),
        date: $("#date1").val(),
        activity: $("#activity").val(),
        quantity: $("#quantity").val(),
        amount: $("#amount").val(),
        office: $("#office").val(),
        supplier: $("#supplierDropdown").val(),
        payment: poValue, // Use the determined PO value
        remarks: $("#remarks").val(),
      },
      success: function (response) {
        console.log("Update response:", response);
        location.reload();
      },
      error: function (xhr, status, error) {
        console.log("Update error:", error);
        console.log("Server response:", xhr.responseText);
      },
    });
  });

  // Edit button click handler
  $(".edit-btn").click(function () {
    var id = $(this).data("id");

    $.ajax({
      url: "get_record_maricris.php",
      type: "POST",
      data: { id: id },
      dataType: "json",
      success: function (data) {
        $("#record_id").val(data.id);
        $("#sr_no").val(data.SR_DR);
        $("#date1").val(data.date);
        $("#activity").val(data.activity);
        $("#quantity").val(data.no_of_pax);
        $("#amount").val(data.amount);
        $("#office").val(data.department);
        $("#supplierDropdown").val(data.store);

        // Store the current PO in a hidden field in case it's not in the dropdown
        $("#current_po").val(data.PO_no);

        // Try to select the PO in the dropdown
        if (
          $("#paymentDropdown option[value='" + data.PO_no + "']").length > 0
        ) {
          $("#paymentDropdown").val(data.PO_no);
        } else {
          // If PO is not in dropdown (e.g., depleted), add it temporarily
          $("#paymentDropdown").append(
            $("<option>", {
              value: data.PO_no,
              text:
                "PO_no: " +
                data.PO_no +
                " - ₱" +
                parseFloat(data.PO_amount).toFixed(2),
              class: "temp-option",
            })
          );
          $("#paymentDropdown").val(data.PO_no);
        }

        $("#remarks").val(data.Remarks);
      },
    });
  });
});

//FILTER
$(document).ready(function () {
  $("#filter_button").click(function () {
    var startDate = $("#start_date").val();
    var endDate = $("#end_date").val();
    var supplier = $("#supplier_filter").val();

    $.ajax({
      url: "filter_records_maam_maricris.php",
      type: "POST",
      data: {
        start_date: startDate,
        end_date: endDate,
        supplier: supplier,
      },
      dataType: "json",
      success: function (response) {
        $("tbody").html(response.html);
        $("#total-amount").html(
          "Total Amount: ₱" +
            Number(response.total).toLocaleString("en-US", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            })
        );
      },
    });
  });
});

$(document).ready(function () {
  // Show payment modal
  $("#add_payment").click(function () {
    $("#paymentModal").modal("show");
  });

  // Handle payment form submission
  $("#save_payment").click(function () {
    var payment_name = $("#payment_name").val();
    var payment_amount = $("#payment_amount").val();

    $.ajax({
      url: "add_payment_maricris.php",
      type: "POST",
      data: {
        payment_name: payment_name,
        payment_amount: payment_amount,
      },
      success: function (response) {
        try {
          var result = JSON.parse(response);
          if (result.status === "success") {
            $("#paymentModal").modal("hide");
            location.reload();
          }
        } catch (e) {
          console.error("Error parsing response:", e);
        }
      },
    });
  });
});
//search field
document.getElementById("search").addEventListener("keyup", function () {
  let searchValue = this.value.toLowerCase();
  let tableRows = document.querySelectorAll("table tbody tr");

  tableRows.forEach((row) => {
    let text = row.textContent.toLowerCase();
    if (text.includes(searchValue)) {
      row.style.display = "";
    } else {
      row.style.display = "none";
    }
  });
});
//add to print
$(document).ready(function () {
  $("#addtoprint").click(function () {
    let visibleRows = $("table tbody tr:visible");
    let rowIds = [];

    visibleRows.each(function () {
      let id = $(this).find(".edit-btn").data("id");
      if (id) {
        // Only add if ID exists
        rowIds.push(id);
      }
    });

    // Check if we have any IDs to process
    if (rowIds.length === 0) {
      alert("No records selected to transfer!");
      return;
    }

    console.log("Rows to transfer:", rowIds); // Log IDs being sent

    $.ajax({
      url: "transfer_to_print_mariecris.php",
      type: "POST",
      data: {
        ids: rowIds,
      },
      dataType: "json", // Specify expected response type
      success: function (result) {
        console.log("Server response:", result); // Log parsed response

        if (result.success) {
          alert("Records transferred to print successfully!");
        } else {
          alert(
            "Error transferring records: " + (result.message || "Unknown error")
          );
        }
      },
      error: function (xhr, status, error) {
        console.error("Ajax error:", {
          status: status,
          error: error,
          response: xhr.responseText,
        });
        alert("Error processing request. See console for details.");
      },
    });
  });
});

//review pdf modal
$(document).ready(function () {
  $("#review_pdf").click(function () {
    // Fetch data for the modal table
    $.ajax({
      url: "get_print_data_maricris.php",
      type: "GET",
      success: function (data) {
        $("#pdfPreviewContent").html(data);
        $("#reviewPdfModal").modal("show");
      },
    });
  });
});
//mark as paid
$(document).ready(function () {
  $("#markAsPaid").click(function () {
    let selectedPO = $("#modalFilter").val();
    let selectedAmount = $("#modalFilter option:selected")
      .text()
      .split("₱")[1]
      .trim();
    let checkedRows = $(".select-item:checked");
    let selectedIds = [];

    console.log("Selected PO Number:", selectedPO);
    console.log("Selected PO Amount:", selectedAmount);
    console.log("Number of checked boxes:", checkedRows.length);

    checkedRows.each(function () {
      let id = $(this).data("id");
      selectedIds.push(id);
      console.log("Checkbox checked - ID:", id);
    });

    console.log("All selected IDs:", selectedIds);

    if (selectedIds.length > 0) {
      $.ajax({
        url: "update_po_payment_maricris.php",
        type: "POST",
        data: {
          ids: selectedIds,
          po_no: selectedPO,
          po_amount: selectedAmount,
        },
        dataType: "json", // Specify that the response is JSON
        success: function (result) {
          // No need to parse, jQuery does it automatically when dataType is "json"
          if (result.success) {
            alert("Payment details updated successfully!");
            // Update the table rows with new PO data
            checkedRows.each(function () {
              let row = $(this).closest("tr");
              row.find("td:nth-child(9)").text(selectedPO);
              row.find("td:nth-child(10)").text("₱" + selectedAmount);
              $(this).prop("checked", false);
            });
          } else {
            alert(
              "Failed to update payment details: " +
                (result.message || "Unknown error")
            );
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error:", status, error);
          console.log("Response:", xhr.responseText);
          alert(
            "An error occurred while updating payment details. See console for details."
          );
        },
      });
    } else {
      alert("Please select items to mark as paid");
    }
  });
});

//delete print bayong individual
$(document).ready(function () {
  $(document).on("click", ".delete-print-btn", function () {
    let button = $(this);
    let id = button.data("id");

    if (confirm("Are you sure you want to delete this record?")) {
      $.ajax({
        url: "delete_print_mariecris.php",
        type: "POST",
        data: { id: id },
        success: function (response) {
          let result = JSON.parse(response);
          if (result.success) {
            button.closest("tr").remove();
          }
        },
      });
    }
  });
});

//delete all print bayong
$("#deleteAllPrint").click(function () {
  if (confirm("Are you sure you want to delete all print records?")) {
    $.ajax({
      url: "delete_all_print_maam_maricris.php",
      type: "POST",
      success: function (response) {
        let result = JSON.parse(response);
        if (result.success) {
          $("#pdfPreviewContent").empty();
        }
      },
    });
  }
});
//generate pdf
$(document).ready(function () {
  // PO Status Modal functionality
  $("#check_po").click(function () {
    loadPOStatusData();
    $("#poStatusModal").modal("show");
  });

  $("#refreshPOStatus").click(function () {
    loadPOStatusData();
  });

  $("#po_search").on("keyup", function () {
    const searchTerm = $(this).val().toLowerCase();
    $("#poStatusContent tr").filter(function () {
      $(this).toggle($(this).text().toLowerCase().indexOf(searchTerm) > -1);
    });
  });

  // Add event delegation for delete payment buttons
  $(document).on("click", ".delete-payment-btn", function () {
    const poNumber = $(this).data("po");

    if (
      confirm(
        `Are you sure you want to delete payment with PO Number: ${poNumber}?`
      )
    ) {
      $.ajax({
        url: "delete_payment_mariecris.php",
        type: "POST",
        data: { po_number: poNumber },
        dataType: "json",
        success: function (response) {
          if (response.success) {
            alert("Payment deleted successfully!");
            loadPOStatusData(); // Reload the table
          } else {
            alert("Error: " + response.message);
          }
        },
        error: function (xhr, status, error) {
          alert("Error deleting payment.");
        },
      });
    }
  });

  function loadPOStatusData() {
    $.ajax({
      url: "get_po_status_maricris.php",
      type: "GET",
      dataType: "json",
      success: function (data) {
        let tableContent = "";
        let statusUpdatePromises = [];

        if (data.length > 0) {
          data.forEach(function (po) {
            // Calculate status based on remaining balance and original amount
            let status;
            let statusClass;

            // Convert to numbers to ensure proper comparison
            const originalAmount = parseFloat(po.original_amount);
            const remainingBalance = parseFloat(po.remaining_balance);

            // Determine status based on remaining balance
            if (remainingBalance <= 0) {
              status = "Depleted";
              statusClass = "text-danger fw-bold";
            } else if (remainingBalance < originalAmount * 0.2) {
              // Less than 20% of original amount remaining
              status = "Low Balance";
              statusClass = "text-warning fw-bold";
            } else {
              status = "Available";
              statusClass = "text-success fw-bold";
            }

            // Update status in the database
            const updatePromise = $.ajax({
              url: "update_po_status_maricris.php",
              type: "POST",
              data: {
                po_number: po.po_number,
                status: status,
              },
              dataType: "json",
            });

            statusUpdatePromises.push(updatePromise);

            tableContent += `
              <tr>
                <td>${po.po_number}</td>
                <td>₱${parseFloat(po.original_amount).toLocaleString("en-US", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}</td>
                <td>₱${parseFloat(po.used_amount).toLocaleString("en-US", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}</td>
                <td>₱${parseFloat(po.remaining_balance).toLocaleString(
                  "en-US",
                  {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  }
                )}</td>
                <td class="${statusClass}">${status}</td>
                <td>
                  <button class="btn btn-danger btn-sm delete-payment-btn" data-po="${
                    po.po_number
                  }">
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
            `;
          });
        } else {
          tableContent =
            '<tr><td colspan="6" class="text-center">No PO data available</td></tr>';
        }

        $("#poStatusContent").html(tableContent);

        // Log any errors with status updates
        $.when
          .apply($, statusUpdatePromises)
          .done(function () {
            console.log("All status updates completed");
          })
          .fail(function (error) {
            console.error("Error updating status:", error);
          });
      },
      error: function (xhr, status, error) {
        console.error("Error fetching PO status data:", error);
        $("#poStatusContent").html(
          '<tr><td colspan="6" class="text-center text-danger">Error loading data. Please try again.</td></tr>'
        );
      },
    });
  }
});

//search po status
$(document).ready(function () {
  // Add event listener for the existing search input
  $("#po_search").on("keyup", function () {
    const searchTerm = $(this).val().toLowerCase();

    // Filter the table rows based on the search term
    $("#poStatusContent tr").each(function () {
      const rowText = $(this).text().toLowerCase();
      const isMatch = rowText.indexOf(searchTerm) > -1;
      $(this).toggle(isMatch);
    });

    // Show a message if no results are found
    if ($("#poStatusContent tr:visible").length === 0) {
      if ($("#noResultsRow").length === 0) {
        $("#poStatusContent").append(`
                  <tr id="noResultsRow">
                      <td colspan="5" class="text-center">No matching PO records found</td>
                  </tr>
              `);
      }
    } else {
      $("#noResultsRow").remove();
    }
  });
});
