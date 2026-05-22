document.addEventListener("DOMContentLoaded", function () {
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const sidebarToggle = document.getElementById("sidebarToggle");
  const sidebarOverlay = document.getElementById("sidebarOverlay");

  function closeMobileSidebar() {
    document.body.classList.remove("sidebar-mobile-open");
    if (sidebarToggle) {
      sidebarToggle.setAttribute("aria-expanded", "false");
    }
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", function () {
      if (window.innerWidth <= 900) {
        const isOpen = document.body.classList.toggle("sidebar-mobile-open");
        sidebarToggle.setAttribute("aria-expanded", String(isOpen));
      } else {
        const isCollapsed = document.body.classList.toggle("sidebar-collapsed");
        sidebarToggle.setAttribute("aria-expanded", String(!isCollapsed));
      }
    });
  }

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener("click", closeMobileSidebar);
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      closeMobileSidebar();
    }
  });

  const revealItems = document.querySelectorAll(".reveal-on-load");
  revealItems.forEach(function (item, index) {
    if (prefersReducedMotion) {
      item.classList.add("is-visible");
      return;
    }

    window.setTimeout(function () {
      item.classList.add("is-visible");
    }, index * 55);
  });

  if (prefersReducedMotion) {
    drawLineCharts();
    initializeWorkspaceInteractions();
    return;
  }

  document.querySelectorAll(".count-up").forEach(function (item) {
    const target = Number(item.dataset.count || item.textContent.replace(/,/g, ""));
    if (!Number.isFinite(target) || target <= 0) {
      item.textContent = "0";
      return;
    }

    const duration = 650;
    const startTime = performance.now();

    function tick(now) {
      const progress = Math.min((now - startTime) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(target * eased);
      item.textContent = current.toLocaleString();

      if (progress < 1) {
        requestAnimationFrame(tick);
      }
    }

    requestAnimationFrame(tick);
  });

  drawLineCharts();
  initializeWorkspaceInteractions();

  let resizeTimer;
  window.addEventListener("resize", function () {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(drawLineCharts, 120);
  });

  function parseChartData(canvas) {
    try {
      return {
        labels: JSON.parse(canvas.dataset.labels || "[]"),
        values: JSON.parse(canvas.dataset.values || "[]").map(Number),
      };
    } catch (error) {
      return { labels: [], values: [] };
    }
  }

  function drawLineCharts() {
    document.querySelectorAll(".master-line-chart").forEach(function (canvas) {
      const data = parseChartData(canvas);
      drawLineChart(canvas, data.labels, data.values);
    });
  }

  function drawLineChart(canvas, labels, values) {
    const context = canvas.getContext("2d");
    if (!context) return;

    const rect = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    const width = Math.max(320, Math.floor(rect.width || canvas.width));
    const height = Math.max(160, Math.floor(rect.height || canvas.height));

    canvas.width = width * ratio;
    canvas.height = height * ratio;
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, width, height);

    const padding = { top: 18, right: 18, bottom: 38, left: 34 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const maxValue = Math.max(1, ...values);
    const pointCount = Math.max(values.length, 1);

    context.font = "700 11px Arial, Helvetica, sans-serif";
    context.textBaseline = "middle";

    for (let i = 0; i <= 3; i += 1) {
      const y = padding.top + (chartHeight / 3) * i;
      const value = Math.round(maxValue - (maxValue / 3) * i);

      context.strokeStyle = "#e8edf3";
      context.lineWidth = 1;
      context.beginPath();
      context.moveTo(padding.left, y);
      context.lineTo(width - padding.right, y);
      context.stroke();

      context.fillStyle = "#667085";
      context.textAlign = "right";
      context.fillText(String(value), padding.left - 8, y);
    }

    const points = values.map(function (value, index) {
      const x = padding.left + (pointCount === 1 ? chartWidth / 2 : (chartWidth / (pointCount - 1)) * index);
      const y = padding.top + chartHeight - (value / maxValue) * chartHeight;
      return { x, y, value };
    });

    if (!points.length) {
      context.fillStyle = "#667085";
      context.textAlign = "center";
      context.fillText("No trend data", width / 2, height / 2);
      return;
    }

    const gradient = context.createLinearGradient(0, padding.top, 0, height - padding.bottom);
    gradient.addColorStop(0, "rgba(21, 128, 61, 0.18)");
    gradient.addColorStop(1, "rgba(21, 128, 61, 0.02)");

    context.beginPath();
    points.forEach(function (point, index) {
      if (index === 0) {
        context.moveTo(point.x, point.y);
      } else {
        context.lineTo(point.x, point.y);
      }
    });
    context.lineTo(points[points.length - 1].x, height - padding.bottom);
    context.lineTo(points[0].x, height - padding.bottom);
    context.closePath();
    context.fillStyle = gradient;
    context.fill();

    context.beginPath();
    points.forEach(function (point, index) {
      if (index === 0) {
        context.moveTo(point.x, point.y);
      } else {
        context.lineTo(point.x, point.y);
      }
    });
    context.strokeStyle = "#15803d";
    context.lineWidth = 3;
    context.lineJoin = "round";
    context.lineCap = "round";
    context.stroke();

    points.forEach(function (point) {
      context.beginPath();
      context.arc(point.x, point.y, 4, 0, Math.PI * 2);
      context.fillStyle = "#fff";
      context.fill();
      context.strokeStyle = "#15803d";
      context.lineWidth = 2;
      context.stroke();

      context.fillStyle = "#111827";
      context.textAlign = "center";
      context.fillText(String(point.value), point.x, point.y - 14);
    });

    labels.forEach(function (label, index) {
      const point = points[index];
      if (!point) return;
      const shortLabel = String(label).slice(0, 6);
      context.fillStyle = "#667085";
      context.textAlign = "center";
      context.fillText(shortLabel, point.x, height - 17);
    });
  }

  function initializeWorkspaceInteractions() {
    initTabs();
    initSearch();
    initModals();
    initTransportationStatus();
    initMotorpoolActions();
  }

  function initTabs() {
    document.querySelectorAll("[data-master-tabs] button").forEach(function (button) {
      button.addEventListener("click", function () {
        const targetId = button.dataset.tabTarget;
        button.closest("[data-master-tabs]").querySelectorAll("button").forEach(function (tab) {
          tab.classList.toggle("active", tab === button);
        });
        document.querySelectorAll(".master-table").forEach(function (table) {
          table.classList.toggle("is-active", table.id === targetId);
        });
        applyMasterSearch();
      });
    });
  }

  function initSearch() {
    document.querySelectorAll(".master-table-search, .master-start-date, .master-end-date").forEach(function (input) {
      input.addEventListener("input", applyMasterSearch);
      input.addEventListener("change", applyMasterSearch);
    });
  }

  function parseDate(value) {
    if (!value) return null;
    const parsed = new Date(String(value).replace(/\//g, "-"));
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }

  function applyMasterSearch() {
    const table = document.querySelector(".master-table.is-active");
    if (!table) return;
    const card = table.closest(".workspace-card") || document;
    const query = (card.querySelector(".master-table-search")?.value || "").toLowerCase();
    const start = parseDate(card.querySelector(".master-start-date")?.value);
    const end = parseDate(card.querySelector(".master-end-date")?.value);
    if (end) end.setHours(23, 59, 59, 999);
    const dateColumn = Number(table.dataset.dateColumn || -1);

    table.querySelectorAll(".master-row:not(.master-head)").forEach(function (row) {
      const cells = Array.from(row.children);
      const textMatch = row.textContent.toLowerCase().includes(query);
      let dateMatch = true;
      if (dateColumn >= 0 && (start || end)) {
        const rowDate = parseDate(cells[dateColumn]?.textContent.trim());
        dateMatch = Boolean(rowDate) && (!start || rowDate >= start) && (!end || rowDate <= end);
      }
      row.style.display = textMatch && dateMatch ? "" : "none";
    });
  }

  function initModals() {
    document.querySelectorAll("[data-modal-open]").forEach(function (button) {
      button.addEventListener("click", function () {
        const modal = document.getElementById(button.dataset.modalOpen);
        if (!modal) return;
        if (button.classList.contains("edit-repair-btn")) populateEditRepair(button);
        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
      });
    });

    document.querySelectorAll("[data-modal-close], .master-modal").forEach(function (item) {
      item.addEventListener("click", function (event) {
        if (event.target !== item && !event.target.matches("[data-modal-close]")) return;
        const modal = item.classList.contains("master-modal") ? item : item.closest(".master-modal");
        modal?.classList.remove("is-open");
        modal?.setAttribute("aria-hidden", "true");
      });
    });
  }

  function postForm(url, data) {
    return fetch(url, { method: "POST", body: data, headers: { "X-Requested-With": "XMLHttpRequest" } })
      .then(function (response) { return response.json(); });
  }

  function initTransportationStatus() {
    document.querySelectorAll(".transport-status-select").forEach(function (select) {
      select.addEventListener("change", function () {
        const data = new FormData();
        data.append("id", select.dataset.recordId);
        data.append("status", select.value);
        select.classList.add("is-saving");
        postForm("transportation_status.php", data)
          .then(function (payload) { if (!payload.success) alert(payload.message || "Unable to update status."); })
          .catch(function () { alert("Unable to update status."); })
          .finally(function () { select.classList.remove("is-saving"); });
      });
    });
  }

  function populateEditRepair(button) {
    const map = {
      edit_repair_id: "id",
      edit_vehicle_id: "plate",
      edit_repair_date: "date",
      edit_repair_type: "type",
      edit_mileage: "mileage",
      edit_parts_replaced: "parts",
      edit_cost: "cost",
      edit_office: "office",
      edit_notes: "notes",
      edit_status: "status",
    };
    Object.keys(map).forEach(function (id) {
      const field = document.getElementById(id);
      if (field) field.value = button.dataset[map[id]] || "";
    });
  }

  function initMotorpoolActions() {
    document.querySelectorAll(".repair-status-select").forEach(function (select) {
      select.addEventListener("change", function () {
        const data = new FormData();
        data.append("repair_id", select.dataset.recordId);
        data.append("status", select.value);
        select.classList.add("is-saving");
        postForm("../update_repair_status_motorpool.php", data)
          .then(function (payload) { if (!payload.success) alert(payload.message || "Unable to update repair."); })
          .catch(function () { alert("Unable to update repair."); })
          .finally(function () { select.classList.remove("is-saving"); });
      });
    });

    document.querySelectorAll(".delete-repair-btn").forEach(function (button) {
      button.addEventListener("click", function () {
        if (!confirm("Delete this repair record?")) return;
        const data = new FormData();
        data.append("repair_id", button.dataset.recordId);
        postForm("../delete_repair_motorpool.php", data)
          .then(function (payload) {
            if (payload.success) button.closest(".master-row")?.remove();
            else alert(payload.message || "Unable to delete repair.");
          })
          .catch(function () { alert("Unable to delete repair."); });
      });
    });

    const editForm = document.getElementById("editRepairForm");
    if (editForm) {
      editForm.addEventListener("submit", function (event) {
        event.preventDefault();
        postForm("../update_repair_motorpool.php", new FormData(editForm))
          .then(function (payload) {
            if (payload.success) window.location.reload();
            else alert(payload.message || "Unable to update repair.");
          })
          .catch(function () { alert("Unable to update repair."); });
      });
    }
  }

});
