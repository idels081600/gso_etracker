document.addEventListener("DOMContentLoaded", function () {
  const csrfToken =
    document.querySelector('meta[name="payables-csrf-token"]')?.content || "";
  const filterButtons = document.querySelectorAll("[data-status-filter]");
  const rows = document.querySelectorAll(".payables-row[data-main-status]");
  const transmitButtons = document.querySelectorAll(".transmit-budget-btn");
  const checklistButtons = document.querySelectorAll(".gso-check-chip");
  const locationSelects = document.querySelectorAll(".accounting-location-select");
  const locationHistoryButtons = document.querySelectorAll(".location-history-btn");
  const remarksHistoryButtons = document.querySelectorAll(".remarks-history-btn");
  const remarksEditButtons = document.querySelectorAll(
    ".workflow-remarks-edit, .workflow-remarks-action"
  );
  const releaseCheckboxes = document.querySelectorAll(".cto-release-checkbox");
  const taskTable = document.querySelector(".task-table");
  const detailHeader = document.getElementById("workflowDetailHeader");
  const actionHeader = document.getElementById("workflowActionHeader");
  const searchInput = document.getElementById("monitoringSearchInput");
  const searchButton = document.getElementById("monitoringSearchButton");
  const confirmModalEl = document.getElementById("transmitConfirmModal");
  const confirmButton = document.getElementById("confirmTransmitBtn");
  const confirmMessage = document.getElementById("transmitConfirmMessage");
  const confirmSubtitle = document.getElementById("transmitConfirmSubtitle");
  const successAlert = document.getElementById("transmitSuccessAlert");
  const locationHistoryModalEl = document.getElementById("locationHistoryModal");
  const locationHistoryTitle = document.getElementById("locationHistoryModalLabel");
  const locationHistorySubtitle = document.getElementById("locationHistorySubtitle");
  const locationHistoryList = document.getElementById("locationHistoryList");
  const remarksEditModalEl = document.getElementById("remarksEditModal");
  const remarksEditTitle = document.getElementById("remarksEditModalLabel");
  const remarksEditForm = document.getElementById("remarksEditForm");
  const remarksEditRecordId = document.getElementById("remarksEditRecordId");
  const remarksEditText = document.getElementById("remarksEditText");
  const remarksEditSubtitle = document.getElementById("remarksEditSubtitle");
  const remarksEditError = document.getElementById("remarksEditError");
  const remarksSaveBtn = document.getElementById("remarksSaveBtn");
  let pendingTransmitButton = null;
  let activeRemarksButton = null;

  const stageLabels = {
    GSO: {
      detail: "Checklist",
      action: "Transmit to Budget",
    },
    BUDGET: {
      detail: "Remarks",
      action: "To Accounting",
    },
    ACCOUNTING: {
      detail: "Remarks",
      action: "To CTO",
    },
    CTO: {
      detail: "Remarks",
      action: "Completed",
    },
    SEARCH: {
      detail: "Details",
      action: "Action",
    },
  };

  const nextStatusMap = {
    GSO: "BUDGET",
    BUDGET: "ACCOUNTING",
    ACCOUNTING: "CTO",
  };

  const statusLabelMap = {
    BUDGET: "Budget",
    ACCOUNTING: "Accounting",
    CTO: "CTO",
  };

  function updateHeaders(status) {
    const labels = stageLabels[status] || stageLabels.GSO;
    if (detailHeader) detailHeader.textContent = labels.detail;
    if (actionHeader) actionHeader.textContent = labels.action;
    if (taskTable) taskTable.dataset.activeStatus = status;
  }

  function applyStatusFilter(status) {
    updateHeaders(status);
  }

  function goToSearch() {
    const activeStatus =
      document.querySelector("[data-status-filter].active")?.dataset
        .statusFilter ||
      taskTable?.dataset.activeStatus ||
      "GSO";
    const params = new URLSearchParams();
    params.set("page", "1");
    if (searchInput && searchInput.value.trim() !== "") {
      params.set("search", searchInput.value.trim());
    } else {
      params.set("status", activeStatus === "SEARCH" ? "GSO" : activeStatus);
    }
    window.location.href = "bac_dashboard.php?" + params.toString();
  }

  function getCheckedKeys(row) {
    const checkedKeys = [];
    row.querySelectorAll("[data-check-key].is-complete").forEach(function (chip) {
      checkedKeys.push(chip.dataset.checkKey);
    });
    return checkedKeys;
  }

  function updateTransmitButtonState(row) {
    const button = row.querySelector(".transmit-budget-btn");
    if (!button) return;

    const status = row.dataset.mainStatus || "GSO";
    const canTransmit =
      status === "GSO" ||
      status === "BUDGET" ||
      status === "ACCOUNTING";

    button.disabled = !canTransmit;
    if (status === "GSO") {
      button.title = "Transmit to Budget";
      button.setAttribute("aria-label", "Transmit to Budget");
    } else if (status === "BUDGET") {
      button.title = "Transmit to Accounting";
      button.setAttribute("aria-label", "Transmit to Accounting");
    } else if (status === "ACCOUNTING") {
      button.title = "Transmit to CTO";
      button.setAttribute("aria-label", "Transmit to CTO");
    } else {
      button.title = "Completed";
      button.setAttribute("aria-label", "Completed");
    }
  }

  function buildWorkflowFormData(row, status) {
    const transmitButton = row.querySelector(".transmit-budget-btn");
    const formData = new FormData();
    formData.append("record_type", "bac");
    formData.append("record_id", transmitButton?.dataset.recordId || "");
    formData.append("main_status", status);
    if (csrfToken) {
      formData.append("csrf_token", csrfToken);
    }

    getCheckedKeys(row).forEach(function (key) {
      formData.append("checklist[]", key);
    });

    return formData;
  }

  function parseJsonResponse(response) {
    return response.text().then(function (text) {
      try {
        return JSON.parse(text);
      } catch (error) {
        throw new Error(text.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim() || "Invalid server response.");
      }
    });
  }

  function formatHistoryDate(value) {
    if (!value) return "Current selection";
    const normalized = value.replace(" ", "T");
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString(undefined, {
      year: "numeric",
      month: "short",
      day: "2-digit",
      hour: "numeric",
      minute: "2-digit",
    });
  }

  function parseHistory(button) {
    try {
      const history = JSON.parse(button.dataset.locationHistory || "[]");
      return Array.isArray(history) ? history : [];
    } catch (error) {
      return [];
    }
  }

  function parseRemarksHistory(button) {
    try {
      const history = JSON.parse(button.dataset.remarksHistory || "[]");
      return Array.isArray(history) ? history : [];
    } catch (error) {
      return [];
    }
  }

  function renderLocationHistory(reference, history) {
    if (locationHistoryTitle) {
      locationHistoryTitle.textContent = "Location History";
    }
    if (locationHistorySubtitle) {
      locationHistorySubtitle.textContent =
        "Location history for " + (reference || "this record") + ".";
    }
    if (!locationHistoryList) return;

    locationHistoryList.innerHTML = "";
    if (!history.length) {
      const empty = document.createElement("div");
      empty.className = "location-history-empty";
      empty.textContent = "No location changes recorded yet.";
      locationHistoryList.appendChild(empty);
      return;
    }

    history.forEach(function (item) {
      const entry = document.createElement("div");
      entry.className = "location-history-entry";
      entry.setAttribute("role", "listitem");

      const marker = document.createElement("span");
      marker.className = "location-history-marker";
      marker.textContent = (item.location || "?").slice(0, 1);

      const copy = document.createElement("div");
      copy.className = "location-history-copy";

      const location = document.createElement("strong");
      location.textContent = item.location || "Unknown";

      const meta = document.createElement("span");
      const changedBy = item.changed_by ? " by " + item.changed_by : "";
      meta.textContent = formatHistoryDate(item.changed_at) + changedBy;

      copy.appendChild(location);
      copy.appendChild(meta);
      entry.appendChild(marker);
      entry.appendChild(copy);
      locationHistoryList.appendChild(entry);
    });
  }

  function renderRemarksHistory(reference, history) {
    if (locationHistoryTitle) {
      locationHistoryTitle.textContent = "Remarks History";
    }
    if (locationHistorySubtitle) {
      locationHistorySubtitle.textContent =
        "Remarks history for " + (reference || "this record") + ".";
    }
    if (!locationHistoryList) return;

    locationHistoryList.innerHTML = "";
    if (!history.length) {
      const empty = document.createElement("div");
      empty.className = "location-history-empty";
      empty.textContent = "No remarks recorded yet.";
      locationHistoryList.appendChild(empty);
      return;
    }

    history.forEach(function (item) {
      const entry = document.createElement("div");
      entry.className = "location-history-entry remarks-history-entry";
      entry.setAttribute("role", "listitem");

      const marker = document.createElement("span");
      marker.className = "location-history-marker remarks-history-marker";
      marker.textContent = "R";

      const copy = document.createElement("div");
      copy.className = "location-history-copy";

      const remarks = document.createElement("strong");
      remarks.textContent = item.remarks || "No remarks entered.";

      const meta = document.createElement("span");
      const changedBy = item.changed_by ? " by " + item.changed_by : "";
      meta.textContent = formatHistoryDate(item.changed_at) + changedBy;

      copy.appendChild(remarks);
      copy.appendChild(meta);
      entry.appendChild(marker);
      entry.appendChild(copy);
      locationHistoryList.appendChild(entry);
    });
  }

  function saveLocation(select) {
    const previousLocation = select.dataset.previousLocation || select.value;
    const formData = new FormData();
    formData.append("record_id", select.dataset.recordId || "");
    formData.append("location", select.value);
    if (csrfToken) {
      formData.append("csrf_token", csrfToken);
    }

    select.disabled = true;
    select.classList.add("is-saving");

    fetch("update_workflow_location.php", {
      method: "POST",
      body: formData,
    })
      .then(parseJsonResponse)
      .then(function (data) {
        if (!data.success) {
          select.value = previousLocation;
          alert(data.error || "Unable to update location.");
          return;
        }

        select.value = data.location || select.value;
        select.dataset.previousLocation = select.value;
        const row = select.closest(".payables-row");
        const historyButton = row?.querySelector(".location-history-btn");
        if (historyButton && Array.isArray(data.history)) {
          historyButton.dataset.locationHistory = JSON.stringify(data.history);
        }
      })
      .catch(function (error) {
        select.value = previousLocation;
        alert(error.message || "Unable to update location. Please try again.");
      })
      .finally(function () {
        select.disabled = false;
        select.classList.remove("is-saving");
      });
  }

  function saveReleaseStatus(checkbox) {
    const formData = new FormData();
    formData.append("record_id", checkbox.dataset.recordId || "");
    formData.append("released", checkbox.checked ? "1" : "0");
    if (csrfToken) {
      formData.append("csrf_token", csrfToken);
    }

    checkbox.disabled = true;
    checkbox.closest(".cto-release-check")?.classList.add("is-saving");

    fetch("update_release_status.php", {
      method: "POST",
      body: formData,
    })
      .then(parseJsonResponse)
      .then(function (data) {
        if (!data.success) {
          checkbox.checked = !checkbox.checked;
          alert(data.error || "Unable to update release status.");
        }
      })
      .catch(function (error) {
        checkbox.checked = !checkbox.checked;
        alert(error.message || "Unable to update release status. Please try again.");
      })
      .finally(function () {
        checkbox.disabled = false;
        checkbox.closest(".cto-release-check")?.classList.remove("is-saving");
      });
  }

  function setRemarksError(message) {
    if (!remarksEditError) return;
    remarksEditError.textContent = message || "";
    remarksEditError.classList.toggle("d-none", !message);
  }

  function setRemarksSaving(isSaving) {
    if (!remarksSaveBtn) return;
    const label = remarksSaveBtn.querySelector(".remarks-save-label");
    remarksSaveBtn.disabled = isSaving;
    remarksSaveBtn.classList.toggle("is-loading", isSaving);
    if (label) {
      label.textContent = isSaving ? "Saving..." : "Save Remarks";
    }
  }

  function openRemarksEditor(button) {
    activeRemarksButton = button;
    setRemarksError("");
    const stage = button.dataset.remarksStage || "Workflow";
    if (remarksEditTitle) {
      remarksEditTitle.textContent = "Edit " + stage + " Remarks";
    }
    if (remarksEditRecordId) {
      remarksEditRecordId.value = button.dataset.recordId || "";
    }
    if (remarksEditText) {
      remarksEditText.value = button.dataset.currentRemarks || "";
    }
    if (remarksEditSubtitle) {
      remarksEditSubtitle.textContent =
        "Update remarks for " + (button.dataset.reference || "this record") + ".";
    }
    bootstrap.Modal.getOrCreateInstance(remarksEditModalEl).show();
    window.setTimeout(function () {
      remarksEditText?.focus();
      remarksEditText?.select();
    }, 160);
  }

  function saveRemarks() {
    if (!activeRemarksButton || !remarksEditForm) return;
    const formData = new FormData(remarksEditForm);
    if (csrfToken) {
      formData.append("csrf_token", csrfToken);
    }

    setRemarksSaving(true);
    setRemarksError("");

    fetch("update_workflow_remarks.php", {
      method: "POST",
      body: formData,
    })
      .then(parseJsonResponse)
      .then(function (data) {
        if (!data.success) {
          setRemarksError(data.error || "Unable to update remarks.");
          return;
        }

        const remarks = data.remarks || "";
        activeRemarksButton.dataset.currentRemarks = remarks;

        const row = activeRemarksButton.closest(".payables-row");
        if (activeRemarksButton.classList.contains("workflow-remarks-edit")) {
          activeRemarksButton.textContent = remarks || "No remarks yet.";
        }
        row?.querySelectorAll(".workflow-remarks-preview").forEach(function (preview) {
          preview.textContent = remarks || "No remarks yet.";
        });
        row
          ?.querySelectorAll(".workflow-remarks-edit, .workflow-remarks-action")
          .forEach(function (button) {
            button.dataset.currentRemarks = remarks;
          });
        const historyButton = row?.querySelector(".remarks-history-btn");
        if (historyButton && Array.isArray(data.history)) {
          historyButton.dataset.remarksHistory = JSON.stringify(data.history);
        }

        bootstrap.Modal.getInstance(remarksEditModalEl)?.hide();
      })
      .catch(function (error) {
        setRemarksError(error.message || "Unable to update remarks. Please try again.");
      })
      .finally(function () {
        setRemarksSaving(false);
      });
  }

  function saveChecklist(row, chip) {
    const status = row.dataset.mainStatus || "GSO";
    chip.disabled = true;
    chip.classList.add("is-saving");

    fetch("update_workflow_status.php", {
      method: "POST",
      body: buildWorkflowFormData(row, status),
    })
      .then(parseJsonResponse)
      .then(function (data) {
        if (!data.success) {
          chip.classList.toggle("is-complete");
          chip.classList.toggle("is-missing");
          chip.setAttribute(
            "aria-pressed",
            chip.classList.contains("is-complete") ? "true" : "false"
          );
          alert(data.error || "Unable to update checklist.");
        }
        updateTransmitButtonState(row);
      })
      .catch(function (error) {
        chip.classList.toggle("is-complete");
        chip.classList.toggle("is-missing");
        chip.setAttribute(
          "aria-pressed",
          chip.classList.contains("is-complete") ? "true" : "false"
        );
        alert(error.message || "Unable to update checklist. Please try again.");
        updateTransmitButtonState(row);
      })
      .finally(function () {
        chip.disabled = false;
        chip.classList.remove("is-saving");
      });
  }

  function showSuccess(message) {
    if (!successAlert) {
      alert(message);
      return;
    }
    const successText = successAlert.querySelector(".transmit-success-text");
    if (successText) {
      successText.textContent = message;
    }
    successAlert.classList.remove("d-none");
    successAlert.classList.remove("is-visible");
    window.requestAnimationFrame(function () {
      successAlert.classList.add("is-visible");
    });
  }

  function runTransmit(button) {
    const row = button.closest(".payables-row");
    const currentStatus = row ? row.dataset.mainStatus || "GSO" : "GSO";
    const nextStatus = nextStatusMap[currentStatus];
    if (!row || !nextStatus) return;
    const formData = buildWorkflowFormData(row, nextStatus);
    const confirmButtonLabel = confirmButton?.querySelector(
      ".transmit-confirm-label"
    );
    const originalConfirmLabel = confirmButtonLabel?.textContent || "Transmit";
    const originalConfirmAriaLabel =
      confirmButton?.getAttribute("aria-label") || originalConfirmLabel;

    button.disabled = true;
    button.classList.add("is-loading");
    button.setAttribute("aria-busy", "true");
    if (confirmButton) {
      confirmButton.disabled = true;
      confirmButton.classList.add("is-loading");
      confirmButton.setAttribute("aria-busy", "true");
      confirmButton.setAttribute("aria-label", "Transmitting request");
      if (confirmButtonLabel) {
        confirmButtonLabel.textContent = "Transmitting...";
      }
    }

    fetch("update_workflow_status.php", {
      method: "POST",
      body: formData,
    })
      .then(parseJsonResponse)
      .then(function (data) {
        if (!data.success) {
          button.disabled = false;
          alert(data.error || "Unable to transmit this record.");
          return;
        }

        row.dataset.mainStatus = nextStatus;
        updateTransmitButtonState(row);
        const activeFilter =
          document.querySelector("[data-status-filter].active")?.dataset
            .statusFilter ||
          taskTable?.dataset.activeStatus ||
          "GSO";
        row.style.display =
          activeFilter === "SEARCH" || activeFilter === nextStatus ? "" : "none";

        showSuccess("Record transmitted to " + statusLabelMap[nextStatus] + " successfully.");
        window.setTimeout(function () {
          bootstrap.Modal.getInstance(confirmModalEl)?.hide();
        }, 900);
      })
      .catch(function (error) {
        button.disabled = false;
        alert(error.message || "Unable to transmit this record. Please try again.");
      })
      .finally(function () {
        button.classList.remove("is-loading");
        button.removeAttribute("aria-busy");
        if (confirmButton) {
          confirmButton.disabled = false;
          confirmButton.classList.remove("is-loading");
          confirmButton.removeAttribute("aria-busy");
          confirmButton.setAttribute("aria-label", originalConfirmAriaLabel);
          if (confirmButtonLabel) {
            confirmButtonLabel.textContent = originalConfirmLabel;
          }
        }
        pendingTransmitButton = null;
      });
  }

  filterButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      window.location.href = button.dataset.statusUrl || "bac_dashboard.php";
    });
  });

  if (searchButton) {
    searchButton.addEventListener("click", goToSearch);
  }

  if (searchInput) {
    searchInput.addEventListener("keydown", function (event) {
      if (event.key === "Enter") {
        event.preventDefault();
        goToSearch();
      }
    });
  }

  transmitButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      if (button.disabled) return;

      const row = button.closest(".payables-row");
      const currentStatus = row ? row.dataset.mainStatus || "GSO" : "GSO";
      const nextStatus = nextStatusMap[currentStatus];
      if (!nextStatus) return;
      pendingTransmitButton = button;
      if (successAlert) successAlert.classList.add("d-none");
      if (confirmSubtitle) {
        confirmSubtitle.textContent = "This will move the record to " + statusLabelMap[nextStatus] + ".";
      }
      if (confirmMessage) {
        const reference = row?.querySelector(".task-name strong")?.textContent || "this record";
        confirmMessage.textContent =
          "Transmit " + reference + " to " + statusLabelMap[nextStatus] + "?";
      }
      bootstrap.Modal.getOrCreateInstance(confirmModalEl).show();
    });
  });

  if (confirmButton) {
    confirmButton.addEventListener("click", function () {
      if (pendingTransmitButton) {
        runTransmit(pendingTransmitButton);
      }
    });
  }

  checklistButtons.forEach(function (chip) {
    chip.addEventListener("click", function () {
      const row = chip.closest(".payables-row");
      if (!row || row.dataset.mainStatus !== "GSO" || chip.disabled) return;

      const isComplete = chip.classList.toggle("is-complete");
      chip.classList.toggle("is-missing", !isComplete);
      chip.setAttribute("aria-pressed", isComplete ? "true" : "false");
      chip.querySelector("i")?.classList.toggle("fa-check", isComplete);
      chip.querySelector("i")?.classList.toggle("fa-minus", !isComplete);
      updateTransmitButtonState(row);
      saveChecklist(row, chip);
    });
  });

  locationSelects.forEach(function (select) {
    select.dataset.previousLocation = select.value;
    select.addEventListener("change", function () {
      saveLocation(select);
    });
  });

  locationHistoryButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      renderLocationHistory(button.dataset.reference, parseHistory(button));
      bootstrap.Modal.getOrCreateInstance(locationHistoryModalEl).show();
    });
  });

  remarksHistoryButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      renderRemarksHistory(button.dataset.reference, parseRemarksHistory(button));
      bootstrap.Modal.getOrCreateInstance(locationHistoryModalEl).show();
    });
  });

  remarksEditButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      openRemarksEditor(button);
    });
  });

  if (remarksEditForm) {
    remarksEditForm.addEventListener("submit", function (event) {
      event.preventDefault();
      saveRemarks();
    });
  }

  releaseCheckboxes.forEach(function (checkbox) {
    checkbox.addEventListener("change", function () {
      saveReleaseStatus(checkbox);
    });
  });

  rows.forEach(updateTransmitButtonState);

  applyStatusFilter(taskTable?.dataset.activeStatus || "GSO");
});
