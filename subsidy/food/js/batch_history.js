// Batch History JavaScript

let currentPage = 1;
let currentFilters = {};
let currentBatchIdToCancel = null;

// Load batches with filters and pagination
async function loadBatches(page = 1, filters = {}) {
    currentPage = page;
    currentFilters = filters;
    
    const tbody = document.getElementById('batchesTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading batches...</td></tr>';
    
    // Build query string
    const params = new URLSearchParams();
    params.append('page', page);
    params.append('per_page', 20);
    
    if (filters.search) params.append('search', filters.search);
    if (filters.status) params.append('status', filters.status);
    if (filters.date_from) params.append('date_from', filters.date_from);
    if (filters.date_to) params.append('date_to', filters.date_to);
    
    try {
        const response = await fetch(`api_get_batches.php?${params.toString()}`);
        const data = await response.json();
        
        if (data.success) {
            renderBatches(data.batches, data.pagination);
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">${data.message || 'No batches found.'}</td></tr>`;
            document.getElementById('totalBatches').textContent = '0 total';
            document.getElementById('pagination').innerHTML = '';
        }
    } catch (error) {
        console.error('Error loading batches:', error);
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Error loading batches. Please try again.</td></tr>';
        document.getElementById('totalBatches').textContent = '0 total';
        document.getElementById('pagination').innerHTML = '';
    }
}

// Render batches in table
function renderBatches(batches, pagination) {
    const tbody = document.getElementById('batchesTableBody');
    tbody.innerHTML = '';
    
    document.getElementById('totalBatches').textContent = `${pagination.total} total`;
    
    if (!batches || batches.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No batches found.</td></tr>';
        document.getElementById('pagination').innerHTML = '';
        return;
    }
    
    batches.forEach(batch => {
        const tr = document.createElement('tr');
        
        // Status badge
        let statusBadge = '';
        switch (batch.status) {
            case 'completed':
                statusBadge = '<span class="badge bg-success status-badge">Completed</span>';
                break;
            case 'pending':
                statusBadge = '<span class="badge bg-warning text-dark status-badge">Pending</span>';
                break;
            case 'cancelled':
                statusBadge = '<span class="badge bg-danger status-badge">Cancelled</span>';
                break;
            default:
                statusBadge = '<span class="badge bg-secondary status-badge">' + batch.status + '</span>';
        }
        
        // Format date
        const createdDate = batch.created_at 
            ? new Date(batch.created_at).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              })
            : 'N/A';
        
        // Format amount
        const formattedAmount = '₱' + batch.total_amount.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        // Actions
        let actions = '';
        if (batch.status !== 'cancelled') {
            actions += `<button class="btn btn-sm btn-outline-warning me-1" onclick="openEditBatchModal(${batch.id})" title="Edit Batch">
                <i class="bi bi-pencil-square"></i>
            </button>`;
            actions += `<button class="btn btn-sm btn-outline-danger me-1" onclick="showCancelModal(${batch.id})" title="Cancel Batch">
                <i class="bi bi-x-circle"></i>
            </button>`;
        }
        actions += `<button class="btn btn-sm btn-outline-primary me-1" onclick="viewBatchDetails(${batch.id})" title="View Details">
            <i class="bi bi-eye"></i>
        </button>`;
        // actions += `<a href="api_export_batch_pdf.php?batch_id=${batch.id}" class="btn btn-sm btn-outline-danger me-1" target="_blank" title="Export PDF">
        //     <i class="bi bi-file-pdf"></i>
        // </a>`;
        // actions += `<a href="api_export_batch_excel.php?batch_id=${batch.id}" class="btn btn-sm btn-outline-success" target="_blank" title="Export Excel">
        //     <i class="bi bi-file-excel"></i>
        // </a>`;
        
        tr.innerHTML = `
            <td><strong>${batch.batch_number}</strong></td>
            <td>${batch.vendor ? batch.vendor.vendor_name : 'N/A'}<br><small class="text-muted">${batch.vendor ? batch.vendor.vendor_serial : ''}</small></td>
            <td>${batch.total_vouchers}</td>
            <td><strong>${formattedAmount}</strong></td>
            <td>${statusBadge}</td>
            <td><small>${createdDate}</small></td>
            <td><small>${batch.redeemer || 'N/A'}</small></td>
            <td>${actions}</td>
        `;
        tbody.appendChild(tr);
    });
    
    // Render pagination
    renderPagination(pagination);
}

