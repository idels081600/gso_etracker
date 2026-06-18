let currentHouseholdCode = '';
let currentHouseholdName = '';
let currentClaimData = null;
let pendingSignatureData = '';

const currentHouseholdEl = document.getElementById('currentHouseholdCode');
const currentStatusEl = document.getElementById('currentHouseholdStatus');
const householdNameEl = document.getElementById('currentHouseholdName');
const mainSearch = document.getElementById('mainSearch');
const searchBtn = document.getElementById('searchBtn');
const searchDropdown = document.getElementById('searchDropdown');
const claimStateCard = document.getElementById('claimStateCard');
const claimStateText = document.getElementById('claimStateText');
const claimStateHint = document.getElementById('claimStateHint');
const reviewClaimBtn = document.getElementById('reviewClaimBtn');
const viewProofBtn = document.getElementById('viewProofBtn');

let searchDebounceTimer = null;
let currentPage = 1;
let currentQuery = '';
let isLoadingMore = false;
let hasMoreResults = false;

function resetClaimView() {
    currentHouseholdCode = '';
    currentHouseholdName = '';
    currentClaimData = null;
    currentHouseholdEl.textContent = '----';
    currentStatusEl.textContent = 'Search to load';
    householdNameEl.textContent = 'No household selected';
    claimStateCard.className = 'card border-2 border-secondary-subtle bg-light';
    claimStateText.textContent = 'No household loaded';
    claimStateHint.textContent = 'Search by household code or household name.';
    reviewClaimBtn.disabled = true;
    viewProofBtn.classList.add('d-none');
}

function renderClaimState(data) {
    currentHouseholdCode = data.household_code;
    currentHouseholdName = data.household_name;
    currentClaimData = data.claim_data || null;

    currentHouseholdEl.textContent = data.household_code;
    currentStatusEl.textContent = data.status;
    householdNameEl.textContent = data.household_name;

    const claimantSelect = document.getElementById('claimantNameHousehold');
    claimantSelect.innerHTML = `<option value="${data.household_name}">${data.household_name}</option>`;
    claimantSelect.value = data.household_name;

    if (data.status !== 'Active') {
        claimStateCard.className = 'card border-2 border-secondary bg-light';
        claimStateText.textContent = 'Inactive';
        claimStateHint.textContent = 'This household is inactive and cannot claim rice assistance.';
        reviewClaimBtn.disabled = true;
        if (data.is_claimed === 1) {
            viewProofBtn.classList.remove('d-none');
        } else {
            viewProofBtn.classList.add('d-none');
        }
    } else if (data.is_claimed === 1) {
        claimStateCard.className = 'card border-2 border-rice-teal bg-rice-teal-soft';
        claimStateText.textContent = 'Claimed';
        claimStateHint.textContent = 'This household has already claimed its rice assistance voucher.';
        reviewClaimBtn.disabled = true;
        viewProofBtn.classList.remove('d-none');
    } else {
        claimStateCard.className = 'card border-2 border-rice-danger bg-rice-danger-soft';
        claimStateText.textContent = 'Unclaimed';
        claimStateHint.textContent = 'This household may claim one rice assistance voucher.';
        reviewClaimBtn.disabled = false;
        viewProofBtn.classList.add('d-none');
    }
}

async function searchHousehold(householdLookup) {
    try {
        let query = '';
        if (typeof householdLookup === 'object' && householdLookup !== null && householdLookup.household_id) {
            query = `household_id=${encodeURIComponent(householdLookup.household_id)}`;
        } else {
            query = `household_code=${encodeURIComponent(householdLookup)}`;
        }

        const response = await fetch(`api_get_rice_claim.php?${query}`);
        const data = await response.json();

        if (data.success) {
            renderClaimState(data.data);
            hideSearchDropdown();
        } else {
            alert(data.message || 'Household not found.');
            resetClaimView();
        }
    } catch (error) {
        console.error('Error searching household:', error);
        alert('Error searching for household. Please try again.');
    }
}

async function searchSuggestions(query, page = 1, append = false) {
    if (query.length < 2) {
        hideSearchDropdown();
        return;
    }

    if (!append) {
        clearTimeout(searchDebounceTimer);
        currentPage = 1;
        currentQuery = query;
        hasMoreResults = false;
        searchDropdown.innerHTML = '<div class="text-muted text-center py-2 small">Searching...</div>';
        showSearchDropdown();

        searchDebounceTimer = setTimeout(async () => {
            await loadSearchResults(query, 1, false);
        }, 300);
    } else {
        await loadSearchResults(query, page, true);
    }
}

