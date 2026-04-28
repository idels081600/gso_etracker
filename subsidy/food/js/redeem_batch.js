// Batch Voucher Redemption JavaScript

let currentVendor = null;

// Search vendor function
async function searchVendor() {
    const vendorSerialInput = document.getElementById('vendorSerial');
    const vendorSerial = vendorSerialInput.value.trim();
    
    if (!vendorSerial) {
        alert('Please enter a vendor serial number.');
        vendorSerialInput.focus();
        return;
    }
    
    const searchBtn = document.getElementById('searchVendorBtn');
    const originalBtnContent = searchBtn.innerHTML;
    
    // Show loading state
    searchBtn.disabled = true;
    searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Searching...';
    
    try {
        const response = await fetch(`api_search_vendor.php?vendor_serial=${encodeURIComponent(vendorSerial)}`);
        const data = await response.json();
        
        if (data.success) {
            if (data.match_type === 'exact') {
                // Single vendor found - display it
                displayVendor(data.vendor);
            } else {
                // Multiple vendors found - show selection (for now, display first one)
                // TODO: Implement vendor selection modal for multiple results
                displayVendor(data.vendors[0]);
            }
        } else {
            // Vendor not found
            hideVendorInfo();
            alert(data.message || 'Vendor not found. Please check the serial number and try again.');
        }
    } catch (error) {
        console.error('Error searching vendor:', error);
        alert('Error searching for vendor. Please try again.');
    } finally {
        // Restore button state
        searchBtn.disabled = false;
        searchBtn.innerHTML = originalBtnContent;
    }
}

// Display vendor information
function displayVendor(vendor) {
    currentVendor = vendor;
    
    // Update vendor info display
    document.getElementById('vendorId').textContent = vendor.vendor_serial;
    document.getElementById('vendorName').textContent = vendor.vendor_name;
    document.getElementById('vendorStallNo').textContent = vendor.stall_no || '-';
    document.getElementById('vendorSection').textContent = vendor.section || '-';
    
    // Show vendor info section
    document.getElementById('vendorInfo').classList.remove('d-none');
    
    // Hide no vendor message
    document.getElementById('noVendorMessage').classList.add('d-none');
    
    // Show voucher section
    document.getElementById('voucherSection').classList.remove('d-none');
    
    // Load vouchers for this vendor
    loadVendorVouchers(vendor.vendor_serial);
}

// Hide vendor information
function hideVendorInfo() {
    currentVendor = null;
    
    document.getElementById('vendorInfo').classList.add('d-none');
    document.getElementById('voucherSection').classList.add('d-none');
    document.getElementById('noVendorMessage').classList.remove('d-none');
    
    // Clear vendor details
    document.getElementById('vendorId').textContent = '-';
    document.getElementById('vendorName').textContent = '-';
    document.getElementById('vendorArea').textContent = '-';
}