// Render pagination controls
function renderPagination(pagination) {
    const ul = document.getElementById('pagination');
    ul.innerHTML = '';
    
    if (pagination.total_pages <= 1) return;
    
    // Previous button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${pagination.page === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#" onclick="loadBatches(${pagination.page - 1}, currentFilters); return false;">Previous</a>`;
    ul.appendChild(prevLi);
    
    // Page numbers
    const maxVisible = 5;
    let startPage = Math.max(1, pagination.page - Math.floor(maxVisible / 2));
    let endPage = Math.min(pagination.total_pages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    if (startPage > 1) {
        const firstLi = document.createElement('li');
        firstLi.className = 'page-item';
        firstLi.innerHTML = `<a class="page-link" href="#" onclick="loadBatches(1, currentFilters); return false;">1</a>`;
        ul.appendChild(firstLi);
        
        if (startPage > 2) {
            const ellipsisLi = document.createElement('li');
            ellipsisLi.className = 'page-item disabled';
            ellipsisLi.innerHTML = '<span class="page-link">...</span>';
            ul.appendChild(ellipsisLi);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === pagination.page ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#" onclick="loadBatches(${i}, currentFilters); return false;">${i}</a>`;
        ul.appendChild(li);
    }
    
    if (endPage < pagination.total_pages) {
        if (endPage < pagination.total_pages - 1) {
            const ellipsisLi = document.createElement('li');
            ellipsisLi.className = 'page-item disabled';
            ellipsisLi.innerHTML = '<span class="page-link">...</span>';
            ul.appendChild(ellipsisLi);
        }
        
        const lastLi = document.createElement('li');
        lastLi.className = 'page-item';
        lastLi.innerHTML = `<a class="page-link" href="#" onclick="loadBatches(${pagination.total_pages}, currentFilters); return false;">${pagination.total_pages}</a>`;
        ul.appendChild(lastLi);
    }
    
    // Next button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${pagination.page === pagination.total_pages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#" onclick="loadBatches(${pagination.page + 1}, currentFilters); return false;">Next</a>`;
    ul.appendChild(nextLi);
}

// View batch details
async function viewBatchDetails(batchId) {
    const modalContent = document.getElementById('batchDetailsContent');
    modalContent.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading details...</div>';
    
    // Update export buttons
    document.getElementById('exportPdfBtn').href = `api_export_batch_pdf.php?batch_id=${batchId}`;
    document.getElementById('exportExcelBtn').href = `api_export_batch_excel.php?batch_id=${batchId}`;
    document.getElementById('generateArBtn').href = `generate_ar.php?batch_id=${batchId}`;
    
    const modal = new bootstrap.Modal(document.getElementById('batchDetailsModal'));
    modal.show();
    
    try {
        const response = await fetch(`api_get_batch_details.php?batch_id=${batchId}`);
        const data = await response.json();
        
        if (data.success) {
            renderBatchDetails(data.batch, data.items);
        } else {
            modalContent.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load batch details.'}</div>`;
        }
    } catch (error) {
        console.error('Error loading batch details:', error);
        modalContent.innerHTML = '<div class="alert alert-danger">Error loading batch details. Please try again.</div>';
    }
}

// Render batch details in modal
function renderBatchDetails(batch, items) {
    const modalContent = document.getElementById('batchDetailsContent');
    
    // Status badge
    let statusBadge = '';
    switch (batch.status) {
        case 'completed':
            statusBadge = '<span class="badge bg-success">Completed</span>';
            break;
        case 'pending':
            statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
            break;
        case 'cancelled':
            statusBadge = '<span class="badge bg-danger">Cancelled</span>';
            break;
        default:
            statusBadge = '<span class="badge bg-secondary">' + batch.status + '</span>';
    }
    
    // Format amount
    const formattedAmount = '₱' + batch.total_amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    // Build items table
    let itemsTable = '';
    if (items && items.length > 0) {
        itemsTable = `
            <div class="table-responsive mt-3">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Voucher #</th>
                            <th>Beneficiary Code</th>
                            <th>Beneficiary Name</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        items.forEach((item, index) => {
            const itemAmount = '₱' + item.amount.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            itemsTable += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.voucher_number}</td>
                    <td>${item.beneficiary_code || 'N/A'}</td>
                    <td>${item.beneficiary_name || 'N/A'}</td>
                    <td class="text-end">${itemAmount}</td>
                </tr>
            `;
        });
        
        itemsTable += `
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <td colspan="4" class="text-end"><strong>Total:</strong></td>
                            <td class="text-end"><strong>${formattedAmount}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        `;
    } else {
        itemsTable = '<div class="alert alert-info mt-3">No items in this batch.</div>';
    }
    
    modalContent.innerHTML = `
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">Batch Information</h6>
                <table class="table table-sm table-borderless">
                    <tr><td><strong>Batch Number:</strong></td><td>${batch.batch_number}</td></tr>
                    <tr><td><strong>Status:</strong></td><td>${statusBadge}</td></tr>
                    <tr><td><strong>Total Vouchers:</strong></td><td>${batch.total_vouchers}</td></tr>
                    <tr><td><strong>Total Amount:</strong></td><td><strong>${formattedAmount}</strong></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Vendor Information</h6>
                <table class="table table-sm table-borderless">
                    <tr><td><strong>Vendor Name:</strong></td><td>${batch.vendor ? batch.vendor.vendor_name : 'N/A'}</td></tr>
                    <tr><td><strong>Vendor Serial:</strong></td><td>${batch.vendor ? batch.vendor.vendor_serial : 'N/A'}</td></tr>
                    <tr><td><strong>Area:</strong></td><td>${batch.vendor ? batch.vendor.area : 'N/A'}</td></tr>
                </table>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">Created</h6>
                <p class="mb-0">${batch.created_at ? new Date(batch.created_at).toLocaleString('en-US') : 'N/A'}</p>
                <p class="text-muted mb-0">By: ${batch.created_by || 'N/A'}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Redeemed</h6>
                <p class="mb-0">${batch.redeemed_at ? new Date(batch.redeemed_at).toLocaleString('en-US') : 'N/A'}</p>
                <p class="text-muted mb-0">By: ${batch.redeemer || 'N/A'}</p>
            </div>
        </div>
        <hr>
        <h6 class="text-muted">Voucher Items (${items ? items.length : 0})</h6>
        ${itemsTable}
    `;
}

