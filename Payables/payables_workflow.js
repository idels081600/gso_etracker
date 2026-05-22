document.addEventListener("DOMContentLoaded", function () {
  const csrfMeta = document.querySelector('meta[name="payables-csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute("content") : "";
  const modalEl = document.getElementById("workflowStatusModal");
  const form = document.getElementById("workflowStatusForm");
  const errorBox = document.getElementById("workflowStatusError");
  const checklistGroup = document.getElementById("gsoChecklistGroup");
  const checklistCount = document.getElementById("gsoChecklistCount");
  const markAllButton = document.getElementById("markAllGsoChecks");
  const clearButton = document.getElementById("clearGsoChecks");
  const proceedButton = document.getElementById("proceedToBudgetBtn");
  let activeButton = null;

  function updateChecklistCount() {
    if (!form || !checklistCount) return;
    const requiredItems = form.querySelectorAll("[data-required-check]");
    const checked = form.querySelectorAll("[data-required-check]:checked").length;
    checklistCount.textContent = checked + "/" + requiredItems.length + " required";
    if (proceedButton) {
      proceedButton.disabled = checked < requiredItems.length;
    }
    updateStatusAvailability();
  }

  function requiredChecksComplete() {
    if (!form) return false;
    const requiredItems = form.querySelectorAll("[data-required-check]");
    const checked = form.querySelectorAll("[data-required-check]:checked").length;
    return requiredItems.length > 0 && checked === requiredItems.length;
  }

  function getSelectedStatus() {
    if (!form) return null;
    return form.querySelector('input[name="main_status"]');
  }

  function selectStatus(status) {
    if (!form) return;
    const statusInput = getSelectedStatus();
    if (statusInput) {
      statusInput.value = status;
    }
    toggleChecklist();
  }

  function updateStatusAvailability() {
    return;
  }

  function toggleChecklist() {
    if (!form || !checklistGroup) return;
    const selected = getSelectedStatus();
    const isGso = !selected || selected.value === "GSO";
    checklistGroup.classList.toggle("d-none", !isGso);
    if (proceedButton) {
      proceedButton.classList.toggle("d-none", !isGso);
    }
  }

  function setWorkflowButtonData(button, workflow) {
    button.dataset.mainStatus = workflow.main_status || "GSO";
    ["inspection", "obr", "ics", "par", "ris"].forEach(function (key) {
      button.dataset[key] = workflow[key] && workflow[key] !== "0" ? "1" : "0";
    });
  }

  function openWorkflowModal(button) {
    if (!modalEl || !form) return;
    activeButton = button;
    errorBox.classList.add("d-none");
    errorBox.textContent = "";
    form.reset();
    document.getElementById("workflow_record_type").value = button.dataset.recordType || "";
    document.getElementById("workflow_record_id").value = button.dataset.recordId || "";

    const status = button.dataset.mainStatus || "GSO";
    selectStatus(status);

    ["inspection", "obr", "ics", "par", "ris"].forEach(function (key) {
      const checkbox = form.querySelector('[data-checklist-item="' + key + '"]');
      if (checkbox) checkbox.checked = button.dataset[key] === "1";
    });

    updateChecklistCount();
    selectStatus(status);
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  document.querySelectorAll(".update-workflow-btn").forEach(function (button) {
    button.addEventListener("click", function () {
      openWorkflowModal(button);
    });
  });

  if (form) {
    form.querySelectorAll("[data-checklist-item]").forEach(function (input) {
      input.addEventListener("change", updateChecklistCount);
    });

    if (markAllButton) {
      markAllButton.addEventListener("click", function () {
        form.querySelectorAll("[data-checklist-item]").forEach(function (input) {
          input.checked = true;
        });
        updateChecklistCount();
      });
    }

    if (clearButton) {
      clearButton.addEventListener("click", function () {
        form.querySelectorAll("[data-checklist-item]").forEach(function (input) {
          input.checked = false;
        });
        updateChecklistCount();
      });
    }

    if (proceedButton) {
      proceedButton.addEventListener("click", function () {
        if (!requiredChecksComplete()) {
          errorBox.textContent = "Complete Inspection and OBR before proceeding to Budget.";
          errorBox.classList.remove("d-none");
          return;
        }

        selectStatus("BUDGET");
        form.requestSubmit();
      });
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      const selectedStatus = getSelectedStatus();
      if (!selectedStatus) {
        selectStatus("GSO");
      }
      if ((selectedStatus ? selectedStatus.value : "GSO") !== "GSO" && !requiredChecksComplete()) {
        errorBox.textContent = "Complete Inspection and OBR before selecting the next status.";
        errorBox.classList.remove("d-none");
        selectStatus("GSO");
        return;
      }
      const formData = new FormData(form);
      if (csrfToken && !formData.has("csrf_token")) {
        formData.append("csrf_token", csrfToken);
      }

      fetch("update_workflow_status.php", {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data.success) {
            errorBox.textContent = data.error || "Unable to save workflow.";
            errorBox.classList.remove("d-none");
            return;
          }

          const recordType = formData.get("record_type");
          const recordId = formData.get("record_id");
          const rowButtonSelector =
            '.update-workflow-btn[data-record-type="' +
            recordType +
            '"][data-record-id="' +
            recordId +
            '"]';
          document.querySelectorAll(rowButtonSelector).forEach(function (button) {
            setWorkflowButtonData(button, data.workflow);
          });

          if (activeButton && data.html) {
            const row = activeButton.closest("tr");
            const cell = row ? row.querySelector(".workflow-cell") : null;
            if (cell) {
              cell.outerHTML = data.html;
              const newButton = row.querySelector(".workflow-cell .update-workflow-btn");
              if (newButton) {
                newButton.addEventListener("click", function () {
                  openWorkflowModal(newButton);
                });
              }
            }
          }

          bootstrap.Modal.getInstance(modalEl).hide();
          applyWorkflowFilter();
        })
        .catch(function () {
          errorBox.textContent = "Unable to save workflow. Please try again.";
          errorBox.classList.remove("d-none");
        });
    });
  }

  function applyWorkflowFilter() {
    const activeFilter = document.querySelector("[data-workflow-filter].active");
    const filter = activeFilter ? activeFilter.dataset.workflowFilter : "all";
    document.querySelectorAll("tbody tr").forEach(function (row) {
      const workflow = row.querySelector(".workflow-cell");
      if (!workflow) return;
      const status = workflow.dataset.workflowStatus || "GSO";
      const completed = Number(workflow.dataset.gsoComplete || 0);
      const matches =
        filter === "all" ||
        filter === status ||
        (filter === "needs-action" && status === "GSO" && completed < 2);
      row.dataset.workflowVisible = matches ? "1" : "0";
      row.style.display =
        matches && row.dataset.searchVisible !== "0" ? "" : "none";
    });
  }

  document.querySelectorAll("[data-workflow-filter]").forEach(function (button) {
    button.addEventListener("click", function () {
      document.querySelectorAll("[data-workflow-filter]").forEach(function (item) {
        item.classList.remove("active");
      });
      button.classList.add("active");
      applyWorkflowFilter();
    });
  });
});