// Load vouchers for vendor
async function loadVendorVouchers(vendorSerial) {
    const tbody = document.getElementById('voucherTableBody');
    
    // Show loading state
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading vouchers...</td></tr>';
    
    try {
        const response = await fetch(`api_get_vendor_vouchers.php?vendor_serial=${encodeURIComponent(vendorSerial)}`);
        const data = await response.json();
        
        if (data.success) {
            renderVouchers(data.vouchers);
        } else {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted">${data.message || 'No vouchers found for this vendor.'}</td></tr>`;
        }
    } catch (error) {
        console.error('Error loading vouchers:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-danger">Error loading vouchers. Please try again.</td></tr>';
    }
}

// Render vouchers in table
function renderVouchers(vouchers) {
    const tbody = document.getElementById('voucherTableBody');
    tbody.innerHTML = '';
    
    if (!vouchers || vouchers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">No claimed vouchers available for redemption.</td></tr>';
        return;
    }
    
    vouchers.forEach((voucher, index) => {
        const tr = document.createElement('tr');
        tr.className = 'voucher-row';
        
        // Format claim date
        const claimDate = voucher.claim_date 
            ? new Date(voucher.claim_date).toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              })
            : 'N/A';
        
        // Status badge - show Verified if is_verified is 1
        const isVerified = voucher.is_verified === 1;
        const statusBadge = isVerified 
            ? '<span class="badge bg-success status-badge">Verified</span>'
            : '<span class="badge bg-warning text-dark status-badge">Pending</span>';
        
        // Redeemed status badge
        const isRedeemed = voucher.is_redeemed === 1;
        const redeemedBadge = isRedeemed
            ? '<span class="badge bg-info status-badge">Yes</span>'
            : '<span class="badge bg-secondary status-badge">No</span>';
        
        // Disable checkbox if already redeemed
        const checkboxDisabled = isRedeemed ? 'disabled' : '';
        const rowClass = isRedeemed ? 'voucher-row text-muted bg-light' : 'voucher-row';
        
        tr.className = rowClass;
        
        tr.innerHTML = `
            <td>
                <input type="checkbox" class="form-check-input voucher-checkbox" value="${voucher.id}" data-voucher-number="${voucher.voucher_number}" ${checkboxDisabled}>
            </td>
            <td><strong>${voucher.beneficiary_code || 'N/A'}</strong> - Voucher #${voucher.voucher_number}</td>
            <td>${voucher.claimant_name || voucher.beneficiary_name || 'N/A'}</td>
            <td>${claimDate}</td>
            <td>${statusBadge}</td>
            <td>${redeemedBadge}</td>
        `;
        
        // Add click handler for checkbox (only if not disabled)
        const checkbox = tr.querySelector('.voucher-checkbox');
        if (!isRedeemed) {
            checkbox.addEventListener('change', () => {
                updateSelectedCount();
                tr.classList.toggle('selected', checkbox.checked);
            });
        }
        
        tbody.appendChild(tr);
    });
    
    // Reset select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
    
    updateSelectedCount();
}

