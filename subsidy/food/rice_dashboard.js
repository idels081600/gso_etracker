const PAGE_SIZE = 10;
let allRiceRecords = [];
let filteredRiceRecords = [];
let currentPage = 1;
let recordsRequestController = null;
let recordsRequestSequence = 0;
let searchDebounceTimer = null;
let hasLoadedFullRecordSet = false;

async function loadRiceRecords(searchTerm = '') {
    const requestSequence = ++recordsRequestSequence;

    if (recordsRequestController) {
        recordsRequestController.abort();
    }

    recordsRequestController = new AbortController();
    const url = new URL('api_get_rice_records.php', window.location.href);
    if (searchTerm) {
        url.searchParams.set('q', searchTerm);
    }

    const tbody = document.getElementById('recordsTable');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-muted py-4">Searching rice household records...</td>
            </tr>
        `;
    }

    try {
        const response = await fetch(url, {
            signal: recordsRequestController.signal,
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) {
            throw new Error(`Records request failed with status ${response.status}`);
        }
        const data = await response.json();
        if (requestSequence === recordsRequestSequence && data.success) {
            const records = Array.isArray(data.data) ? data.data : [];
            if (!searchTerm) {
                allRiceRecords = records;
                hasLoadedFullRecordSet = true;
            }
            filteredRiceRecords = records;
            currentPage = 1;
            renderCurrentPage();
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        console.error('Error loading rice records:', error);
        if (requestSequence === recordsRequestSequence && tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center text-danger py-4">Unable to load rice household records. Please try again.</td>
                </tr>
            `;
        }
    }
}

const addHouseholdBarangay = document.getElementById('addHouseholdBarangay');
const addHouseholdLastNumber = document.getElementById('addHouseholdLastNumber');
const addHouseholdCodePreview = document.getElementById('addHouseholdCodePreview');
const addHouseholdName = document.getElementById('addHouseholdName');
const addHouseholdCodeHint = document.getElementById('addHouseholdCodeHint');
const saveHouseholdBtn = document.getElementById('saveHouseholdBtn');
const addHouseholdModalEl = document.getElementById('addHouseholdModal');
const tableSearch = document.getElementById('tableSearch');
const tablePaginationInfo = document.getElementById('tablePaginationInfo');
const pageIndicator = document.getElementById('pageIndicator');
const prevPageBtn = document.getElementById('prevPageBtn');
const nextPageBtn = document.getElementById('nextPageBtn');
let currentNextHouseholdCode = '';

function renderRiceTable(records, startIndex = 0) {
    const tbody = document.getElementById('recordsTable');
    tbody.innerHTML = '';

    if (!records.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-muted py-4">No rice household records found.</td>
            </tr>
        `;
        return;
    }

    records.forEach((record, index) => {
        const tr = document.createElement('tr');
        const statusBadge = record.status === 'Active'
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Not Active</span>';
        const previousWaveBadge = record.previous_wave_exists !== 1
            ? '<span class="badge bg-danger">Not Found</span>'
            : record.previous_wave_is_claimed === 1
                ? '<span class="badge bg-success">Claimed</span>'
                : '<span class="badge bg-secondary">Not Claimed</span>';
        const claimBadge = record.next_wave_exists !== 1
            ? '<span class="badge bg-secondary">Not in 2nd Batch</span>'
            : record.is_claimed === 1
                ? '<span class="badge bg-success">Claimed</span>'
                : '<span class="badge bg-warning text-dark">Unclaimed</span>';
        const claimDate = record.claimed_at
            ? new Date(record.claimed_at).toLocaleString()
            : 'N/A';
        const thirdClaimBadge = record.third_wave_exists !== 1
            ? '<span class="badge bg-secondary">Not in 3rd Batch</span>'
            : record.third_wave_is_claimed === 1
                ? '<span class="badge bg-success">Claimed</span>'
                : '<span class="badge bg-warning text-dark">Unclaimed</span>';
        const thirdClaimDate = record.third_wave_claimed_at
            ? new Date(record.third_wave_claimed_at).toLocaleString()
            : 'N/A';
        const fourthClaimBadge = record.fourth_wave_exists !== 1
            ? '<span class="badge bg-secondary">Not in 4th Batch</span>'
            : record.fourth_wave_is_claimed === 1
                ? '<span class="badge bg-success">Claimed</span>'
                : '<span class="badge bg-warning text-dark">Unclaimed</span>';
        const fourthClaimDate = record.fourth_wave_claimed_at
            ? new Date(record.fourth_wave_claimed_at).toLocaleString()
            : 'N/A';

        tr.innerHTML = `
            <td>${startIndex + index + 1}</td>
            <td><strong>${record.household_code}</strong></td>
            <td>${record.household_name}</td>
            <td>${statusBadge}</td>
            <td>${previousWaveBadge}</td>
            <td>${claimBadge}</td>
            <td>${claimDate}</td>
            <td>${thirdClaimBadge}</td>
            <td>${thirdClaimDate}</td>
            <td>${fourthClaimBadge}</td>
            <td>${fourthClaimDate}</td>
        `;
        tbody.appendChild(tr);
    });
}

function renderCurrentPage() {
    const totalRecords = filteredRiceRecords.length;
    const totalPages = Math.max(1, Math.ceil(totalRecords / PAGE_SIZE));
    currentPage = Math.min(Math.max(currentPage, 1), totalPages);

    const startIndex = (currentPage - 1) * PAGE_SIZE;
    const endIndex = Math.min(startIndex + PAGE_SIZE, totalRecords);
    const pageRecords = filteredRiceRecords.slice(startIndex, endIndex);

    renderRiceTable(pageRecords, startIndex);

    if (tablePaginationInfo) {
        if (totalRecords === 0) {
            tablePaginationInfo.textContent = 'Showing 0 to 0 of 0 records';
        } else {
            tablePaginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalRecords} records`;
        }
    }

    if (pageIndicator) {
        pageIndicator.textContent = `Page ${totalRecords === 0 ? 0 : currentPage} of ${totalRecords === 0 ? 0 : totalPages}`;
    }

    if (prevPageBtn) {
        prevPageBtn.disabled = currentPage <= 1 || totalRecords === 0;
    }

    if (nextPageBtn) {
        nextPageBtn.disabled = currentPage >= totalPages || totalRecords === 0;
    }
}