// Show cancel modal
function showCancelModal(batchId) {
    currentBatchIdToCancel = batchId;
    const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
    modal.show();
}

// Cancel batch
async function cancelBatch() {
    if (!currentBatchIdToCancel) return;
    
    const confirmBtn = document.getElementById('confirmCancelBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cancelling...';
    
    try {
        const response = await fetch('api_update_batch_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                batch_id: currentBatchIdToCancel,
                status: 'cancelled'
            })
        });
        
        const data = await response.json();
        
        // Hide modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('cancelModal'));
        modal.hide();
        
        if (data.success) {
            // Reload batches
            loadBatches(currentPage, currentFilters);
            
            // Show success alert
            showAlert('Batch cancelled successfully. Voucher redemptions have been reversed and redemption items have been voided.', 'success');
        } else {
            showAlert(data.message || 'Failed to cancel batch.', 'danger');
        }
    } catch (error) {
        console.error('Error cancelling batch:', error);
        showAlert('Error cancelling batch. Please try again.', 'danger');
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
        currentBatchIdToCancel = null;
    }
}

// Show alert
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Event listeners
document.addEventListener('DOMContentLoaded', () => {
    // Load initial batches
    loadBatches(1);
    
    // Apply filters button
    const applyFiltersBtn = document.getElementById('applyFilters');
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', () => {
            const filters = {
                search: document.getElementById('searchInput').value.trim(),
                status: document.getElementById('statusFilter').value,
                date_from: document.getElementById('dateFrom').value,
                date_to: document.getElementById('dateTo').value
            };
            loadBatches(1, filters);
        });
    }
    
    // Clear filters button
    const clearFiltersBtn = document.getElementById('clearFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            loadBatches(1, {});
        });
    }
    
    // Search input enter key
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFiltersBtn.click();
            }
        });
    }
    
    // Confirm cancel button
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', cancelBatch);
    }

    // Save batch edit button
    const saveBatchEditBtn = document.getElementById('saveBatchEditBtn');
    if (saveBatchEditBtn) {
        saveBatchEditBtn.addEventListener('click', saveBatchEdit);
    }

    // Edit voucher search filter
    const editVoucherSearch = document.getElementById('editVoucherSearch');
    if (editVoucherSearch) {
        editVoucherSearch.addEventListener('keyup', filterEditAvailableVouchers);
    }
});

