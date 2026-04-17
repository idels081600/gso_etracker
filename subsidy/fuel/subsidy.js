// Dashboard JavaScript

// Load records from API
async function loadRecords() {
    try {
        const response = await fetch('api_get_records.php');
        const data = await response.json();
        
        if (data.success) {
            updateDashboard(data.data);
            renderTable(data.data);
        }
    } catch (error) {
        console.error('Error loading records:', error);
    }
}

// Update dashboard stats
function updateDashboard(records) {
    const totalTricycles = records.length;
    const activeCount = records.filter(r => r.status === 'Active').length;
    const inactiveCount = records.filter(r => r.status === 'Not Active').length;
    const totalClaimed = records.reduce((sum, r) => sum + parseInt(r.claimed_vouchers || 0), 0);
    
    document.getElementById('totalTricycles').textContent = totalTricycles;
    document.getElementById('activeCount').textContent = activeCount;
    document.getElementById('inactiveCount').textContent = inactiveCount;
    document.getElementById('totalClaimed').textContent = totalClaimed;
}

// Render table
function renderTable(records) {
    const tbody = document.getElementById('recordsTable');
    tbody.innerHTML = '';
    
    records.forEach((record, index) => {
        const tr = document.createElement('tr');
        
        const statusBadge = record.status === 'Active' 
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Not Active</span>';
        
        const lastClaim = record.last_claim_date 
            ? new Date(record.last_claim_date).toLocaleDateString()
            : 'N/A';
        
        // Calculate remaining vouchers
        const claimed = parseInt(record.claimed_vouchers || 0);
        const total = parseInt(record.total_vouchers || 10);
        
        // Create 10 check icons - filled for claimed, outline for available
        let balanceIcons = '<div class="d-flex flex-wrap gap-1 align-items-center">';
        for (let i = 0; i < total; i++) {
            if (i < claimed) {
                // Claimed voucher - filled green check
                balanceIcons += '<i class="bi bi-check-circle-fill text-success"></i>';
            } else {
                // Available voucher - outline check
                balanceIcons += '<i class="bi bi-check-circle text-muted"></i>';
            }
        }
        // Add text showing claimed (green checks) out of total
        balanceIcons += `<span class="ms-2 text-muted small">${claimed} out of ${total}</span>`;
        balanceIcons += '</div>';
        
        const balanceDisplay = balanceIcons;
        
        tr.innerHTML = `
            <td>${index + 1}</td>
            <td><strong>${record.tricycle_no}</strong></td>
            <td>${record.driver_name}</td>
            <td>${balanceDisplay}</td>
            <td>${statusBadge}</td>
            <td>${lastClaim}</td>
        `;
        
        tbody.appendChild(tr);
    });
}

// Add new record
document.getElementById('saveDataBtn').addEventListener('click', async () => {
    const formData = {
        tricycle_no: document.getElementById('tricycleNo').value,
        driver_name: document.getElementById('driverName').value,
        address: document.getElementById('address').value,
        contact_number: document.getElementById('contactNumber').value,
        total_vouchers: document.getElementById('voucherCount').value
    };
    
    if (!formData.tricycle_no || !formData.driver_name) {
        alert('Please fill in required fields.');
        return;
    }
    
    try {
        const response = await fetch('api_add_record.php', {
            method: 'POST',
            body: new URLSearchParams(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addDataModal'));
            modal.hide();
            
            // Clear form
            document.getElementById('addDataForm').reset();
            
            // Reload records
            loadRecords();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error adding record:', error);
        alert('Error adding record. Please try again.');
    }
});

// Table search
document.getElementById('tableSearch').addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#recordsTable tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Bulk Import functionality
document.getElementById('processImportBtn').addEventListener('click', async () => {
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a CSV file to import.');
        return;
    }
    
    // Show progress
    const progressDiv = document.getElementById('importProgress');
    const resultDiv = document.getElementById('importResult');
    const progressBar = progressDiv.querySelector('.progress-bar');
    const statusText = document.getElementById('importStatus');
    const importBtn = document.getElementById('processImportBtn');
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    progressBar.style.width = '0%';
    statusText.textContent = 'Uploading file...';
    importBtn.disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('csv_file', file);
    
    try {
        progressBar.style.width = '50%';
        statusText.textContent = 'Processing CSV...';
        
        const response = await fetch('api_bulk_import.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        progressBar.style.width = '100%';
        statusText.textContent = 'Complete!';
        
        // Show result
        resultDiv.style.display = 'block';
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Success!</strong> ${data.message}
                    <hr>
                    <small>Inserted: ${data.inserted} | Duplicates skipped: ${data.duplicates}</small>
                </div>
            `;
            
            // Clear file input
            fileInput.value = '';
            
            // Reload records
            loadRecords();
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <strong>Error:</strong> ${data.message}
                </div>
            `;
        }
        
    } catch (error) {
        console.error('Error importing data:', error);
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i>
                <strong>Error:</strong> Failed to process the import. Please try again.
            </div>
        `;
    } finally {
        importBtn.disabled = false;
        setTimeout(() => {
            progressDiv.style.display = 'none';
        }, 2000);
    }
});

// Export PDF button handler
document.getElementById('exportPdfBtn').addEventListener('click', () => {
    const stationSelect = document.getElementById('exportStationSelect');
    const stationId = stationSelect.value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (!stationId) {
        alert('Please select a gas station.');
        return;
    }
    
    // Build URL with parameters
    let exportUrl = 'export_claimed_vouchers.php?station_id=' + stationId;
    
    if (startDate) {
        exportUrl += '&start_date=' + encodeURIComponent(startDate);
    }
    
    if (endDate) {
        exportUrl += '&end_date=' + encodeURIComponent(endDate);
    }
    
    // Open PDF in new tab
    window.open(exportUrl, '_blank');
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('exportPdfModal'));
    modal.hide();
    
    // Reset form
    stationSelect.value = '';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
});

// Load records on page load
document.addEventListener('DOMContentLoaded', loadRecords);