function applyTableSearch() {
    const searchTerm = (tableSearch?.value || '').trim();
    window.clearTimeout(searchDebounceTimer);

    if (hasLoadedFullRecordSet) {
        const normalizedSearchTerm = searchTerm.toLocaleLowerCase();
        filteredRiceRecords = !normalizedSearchTerm
            ? [...allRiceRecords]
            : allRiceRecords.filter((record) => [
                record.household_code,
                record.household_name,
                record.address,
                record.status
            ].join(' ').toLocaleLowerCase().includes(normalizedSearchTerm));
        currentPage = 1;
        renderCurrentPage();
        return;
    }

    searchDebounceTimer = window.setTimeout(() => loadRiceRecords(searchTerm), 250);
}

function resetAddHouseholdForm() {
    if (!addHouseholdBarangay) {
        return;
    }

    addHouseholdBarangay.value = '';
    addHouseholdLastNumber.value = 'Select barangay';
    addHouseholdCodePreview.value = 'Select barangay';
    addHouseholdName.value = '';
    addHouseholdCodeHint.textContent = 'The next available code will continue from the latest number in the selected barangay.';
    saveHouseholdBtn.disabled = false;
    currentNextHouseholdCode = '';
}

async function loadNextHouseholdCode() {
    const barangay = addHouseholdBarangay.value.trim();
    if (!barangay) {
        addHouseholdLastNumber.value = 'Select barangay';
        addHouseholdCodePreview.value = 'Select barangay';
        addHouseholdCodeHint.textContent = 'The next available code will continue from the latest number in the selected barangay.';
        currentNextHouseholdCode = '';
        return;
    }

    addHouseholdLastNumber.value = 'Loading...';
    addHouseholdCodePreview.value = 'Loading...';
    addHouseholdCodeHint.textContent = 'Checking the latest household number for this barangay...';

    try {
        const response = await fetch(`api_get_rice_next_code.php?barangay=${encodeURIComponent(barangay)}`);
        const data = await response.json();

        if (data.success) {
            currentNextHouseholdCode = data.next_code;
            addHouseholdLastNumber.value = data.last_number;
            addHouseholdCodePreview.value = data.next_code;
            addHouseholdCodeHint.textContent = `Selected barangay: ${barangay}. The new household will continue after number ${data.last_number}.`;
        } else {
            currentNextHouseholdCode = '';
            addHouseholdLastNumber.value = 'Unavailable';
            addHouseholdCodePreview.value = 'Unable to generate';
            addHouseholdCodeHint.textContent = data.message || 'Unable to load the next household code.';
        }
    } catch (error) {
        console.error('Error loading next rice code:', error);
        currentNextHouseholdCode = '';
        addHouseholdLastNumber.value = 'Unavailable';
        addHouseholdCodePreview.value = 'Unable to generate';
        addHouseholdCodeHint.textContent = 'Unable to load the next household code right now.';
    }
}

if (tableSearch) {
    tableSearch.addEventListener('input', applyTableSearch);
}

if (prevPageBtn) {
    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage -= 1;
            renderCurrentPage();
        }
    });
}

if (nextPageBtn) {
    nextPageBtn.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(filteredRiceRecords.length / PAGE_SIZE));
        if (currentPage < totalPages) {
            currentPage += 1;
            renderCurrentPage();
        }
    });
}

if (addHouseholdBarangay) {
    addHouseholdBarangay.addEventListener('change', loadNextHouseholdCode);
}

