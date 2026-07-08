(function () {
  "use strict";

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const escapeHtml = window.AssetApp?.escapeHtml || ((value) => String(value ?? ""));
  let loadedVehicleData = [];
  let pendingConfirmAction = null;

  function byId(id) {
    return document.getElementById(id);
  }

  function showToast(message, isSuccess = true) {
    let toastContainer = document.querySelector(".toast-container");
    if (!toastContainer) {
      toastContainer = document.createElement("div");
      toastContainer.className = "toast-container position-fixed bottom-0 end-0 p-3";
      document.body.appendChild(toastContainer);
    }

    const toastEl = document.createElement("div");
    toastEl.className = `toast align-items-center ${isSuccess ? "text-bg-success" : "text-bg-danger"}`;
    toastEl.setAttribute("role", "alert");
    toastEl.setAttribute("aria-live", "assertive");
    toastEl.setAttribute("aria-atomic", "true");
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body"></div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;
    toastEl.querySelector(".toast-body").textContent = message;
    toastContainer.appendChild(toastEl);

    const toast = new bootstrap.Toast(toastEl, { delay: 2600 });
    toast.show();
    toastEl.addEventListener("hidden.bs.toast", () => toastEl.remove());
  }

  function ensureConfirmModal() {
    let modalEl = byId("motorpoolConfirmModal");
    if (modalEl) return modalEl;

    modalEl = document.createElement("div");
    modalEl.className = "modal fade";
    modalEl.id = "motorpoolConfirmModal";
    modalEl.tabIndex = -1;
    modalEl.setAttribute("aria-labelledby", "motorpoolConfirmTitle");
    modalEl.setAttribute("aria-hidden", "true");
    modalEl.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="motorpoolConfirmTitle">Confirm action</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0" id="motorpoolConfirmMessage"></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="motorpoolConfirmButton">Confirm</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modalEl);
    byId("motorpoolConfirmButton").addEventListener("click", async function () {
      const action = pendingConfirmAction;
      pendingConfirmAction = null;
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      if (typeof action === "function") await action();
    });
    return modalEl;
  }

  function confirmAction(message, action) {
    const modalEl = ensureConfirmModal();
    byId("motorpoolConfirmMessage").textContent = message;
    pendingConfirmAction = action;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  async function apiRequest(url, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set("Accept", "application/json");
    headers.set("X-Requested-With", "XMLHttpRequest");
    if (csrfToken) headers.set("X-CSRF-Token", csrfToken);

    const response = await fetch(url, { ...options, headers });
    const payload = await response.json().catch(() => ({
      success: false,
      message: "The server returned an invalid response.",
    }));

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "The request could not be completed.");
    }
    return payload;
  }

  function setButtonLoading(button, loading, label) {
    if (!button) return () => {};
    const previous = button.innerHTML;
    button.disabled = loading;
    if (loading) {
      button.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ${escapeHtml(label)}`;
    }
    return () => {
      button.disabled = false;
      button.innerHTML = previous;
    };
  }

  function cleanModalBackdrops() {
    if (document.querySelector(".modal.show")) return;
    document.body.classList.remove("modal-open");
    document.body.style.removeProperty("overflow");
    document.body.style.removeProperty("padding-right");
    document.querySelectorAll(".modal-backdrop").forEach((backdrop) => backdrop.remove());
  }

  function setTextCell(row, value, className) {
    const cell = document.createElement("td");
    if (className) cell.className = className;
    cell.textContent = String(value ?? "");
    row.appendChild(cell);
    return cell;
  }

  function renderVehicleSelectionTable(vehicles) {
    const tableBody = document.querySelector("#vehicleSelectionTable tbody");
    if (!tableBody) return;
    tableBody.replaceChildren();

    if (!vehicles.length) {
      const row = document.createElement("tr");
      const cell = setTextCell(row, "No vehicles found", "text-center text-muted py-3");
      cell.colSpan = 10;
      tableBody.appendChild(row);
      return;
    }

    vehicles.forEach((vehicle) => {
      const row = document.createElement("tr");
      setTextCell(row, vehicle.plate_no);
      setTextCell(row, vehicle.car_model || "-");
      setTextCell(row, vehicle.office || "-");
      setTextCell(row, vehicle.status || "-");
      setTextCell(row, vehicle.old_mileage ?? "0", "text-end");
      setTextCell(row, vehicle.latest_mileage ?? "0", "text-end");
      setTextCell(row, vehicle.no_of_repairs ?? "0", "text-end");
      setTextCell(row, vehicle.new_repair_date || "-");
      setTextCell(row, vehicle.date_procured || "-");

      const actionCell = document.createElement("td");
      const actionWrap = document.createElement("div");
      actionWrap.className = "d-flex gap-1";

      const editButton = document.createElement("button");
      editButton.type = "button";
      editButton.className = "btn btn-sm btn-primary select-vehicle";
      editButton.setAttribute("aria-label", "Edit vehicle");
      editButton.innerHTML = '<i class="fas fa-edit"></i>';
      editButton.addEventListener("click", () => fillVehicleForm(vehicle));

      const deleteButton = document.createElement("button");
      deleteButton.type = "button";
      deleteButton.className = "btn btn-sm btn-danger delete-vehicle";
      deleteButton.setAttribute("aria-label", "Delete vehicle");
      deleteButton.innerHTML = '<i class="fas fa-trash-alt"></i>';
      deleteButton.addEventListener("click", () => {
        confirmAction(`Delete vehicle ${vehicle.plate_no}? This cannot be undone.`, () => deleteVehicle(vehicle.plate_no));
      });

      actionWrap.append(editButton, deleteButton);
      actionCell.appendChild(actionWrap);
      row.appendChild(actionCell);
      tableBody.appendChild(row);
    });
  }

  async function loadVehicleSelectionTable() {
    const tableBody = document.querySelector("#vehicleSelectionTable tbody");
    if (tableBody) {
      tableBody.innerHTML = '<tr><td colspan="10" class="text-center py-3">Loading vehicles...</td></tr>';
    }

    try {
      const payload = await apiRequest("get_vehicle_records_motorpool.php");
      loadedVehicleData = payload.data?.vehicles || [];
      renderVehicleSelectionTable(loadedVehicleData);
    } catch (error) {
      if (tableBody) {
        tableBody.innerHTML = "";
        const row = document.createElement("tr");
        const cell = setTextCell(row, error.message || "Unable to load vehicles.", "text-center text-danger py-3");
        cell.colSpan = 10;
        tableBody.appendChild(row);
      }
      showToast(error.message || "Unable to load vehicles.", false);
    }
  }

  async function deleteVehicle(plateNo) {
    try {
      await apiRequest("delete_vehicle_record_motorpool.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ plate_no: plateNo }),
      });
      showToast("Vehicle deleted successfully.");
      await loadVehicleSelectionTable();
    } catch (error) {
      showToast(error.message || "Unable to delete vehicle.", false);
    }
  }

  function fillVehicleForm(vehicle) {
    byId("original_plate_no").value = vehicle.id || "";
    byId("update_plate_no").value = vehicle.plate_no || "";
    byId("update_car_model").value = vehicle.car_model || "";
    byId("update_office").value = vehicle.office || "";
    byId("update_status").value = vehicle.status || "Active";
    byId("update_old_mileage").value = vehicle.old_mileage ?? 0;
    byId("update_latest_mileage").value = vehicle.latest_mileage ?? 0;
    byId("update_no_of_repairs").value = vehicle.no_of_repairs ?? 0;
    byId("update_latest_repair_date").value = vehicle.new_repair_date || "";
    byId("update_date_procured").value = vehicle.date_procured || "";
    byId("update_no_dispatch").value = vehicle.no_dispatch ?? 0;
    document.querySelector("#updateVehicleModal .modal-body")?.scrollTo({ top: 0, behavior: "smooth" });
  }

  function filterTableRows(input, table) {
    const term = input.value.trim().toLowerCase();
    table.querySelectorAll("tbody tr").forEach((row) => {
      row.style.display = row.textContent.toLowerCase().includes(term) ? "" : "none";
    });
  }

  function selectedRepairTypeCount(selector) {
    return document.querySelectorAll(`${selector}:checked`).length;
  }

  async function submitAjaxForm(form, successMessage, options = {}) {
    const submitButton = document.querySelector(`[form="${form.id}"]`) || form.querySelector('[type="submit"]');
    const resetButton = setButtonLoading(submitButton, true, options.loadingLabel || "Saving...");
    try {
      if (options.validate && !options.validate()) return;
      const payload = await apiRequest(form.action || options.url, {
        method: "POST",
        body: new FormData(form),
      });
      showToast(payload.message || successMessage);
      if (options.reset) form.reset();
      if (options.hideModalId) {
        bootstrap.Modal.getInstance(byId(options.hideModalId))?.hide();
      }
      if (options.afterSuccess) await options.afterSuccess(payload);
    } catch (error) {
      showToast(error.message || "The request could not be completed.", false);
    } finally {
      resetButton();
    }
  }

  function populateEditRepairModal(repair) {
    byId("edit_repair_id").value = repair.id || "";
    byId("edit_vehicle_id").value = repair.plate_no || "";
    byId("edit_repair_date").value = repair.repair_date || "";
    byId("edit_mileage").value = repair.mileage || "";
    byId("edit_parts_replaced").value = repair.parts_replaced || "";
    byId("edit_cost").value = repair.cost || "";
    byId("edit_office").value = repair.office || "";
    byId("edit_notes").value = repair.remarks || "";
    byId("edit_status").value = repair.status || "Pending";

    document.querySelectorAll(".edit-repair-type-checkbox").forEach((checkbox) => {
      checkbox.checked = false;
    });

    String(repair.repair_type || "")
      .split(",")
      .map((type) => type.trim())
      .filter(Boolean)
      .forEach((type) => {
        const escaped = window.CSS?.escape ? CSS.escape(type) : type.replace(/"/g, '\\"');
        const checkbox = document.querySelector(`input[name="edit_repair_type[]"][value="${escaped}"]`);
        if (checkbox) checkbox.checked = true;
      });
  }

  async function editRepair(repairId) {
    try {
      const payload = await apiRequest("get_repair_data_motorpool.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ repair_id: repairId }),
      });
      populateEditRepairModal(payload.data?.repair || payload.data || {});
      bootstrap.Modal.getOrCreateInstance(byId("editRepairModal")).show();
    } catch (error) {
      showToast(error.message || "Unable to load repair details.", false);
    }
  }

  async function deleteRepair(repairId) {
    try {
      await apiRequest("delete_repair_motorpool.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ repair_id: repairId }),
      });
      showToast("Repair record deleted successfully.");
      window.setTimeout(() => window.location.reload(), 450);
    } catch (error) {
      showToast(error.message || "Unable to delete repair record.", false);
    }
  }

  function initializeCharts() {
    const chartDataElement = byId("repairChartData");
    if (!chartDataElement || typeof Chart === "undefined") return;

    const readData = (name) => {
      try {
        return JSON.parse(chartDataElement.dataset[name] || "[]");
      } catch (_) {
        return [];
      }
    };

    const dates = readData("dates");
    const counts = readData("counts");
    const vehicleLabels = readData("vehicleLabels");
    const vehicleCounts = readData("vehicleCounts");
    const repairTypeLabels = readData("repairTypeLabels");
    const repairTypeCounts = readData("repairTypeCounts");
    const gridColor = "rgba(148, 163, 184, 0.2)";
    const labelColor = "#475569";

    const baseOptions = {
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { top: 4, right: 4, bottom: 0, left: 0 } },
      plugins: {
        legend: { labels: { color: labelColor, boxWidth: 10, boxHeight: 10 } },
        tooltip: {
          backgroundColor: "#0f172a",
          padding: 10,
          titleColor: "#ffffff",
          bodyColor: "#e2e8f0",
        },
      },
    };

    const hasValues = (values) => values.some((value) => Number(value) > 0);
    const shortenLabel = (label, max = 24) => {
      const text = String(label ?? "");
      return text.length > max ? `${text.slice(0, max - 1)}...` : text;
    };
    const showEmptyChart = (canvas, message) => {
      const container = canvas?.parentElement;
      if (!container) return;
      container.classList.add("chart-container--empty");
      const emptyState = document.createElement("div");
      emptyState.className = "motorpool-chart-empty";
      emptyState.textContent = message;
      container.appendChild(emptyState);
    };

    const dailyCanvas = byId("dailyRepairsChart");
    if (dailyCanvas && !hasValues(counts)) {
      showEmptyChart(dailyCanvas, "No repair trend data yet.");
    }
    if (dailyCanvas) new Chart(dailyCanvas.getContext("2d"), {
      type: "line",
      data: {
        labels: dates,
        datasets: [{
          label: "Repairs",
          data: counts,
          fill: true,
          backgroundColor: "rgba(15, 118, 110, 0.12)",
          borderColor: "#0f766e",
          borderWidth: 2,
          tension: 0.35,
          pointBackgroundColor: "#0f766e",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 3,
        }],
      },
      options: {
        ...baseOptions,
        scales: {
          x: { grid: { display: false }, ticks: { color: labelColor } },
          y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: labelColor } },
        },
        plugins: { ...baseOptions.plugins, legend: { display: false } },
      },
    });

    const vehicleCanvas = byId("repairsChart");
    if (vehicleCanvas && !hasValues(vehicleCounts)) {
      showEmptyChart(vehicleCanvas, "No completed vehicle repairs yet.");
    }
    if (vehicleCanvas) new Chart(vehicleCanvas.getContext("2d"), {
      type: "bar",
      data: {
        labels: vehicleLabels,
        datasets: [{
          label: "Completed repairs",
          data: vehicleCounts,
          backgroundColor: "#0f766e",
          borderRadius: 7,
          maxBarThickness: 36,
        }],
      },
      options: {
        ...baseOptions,
        indexAxis: "y",
        scales: {
          x: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: labelColor } },
          y: { grid: { display: false }, ticks: { color: labelColor, font: { size: 11 }, callback: (value) => shortenLabel(vehicleLabels[value]) } },
        },
        plugins: { ...baseOptions.plugins, legend: { display: false } },
      },
    });

    const repairTypesCanvas = byId("repairTypesChart");
    if (repairTypesCanvas && !hasValues(repairTypeCounts)) {
      showEmptyChart(repairTypesCanvas, "No repair type data yet.");
    }
    if (repairTypesCanvas) new Chart(repairTypesCanvas.getContext("2d"), {
      type: "bar",
      data: {
        labels: repairTypeLabels,
        datasets: [{
          label: "Repair records",
          data: repairTypeCounts,
          backgroundColor: "#14b8a6",
          borderRadius: 7,
          maxBarThickness: 26,
        }],
      },
      options: {
        ...baseOptions,
        indexAxis: "y",
        scales: {
          x: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: labelColor } },
          y: { grid: { display: false }, ticks: { color: labelColor, font: { size: 11 }, callback: (value) => shortenLabel(repairTypeLabels[value]) } },
        },
        plugins: { ...baseOptions.plugins, legend: { display: false } },
      },
    });
  }

  function initializeUrlToasts() {
    const params = new URLSearchParams(window.location.search);
    if (params.has("success")) showToast(params.get("success") || "Action completed successfully.");
    if (params.has("error")) showToast(params.get("error") || "The request could not be completed.", false);
    if (params.has("success") || params.has("error")) {
      history.replaceState({}, document.title, window.location.pathname);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    byId("repairSearch")?.addEventListener("input", function () {
      document.querySelectorAll(".table-group-divider tr").forEach((row) => {
        row.style.display = row.textContent.toLowerCase().includes(this.value.toLowerCase()) ? "" : "none";
      });
    });

    const completedSearch = byId("completedRepairSearch");
    const completedTable = byId("completedRepairsTable");
    if (completedSearch && completedTable) {
      completedSearch.addEventListener("input", () => filterTableRows(completedSearch, completedTable));
    }

    byId("updateVehicleModal")?.addEventListener("show.bs.modal", loadVehicleSelectionTable);
    document.addEventListener("hidden.bs.modal", cleanModalBackdrops);

    byId("vehicleSearchInput")?.addEventListener("input", function () {
      const term = this.value.trim().toLowerCase();
      renderVehicleSelectionTable(
        loadedVehicleData.filter((vehicle) =>
          Object.values(vehicle).some((value) => String(value ?? "").toLowerCase().includes(term))
        )
      );
    });

    byId("clearVehicleSearch")?.addEventListener("click", function () {
      byId("vehicleSearchInput").value = "";
      renderVehicleSelectionTable(loadedVehicleData);
      byId("vehicleSearchInput").focus();
    });

    byId("addVehicleForm")?.addEventListener("submit", function (event) {
      event.preventDefault();
      submitAjaxForm(this, "Vehicle added successfully.", {
        loadingLabel: "Saving...",
        reset: true,
        hideModalId: "addVehicleModal",
      });
    });

    byId("updateVehicleForm")?.addEventListener("submit", function (event) {
      event.preventDefault();
      submitAjaxForm(this, "Vehicle updated successfully.", {
        loadingLabel: "Updating...",
        afterSuccess: loadVehicleSelectionTable,
      });
    });

    byId("addRepairForm")?.addEventListener("submit", function (event) {
      event.preventDefault();
      submitAjaxForm(this, "Repair record added successfully.", {
        loadingLabel: "Saving...",
        validate: () => {
          if (selectedRepairTypeCount(".repair-type-checkbox") > 0) return true;
          showToast("Select at least one repair type.", false);
          return false;
        },
        afterSuccess: () => window.setTimeout(() => window.location.reload(), 450),
      });
    });

    byId("editRepairForm")?.addEventListener("submit", function (event) {
      event.preventDefault();
      submitAjaxForm(this, "Repair record updated successfully.", {
        loadingLabel: "Updating...",
        validate: () => {
          if (selectedRepairTypeCount(".edit-repair-type-checkbox") > 0) return true;
          showToast("Select at least one repair type.", false);
          return false;
        },
        hideModalId: "editRepairModal",
        afterSuccess: () => window.setTimeout(() => window.location.reload(), 450),
      });
    });

    document.querySelectorAll(".status-select").forEach((select) => {
      select.dataset.previousValue = select.value;
      select.addEventListener("change", async function () {
        const previousValue = this.dataset.previousValue || this.value;
        const repairId = this.closest(".status-form")?.dataset.repairId || "";
        this.disabled = true;
        try {
          const payload = await apiRequest("update_repair_status_motorpool.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({ repair_id: repairId, status: this.value }),
          });
          this.dataset.previousValue = this.value;
          showToast(payload.message || "Repair status updated.");
          if (["Completed", "Cancelled"].includes(this.value)) {
            window.setTimeout(() => window.location.reload(), 450);
          }
        } catch (error) {
          this.value = previousValue;
          showToast(error.message || "Unable to update status.", false);
        } finally {
          this.disabled = false;
        }
      });
    });

    document.addEventListener("click", function (event) {
      const editButton = event.target.closest(".edit-repair-btn");
      if (editButton) {
        editRepair(editButton.dataset.repairId);
        return;
      }

      const deleteButton = event.target.closest(".delete-repair-btn");
      if (deleteButton) {
        confirmAction("Delete this repair record? This action cannot be undone.", () => deleteRepair(deleteButton.dataset.repairId));
      }
    });

    const vehicleSelect = byId("vehicle_id");
    const officeField = byId("office");
    if (vehicleSelect && officeField && Array.isArray(window.motorpoolVehicleData)) {
      vehicleSelect.addEventListener("change", function () {
        const selectedVehicle = window.motorpoolVehicleData.find((vehicle) => vehicle.plate_no === this.value);
        officeField.value = selectedVehicle?.office || "";
      });
    }

    initializeCharts();
    initializeUrlToasts();
  });
})();
