// Releasing Page JavaScript

let currentTricycleNo = '';
let currentDriverName = '';
let claimedVouchersList = [];
let claimsData = [];
let totalVouchers = 10;

const voucherButtons = document.getElementById('voucherButtons');
const currentTricycleEl = document.getElementById('currentTricycle');
const currentStatusEl = document.getElementById('currentStatus');
const mainSearch = document.getElementById('mainSearch');
const searchBtn = document.getElementById('searchBtn');

// Create voucher card
function createVoucherCard(voucherId, index, isClaimed = false) {
    const col = document.createElement('div');
    col.className = 'col-6 col-sm-4 col-md-3 col-lg-2';
    
    const card = document.createElement('div');
    card.className = 'card voucher-card text-center' + (isClaimed ? ' claimed' : '');
    card.dataset.voucher = voucherId;
    card.dataset.index = index;
    
    if (isClaimed) {
        card.innerHTML = `
            <div class="card-body py-3 px-2">
                <p class="voucher-label text-white-50 mb-1">Claimed</p>
                <p class="voucher-number text-white mb-0">
                    <i class="bi bi-check-circle-fill me-1"></i>${voucherId}
                </p>
                <small class="text-white-50"><i class="bi bi-eye"></i> View Proof</small>
            </div>
        `;
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => showProofModal(index, voucherId));
    } else {
        card.innerHTML = `
            <div class="card-body py-3 px-2">
                <p class="voucher-label text-muted mb-1">Voucher #${index}</p>
                <p class="voucher-number text-success mb-0">${voucherId}</p>
            </div>
        `;
        
        card.addEventListener('click', () => {
            if (card.classList.contains('claimed')) {
                // Unclaim if this is not an actual submitted voucher (no view proof)
                card.classList.remove('claimed');
                card.innerHTML = `
                    <div class="card-body py-3 px-2">
                        <p class="voucher-label text-muted mb-1">Voucher #${index}</p>
                        <p class="voucher-number text-success mb-0">${voucherId}</p>
                    </div>
                `;
            } else {
                card.classList.add('claimed');
                card.innerHTML = `
                    <div class="card-body py-3 px-2">
                        <p class="voucher-label text-white-50 mb-1">Claimed</p>
                        <p class="voucher-number text-white mb-0">
                            <i class="bi bi-check-circle-fill me-1"></i>${voucherId}
                        </p>
                    </div>
                `;
            }
        });
    }
    
    col.appendChild(card);
    return col;
}

// Render voucher buttons
function renderVoucherButtons(tricycleNo, alreadyClaimed = []) {
    voucherButtons.innerHTML = '';
    for (let i = 1; i <= totalVouchers; i++) {
        const voucherId = `${tricycleNo}-${i}`;
        const isClaimed = alreadyClaimed.includes(i);
        voucherButtons.appendChild(createVoucherCard(voucherId, i, isClaimed));
    }
}

// Debounce timer
let searchDebounceTimer = null;
const searchDropdown = document.getElementById('searchDropdown');
const searchLoading = document.getElementById('searchLoading');

// Search tricycle
async function searchTricycle(tricycleNo) {
    try {
        const response = await fetch(`api_get_claims.php?tricycle_no=${encodeURIComponent(tricycleNo)}`);
        const data = await response.json();
        
        if (data.success) {
            currentTricycleNo = data.data.tricycle_no;
            currentDriverName = data.data.driver_name || '';
            totalVouchers = data.data.total_vouchers;
            claimedVouchersList = data.data.claimed_vouchers_list || [];
            claimsData = data.data.claims_data || [];
            
            currentTricycleEl.textContent = data.data.tricycle_no;
            currentStatusEl.textContent = `${data.data.status} | ${data.data.driver_name}`;
            
            // Update driver name dropdown
            const driverSelect = document.getElementById('claimantNameDriver');
            driverSelect.innerHTML = `<option value="${currentDriverName}">${currentDriverName}</option>`;
            driverSelect.value = currentDriverName;
            
            renderVoucherButtons(data.data.tricycle_no, claimedVouchersList);
            
            // Hide dropdown
            hideSearchDropdown();
        } else {
            alert('Tricycle not found. Please check the number and try again.');
            currentTricycleEl.textContent = '----';
            currentStatusEl.textContent = 'Not found';
            voucherButtons.innerHTML = '<div class="col-12 text-center text-muted py-4">No tricycle found</div>';
        }
    } catch (error) {
        console.error('Error searching tricycle:', error);
        alert('Error searching for tricycle. Please try again.');
    }
}

