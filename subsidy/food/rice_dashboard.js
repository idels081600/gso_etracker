async function loadRiceRecords() {
    try {
        const response = await fetch('api_get_rice_records.php');
        const data = await response.json();
        if (data.success) {
            renderRiceTable(data.data);
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
let currentNextHouseholdCode = '';

function renderRiceTable(records) {
    const tbody = document.getElementById('recordsTable');
    tbody.innerHTML = '';

    records.forEach((record, index) => {
        const tr = document.createElement('tr');
        const statusBadge = record.status === 'Active'
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Not Active</span>';
        const claimBadge = record.is_claimed === 1
            ? '<span class="badge bg-success">Claimed</span>'
            : '<span class="badge bg-warning text-dark">Unclaimed</span>';
        const claimDate = record.claimed_at
            ? new Date(record.claimed_at).toLocaleString()
            : 'N/A';

        tr.innerHTML = `
            <td>${index + 1}</td>
            <td><strong>${record.household_code}</strong></td>
            <td>${record.household_name}</td>
            <td>${statusBadge}</td>
            <td>${claimBadge}</td>
            <td>${claimDate}</td>
        `;
        tbody.appendChild(tr);
    });
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

document.getElementById('tableSearch').addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#recordsTable tr');
    rows.forEach((row) => {
        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
    });
});

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

document.addEventListener('DOMContentLoaded', () => {
    loadRiceRecords();
    resetAddHouseholdForm();
});
