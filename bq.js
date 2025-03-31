$(document).ready(function () {
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
        url: "delete_record_bq.php",
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

    $.ajax({
      url: "/gso_test/update_record_bq.php",
      type: "POST",
      data: {
        id: currentId,
        sr_no: $("#sr_no").val(),
        date: $("#date1").val(),
        supplier: $("#supplierDropdown").val(),
        quantity: $("#quantity").val(),
        description: $("#description").val(),
        activity: $("#activity").val(),
        amount: $("#amount").val(),
        office: $("#office").val(),
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
      url: "get_record_bq.php",
      type: "POST",
      data: { id: id },
      dataType: "json",
      success: function (data) {
        $("#record_id").val(data.id);
        $("#sr_no").val(data.SR_DR);
        $("#date1").val(data.date);
        $("#quantity").val(data.quantity);
        $("#description").val(data.description);
        $("#activity").val(data.activity);
        $("#amount").val(data.amount);
        $("#office").val(data.requestor);
        $("#supplierDropdown").val(data.supplier);
        $("#remarks").val(data.remarks);
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
      url: "filter_records_bq.php",
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
    var po_number = $("#po_number").val();
    var payment_amount = $("#payment_amount").val();

    $.ajax({
      url: "add_payment_bq.php",
      type: "POST",
      data: {
        po_number: po_number,
        payment_amount: payment_amount,
      },
      success: function (response) {
        var result = JSON.parse(response);
        if (result.status === "success") {
          $("#paymentModal").modal("hide");
          location.reload();
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
      rowIds.push(id);
    });

    console.log("Rows to transfer:", rowIds); // Log IDs being sent

    $.ajax({
      url: "transfer_to_print_bq.php",
      type: "POST",
      data: {
        ids: rowIds,
      },
      success: function (response) {
        console.log("Server response:", response); // Log full server response
        try {
          let result = JSON.parse(response);
          if (result.success) {
            alert("Records transferred to print successfully!");
          } else {
            alert("Error transferring records");
          }
        } catch (e) {
          console.error("JSON parsing error:", e);
          console.log("Raw response:", response);
        }
      },
      error: function (xhr, status, error) {
        console.error("Ajax error:", {
          status: status,
          error: error,
          response: xhr.responseText,
        });
        alert("Error processing request");
      },
    });
  });
});
//review pdf modal
$(document).ready(function () {
  $("#review_pdf").click(function () {
    // Fetch data for the modal table
    $.ajax({
      url: "get_print_data_bq.php",
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
        url: "update_po_payment_bq.php",
        type: "POST",
        data: {
          ids: selectedIds,
          po_no: selectedPO,
          po_amount: selectedAmount,
        },
        success: function (response) {
          let result = JSON.parse(response);
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
            alert("Failed to update payment details");
          }
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
        url: "delete_print_bq.php",
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
      url: "delete_all_print_BQ.php",
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
  $("#confirmPdf").click(function () {
    window.open("generate_bq_pdf.php", "_blank");
  });
});