if (addHouseholdModalEl) {
    addHouseholdModalEl.addEventListener('hidden.bs.modal', resetAddHouseholdForm);
}

if (saveHouseholdBtn) {
    saveHouseholdBtn.addEventListener('click', async () => {
        const barangay = addHouseholdBarangay.value.trim();
        const householdName = addHouseholdName.value.trim();

        if (!barangay) {
            alert('Please select a barangay.');
            addHouseholdBarangay.focus();
            return;
        }

        if (!householdName) {
            alert('Please enter the household name.');
            addHouseholdName.focus();
            return;
        }

        if (!currentNextHouseholdCode) {
            alert('Please wait for the code preview before saving.');
            return;
        }

        saveHouseholdBtn.disabled = true;

        try {
            const response = await fetch('api_add_rice_household.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    barangay,
                    household_name: householdName
                })
            });

            const data = await response.json();
            if (data.success) {
                alert(`${data.message} New code: ${data.household_code}`);
                const modal = bootstrap.Modal.getInstance(addHouseholdModalEl);
                if (modal) {
                    modal.hide();
                }
                window.location.reload();
            } else {
                alert(data.message || 'Unable to add the household.');
            }
        } catch (error) {
            console.error('Error adding rice household:', error);
            alert('Error adding household. Please try again.');
        } finally {
            saveHouseholdBtn.disabled = false;
        }
    });
}

const dashboardWaveButtons = document.querySelectorAll('[data-dashboard-wave]');
const metricTotalHouseholds = document.getElementById('metricTotalHouseholds');
const metricClaimed = document.getElementById('metricClaimed');
const metricNotClaimed = document.getElementById('metricNotClaimed');
const metricTotalDescription = document.getElementById('metricTotalDescription');
const metricClaimedDescription = document.getElementById('metricClaimedDescription');
const metricNotClaimedDescription = document.getElementById('metricNotClaimedDescription');
const DASHBOARD_WAVE_SETTING_KEY = 'rice_dashboard_wave';

function getSavedDashboardWave() {
    try {
        return window.localStorage.getItem(DASHBOARD_WAVE_SETTING_KEY) || 'first_wave';
    } catch (error) {
        return 'first_wave';
    }
}

function saveDashboardWave(wave) {
    try {
        window.localStorage.setItem(DASHBOARD_WAVE_SETTING_KEY, wave);
    } catch (error) {
        // The toggle still works when mobile privacy settings block local storage.
    }
}

function renderDashboardWave(wave) {
    const selectedWave = ['first_wave', 'next_wave', 'third_wave', 'fourth_wave'].includes(wave) ? wave : 'first_wave';

    dashboardWaveButtons.forEach((button) => {
        const isActive = button.dataset.dashboardWave === selectedWave;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    const metrics = window.RICE_DASHBOARD_METRICS?.[selectedWave];
    if (!metrics) {
        return;
    }

    const formatter = new Intl.NumberFormat();
    metricTotalHouseholds.textContent = formatter.format(metrics.total || 0);
    metricClaimed.textContent = formatter.format(metrics.claimed || 0);
    metricNotClaimed.textContent = formatter.format(metrics.not_claimed || 0);

    const batchLabel = { first_wave: 'First-batch', next_wave: 'Second-batch', third_wave: 'Third-batch', fourth_wave: 'Fourth-batch' }[selectedWave];
    metricTotalDescription.textContent = `${batchLabel} household list`;
    metricClaimedDescription.textContent = `${batchLabel} households already claimed`;
    metricNotClaimedDescription.textContent = `Active ${batchLabel.toLowerCase()} households not yet claimed`;
    const activationStatus = document.getElementById('batchActivationStatus');
    const isLocked = selectedWave === 'fourth_wave' && metrics.active === false;
    activationStatus.className = `badge ${isLocked ? 'text-bg-warning' : 'text-bg-success'}`;
    activationStatus.textContent = isLocked ? 'Claiming Locked' : 'Active';
    document.getElementById('claimedPdfBatch').value = selectedWave;
    ['voucherBarangayBatch', 'voucherCodeBatch', 'attendanceBarangayBatch', 'attendanceSectorBatch'].forEach((id) => {
        document.getElementById(id).value = ['third_wave', 'fourth_wave'].includes(selectedWave) ? selectedWave : 'next_wave';
    });

    document.querySelectorAll('[data-rice-wave-export]').forEach((link) => {
        const url = new URL(link.href);
        url.searchParams.set('wave', selectedWave);
        link.href = url.href;
        link.title = `${batchLabel} CSV export`;
    });
    saveDashboardWave(selectedWave);
}

dashboardWaveButtons.forEach((button) => {
    button.addEventListener('click', () => {
        renderDashboardWave(button.dataset.dashboardWave);
    });
});

function initializeRiceDashboard() {
    renderDashboardWave(getSavedDashboardWave());
    loadRiceRecords();
    resetAddHouseholdForm();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRiceDashboard, { once: true });
} else {
    initializeRiceDashboard();
}
