document.addEventListener("DOMContentLoaded", function () {
  const csrfToken =
    document.querySelector('meta[name="payables-csrf-token"]')?.content || "";
  const editForm = document.getElementById("editTransmittalForm");
  const createForm = document.getElementById("transmittalForm");
  const editModalEl = document.getElementById("editTransmittalModal");
  const deleteModalEl = document.getElementById("deleteConfirmModal");
  const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
  const actionError = document.getElementById("actionError");
  let pendingDeleteId = "";

  function parseJsonResponse(response) {
    return response.text().then(function (text) {
      try {
        return JSON.parse(text);
      } catch (error) {
        throw new Error(
          text.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim() ||
            "Invalid server response."
        );
      }
    });
  }

  function setButtonLoading(button, isLoading, loadingText) {
    if (!button) return;
    if (isLoading) {
      button.dataset.originalText = button.textContent.trim();
      button.disabled = true;
      button.classList.add("is-loading");
      button.innerHTML =
        '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>' +
        loadingText +
        "</span>";
      return;
    }

    button.disabled = false;
    button.classList.remove("is-loading");
    button.textContent = button.dataset.originalText || "Submit";
  }

  function showActionError(message) {
    if (!actionError) {
      alert(message);
      return;
    }
    actionError.textContent = message;
    actionError.classList.remove("d-none");
  }

  function hideActionError() {
    if (actionError) {
      actionError.classList.add("d-none");
      actionError.textContent = "";
    }
  }

  function setValue(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value ?? "";
  }

  function formatAmountValue(value) {
    const numeric = Number(String(value || "").replace(/[^\d.-]/g, ""));
    if (!Number.isFinite(numeric) || numeric === 0) return "";
    return numeric.toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  document.querySelectorAll('input[name="amount"]').forEach(function (input) {
    input.addEventListener("blur", function () {
      input.value = formatAmountValue(input.value);
    });
  });

  document.addEventListener("click", function (event) {
    const editButton = event.target.closest(".edit-btn");
    if (editButton) {
      const id = editButton.dataset.id || "";
      setButtonLoading(editButton, true, "");
      fetch("fetch_rfq.php?id=" + encodeURIComponent(id))
        .then(parseJsonResponse)
        .then(function (data) {
          if (!data.success) {
            alert(data.error || "Failed to fetch data.");
            return;
          }

          setValue("edit_id", data.row.id);
          setValue("edit_rfq_no", data.row.RFQ_no);
          setValue("edit_supplier", data.row.supplier);
          setValue("edit_description", data.row.description);
          setValue("edit_amount", formatAmountValue(data.row.amount));
          setValue(
            "edit_date_received",
            data.row.date_received ? data.row.date_received.split(" ")[0] : ""
          );
          setValue("edit_office", data.row.office);
          setValue("edit_received_by", data.row.received_by);
          setValue("edit_status", data.row.status);
          bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        })
        .catch(function (error) {
          alert(error.message || "Error fetching data.");
        })
        .finally(function () {
          setButtonLoading(editButton, false);
          editButton.innerHTML = '<i class="fas fa-edit"></i>';
        });
      return;
    }

    const deleteButton = event.target.closest(".delete-btn");
    if (deleteButton) {
      pendingDeleteId = deleteButton.dataset.id || "";
      hideActionError();
      bootstrap.Modal.getOrCreateInstance(deleteModalEl).show();
    }
  });

  if (editForm) {
    editForm.addEventListener("submit", function (event) {
      event.preventDefault();
      const submitButton = editForm.querySelector('[type="submit"]');
      setButtonLoading(submitButton, true, "Saving");

      fetch("update_rfq.php", {
        method: "POST",
        body: new FormData(editForm),
      })
        .then(parseJsonResponse)
        .then(function (data) {
          if (!data.success) {
            alert("Update failed: " + (data.error || "Unknown error"));
            return;
          }
          bootstrap.Modal.getInstance(editModalEl)?.hide();
          window.location.reload();
        })
        .catch(function (error) {
          alert(error.message || "Error updating RFQ.");
        })
        .finally(function () {
          setButtonLoading(submitButton, false);
        });
    });
  }

  if (createForm) {
    createForm.addEventListener("submit", function () {
      setButtonLoading(createForm.querySelector('[type="submit"]'), true, "Submitting");
    });
  }

  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener("click", function () {
      if (!pendingDeleteId) return;
      hideActionError();
      setButtonLoading(confirmDeleteBtn, true, "Deleting");

      const body = new URLSearchParams();
      body.set("id", pendingDeleteId);
      body.set("csrf_token", csrfToken);

      fetch("delete_rfq_row.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      })
        .then(parseJsonResponse)
        .then(function (data) {
          if (!data.success) {
            showActionError(data.error || "Delete failed.");
            return;
          }
          window.location.reload();
        })
        .catch(function (error) {
          showActionError(error.message || "Error deleting RFQ.");
        })
        .finally(function () {
          setButtonLoading(confirmDeleteBtn, false);
        });
    });
  }
});