// ============================================================
// EDIT BATCH FUNCTIONALITY
// ============================================================

let editBatchData = null;       // Current batch details
let editBatchItems = [];        // Current items in the batch (tracking removals locally)
let editAvailableVouchers = []; // Available vouchers to add
let editAddedVouchers = [];     // Vouchers added during this edit session (local tracking)
let editRemovedVoucherIds = []; // Voucher IDs removed during this edit session
let editInitialOrder = '';      // Snapshot of initial voucher order for reorder detection

// Open the edit batch modal
async function openEditBatchModal(batchId) {
    // Reset edit state
    editBatchData = null;
    editBatchItems = [];
    editAvailableVouchers = [];
    editAddedVouchers = [];
    editRemovedVoucherIds = [];

    // Show loading in modal
    document.getElementById('editCurrentItemsBody').innerHTML = 
        '<tr><td colspan="4" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';
    document.getElementById('editAvailableVoucherBody').innerHTML = 
        '<tr><td colspan="3" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';
    document.getElementById('saveBatchEditBtn').disabled = true;

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editBatchModal'));
    modal.show();

    // Load batch details
    try {
        const response = await fetch(`api_get_batch_details.php?batch_id=${batchId}`);
        const data = await response.json();

        if (data.success) {
            editBatchData = data.batch;
            editBatchItems = data.items || [];
            
            // Store initial order for reorder detection
            editInitialOrder = editBatchItems.map(item => item.voucher_id).join(',');
            
            // Update header info
            document.getElementById('editBatchNumber').textContent = data.batch.batch_number;
            document.getElementById('editVendorName').textContent = data.batch.vendor ? data.batch.vendor.vendor_name : 'N/A';
            updateEditTotals();
            
            renderEditBatchItems();
            
            // Load available vouchers
            await loadEditAvailableVouchers(data.batch.vendor ? data.batch.vendor.vendor_serial : '');
        } else {
            document.getElementById('editCurrentItemsBody').innerHTML = 
                '<tr><td colspan="4" class="text-center text-danger py-3">' + (data.message || 'Failed to load batch details.') + '</td></tr>';
            document.getElementById('editAvailableVoucherBody').innerHTML = 
                '<tr><td colspan="3" class="text-center text-danger py-3">Failed to load.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading edit batch data:', error);
        document.getElementById('editCurrentItemsBody').innerHTML = 
            '<tr><td colspan="4" class="text-center text-danger py-3">Error loading batch details.</td></tr>';
        document.getElementById('editAvailableVoucherBody').innerHTML = 
            '<tr><td colspan="3" class="text-center text-danger py-3">Error loading.</td></tr>';
    }
}

// Load available vouchers for the vendor (without pagination limit - get all)
async function loadEditAvailableVouchers(vendorSerial) {
    if (!vendorSerial) {
        document.getElementById('editAvailableVoucherBody').innerHTML = 
            '<tr><td colspan="3" class="text-center text-muted py-3">No vendor serial available.</td></tr>';
        document.getElementById('availableCountEdit').textContent = '0';
        return;
    }

    document.getElementById('editAvailableVoucherBody').innerHTML = 
        '<tr><td colspan="3" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading all vouchers...</td></tr>';

    try {
        // Fetch all available vouchers without pagination limit
        const response = await fetch(`api_get_vendor_vouchers.php?vendor_serial=${encodeURIComponent(vendorSerial)}&page=1&limit=9999`);
        const data = await response.json();

        if (data.success) {
            editAvailableVouchers = data.vouchers || [];
            renderEditAvailableVouchers();
        } else {
            document.getElementById('editAvailableVoucherBody').innerHTML = 
                '<tr><td colspan="3" class="text-center text-muted py-3">No vouchers available.</td></tr>';
            document.getElementById('availableCountEdit').textContent = '0';
        }
    } catch (error) {
        console.error('Error loading available vouchers:', error);
        document.getElementById('editAvailableVoucherBody').innerHTML = 
            '<tr><td colspan="3" class="text-center text-danger py-3">Error loading vouchers.</td></tr>';
        document.getElementById('availableCountEdit').textContent = '0';
    }
}

// Track drag state
let draggedRowIndex = null;