// Event listeners
document.addEventListener('DOMContentLoaded', () => {
    // Search button click
    const searchBtn = document.getElementById('searchVendorBtn');
    if (searchBtn) {
        searchBtn.addEventListener('click', searchVendor);
    }
    
    // Enter key on vendor serial input
    const vendorSerialInput = document.getElementById('vendorSerial');
    if (vendorSerialInput) {
        vendorSerialInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchVendor();
            }
        });
    }
    
    // Refresh button
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            if (currentVendor) {
                loadVendorVouchers(currentVendor.vendor_serial);
            }
        });
    }
    
    // Voucher search input
    const voucherSearchInput = document.getElementById('voucherSearch');
    if (voucherSearchInput) {
        voucherSearchInput.addEventListener('input', (e) => {
            filterVouchers(e.target.value);
        });
    }
    
    // Clear voucher search button
    const clearVoucherSearchBtn = document.getElementById('clearVoucherSearch');
    if (clearVoucherSearchBtn) {
        clearVoucherSearchBtn.addEventListener('click', () => {
            const searchInput = document.getElementById('voucherSearch');
            if (searchInput) {
                searchInput.value = '';
                filterVouchers('');
            }
        });
    }
    
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', (e) => {
            const checkboxes = document.querySelectorAll('.voucher-checkbox:not(:disabled)');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            // Update row styling for selected rows
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (row) {
                    row.classList.toggle('selected', cb.checked);
                }
            });
            updateSelectedCount();
        });
    }
    
    // Redeem button
    const redeemBtn = document.getElementById('redeemBtn');
    if (redeemBtn) {
        redeemBtn.addEventListener('click', () => {
            const selectedCount = document.querySelectorAll('.voucher-checkbox:checked').length;
            if (selectedCount === 0) {
                alert('Please select at least one voucher to redeem.');
                return;
            }
            
            // Show confirmation modal
            document.getElementById('modalCount').textContent = selectedCount;
            document.getElementById('modalVendorName').textContent = currentVendor ? currentVendor.vendor_name : '-';
            
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
        });
    }
    
    // Confirm redeem button - creates batch
    const confirmRedeemBtn = document.getElementById('confirmRedeemBtn');
    if (confirmRedeemBtn) {
        confirmRedeemBtn.addEventListener('click', async () => {
            const selectedCheckboxes = document.querySelectorAll('.voucher-checkbox:checked');
            const voucherIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.value));
            
            if (voucherIds.length === 0) {
                alert('No vouchers selected.');
                return;
            }
            
            if (!currentVendor) {
                alert('No vendor selected.');
                return;
            }
            
            // Disable button during processing
            confirmRedeemBtn.disabled = true;
            const originalText = confirmRedeemBtn.innerHTML;
            confirmRedeemBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
            
            try {
                const response = await fetch('api_create_batch.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        vendor_serial: currentVendor.vendor_serial,
                        voucher_ids: voucherIds
                    })
                });
                
                const data = await response.json();
                
                // Hide confirmation modal
                const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
                confirmModal.hide();
                
                if (data.success) {
                    // Show success modal with batch info
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    const batch = data.batch;
                    const formattedAmount = '₱' + batch.total_amount.toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    
                    document.getElementById('successMessage').innerHTML = `
                        <strong>Batch Number:</strong> ${batch.batch_number}<br>
                        <strong>Vendor:</strong> ${batch.vendor_name}<br>
                        <strong>Vouchers:</strong> ${batch.total_vouchers}<br>
                        <strong>Total Amount:</strong> ${formattedAmount}<br><br>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <a href="batch_history.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-archive me-1"></i> View Batch History
                            </a>
                            <a href="api_export_batch_pdf.php?batch_id=${batch.batch_id}" class="btn btn-danger btn-sm" target="_blank">
                                <i class="bi bi-file-pdf me-1"></i> Export PDF
                            </a>
                            <a href="generate_ar.php?batch_id=${batch.batch_id}" class="btn btn-warning btn-sm" target="_blank">
                                <i class="bi bi-receipt me-1"></i> Generate AR
                            </a>
                        </div>
                    `;
                    successModal.show();
                    
                    // Pass batch ID to modal event when showing
                    successModal._element.dispatchEvent(new CustomEvent('shown.bs.modal', {
                        detail: {
                            batch_id: batch.batch_id
                        }
                    }));
                    
                    // Refresh voucher list to show updated redeemed status
                    loadVendorVouchers(currentVendor.vendor_serial);
                } else {
                    // Show error
                    alert('Error: ' + (data.message || 'Failed to create batch.'));
                    if (data.invalid_vouchers && data.invalid_vouchers.length > 0) {
                        console.log('Invalid vouchers:', data.invalid_vouchers);
                    }
                }
            } catch (error) {
                console.error('Error creating batch:', error);
                alert('Error creating batch. Please try again.');
            } finally {
                // Restore button state
                confirmRedeemBtn.disabled = false;
                confirmRedeemBtn.innerHTML = originalText;
            }
        });
    }
});

// Voucher value constant
const VOUCHER_VALUE = 200;

// Update selected count and total amount
function updateSelectedCount() {
    const selectedCount = document.querySelectorAll('.voucher-checkbox:checked').length;
    const totalAmount = selectedCount * VOUCHER_VALUE;
    
    document.getElementById('selectedCount').textContent = `${selectedCount} selected`;
    
    const totalAmountElement = document.getElementById('totalAmount');
    if (totalAmountElement) {
        totalAmountElement.textContent = `₱${totalAmount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
    
    const redeemBtn = document.getElementById('redeemBtn');
    if (redeemBtn) {
        redeemBtn.disabled = selectedCount === 0;
    }
}

// Filter vouchers based on search term
function filterVouchers(searchTerm) {
    const rows = document.querySelectorAll('#voucherTableBody tr.voucher-row');
    const term = searchTerm.toLowerCase().trim();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (term === '' || text.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Show proof modal
function showProofModal(voucherNo, claimant, claimDate, signatureUrl) {
    document.getElementById('proofVoucherNo').textContent = voucherNo;
    document.getElementById('proofClaimant').textContent = claimant || 'N/A';
    document.getElementById('proofDate').textContent = claimDate || 'N/A';
    document.getElementById('proofSignature').src = signatureUrl || '';
    
    const proofModal = new bootstrap.Modal(document.getElementById('proofModal'));
    proofModal.show();
}