async function loadSearchResults(query, page, append) {
    if (isLoadingMore) {
        return;
    }

    isLoadingMore = true;

    if (append) {
        const loadingEl = document.createElement('div');
        loadingEl.id = 'loadMoreSpinner';
        loadingEl.className = 'text-center py-2 small text-muted';
        loadingEl.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Loading more...';
        searchDropdown.appendChild(loadingEl);
    }

    try {
        const response = await fetch(`api_search_rice_suggestions.php?q=${encodeURIComponent(query)}&page=${page}`);
        const data = await response.json();

        if (append) {
            const spinner = document.getElementById('loadMoreSpinner');
            if (spinner) {
                spinner.remove();
            }
        } else {
            searchDropdown.innerHTML = '';
        }

        if (data.success && data.results.length > 0) {
            data.results.forEach((item) => {
                const option = document.createElement('button');
                option.className = 'dropdown-item d-flex justify-content-between align-items-center';
                option.innerHTML = `
                    <div>
                        <strong>${item.household_code}</strong>
                        <div class="small text-muted">${item.household_name}</div>
                    </div>
                    <span class="badge ${item.is_claimed ? 'bg-success' : 'bg-warning text-dark'}">
                        ${item.is_claimed ? 'Claimed' : '1 left'}
                    </span>
                `;
                option.addEventListener('click', () => {
                    mainSearch.value = item.household_code;
                    searchHousehold(item);
                });
                searchDropdown.appendChild(option);
            });

            hasMoreResults = data.has_more;
            currentPage = data.page;

            if (data.has_more) {
                const loadMoreTrigger = document.createElement('div');
                loadMoreTrigger.id = 'loadMoreTrigger';
                loadMoreTrigger.className = 'p-1';
                searchDropdown.appendChild(loadMoreTrigger);

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting && hasMoreResults) {
                            searchSuggestions(currentQuery, currentPage + 1, true);
                            observer.disconnect();
                        }
                    });
                }, { root: searchDropdown, threshold: 0.1 });

                observer.observe(loadMoreTrigger);
            }
        } else if (!append) {
            searchDropdown.innerHTML = '<div class="text-muted text-center py-2 small">No matches found</div>';
        }
    } catch (error) {
        console.error('Error fetching suggestions:', error);
        if (!append) {
            searchDropdown.innerHTML = '<div class="text-danger text-center py-2 small">Error loading results</div>';
        }
    }

    isLoadingMore = false;
}

function showSearchDropdown() {
    searchDropdown.style.display = 'block';
}

function hideSearchDropdown() {
    searchDropdown.style.display = 'none';
}

const signatureCanvas = document.getElementById('signatureCanvas');
let ctx = signatureCanvas.getContext('2d');
let isDrawing = false;
let lastX = 0;
let lastY = 0;

function resizeCanvas() {
    const rect = signatureCanvas.getBoundingClientRect();
    if (rect.width <= 0) {
        return;
    }

    const width = Math.max(1, Math.floor(rect.width));
    const height = 150;
    const previousDataUrl = signatureCanvas.width > 0 && signatureCanvas.height > 0 ? signatureCanvas.toDataURL() : null;
    signatureCanvas.width = width;
    signatureCanvas.height = height;
    ctx = signatureCanvas.getContext('2d');

    if (previousDataUrl) {
        const img = new Image();
        img.onload = () => {
            ctx.drawImage(img, 0, 0, signatureCanvas.width, signatureCanvas.height);
        };
        img.src = previousDataUrl;
    }
}

function getCanvasPoint(e) {
    const rect = signatureCanvas.getBoundingClientRect();
    const point = e.touches && e.touches.length ? e.touches[0] : e;
    return {
        x: point.clientX - rect.left,
        y: point.clientY - rect.top
    };
}

function startDrawing(e) {
    e.preventDefault();
    isDrawing = true;
    const point = getCanvasPoint(e);
    lastX = point.x;
    lastY = point.y;
}

function draw(e) {
    if (!isDrawing) {
        return;
    }

    e.preventDefault();
    const point = getCanvasPoint(e);
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(point.x, point.y);
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.stroke();
    lastX = point.x;
    lastY = point.y;
}

function stopDrawing() {
    isDrawing = false;
}

function hasSignature() {
    const imageData = ctx.getImageData(0, 0, signatureCanvas.width, signatureCanvas.height);
    const data = imageData.data;
    for (let i = 0; i < data.length; i += 4) {
        if (data[i + 3] > 0) {
            return true;
        }
    }
    return false;
}

function populateProofModal() {
    if (!currentClaimData) {
        alert('No proof data found for this claim.');
        return;
    }

    document.getElementById('proofHouseholdCode').textContent = currentHouseholdCode;
    document.getElementById('proofClaimant').textContent = currentClaimData.claimant_name || 'N/A';
    document.getElementById('proofDate').textContent = currentClaimData.claim_date ? new Date(currentClaimData.claim_date).toLocaleString() : 'N/A';
        const proofSignature = document.getElementById('proofSignature');
    if (currentClaimData.e_signature) {
        proofSignature.src = currentClaimData.e_signature;
        proofSignature.style.display = 'block';
    } else {
        proofSignature.src = '';
        proofSignature.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    resetClaimView();
    resizeCanvas();
});