// Render current batch items (left panel) with drag-and-drop support
function renderEditBatchItems() {
    const tbody = document.getElementById('editCurrentItemsBody');
    tbody.innerHTML = '';

    if (editBatchItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No vouchers in this batch.</td></tr>';
        document.getElementById('currentBatchCount').textContent = '0';
        updateEditTotals();
        return;
    }

    editBatchItems.forEach((item, index) => {
        const row = document.createElement('tr');
        row.draggable = true;
        row.dataset.index = index;
        
        const beneficiaryCode = item.beneficiary_code || 'N/A';
        const sequenceNumber = String(item.voucher_number).padStart(3, '0');
        
        row.innerHTML = `
            <td class="drag-handle" style="cursor: grab;">
                <i class="bi bi-grip-vertical text-muted"></i> ${index + 1}
            </td>
            <td><strong>${beneficiaryCode}-<span style="color: #dc3545; font-weight: bold;">${sequenceNumber}</span></strong></td>
            <td><small>${item.beneficiary_name || item.claimant_name || 'N/A'}</small></td>
            <td>
                <button class="btn btn-sm btn-outline-danger" onclick="removeEditItem(${item.voucher_id}, this)" title="Remove from batch">
                    <i class="bi bi-dash-circle"></i>
                </button>
            </td>
        `;

        // Drag event handlers
        row.addEventListener('dragstart', function(e) {
            draggedRowIndex = parseInt(this.dataset.index);
            this.classList.add('table-warning');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.index);
        });

        row.addEventListener('dragend', function() {
            this.classList.remove('table-warning');
            document.querySelectorAll('#editCurrentItemsBody tr').forEach(r => {
                r.classList.remove('drop-target');
            });
            draggedRowIndex = null;
        });

        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            // Highlight the drop target row
            document.querySelectorAll('#editCurrentItemsBody tr').forEach(r => {
                r.classList.remove('drop-target');
            });
            this.classList.add('drop-target');
        });

        row.addEventListener('dragleave', function() {
            this.classList.remove('drop-target');
        });

        row.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drop-target');
            
            const fromIndex = draggedRowIndex;
            const toIndex = parseInt(this.dataset.index);
            
            if (fromIndex === null || fromIndex === toIndex) return;
            
            // Reorder the editBatchItems array
            const [movedItem] = editBatchItems.splice(fromIndex, 1);
            editBatchItems.splice(toIndex, 0, movedItem);
            
            // Re-render and update totals (which re-enables save button)
            renderEditBatchItems();
            updateEditTotals();
        });

        tbody.appendChild(row);
    });

    document.getElementById('currentBatchCount').textContent = editBatchItems.length;
    updateEditTotals();
}

