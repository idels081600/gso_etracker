(function () {
    "use strict";

    const table = document.getElementById("table_tent");
    if (!table) return;

    const rows = Array.from(table.tBodies[0].rows);
    const searchInput = document.getElementById("search-input");
    const filters = Array.from(document.querySelectorAll(".tracking-filter"));

    const summary = document.getElementById("trackingPaginationSummary");
    const pagination = document.getElementById("trackingPaginationButtons");
    const emptyState = document.getElementById("trackingEmptyState");
    const perPage = 10;
    let activeFilter = "all";
    let currentPage = 1;

    function matchingRows() {
        const query = (searchInput?.value || "").trim().toLowerCase();
        return rows.filter((row) => {
            const status = row.dataset.status || "";
            const matchesFilter = activeFilter === "all" || status === activeFilter;
            const matchesSearch = !query || row.textContent.toLowerCase().includes(query);
            return matchesFilter && matchesSearch;
        });
    }
    function pageButton(label, page, disabled, active) {
        const button = document.createElement("button");
        button.type = "button";
        button.textContent = label;
        button.disabled = disabled;
        button.classList.toggle("is-active", active);
        button.setAttribute("aria-label", label === "Previous" || label === "Next" ? label + " page" : "Page " + label);
        if (active) button.setAttribute("aria-current", "page");
        button.addEventListener("click", () => {
            currentPage = page;
            render();
        });
        return button;
    }

    function render() {
        const matches = matchingRows();
        const totalPages = Math.max(1, Math.ceil(matches.length / perPage));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * perPage;
        const visibleRows = new Set(matches.slice(start, start + perPage));

        rows.forEach((row) => {
            row.style.display = visibleRows.has(row) ? "" : "none";
        });

        emptyState.hidden = matches.length !== 0;
        table.hidden = matches.length === 0;
        const first = matches.length ? start + 1 : 0;
        const last = Math.min(start + perPage, matches.length);
        summary.textContent = `Showing ${first}-${last} of ${matches.length} records`;

        pagination.replaceChildren();
        pagination.append(pageButton("Previous", Math.max(1, currentPage - 1), currentPage === 1, false));
        for (let page = 1; page <= totalPages; page += 1) {
            pagination.append(pageButton(String(page), page, false, page === currentPage));
        }
        pagination.append(pageButton("Next", Math.min(totalPages, currentPage + 1), currentPage === totalPages, false));
    }

    filters.forEach((filter) => {
        filter.addEventListener("click", () => {
            activeFilter = filter.dataset.filter || "all";
            currentPage = 1;
            filters.forEach((item) => {
                const active = item === filter;
                item.classList.toggle("is-active", active);
                item.setAttribute("aria-pressed", active ? "true" : "false");
            });
            render();
        });
    });

    searchInput?.addEventListener("input", () => {
        currentPage = 1;
        window.setTimeout(render, 0);
    });

    const rangeModal = document.getElementById("dateRangeViewerModal");
    const rangeForm = document.getElementById("dateRangeViewerForm");
    const rangeFrom = document.getElementById("range-viewer-from");
    const rangeTo = document.getElementById("range-viewer-to");
    const rangeClear = document.getElementById("range-viewer-clear");
    const rangeSubmit = document.getElementById("range-viewer-submit");
    const rangeError = document.getElementById("range-viewer-error");
    const rangeFeedback = document.getElementById("range-viewer-feedback");
    const rangeResults = document.getElementById("range-viewer-results");
    const rangeBody = document.getElementById("range-viewer-table-body");
    const rangeSummary = document.getElementById("range-viewer-summary");
    const rangeCaption = document.getElementById("range-viewer-caption");
    const rangePrint = document.getElementById("range-viewer-print");
    const rangeExport = document.getElementById("range-viewer-export");
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";
    let rangeRecords = [];
    let loadedRange = null;

    function setRangeFeedback(kind, title, detail) {
        if (!rangeFeedback) return;
        rangeFeedback.replaceChildren();
        const state = document.createElement("div");
        state.className = "tracking-range-placeholder is-" + kind;
        if (kind === "loading") {
            const spinner = document.createElement("span");
            spinner.className = "spinner-border spinner-border-sm";
            spinner.setAttribute("aria-hidden", "true");
            state.append(spinner);
        } else {
            const icon = document.createElement("i");
            icon.className = kind === "error" ? "fas fa-exclamation-circle" : "far fa-calendar-check";
            icon.setAttribute("aria-hidden", "true");
            state.append(icon);
        }
        const strong = document.createElement("strong");
        strong.textContent = title;
        const span = document.createElement("span");
        span.textContent = detail;
        state.append(strong, span);
        rangeFeedback.append(state);
        rangeFeedback.hidden = false;
    }

    function setRangeValidation(message = "") {
        if (rangeError) rangeError.textContent = message;
        const invalid = message !== "";
        rangeFrom?.classList.toggle("is-invalid", invalid);
        rangeTo?.classList.toggle("is-invalid", invalid);
        rangeFrom?.setAttribute("aria-invalid", invalid ? "true" : "false");
        rangeTo?.setAttribute("aria-invalid", invalid ? "true" : "false");
        return !invalid;
    }

    function validateRange() {
        const from = rangeFrom?.value || "";
        const to = rangeTo?.value || "";
        if (!from || !to) return setRangeValidation("Choose both the start and end dates.");
        if (from > to) return setRangeValidation("The start date must be on or before the end date.");
        return setRangeValidation();
    }

    function formatRangeDate(value) {
        if (!value) return "Not set";
        const date = new Date(value + "T00:00:00");
        return Number.isNaN(date.getTime())
            ? value
            : date.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
    }

    function daysOnField(record) {
        if (!record.installation_date || record.status === "Pending") return 0;
        const start = new Date(record.installation_date + "T00:00:00");
        const endValue = record.status === "Retrieved" && record.retrieval_date
            ? record.retrieval_date
            : new Date().toISOString().slice(0, 10);
        const end = new Date(endValue + "T00:00:00");
        return Math.max(0, Math.round((end - start) / 86400000));
    }

    function displayedStatus(record) {
        const today = new Date().toISOString().slice(0, 10);
        return record.status === "For Retrieval" && record.retrieval_date && record.retrieval_date < today
            ? "Overdue"
            : (record.status || "Pending");
    }

    function appendRangeCell(row, label, value, className = "") {
        const cell = document.createElement("td");
        cell.dataset.label = label;
        if (className) cell.className = className;
        const content = document.createElement("div");
        content.className = "tracking-range-cell-value";
        content.textContent = value;
        cell.append(content);
        row.append(cell);
    }

    function renderRangeRecords(records, from, to) {
        rangeBody?.replaceChildren();
        records.forEach((record) => {
            const row = document.createElement("tr");
            const tentLabel = record.tent_no || "Unassigned";
            const tentCount = record.no_of_tents + " tent" + (record.no_of_tents === 1 ? "" : "s");
            appendRangeCell(row, "Tent No.", tentLabel + " · " + tentCount);
            appendRangeCell(row, "Requestor", record.name || "Not set");
            appendRangeCell(row, "Contact", record.contact || "Not set");
            appendRangeCell(row, "Location", record.location || "Not set");
            appendRangeCell(row, "Purpose", record.purpose || "Not set");
            appendRangeCell(row, "Installed", formatRangeDate(record.installation_date));
            appendRangeCell(row, "Retrieval", formatRangeDate(record.retrieval_date));
            appendRangeCell(row, "Days", String(daysOnField(record)));
            const status = displayedStatus(record);
            appendRangeCell(row, "Status", status, "range-status status-" + status.toLowerCase().replace(/[^a-z0-9]+/g, "-"));
            rangeBody?.append(row);
        });

        rangeRecords = records;
        loadedRange = { from, to };
        if (rangeSummary) rangeSummary.textContent = records.length + " record" + (records.length === 1 ? "" : "s");
        if (rangeCaption) rangeCaption.textContent = formatRangeDate(from) + " to " + formatRangeDate(to);
        if (rangeFeedback) rangeFeedback.hidden = records.length > 0;
        if (rangeResults) rangeResults.hidden = records.length === 0;
        if (rangePrint) rangePrint.disabled = records.length === 0;
        if (rangeExport) rangeExport.disabled = records.length === 0;
        if (records.length === 0) {
            setRangeFeedback("empty", "No records in this date range", "Choose another installation date range and try again.");
        }
    }

    function csvValue(value) {
        return '"' + String(value ?? "").replace(/"/g, '""') + '"';
    }

    rangeForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!validateRange()) {
            rangeFrom?.focus();
            return;
        }

        rangeSubmit.disabled = true;
        rangeClear.disabled = true;
        rangeResults.hidden = true;
        setRangeFeedback("loading", "Loading records", "Fetching every matching deployment without pagination.");

        try {
            const response = await fetch("fetch_date_range.php", {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-CSRF-Token": csrfToken,
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: new URLSearchParams({ from: rangeFrom.value, to: rangeTo.value }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || "Unable to load records.");
            renderRangeRecords(Array.isArray(result.data?.records) ? result.data.records : [], result.data.from, result.data.to);
        } catch (error) {
            rangeRecords = [];
            loadedRange = null;
            rangeResults.hidden = true;
            setRangeFeedback("error", "Records could not be loaded", error.message || "Please try again.");
        } finally {
            rangeSubmit.disabled = false;
            rangeClear.disabled = !rangeFrom.value && !rangeTo.value;
        }
    });

    [rangeFrom, rangeTo].forEach((input) => {
        input?.addEventListener("change", () => {
            setRangeValidation();
            if (rangeClear) rangeClear.disabled = !rangeFrom.value && !rangeTo.value;
        });
    });

    rangeClear?.addEventListener("click", () => {
        rangeForm?.reset();
        setRangeValidation();
        rangeRecords = [];
        loadedRange = null;
        rangeResults.hidden = true;
        rangeClear.disabled = true;
        setRangeFeedback("empty", "Select an installation date range", "All matching records will appear here without pagination.");
        rangeFrom?.focus();
    });

    rangeModal?.addEventListener("shown.bs.modal", () => rangeFrom?.focus());

    rangeExport?.addEventListener("click", () => {
        if (!rangeRecords.length || !loadedRange) return;
        const headers = ["Tent No.", "Quantity", "Requestor", "Contact", "Location", "Address", "Purpose", "Installation Date", "Retrieval Date", "Days on Field", "Status"];
        const lines = [headers.map(csvValue).join(",")];
        rangeRecords.forEach((record) => {
            lines.push([
                record.tent_no || "Unassigned",
                record.no_of_tents,
                record.name,
                record.contact,
                record.location,
                record.address,
                record.purpose,
                record.installation_date,
                record.retrieval_date,
                daysOnField(record),
                displayedStatus(record),
            ].map(csvValue).join(","));
        });
        const blob = new Blob(["\uFEFF" + lines.join("\r\n")], { type: "text/csv;charset=utf-8" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "tent-installations-" + loadedRange.from + "-to-" + loadedRange.to + ".csv";
        document.body.append(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    });

    rangePrint?.addEventListener("click", () => {
        if (!rangeRecords.length || !loadedRange) return;
        const printWindow = window.open("", "_blank");
        if (!printWindow) {
            setRangeFeedback("error", "Print window was blocked", "Allow pop-ups for this site and try again.");
            return;
        }
        printWindow.opener = null;
        const tableClone = document.querySelector(".tracking-range-table")?.cloneNode(true);
        const documentTitle = "Tent installations: " + formatRangeDate(loadedRange.from) + " to " + formatRangeDate(loadedRange.to);
        printWindow.document.title = documentTitle;
        const style = printWindow.document.createElement("style");
        style.textContent = "body{font:12px Arial,sans-serif;color:#172033;padding:24px}h1{font-size:20px;margin:0 0 4px}p{color:#526079;margin:0 0 20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d8dee8;padding:7px;text-align:left;vertical-align:top}th{background:#f2f5f8;font-size:10px;text-transform:uppercase}@media print{body{padding:0}}";
        const heading = printWindow.document.createElement("h1");
        heading.textContent = "Tent Installation Records";
        const caption = printWindow.document.createElement("p");
        caption.textContent = documentTitle + " · " + rangeRecords.length + " record" + (rangeRecords.length === 1 ? "" : "s");
        printWindow.document.head.append(style);
        printWindow.document.body.append(heading, caption);
        if (tableClone) printWindow.document.body.append(tableClone);
        printWindow.focus();
        printWindow.print();
    });
    document.getElementById("dismissOverdueAlert")?.addEventListener("click", (event) => {
        event.currentTarget.closest(".tracking-alert")?.remove();
    });

    const installForm = document.getElementById("installTentForm");
    if (installForm) {
        const location = installForm.querySelector("#Location");
        const otherLocation = installForm.querySelector("#other");
        const otherLocationField = installForm.querySelector("#otherLocationField");
        const submitButton = installForm.querySelector("#installTentSubmit");

        const toggleOtherLocation = () => {
            const isOther = location.value === "Other";
            otherLocationField.hidden = !isOther;
            otherLocation.required = isOther;
            if (!isOther) otherLocation.value = "";
        };

        location.addEventListener("change", toggleOtherLocation);

        installForm.addEventListener("submit", (event) => {
            if (!installForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                installForm.classList.add("was-validated");
                installForm.querySelector(":invalid")?.focus();
                return;
            }
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Submitting...';
        });

        document.getElementById("detailsModal")?.addEventListener("shown.bs.modal", () => {
            installForm.querySelector("#name")?.focus();
        });
        toggleOtherLocation();
    }

    table.addEventListener("change", (event) => {
        const select = event.target.closest(".status-dropdown");
        if (!select) return;
        const row = select.closest("tr");
        const badge = row?.querySelector(".tracking-status");
        const status = select.value || "Pending";
        const filterStatus = status.toLowerCase().replace(/\s+/g, "-");
        if (row) row.dataset.status = filterStatus;
        if (badge) {
            badge.className = `tracking-status status-${filterStatus}`;
            badge.textContent = status;
        }
        window.setTimeout(render, 0);
    });

    document.addEventListener("click", async (event) => {
        const button = event.target.closest(".mark-retrieved-button");
        if (!button) return;
        if (!window.confirm("Mark this tent request as retrieved?")) return;

        button.disabled = true;
        try {
            const response = await fetch("../update_status.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
                body: new URLSearchParams({ id: button.dataset.id, status: "Retrieved" }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || "Unable to update status.");
            window.location.reload();
        } catch (error) {
            window.alert(error.message || "Unable to update status.");
            button.disabled = false;
        }
    });

    render();
})();

