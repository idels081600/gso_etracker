const PAGE_SIZE = 10;
let allRiceRecords = [];
let filteredRiceRecords = [];
let currentPage = 1;

async function loadRiceRecords() {
    try {
        const response = await fetch('api_get_rice_records.php');
        const data = await response.json();
        if (data.success) {
            allRiceRecords = Array.isArray(data.data) ? data.data : [];
            filteredRiceRecords = [...allRiceRecords];
            currentPage = 1;
            renderCurrentPage();
        }
    } catch (error) {
        console.error('Error loading rice records:', error);
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
                <td colspan="7" class="text-center text-muted py-4">No next-wave rice household records found.</td>
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
            ? '<span class="badge bg-secondary">Not in Next Wave</span>'
            : record.is_claimed === 1
                ? '<span class="badge bg-success">Claimed</span>'
                : '<span class="badge bg-warning text-dark">Unclaimed</span>';
        const claimDate = record.claimed_at
            ? new Date(record.claimed_at).toLocaleString()
            : 'N/A';

        tr.innerHTML = `
            <td>${startIndex + index + 1}</td>
            <td><strong>${record.household_code}</strong></td>
            <td>${record.household_name}</td>
            <td>${statusBadge}</td>
            <td>${previousWaveBadge}</td>
            <td>${claimBadge}</td>
            <td>${claimDate}</td>
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
    const searchTerm = (tableSearch?.value || '').trim().toLowerCase();

    if (!searchTerm) {
        filteredRiceRecords = [...allRiceRecords];
    } else {
        filteredRiceRecords = allRiceRecords.filter((record) =>
            [
                record.household_code,
                record.household_name,
                record.status,
                record.previous_wave_exists !== 1 ? 'first wave not found' : (record.previous_wave_is_claimed === 1 ? 'first wave claimed' : 'first wave not claimed'),
                record.next_wave_exists !== 1 ? 'not in next wave' : (record.is_claimed === 1 ? 'next wave claimed' : 'next wave unclaimed'),
                record.claimed_at || ''
            ].join(' ').toLowerCase().includes(searchTerm)
        );
    }

    currentPage = 1;
    renderCurrentPage();
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

const dashboardWaveInputs = document.querySelectorAll('input[name="dashboardWave"]');
const metricTotalHouseholds = document.getElementById('metricTotalHouseholds');
const metricClaimed = document.getElementById('metricClaimed');
const metricNotClaimed = document.getElementById('metricNotClaimed');
const metricTotalDescription = document.getElementById('metricTotalDescription');
const metricClaimedDescription = document.getElementById('metricClaimedDescription');
const metricNotClaimedDescription = document.getElementById('metricNotClaimedDescription');
const DASHBOARD_WAVE_SETTING_KEY = 'rice_dashboard_wave';

function renderDashboardWave(wave) {
    const selectedWave = wave === 'next_wave' ? 'next_wave' : 'first_wave';
    const metrics = window.RICE_DASHBOARD_METRICS?.[selectedWave];
    if (!metrics) {
        return;
    }

    const formatter = new Intl.NumberFormat();
    metricTotalHouseholds.textContent = formatter.format(metrics.total || 0);
    metricClaimed.textContent = formatter.format(metrics.claimed || 0);
    metricNotClaimed.textContent = formatter.format(metrics.not_claimed || 0);

    const isNextWave = selectedWave === 'next_wave';
    metricTotalDescription.textContent = isNextWave
        ? 'Households in the next-wave list'
        : 'Households in the first-wave list';
    metricClaimedDescription.textContent = isNextWave
        ? 'Next-wave households already claimed'
        : 'First-wave households already claimed';
    metricNotClaimedDescription.textContent = isNextWave
        ? 'Active next-wave households not yet claimed'
        : 'Active first-wave households not yet claimed';

    dashboardWaveInputs.forEach((input) => {
        input.checked = input.value === selectedWave;
    });

    localStorage.setItem(DASHBOARD_WAVE_SETTING_KEY, selectedWave);
}

dashboardWaveInputs.forEach((input) => {
    input.addEventListener('change', () => {
        if (input.checked) {
            renderDashboardWave(input.value);
        }
    });
});
document.addEventListener('DOMContentLoaded', () => {
    const savedWave = localStorage.getItem(DASHBOARD_WAVE_SETTING_KEY) || 'first_wave';
    renderDashboardWave(savedWave);
    loadRiceRecords();
    resetAddHouseholdForm();
});