// Render available vouchers (right panel) - excluding already-batched and already-removed vouchers
function renderEditAvailableVouchers() {
    const tbody = document.getElementById('editAvailableVoucherBody');
    tbody.innerHTML = '';

    // IDs already in the batch (current items) or added in this session
    const batchVoucherIds = editBatchItems.map(item => item.voucher_id);
    const addedIds = editAddedVouchers.map(v => v.id);
    const excludeIds = [...batchVoucherIds, ...addedIds];

    // Already redeemed vouchers (in another batch) - filter out
    const available = editAvailableVouchers.filter(v => {
        return v.is_verified === 1 && !excludeIds.includes(v.id);
    });

    if (available.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No available vouchers.</td></tr>';
        document.getElementById('availableCountEdit').textContent = '0';
        return;
    }

    available.forEach(voucher => {
        const row = document.createElement('tr');
        const beneficiaryCode = voucher.beneficiary_code || 'N/A';
        const sequenceNumber = String(voucher.voucher_number).padStart(3, '0');

        row.innerHTML = `
            <td><strong>${beneficiaryCode}-<span style="color: #dc3545; font-weight: bold;">${sequenceNumber}</span></strong></td>
            <td><small>${voucher.beneficiary_name || voucher.claimant_name}</small></td>
            <td>
                <button class="btn btn-sm btn-outline-success" onclick="addEditAvailable(${voucher.id})" title="Add to batch">
                    <i class="bi bi-plus-circle"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });

    document.getElementById('availableCountEdit').textContent = available.length;
}

// Remove an item from the edit batch (local)
function removeEditItem(voucherId, btnElement) {
    const itemIndex = editBatchItems.findIndex(item => item.voucher_id === voucherId);
    if (itemIndex === -1) return;

    // Track removed voucher for the API call
    editRemovedVoucherIds.push(voucherId);
    
    // Remove from local display list
    editBatchItems.splice(itemIndex, 1);
    
    renderEditBatchItems();
    renderEditAvailableVouchers();
    updateEditTotals();
}

// Add an available voucher to the batch (local)
function addEditAvailable(voucherId) {
    const voucher = editAvailableVouchers.find(v => v.id === voucherId);
    if (!voucher) return;

    // Track locally
    editAddedVouchers.push(voucher);

    // Add to current items display
    const newItem = {
        voucher_id: voucher.id,
        voucher_number: voucher.voucher_number,
        beneficiary_code: voucher.beneficiary_code,
        beneficiary_name: voucher.beneficiary_name || voucher.claimant_name,
        claimant_name: voucher.claimant_name,
        amount: 200.00
    };
    editBatchItems.push(newItem);

    renderEditBatchItems();
    renderEditAvailableVouchers();
    updateEditTotals();
}

// Update the totals display and enable/disable save button
function updateEditTotals() {
    const totalVouchers = editBatchItems.length;
    const totalAmount = totalVouchers * 200.00;
    
    const formattedAmount = '₱' + totalAmount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    document.getElementById('editBatchTotals').textContent = `${totalVouchers} vouchers | ${formattedAmount}`;

    // Enable save button if there are changes OR items were reordered
    const hasChanges = editRemovedVoucherIds.length > 0 || editAddedVouchers.length > 0;
    // Compare current order with the snapshot stored when modal was opened
    const currentOrder = editBatchItems.map(item => item.voucher_id).join(',');
    const isReordered = editInitialOrder !== '' && currentOrder !== editInitialOrder;
    document.getElementById('saveBatchEditBtn').disabled = (!hasChanges && !isReordered) || totalVouchers === 0;
}

// Filter available vouchers in edit modal - now supports full voucher code search
function filterEditAvailableVouchers() {
    const searchTerm = document.getElementById('editVoucherSearch').value.toLowerCase().trim();
    const tbody = document.getElementById('editAvailableVoucherBody');
    const rows = tbody.querySelectorAll('tr');
    
    let visibleCount = 0;

    rows.forEach(row => {
        // Search across voucher code, beneficiary code, and beneficiary name
        const rowText = row.textContent.toLowerCase();
        
        if (searchTerm === '' || rowText.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Update available count
    document.getElementById('availableCountEdit').textContent = visibleCount;

    // Show "no results" message if needed
    if (visibleCount === 0 && searchTerm !== '') {
        const noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-row';
        noResultsRow.innerHTML = `
            <td colspan="3" class="text-center text-muted py-3">
                <i class="bi bi-search"></i> No results for "<strong>${searchTerm}</strong>"<br>
                <small class="text-muted mt-2 d-block">Try: full voucher code (si-1271-001), beneficiary code, name, or sequence #</small>
            </td>
        `;
        tbody.appendChild(noResultsRow);
    } else if (visibleCount === 0 && searchTerm === '') {
        const noVouchersRow = document.createElement('tr');
        noVouchersRow.innerHTML = '<td colspan="3" class="text-center text-muted py-3">No available vouchers.</td>';
        tbody.appendChild(noVouchersRow);
    }
}

// Save batch edit - call API
async function saveBatchEdit() {
    const saveBtn = document.getElementById('saveBatchEditBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    // Build the reordered voucher ID list (all items in current display order)
    const reorderedVoucherIds = editBatchItems.map(item => item.voucher_id);

    try {
        const response = await fetch('api_update_batch.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                batch_id: editBatchData.id,
                remove_voucher_ids: editRemovedVoucherIds,
                add_voucher_ids: editAddedVouchers.map(v => v.id),
                reorder_voucher_ids: reorderedVoucherIds
            })
        });

        const data = await response.json();

        // Hide modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('editBatchModal'));
        modal.hide();

        if (data.success) {
            // Reload batches table
            loadBatches(currentPage, currentFilters);
            
            let msg = 'Batch updated successfully.';
            if (data.removed) msg += ` ${data.removed} voucher(s) voided.`;
            if (data.added) msg += ` ${data.added} voucher(s) added.`;
            showAlert(msg, 'success');
        } else {
            showAlert(data.message || 'Failed to update batch.', 'danger');
        }
    } catch (error) {
        console.error('Error saving batch edit:', error);
        showAlert('Error saving batch edit. Please try again.', 'danger');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
}
