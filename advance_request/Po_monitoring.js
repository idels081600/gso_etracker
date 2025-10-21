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
  // Handle single item save
  $("#saveSingleItemBtn").on("click", function () {
    // Get form data
    const singleItemData = {
      supplier: $("#addDataForm select[name='supplier']").val(),
      po_date: $("#po_date").val(),
      po_number: $("#po_number").val(),
      office: $("#office").val(),
      description: $("#description").val(),
      amount: $("#amount").val(),
      destination: $("#destination").val(),
    };

    // Disable the button to prevent multiple submissions
    $("#saveSingleItemBtn").prop("disabled", true);
    $("#saveSingleItemBtn").text("Saving...");

    // Add checklist data to the save request
    singleItemData.preAuditchecklist_cb = $("#preAuditchecklist_cb").is(":checked") ? 1 : 0;
    singleItemData.preAuditchecklist_remarks = $("#preAuditchecklist_remarks").val();
    singleItemData.obr_cb = $("#obr_cb").is(":checked") ? 1 : 0;
    singleItemData.obr_remarks = $("#obr_remarks").val();
    singleItemData.dv_cb = $("#dv_cb").is(":checked") ? 1 : 0;
    singleItemData.dv_remarks = $("#dv_remarks").val();
    singleItemData.billing_request_cb = $("#billing_request_cb").is(":checked") ? 1 : 0;
    singleItemData.billing_request_remarks = $("#billing_request_remarks").val();
    singleItemData.certWarranty_cb = $("#certWarranty_cb").is(":checked") ? 1 : 0;
    singleItemData.certWarranty_remarks = $("#certWarranty_remarks").val();
    singleItemData.omnibus_cb = $("#omnibus_cb").is(":checked") ? 1 : 0;
    singleItemData.omnibus_remarks = $("#omnibus_remarks").val();
    singleItemData.ris_cb = $("#ris_cb").is(":checked") ? 1 : 0;
    singleItemData.ris_remarks = $("#ris_remarks").val();
    singleItemData.acceptance_cb = $("#acceptance_cb").is(":checked") ? 1 : 0;
    singleItemData.acceptance_remarks = $("#acceptance_remarks").val();
    singleItemData.rfq_cb = $("#rfq_cb").is(":checked") ? 1 : 0;
    singleItemData.rfq_remarks = $("#rfq_remarks").val();
    singleItemData.recommending_cb = $("#recommending_cb").is(":checked") ? 1 : 0;
    singleItemData.recommending_remarks = $("#recommending_remarks").val();
    singleItemData.PR_cb = $("#PR_cb").is(":checked") ? 1 : 0;
    singleItemData.PR_remarks = $("#PR_remarks").val();
    singleItemData.PO_cb = $("#PO_cb").is(":checked") ? 1 : 0;
    singleItemData.PO_remarks = $("#PO_remarks").val();
    singleItemData.receipts_cb = $("#receipts_cb").is(":checked") ? 1 : 0;
    singleItemData.receipts_remarks = $("#receipts_remarks").val();
    singleItemData.delegation_cb = $("#delegation_cb").is(":checked") ? 1 : 0;
    singleItemData.delegation_remarks = $("#delegation_remarks").val();
    singleItemData.mayorsPermit_cb = $("#mayorsPermit_cb").is(":checked") ? 1 : 0;
    singleItemData.mayorsPermit_remarks = $("#mayorsPermit_remarks").val();
    singleItemData.justification_cb = $("#justification_cb").is(":checked") ? 1 : 0;
    singleItemData.justification_remarks = $("#justification_remarks").val();
    singleItemData.waste_material_report_cb = $("#waste_material_report_cb").is(":checked") ? 1 : 0;
    singleItemData.waste_material_report_remarks = $("#waste_material_report_remarks").val();
    singleItemData.pre_repair_inspection_cb = $("#pre_repair_inspection_cb").is(":checked") ? 1 : 0;
    singleItemData.pre_repair_inspection_remarks = $("#pre_repair_inspection_remarks").val();
    singleItemData.post_repair_inspection_cb = $("#post_repair_inspection_cb").is(":checked") ? 1 : 0;
    singleItemData.post_repair_inspection_remarks = $("#post_repair_inspection_remarks").val();
    singleItemData.repair_history_of_property_cb = $("#repair_history_of_property_cb").is(":checked") ? 1 : 0;
    singleItemData.repair_history_of_property_remarks = $("#repair_history_of_property_remarks").val();
    singleItemData.warranty_certificate_cb = $("#warranty_certificate_cb").is(":checked") ? 1 : 0;
    singleItemData.warranty_certificate_remarks = $("#warranty_certificate_remarks").val();
    singleItemData.jetsCert_cb = $("#jetsCert_cb").is(":checked") ? 1 : 0;
    singleItemData.jetsCert_remarks = $("#jetsCert_remarks").val();
    // Send AJAX request
    $.ajax({
      url: "insert_po.php",
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
            // Reset checklists and remarks
            $("#preAuditchecklist_cb, #obr_cb, #dv_cb, #billing_request_cb, #certWarranty_cb, #omnibus_cb, #ris_cb, #acceptance_cb, #rfq_cb, #recommending_cb, #PR_cb, #PO_cb, #receipts_cb, #delegation_cb, #mayorsPermit_cb, #justification_cb, #waste_material_report_cb, #pre_repair_inspection_cb, #post_repair_inspection_cb, #repair_history_of_property_cb, #warranty_certificate_cb, #jetsCert_cb").prop("checked", false);
            $("#preAuditchecklist_remarks_section, #obr_remarks_section, #dv_remarks_section, #billing_request_remarks_section, #certWarranty_remarks_section, #omnibus_remarks_section, #ris_remarks_section, #acceptance_remarks_section, #rfq_remarks_section, #recommending_remarks_section, #PR_remarks_section, #PO_remarks_section, #receipts_remarks_section, #delegation_remarks_section, #mayorsPermit_remarks_section, #justification_remarks_section, #waste_material_report_remarks_section, #pre_repair_inspection_remarks_section, #post_repair_inspection_remarks_section, #repair_history_of_property_remarks_section, #warranty_certificate_remarks_section, #jetsCert_remarks_section").hide();
            $("#preAuditchecklist_remarks, #obr_remarks, #dv_remarks, #billing_request_remarks, #certWarranty_remarks, #omnibus_remarks, #ris_remarks, #acceptance_remarks, #rfq_remarks, #recommending_remarks, #PR_remarks, #PO_remarks, #receipts_remarks, #delegation_remarks, #mayorsPermit_remarks, #justification_remarks, #waste_material_report_remarks, #pre_repair_inspection_remarks, #post_repair_inspection_remarks, #repair_history_of_property_remarks, #warranty_certificate_remarks, #jetsCert_remarks").val("");
            window.location.reload();
          } else {
            console.log("Response error:", response);
            // Show debug info if available (for debugging column count issues)
            if (response.debug_info) {
              console.log("DEBUG INFO:", response.debug_info);
              showNotification(
                "Database Error - Check console for details. " +
                  (response.message || "Failed to save item"),
                "error"
              );
            } else {
              showNotification(
                response.message || "Failed to save item",
                "error"
              );
            }
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

  // Handle "Add Multiple Items" button
  $("#addItemBtn").on("click", function () {
    showNotification("Feature not available", "error");
  });

  // Optional: Add date formatting and validation for po_date
  $("#po_date").on("change", function () {
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
  function loadEditData(searchTerm = "") {
    let url = "fetch_edit_data_PO_monitoring.php";
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
        $("#editDataTable").html(
          "<tr><td colspan='10' class='text-center'>Error loading data</td></tr>"
        );
      },
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
      url: "get_single_record_PO_monitoring.php",
      type: "GET",
      data: { id: id },
      success: function (response) {
        try {
          var result = JSON.parse(response);
          if (result.success) {
            var data = result.data;
            $("#edit_row_id").val(data.id);
            $("#edit_supplier").val(data.supplier);
            $("#edit_po_date").val(data.po_date);
            $("#edit_po_number").val(data.po_number);
            $("#edit_office").val(data.office);
            $("#edit_description").val(data.description);
            $("#edit_price").val(data.price);
            $("#edit_destination").val(data.destination);
            $("#edit_status").val(data.status);

            // Set checkboxes
            $("#edit_pre_audit_checklist_cb").prop(
              "checked",
              data.preAuditchecklist_cb == "1"
            );
            $("#edit_obr_cb").prop("checked", data.obr_cb == "1");
            $("#edit_dv_cb").prop("checked", data.dv_cb == "1");
            $("#edit_billing_request_cb").prop(
              "checked",
              data.billing_request_cb == "1"
            );
            $("#edit_cert_warranty_cb").prop(
              "checked",
              data.certWarranty_cb == "1"
            );
            $("#edit_omnibus_cb").prop("checked", data.omnibus_cb == "1");
            $("#edit_ris_cb").prop("checked", data.ris_cb == "1");
            $("#edit_acceptance_cb").prop("checked", data.acceptance_cb == "1");
            $("#edit_rfq_cb").prop("checked", data.rfq_cb == "1");
            $("#edit_recommending_cb").prop(
              "checked",
              data.recommending_cb == "1"
            );
            $("#edit_PR_cb").prop("checked", data.PR_cb == "1");
            $("#edit_PO_cb").prop("checked", data.PO_cb == "1");
            $("#edit_RECEIPTS_cb").prop("checked", data.RECEIPTS_cb == "1");
            $("#edit_DELEGATION_cb").prop("checked", data.delegation_cb == "1");
            $("#edit_MAYORS_PERMIT_cb").prop(
              "checked",
              data.mayorsPermit_cb == "1"
            );
            $("#edit_JETS_CERTIFICATION_cb").prop(
              "checked",
              data.jetsCert_cb == "1"
            );

            // Show remarks sections if remarks exist
            if (
              data.preAuditchecklist_remarks &&
              data.preAuditchecklist_remarks.trim() !== ""
            ) {
              $("#edit_pre_audit_checklist_remarks_section").show();
              $("#pre_audit_checklist_remarks").val(
                data.preAuditchecklist_remarks
              );
            }
            if (data.obr_remarks && data.obr_remarks.trim() !== "") {
              $("#obr_remarks_section").show();
              $("#obr_remarks").val(data.obr_remarks);
            }
            if (data.dv_remarks && data.dv_remarks.trim() !== "") {
              $("#dv_remarks_section").show();
              $("#dv_remarks").val(data.dv_remarks);
            }
            if (
              data.billing_request_remarks &&
              data.billing_request_remarks.trim() !== ""
            ) {
              $("#billing_request_remarks_section").show();
              $("#billing_request_remarks").val(data.billing_request_remarks);
            }
            if (
              data.certWarranty_remarks &&
              data.certWarranty_remarks.trim() !== ""
            ) {
              $("#cert_warranty_remarks_section").show();
              $("#cert_warranty_remarks").val(data.certWarranty_remarks);
            }
            if (data.omnibus_remarks && data.omnibus_remarks.trim() !== "") {
              $("#omnibus_remarks_section").show();
              $("#omnibus_remarks").val(data.omnibus_remarks);
            }
            if (data.ris_remarks && data.ris_remarks.trim() !== "") {
              $("#ris_remarks_section").show();
              $("#ris_remarks").val(data.ris_remarks);
            }
            if (
              data.acceptance_remarks &&
              data.acceptance_remarks.trim() !== ""
            ) {
              $("#acceptance_remarks_section").show();
              $("#acceptance_remarks").val(data.acceptance_remarks);
            }
            if (data.rfq_remarks && data.rfq_remarks.trim() !== "") {
              $("#rfq_remarks_section").show();
              $("#rfq_remarks").val(data.rfq_remarks);
            }
            if (
              data.recommendating_remarks &&
              data.recommendating_remarks.trim() !== ""
            ) {
              $("#recommending_remarks_section").show();
              $("#recommending_remarks").val(data.recommendating_remarks);
            }
            if (data.PR_remarks && data.PR_remarks.trim() !== "") {
              $("#PR_remarks_section").show();
              $("#PR_remarks").val(data.PR_remarks);
            }
            if (data.PO_remarks && data.PO_remarks.trim() !== "") {
              $("#PO_remarks_section").show();
              $("#PO_remarks").val(data.PO_remarks);
            }
            if (data.RECEIPTS_remarks && data.RECEIPTS_remarks.trim() !== "") {
              $("#RECEIPTS_remarks_section").show();
              $("#RECEIPTS_remarks").val(data.RECEIPTS_remarks);
            }
            if (
              data.DELEGATION_remarks &&
              data.DELEGATION_remarks.trim() !== ""
            ) {
              $("#DELEGATION_remarks_section").show();
              $("#DELEGATION_remarks").val(data.DELEGATION_remarks);
            }
            if (
              data.MAYORS_PERMIT_remarks &&
              data.MAYORS_PERMIT_remarks.trim() !== ""
            ) {
              $("#MAYORS_PERMIT_remarks_section").show();
              $("#MAYORS_PERMIT_remarks").val(data.MAYORS_PERMIT_remarks);
            }
            if (
              data.JETS_CERTIFICATION_remarks &&
              data.JETS_CERTIFICATION_remarks.trim() !== ""
            ) {
              $("#JETS_CERTIFICATION_remarks_section").show();
              $("#JETS_CERTIFICATION_remarks").val(
                data.JETS_CERTIFICATION_remarks
              );
            }
          } else {
            showNotification(result.message, "error");
          }
        } catch (e) {
          showNotification("Error parsing response", "error");
        }
      },
      error: function () {
        showNotification("Error loading record", "error");
      },
    });
  });
  // Handle save edit// Handle save edit
  $("#saveEditBtn").on("click", function () {
    const btn = $(this);
    const formData = $("#editRowForm").serialize();

    btn.prop("disabled", true).text("Saving...");

    $.post("update_record_Po_monitoring.php", formData, function (res) {
      console.log("Response:", res);

      if (res.success) {
        showNotification(res.message, "success");
        $("#editRowModal").modal("hide");
        loadEditData();
        location.reload();
      } else {
        showNotification(res.message || "Error updating record", "error");
      }
    })
      .fail((xhr) => {
        console.error("AJAX Error:", xhr.responseText);
        showNotification("Server error. Check console for details.", "error");
      })
      .always(() => {
        btn.prop("disabled", false).text("Save Changes");
      });
  });

  // Handle view button click
  $(document).on("click", ".view-btn", function () {
    var id = $(this).data("id");

    // Clear all checkboxes first
    $("#checklistContainer input[type='checkbox']").prop("checked", false);

    $.ajax({
      url: "get_single_record_PO_monitoring.php", // Use PO monitoring specific endpoint
      type: "GET",
      data: { id: id },
      success: function (response) {
        try {
          var result = JSON.parse(response);
          if (result.success) {
            var data = result.data;
            // Set checkboxes based on data
            $("#view_preAuditchecklist_cb").prop(
              "checked",
              data.preAuditchecklist_cb == "1"
            );
            $("#view_obr_cb").prop("checked", data.obr_cb == "1");
            $("#view_dv_cb").prop("checked", data.dv_cb == "1");
            $("#view_billing_request_cb").prop(
              "checked",
              data.billing_request_cb == "1"
            );
            $("#view_certWarranty_cb").prop(
              "checked",
              data.certWarranty_cb == "1"
            );
            $("#view_omnibus_cb").prop("checked", data.omnibus_cb == "1");
            $("#view_ris_cb").prop("checked", data.ris_cb == "1");
            $("#view_acceptance_cb").prop("checked", data.acceptance_cb == "1");
            $("#view_rfq_cb").prop("checked", data.rfq_cb == "1");
            $("#view_recommending_cb").prop(
              "checked",
              data.recommending_cb == "1"
            );
            $("#view_PR_cb").prop("checked", data.PR_cb == "1");
            $("#view_PO_cb").prop("checked", data.PO_cb == "1");
            $("#view_receipts_cb").prop("checked", data.receipts_cb == "1");
            $("#view_delegation_cb").prop("checked", data.delegation_cb == "1");
            $("#view_mayorsPermit_cb").prop(
              "checked",
              data.mayorsPermit_cb == "1"
            );
            $("#view_jetsCert_cb").prop("checked", data.jetsCert_cb == "1");
            $("#view_waste_material_report_cb").prop("checked", data.waste_material_report_cb == "1");
            $("#view_post_repair_inspection_cb").prop("checked", data.post_repair_inspection_cb == "1");
            $("#view_repair_history_of_property_cb").prop("checked", data.repair_history_of_property_cb == "1");
            $("#view_warranty_certificate_cb").prop("checked", data.warranty_certificate_cb == "1");

            // Clear previous remarks
            $("#checklistContainer .remarks-text").remove();

            // Show remarks if they exist
            if (
              data.preAuditchecklist_remarks &&
              data.preAuditchecklist_remarks.trim()
            ) {
              $("#view_preAuditchecklist_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.preAuditchecklist_remarks +
                  "</small>"
              );
            }
            if (data.obr_remarks && data.obr_remarks.trim()) {
              $("#view_obr_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.obr_remarks +
                  "</small>"
              );
            }
            if (data.dv_remarks && data.dv_remarks.trim()) {
              $("#view_dv_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.dv_remarks +
                  "</small>"
              );
            }
            if (
              data.billing_request_remarks &&
              data.billing_request_remarks.trim()
            ) {
              $("#view_billing_request_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.billing_request_remarks +
                  "</small>"
              );
            }
            if (data.certWarranty_remarks && data.certWarranty_remarks.trim()) {
              $("#view_certWarranty_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.certWarranty_remarks +
                  "</small>"
              );
            }
            if (data.omnibus_remarks && data.omnibus_remarks.trim()) {
              $("#view_omnibus_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.omnibus_remarks +
                  "</small>"
              );
            }
            if (data.ris_remarks && data.ris_remarks.trim()) {
              $("#view_ris_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.ris_remarks +
                  "</small>"
              );
            }
            if (data.acceptance_remarks && data.acceptance_remarks.trim()) {
              $("#view_acceptance_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.acceptance_remarks +
                  "</small>"
              );
            }
            if (data.rfq_remarks && data.rfq_remarks.trim()) {
              $("#view_rfq_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.rfq_remarks +
                  "</small>"
              );
            }
            if (data.recommending_remarks && data.recommending_remarks.trim()) {
              $("#view_recommending_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.recommending_remarks +
                  "</small>"
              );
            }
            if (data.PR_remarks && data.PR_remarks.trim()) {
              $("#view_PR_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.PR_remarks +
                  "</small>"
              );
            }
            if (data.PO_remarks && data.PO_remarks.trim()) {
              $("#view_PO_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.PO_remarks +
                  "</small>"
              );
            }
            if (data.receipts_remarks && data.receipts_remarks.trim()) {
              $("#view_receipts_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.receipts_remarks +
                  "</small>"
              );
            }
            if (data.delegation_remarks && data.delegation_remarks.trim()) {
              $("#view_delegation_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.delegation_remarks +
                  "</small>"
              );
            }
            if (data.mayorsPermit_remarks && data.mayorsPermit_remarks.trim()) {
              $("#view_mayorsPermit_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.mayorsPermit_remarks +
                  "</small>"
              );
            }
            if (data.jetsCert_remarks && data.jetsCert_remarks.trim()) {
              $("#view_jetsCert_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.jetsCert_remarks +
                  "</small>"
              );
            }
            if (data.wasteMaterialReport_remarks && data.wasteMaterialReport_remarks.trim()) {
              $("#view_wasteMaterialReport_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.waste_material_report_remarks +
                  "</small>"
              );
            }
            if (data.postRepairInspection_remarks && data.postRepairInspection_remarks.trim()) {
              $("#view_postRepairInspection_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.post_repair_inspection_remarks +
                  "</small>"
              );
            }
            if (data.repairHistoryOfProperty_remarks && data.repairHistoryOfProperty_remarks.trim()) {
              $("#view_repairHistoryOfProperty_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.repair_history_of_property_remarks +
                  "</small>"
              );
            }
            if (data.warrantyCertificate_remarks && data.warrantyCertificate_remarks.trim()) {
              $("#view_warrantyCertificate_cb_label").after(
                '<br><small class="text-muted remarks-text">' +
                  data.warranty_certificate_remarks +
                  "</small>"
              );
            }
          } else {
            showNotification(result.message, "error");
          }
        } catch (e) {
          showNotification("Error parsing response", "error");
        }
      },
      error: function () {
        showNotification("Error loading record", "error");
      },
    });
  });

  // Handle delete button click
  $(document).on("click", ".delete-btn", function () {
    var id = $(this).data("id");
    console.log("Delete button clicked for ID:", id); // Log delete action

    if (!confirm("Are you sure you want to delete this record?")) {
      console.log("Delete cancelled by user");
      return;
    }

    var button = $(this);
    button.prop("disabled", true).text("Deleting...");

    console.log("Sending delete request for ID:", id); // Log AJAX call

    $.ajax({
      url: "delete_record_Po_monitoring.php",
      type: "POST",
      data: { id: id },
      dataType: "text", // Ensure we get string response
      success: function (response) {
        console.log("Delete response received:", response); // Log server response
        console.log("Response type:", typeof response); // Check response type

        if (typeof response === "object") {
          // Response is already parsed by jQuery
          console.log("Response is already parsed object:", response);
          var result = response;
        } else {
          try {
            var result = JSON.parse(response);
            console.log("Delete result parsed:", result); // Log parsed result
          } catch (e) {
            console.error("JSON parse error on delete:", e);
            console.error("Raw response that failed to parse:", response);
            showNotification("Error parsing response", "error");
            return;
          }
        }

        console.log("Final result to process:", result);

        if (result && result.success) {
          showNotification(result.message || "Record deleted successfully", "success");
          console.log("Delete successful:", result.message);
          // loadEditData(); // Refresh the edit table
          window.location.reload(); // Refresh main dashboard data
        } else {
          showNotification(result.message || "Failed to delete record", "error");
          console.error("Delete failed:", result ? result.message : "Unknown error");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error on delete:", {
          status: status,
          error: error,
          xhr: xhr,
          responseText: xhr.responseText
        });
        showNotification("Error deleting record", "error");
      },
      complete: function () {
        button.prop("disabled", false).text("Delete");
      },
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

    searchTimeout = setTimeout(function () {
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

  // Handle checklist checkboxes dynamically
  $("[id$='_cb']").on("change", function () {
    var checkboxId = $(this).attr("id"); // e.g., "requirements_cb" or "obr_cb"
    var remarksSectionId = checkboxId.replace("_cb", "_remarks_section"); // e.g., "requirements_remarks_section" or "obr_remarks_section"
    var remarksTextareaId = checkboxId.replace("_cb", "_remarks"); // e.g., "requirements_remarks" or "obr_remarks"

    if ($(this).is(":checked")) {
      $("#" + remarksSectionId).slideDown();
    } else {
      $("#" + remarksSectionId).slideUp();
      $("#" + remarksTextareaId).val(""); // Clear the textarea
    }
  });

  // Initialize datepicker for po_date field
  $("#po_date").datepicker({
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
