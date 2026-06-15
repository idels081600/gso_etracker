(function () {
    "use strict";

    const table = document.getElementById("table_tent");
    if (!table) return;

    const rows = Array.from(table.tBodies[0].rows);
    const searchInput = document.getElementById("search-input");
    const filters = Array.from(document.querySelectorAll(".tracking-filter"));
    const dateFromInput = document.getElementById("date-filter-from");
    const dateToInput = document.getElementById("date-filter-to");
    const clearDateButton = document.getElementById("clear-date-filter");
    const dateFilterError = document.getElementById("date-filter-error");
    const summary = document.getElementById("trackingPaginationSummary");
    const pagination = document.getElementById("trackingPaginationButtons");
    const emptyState = document.getElementById("trackingEmptyState");
    const perPage = 10;
    let activeFilter = "all";
    let currentPage = 1;

    function dateRangeIsValid() {
        const from = dateFromInput?.value || "";
        const to = dateToInput?.value || "";
        const isValid = !from || !to || from <= to;

        dateFromInput?.setAttribute("aria-invalid", isValid ? "false" : "true");
        dateToInput?.setAttribute("aria-invalid", isValid ? "false" : "true");
        dateFromInput?.classList.toggle("is-invalid", !isValid);
        dateToInput?.classList.toggle("is-invalid", !isValid);
        if (dateFilterError) {
            dateFilterError.textContent = isValid ? "" : "The start date must be on or before the end date.";
        }
        if (clearDateButton) clearDateButton.disabled = !from && !to;
        return isValid;
    }

    function matchingRows() {
        const query = (searchInput?.value || "").trim().toLowerCase();
        const from = dateFromInput?.value || "";
        const to = dateToInput?.value || "";
        const validDateRange = dateRangeIsValid();
        return rows.filter((row) => {
            const status = row.dataset.status || "";
            const installedDate = row.dataset.installedDate || "";
            const matchesFilter = activeFilter === "all" || status === activeFilter;
            const matchesSearch = !query || row.textContent.toLowerCase().includes(query);
            const matchesDateRange = !validDateRange
                || (!from && !to)
                || (installedDate !== "" && (!from || installedDate >= from) && (!to || installedDate <= to));
            return matchesFilter && matchesSearch && matchesDateRange;
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

    [dateFromInput, dateToInput].forEach((input) => {
        input?.addEventListener("change", () => {
            currentPage = 1;
            render();
        });
    });

    clearDateButton?.addEventListener("click", () => {
        dateFromInput.value = "";
        dateToInput.value = "";
        currentPage = 1;
        render();
        dateFromInput.focus();
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
