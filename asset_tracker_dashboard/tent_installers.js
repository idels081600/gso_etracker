(function () {
  "use strict";

  const refreshIntervalMs = 10000;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const tableBody = document.getElementById("tableBody");
  const searchInput = document.getElementById("searchInput");
  const refreshButton = document.getElementById("refreshBtn");
  const pageMessage = document.getElementById("pageMessage");
  const form = document.getElementById("editForm");
  const formMessage = document.getElementById("formMessage");
  const saveButton = document.getElementById("saveButton");
  const modal = $("#editModal");
  const tentBoxes = Array.from(document.querySelectorAll(".box"));
  let records = [];
  let loading = false;

  function showMessage(element, message) {
    element.textContent = message;
    element.hidden = !message;
  }

  function normalizeTentIds(value) {
    return Array.from(new Set(
      String(value || "")
        .split(",")
        .map((item) => Number.parseInt(item.trim(), 10))
        .filter((item) => Number.isInteger(item) && item > 0)
    )).sort((a, b) => a - b);
  }

  function setTentValue(ids) {
    document.getElementById("tentNumber").value = ids.join(",");
  }

  function createCell(value) {
    const cell = document.createElement("td");
    cell.textContent = String(value ?? "");
    return cell;
  }

  function createLocationCell(value) {
    const cell = createCell(value);
    cell.title = String(value ?? "");
    cell.className = "installer-location-cell";
    return cell;
  }

  function renderTable() {
    const query = searchInput.value.trim().toLowerCase();
    const visibleRecords = records.filter((record) =>
      !query || [
        record.name,
        record.location,
        record.address,
        record.contact_no,
        record.no_of_tents,
        record.date,
        record.status,
        record.tent_no,
      ].some((value) => String(value ?? "").toLowerCase().includes(query))
    );

    tableBody.replaceChildren();
    if (visibleRecords.length === 0) {
      const row = document.createElement("tr");
      const cell = createCell(query ? "No matching jobs found." : "No jobs require action.");
      cell.colSpan = 6;
      cell.className = "text-center text-muted";
      row.append(cell);
      tableBody.append(row);
      return;
    }

    visibleRecords.forEach((record) => {
      const row = document.createElement("tr");
      row.className = ["pending-row", "for-retrieval-row", "overdue-row"].includes(record.row_class)
        ? record.row_class
        : "";
      row.append(
        createCell(record.name),
        createLocationCell(record.location),
        createCell(record.no_of_tents),
        createCell(record.date),
        createCell(record.status)
      );

      const actionCell = document.createElement("td");
      actionCell.className = "text-right";
      const editButton = document.createElement("button");
      editButton.type = "button";
      editButton.className = "btn btn-primary btn-sm edit-job-button";
      editButton.textContent = record.status === "Pending" ? "Install" : "Complete Retrieval";
      editButton.dataset.recordId = String(record.id);
      actionCell.append(editButton);
      row.append(actionCell);
      tableBody.append(row);
    });
  }

  async function loadTableData(manual) {
    if (loading || modal.hasClass("show")) return;
    loading = true;
    showMessage(pageMessage, "");

    if (manual) {
      refreshButton.disabled = true;
      refreshButton.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Refreshing...';
    }

    try {
      const response = await fetch("get_tent_data.php", {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        cache: "no-store",
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || result.error || "Unable to load tent jobs.");
      }
      records = Array.isArray(result.data) ? result.data : [];
      renderTable();
      document.getElementById("lastUpdated").textContent = `Last updated: ${result.timestamp || new Date().toLocaleTimeString()}`;
    } catch (error) {
      showMessage(pageMessage, error.message || "Unable to load tent jobs.");
    } finally {
      loading = false;
      if (manual) {
        refreshButton.disabled = false;
        refreshButton.innerHTML = '<i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh Data';
      }
    }
  }

  function populateStatusOptions(currentStatus) {
    const select = document.getElementById("clientStatus");
    const transitions = {
      Pending: [["Installed", "Mark as Installed"]],
      Installed: [["For Retrieval", "Mark for Retrieval"], ["Retrieved", "Mark as Retrieved"]],
      "For Retrieval": [["Retrieved", "Mark as Retrieved"]],
    };
    select.replaceChildren();
    (transitions[currentStatus] || []).forEach(([value, label]) => {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = label;
      select.append(option);
    });
  }

  function openEditModal(record) {
    const assignedIds = normalizeTentIds(record.tent_no);
    const isInstallation = record.status === "Pending";

    document.getElementById("clientId").value = String(record.id);
    document.getElementById("clientName").value = record.name || "";
    document.getElementById("clientLocation").value = record.location || "";
    document.getElementById("clientAddress").value = record.address || "";
    document.getElementById("clientContact").value = record.contact_no || "";
    document.getElementById("noOfTents").value = String(record.no_of_tents || "");
    document.getElementById("currentJobStatus").textContent = `Current status: ${record.status}`;
    document.getElementById("tentSelectionHelp").textContent = isInstallation
      ? `Select exactly ${record.no_of_tents} available tent(s).`
      : "Assigned tents are locked while completing retrieval.";
    populateStatusOptions(record.status);
    setTentValue(assignedIds);
    showMessage(formMessage, "");

    tentBoxes.forEach((box) => {
      const id = Number.parseInt(box.dataset.tentId, 10);
      const isAssigned = assignedIds.includes(id);
      const selectable = isInstallation && box.dataset.status === "Retrieved";
      box.classList.toggle("selected", isAssigned);
      box.classList.toggle("current-assignment", isAssigned);
      box.disabled = !selectable;
      box.setAttribute("aria-pressed", isAssigned ? "true" : "false");
    });

    modal.modal("show");
  }

  tableBody.addEventListener("click", (event) => {
    const button = event.target.closest(".edit-job-button");
    if (!button) return;
    const record = records.find((item) => String(item.id) === button.dataset.recordId);
    if (record) openEditModal(record);
  });

  document.getElementById("tentGrid").addEventListener("click", (event) => {
    const box = event.target.closest(".box");
    if (!box || box.disabled) return;
    const tentId = Number.parseInt(box.dataset.tentId, 10);
    const selectedIds = normalizeTentIds(document.getElementById("tentNumber").value);
    const selectedIndex = selectedIds.indexOf(tentId);

    if (selectedIndex >= 0) {
      selectedIds.splice(selectedIndex, 1);
    } else {
      const requiredCount = Number.parseInt(document.getElementById("noOfTents").value, 10);
      if (selectedIds.length >= requiredCount) {
        showMessage(formMessage, `Select exactly ${requiredCount} tent(s). Remove one before selecting another.`);
        return;
      }
      selectedIds.push(tentId);
    }

    selectedIds.sort((a, b) => a - b);
    setTentValue(selectedIds);
    box.classList.toggle("selected", selectedIds.includes(tentId));
    box.setAttribute("aria-pressed", selectedIds.includes(tentId) ? "true" : "false");
    showMessage(formMessage, "");
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    showMessage(formMessage, "");
    saveButton.disabled = true;

    try {
      const response = await fetch("update_tent_installer.php", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-CSRF-Token": csrfToken,
          "X-Requested-With": "XMLHttpRequest",
        },
        body: new URLSearchParams({
          clientId: document.getElementById("clientId").value,
          clientStatus: document.getElementById("clientStatus").value,
          tentNumber: document.getElementById("tentNumber").value,
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to update the tent job.");
      window.location.reload();
    } catch (error) {
      showMessage(formMessage, error.message || "Unable to update the tent job.");
      saveButton.disabled = false;
    }
  });

  searchInput.addEventListener("input", renderTable);
  refreshButton.addEventListener("click", () => loadTableData(true));
  modal.on("hidden.bs.modal", function () {
    form.reset();
    tentBoxes.forEach((box) => {
      box.classList.remove("selected", "current-assignment");
      box.disabled = false;
      box.setAttribute("aria-pressed", "false");
    });
    document.body.classList.remove("modal-open");
    document.querySelectorAll(".modal-backdrop").forEach((backdrop) => backdrop.remove());
  });

  loadTableData(false);
  window.setInterval(() => loadTableData(false), refreshIntervalMs);
})();