window.addEventListener('resize', resizeCanvas);
document.getElementById('claimModal').addEventListener('shown.bs.modal', resizeCanvas);

mainSearch.addEventListener('input', (e) => {
    searchSuggestions(e.target.value.trim());
});

mainSearch.addEventListener('focus', () => {
    if (mainSearch.value.trim().length >= 2) {
        showSearchDropdown();
    }
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.input-group')) {
        hideSearchDropdown();
    }
});

searchBtn.addEventListener('click', () => {
    const searchValue = mainSearch.value.trim();
    if (searchValue) {
        searchHousehold(searchValue);
    }
});

mainSearch.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        const searchValue = mainSearch.value.trim();
        if (searchValue) {
            searchHousehold(searchValue);
        }
    }
});

signatureCanvas.addEventListener('mousedown', startDrawing);
signatureCanvas.addEventListener('mousemove', draw);
signatureCanvas.addEventListener('mouseup', stopDrawing);
signatureCanvas.addEventListener('mouseout', stopDrawing);
signatureCanvas.addEventListener('touchstart', startDrawing);
signatureCanvas.addEventListener('touchmove', draw);
signatureCanvas.addEventListener('touchend', stopDrawing);

document.getElementById('clearSignature').addEventListener('click', () => {
    ctx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
    pendingSignatureData = '';
});

const claimantHouseholdRadio = document.getElementById('claimantHousehold');
const claimantManualRadio = document.getElementById('claimantManual');
const claimantNameHousehold = document.getElementById('claimantNameHousehold');
const claimantNameManual = document.getElementById('claimantNameManual');

claimantHouseholdRadio.addEventListener('change', () => {
    if (claimantHouseholdRadio.checked) {
        claimantNameHousehold.disabled = false;
        claimantNameManual.disabled = true;
        claimantNameManual.value = '';
    }
});

claimantManualRadio.addEventListener('change', () => {
    if (claimantManualRadio.checked) {
        claimantNameHousehold.disabled = true;
        claimantNameManual.disabled = false;
    }
});

document.getElementById('clearBtn').addEventListener('click', () => {
    mainSearch.value = '';
    ctx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
    pendingSignatureData = '';
    claimantHouseholdRadio.checked = true;
    claimantNameHousehold.disabled = false;
    claimantNameManual.disabled = true;
    claimantNameManual.value = '';
    resetClaimView();
});

viewProofBtn.addEventListener('click', () => {
    populateProofModal();
    new bootstrap.Modal(document.getElementById('proofModal')).show();
});

document.getElementById('confirmSubmit').addEventListener('click', () => {
    let claimantName = '';
    if (claimantHouseholdRadio.checked) {
        claimantName = claimantNameHousehold.value;
    } else {
        claimantName = claimantNameManual.value.trim();
        if (!claimantName) {
            alert('Please enter claimant name manually.');
            claimantNameManual.focus();
            return;
        }
    }

    if (!currentHouseholdCode) {
        alert('Please search for a household first.');
        return;
    }

    if (currentStatusEl.textContent !== 'Active') {
        alert('Only active households can claim rice assistance.');
        return;
    }

    if (currentClaimData) {
        alert('This household has already claimed the rice assistance voucher.');
        return;
    }

    if (!hasSignature()) {
        alert('Please provide your e-signature on the canvas.');
        return;
    }

    pendingSignatureData = signatureCanvas.toDataURL('image/png');
    document.getElementById('claimantName').value = claimantName;
    document.getElementById('confirmHouseholdCode').textContent = currentHouseholdCode;
    document.getElementById('confirmHouseholdName').textContent = currentHouseholdName;
    document.getElementById('confirmClaimantName').textContent = claimantName;
    document.getElementById('confirmSignaturePreview').src = pendingSignatureData;

    const claimModal = bootstrap.Modal.getInstance(document.getElementById('claimModal'));
    claimModal.hide();
    new bootstrap.Modal(document.getElementById('finalConfirmModal')).show();
});

document.getElementById('finalConfirmSubmit').addEventListener('click', async () => {
    const claimantName = document.getElementById('claimantName').value;
    if (!pendingSignatureData) {
        alert('Missing e-signature. Please review the signature again before submitting.');
        return;
    }

    try {
        const response = await fetch('api_claim_rice_voucher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                household_code: currentHouseholdCode,
                claimant_name: claimantName,
                e_signature: pendingSignatureData
            })
        });

        const data = await response.json();
        if (data.success) {
            alert(data.message);
            const finalModal = bootstrap.Modal.getInstance(document.getElementById('finalConfirmModal'));
            finalModal.hide();
            ctx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
            pendingSignatureData = '';
            claimantNameManual.value = '';
            claimantHouseholdRadio.checked = true;
            claimantNameHousehold.disabled = false;
            claimantNameManual.disabled = true;
            searchHousehold(currentHouseholdCode);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error submitting rice claim:', error);
        alert('Error submitting claim. Please try again.');
    }
});