// Autocomplete search suggestions
async function searchSuggestions(query) {
    if (query.length < 2) {
        hideSearchDropdown();
        return;
    }
    
    clearTimeout(searchDebounceTimer);
    
    // Show loading state
    searchDropdown.innerHTML = '<div class="text-muted text-center py-2 small">Searching...</div>';
    showSearchDropdown();
    
    searchDebounceTimer = setTimeout(async () => {
        try {
            const response = await fetch(`api_search_suggestions.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            searchDropdown.innerHTML = '';
            
            if (data.success && data.results.length > 0) {
                data.results.forEach(item => {
                    const option = document.createElement('button');
                    option.className = 'dropdown-item d-flex justify-content-between align-items-center';
                    option.innerHTML = `
                        <div>
                            <strong>${item.tricycle_no}</strong>
                            <div class="small text-muted">${item.driver_name}</div>
                        </div>
                        <span class="badge bg-light text-dark">${item.remaining} left</span>
                    `;
                    option.addEventListener('click', () => {
                        mainSearch.value = item.tricycle_no;
                        searchTricycle(item.tricycle_no);
                    });
                    searchDropdown.appendChild(option);
                });
            } else {
                searchDropdown.innerHTML = '<div class="text-muted text-center py-2 small">No matches found</div>';
            }
        } catch (error) {
            console.error('Error fetching suggestions:', error);
            searchDropdown.innerHTML = '<div class="text-danger text-center py-2 small">Error loading results</div>';
        }
    }, 300);
}

function showSearchDropdown() {
    searchDropdown.style.display = 'block';
}

function hideSearchDropdown() {
    searchDropdown.style.display = 'none';
}

// Search button click
searchBtn.addEventListener('click', () => {
    const searchValue = mainSearch.value.trim();
    if (searchValue) {
        searchTricycle(searchValue);
    }
});

// Enter key search
mainSearch.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        const searchValue = mainSearch.value.trim();
        if (searchValue) {
            searchTricycle(searchValue);
        }
    }
});

// E-Signature Canvas
const signatureCanvas = document.getElementById('signatureCanvas');
const ctx = signatureCanvas.getContext('2d');
let isDrawing = false;
let lastX = 0;
let lastY = 0;

// Set canvas size properly
function resizeCanvas() {
    const rect = signatureCanvas.getBoundingClientRect();
    signatureCanvas.width = rect.width;
    signatureCanvas.height = 150;
}

// Initial resize and window resize handler
window.addEventListener('resize', resizeCanvas);

// Resize canvas when modal is shown
document.getElementById('submitModal').addEventListener('shown.bs.modal', () => {
    resizeCanvas();
});

// Drawing functions
function startDrawing(e) {
    isDrawing = true;
    const rect = signatureCanvas.getBoundingClientRect();
    lastX = (e.clientX || e.touches[0].clientX) - rect.left;
    lastY = (e.clientY || e.touches[0].clientY) - rect.top;
}

function draw(e) {
    if (!isDrawing) return;
    e.preventDefault();
    
    const rect = signatureCanvas.getBoundingClientRect();
    const x = (e.clientX || e.touches[0].clientX) - rect.left;
    const y = (e.clientY || e.touches[0].clientY) - rect.top;

    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.stroke();

    lastX = x;
    lastY = y;
}

function stopDrawing() {
    isDrawing = false;
}

// Mouse events
signatureCanvas.addEventListener('mousedown', startDrawing);
signatureCanvas.addEventListener('mousemove', draw);
signatureCanvas.addEventListener('mouseup', stopDrawing);
signatureCanvas.addEventListener('mouseout', stopDrawing);

// Touch events for mobile
signatureCanvas.addEventListener('touchstart', startDrawing);
signatureCanvas.addEventListener('touchmove', draw);
signatureCanvas.addEventListener('touchend', stopDrawing);

// Clear signature button
document.getElementById('clearSignature').addEventListener('click', () => {
    ctx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
});

// Claimant option radio buttons
const claimantDriverRadio = document.getElementById('claimantDriver');
const claimantManualRadio = document.getElementById('claimantManual');
const claimantNameDriver = document.getElementById('claimantNameDriver');
const claimantNameManual = document.getElementById('claimantNameManual');

claimantDriverRadio.addEventListener('change', () => {
    if (claimantDriverRadio.checked) {
        claimantNameDriver.disabled = false;
        claimantNameManual.disabled = true;
        claimantNameManual.value = '';
    }
});

claimantManualRadio.addEventListener('change', () => {
    if (claimantManualRadio.checked) {
        claimantNameDriver.disabled = true;
        claimantNameManual.disabled = false;
    }
});

// Show proof of claim modal
function showProofModal(voucherIndex, voucherId) {
    const claim = claimsData.find(c => c.voucher_number === voucherIndex);
    
    if (claim) {
        document.getElementById('proofVoucherNo').textContent = voucherId;
        document.getElementById('proofClaimant').textContent = claim.claimant_name || 'N/A';
        document.getElementById('proofDate').textContent = claim.claim_date ? new Date(claim.claim_date).toLocaleString() : 'N/A';
        document.getElementById('proofSignature').src = claim.e_signature || '';
        
        const proofModal = new bootstrap.Modal(document.getElementById('proofModal'));
        proofModal.show();
    } else {
        alert('No proof data found for this voucher.');
    }
}

// Autocomplete events
mainSearch.addEventListener('input', (e) => {
    searchSuggestions(e.target.value);
});

// Hide dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.input-group')) {
        hideSearchDropdown();
    }
});

// Keep dropdown open when focusing search
mainSearch.addEventListener('focus', () => {
    if (mainSearch.value.length >= 2) {
        showSearchDropdown();
    }
});

// Clear button - resets all claimed vouchers
document.getElementById('clearBtn').addEventListener('click', () => {
    if (currentTricycleNo) {
        renderVoucherButtons(currentTricycleNo, claimedVouchersList);
    }
});

// Confirm Submit button
document.getElementById('confirmSubmit').addEventListener('click', async () => {
    // Get claimant name based on selected option
    let claimantName = '';
    if (claimantDriverRadio.checked) {
        claimantName = claimantNameDriver.value;
    } else {
        claimantName = claimantNameManual.value.trim();
    }
    
    // Set hidden field value
    document.getElementById('claimantName').value = claimantName;
    
    const signatureData = signatureCanvas.toDataURL();
    
    // Get newly claimed vouchers
    const newClaimedCards = document.querySelectorAll('.voucher-card.claimed:not([data-previously-claimed])');
    const newVouchers = [];
    
    newClaimedCards.forEach(card => {
        const index = parseInt(card.dataset.index);
        if (!claimedVouchersList.includes(index)) {
            newVouchers.push(index);
        }
    });
    
    if (newVouchers.length === 0) {
        alert('Please select at least one new voucher to claim.');
        return;
    }

    if (!currentTricycleNo) {
        alert('Please search for a tricycle first.');
        return;
    }

    try {
        const response = await fetch('api_claim_voucher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tricycle_no: currentTricycleNo,
                vouchers: newVouchers,
                claimant_name: claimantName,
                e_signature: signatureData
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            
            // Close modal and reset
            const modal = bootstrap.Modal.getInstance(document.getElementById('submitModal'));
            modal.hide();
            
            // Clear form - reset to driver option
            document.getElementById('claimantName').value = '';
            document.getElementById('claimantNameManual').value = '';
            claimantDriverRadio.checked = true;
            claimantNameDriver.disabled = false;
            claimantNameManual.disabled = true;
            ctx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
            
            // Refresh tricycle data
            searchTricycle(currentTricycleNo);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error submitting claim:', error);
        alert('Error submitting claim. Please try again.');
    }
});