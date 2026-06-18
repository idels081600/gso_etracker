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

document.getElementById('tableSearch').addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#recordsTable tr');
    rows.forEach((row) => {
        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
    });
});

document.addEventListener('DOMContentLoaded', loadRiceRecords);
