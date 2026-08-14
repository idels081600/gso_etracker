let currentHousehold = null;
let searchDebounceTimer = null;
let currentPage = 1;
let currentQuery = '';
let isLoadingMore = false;
let hasMoreResults = false;

const searchInput = document.getElementById('crossCheckSearch');
const searchBtn = document.getElementById('crossCheckSearchBtn');
const searchDropdown = document.getElementById('crossCheckDropdown');
const clearBtn = document.getElementById('crossCheckClearBtn');
const toggleCheckedBtn = document.getElementById('toggleCheckedBtn');
const checkedStatusBadge = document.getElementById('checkedStatusBadge');
const selectedHouseholdCode = document.getElementById('selectedHouseholdCode');
const selectedHouseholdName = document.getElementById('selectedHouseholdName');
const selectedHouseholdMeta = document.getElementById('selectedHouseholdMeta');
const stateCard = document.getElementById('crossCheckStateCard');
const stateText = document.getElementById('crossCheckStateText');
const stateHint = document.getElementById('crossCheckStateHint');
const detailHouseholdCode = document.getElementById('detailHouseholdCode');
const detailHouseholdName = document.getElementById('detailHouseholdName');
const detailBarangay = document.getElementById('detailBarangay');
const detailCheckedTime = document.getElementById('detailCheckedTime');

function formatDateTime(value) {
    if (!value) {
        return 'N/A';
    }

    const parsed = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString();
}

function updateCheckedUI(isChecked) {
    checkedStatusBadge.className = `badge checked-badge ${isChecked ? 'text-bg-success' : 'text-bg-secondary'}`;
    checkedStatusBadge.textContent = isChecked ? 'Checked' : 'Not Checked';
    toggleCheckedBtn.disabled = !currentHousehold;
    toggleCheckedBtn.className = isChecked ? 'btn btn-outline-warning' : 'btn btn-outline-success';
    toggleCheckedBtn.innerHTML = isChecked
        ? '<i class="bi bi-arrow-counterclockwise me-1"></i>Uncheck'
        : '<i class="bi bi-check2-square me-1"></i>Mark Checked';
}

function resetCrossCheckView() {
    currentHousehold = null;
    selectedHouseholdCode.textContent = '----';
    selectedHouseholdName.textContent = 'No household selected';
    selectedHouseholdMeta.textContent = 'Search to load';
    stateCard.className = 'card border-2 border-secondary-subtle bg-light mb-4';
    stateText.textContent = 'No household loaded';
    stateHint.textContent = 'Search by household name or household code.';
    detailHouseholdCode.textContent = '--';
    detailHouseholdName.textContent = '--';
    detailBarangay.textContent = '--';
    detailCheckedTime.textContent = '--';
    updateCheckedUI(false);
}

function renderCrossCheckState(data) {
    currentHousehold = data;
    const barangay = data.address && data.address.trim() !== '' ? data.address : 'N/A';

    selectedHouseholdCode.textContent = data.household_code;
    selectedHouseholdName.textContent = data.household_name;
    selectedHouseholdMeta.textContent = barangay;

    detailHouseholdCode.textContent = data.household_code;
    detailHouseholdName.textContent = data.household_name;
    detailBarangay.textContent = barangay;
    detailCheckedTime.textContent = formatDateTime(data.modified);

    updateCheckedUI(Number(data.is_checked) === 1);

    if (data.status !== 'Active') {
        stateCard.className = 'card border-2 border-secondary bg-light mb-4';
        stateText.textContent = 'Inactive';
        stateHint.textContent = 'This household exists but is inactive.';
    } else if (data.is_claimed === 1) {
        stateCard.className = 'card border-2 border-rice-teal bg-rice-teal-soft mb-4';
        stateText.textContent = 'Claimed';
        stateHint.textContent = 'This household exists and has already claimed rice assistance.';
    } else {
        stateCard.className = 'card border-2 border-rice-danger bg-rice-danger-soft mb-4';
        stateText.textContent = 'Not Claimed';
        stateHint.textContent = 'This household exists and has not yet claimed rice assistance.';
    }
}

function renderNotFound(message = 'Household not found.') {
    currentHousehold = null;
    selectedHouseholdCode.textContent = '----';
    selectedHouseholdName.textContent = 'No household found';
    selectedHouseholdMeta.textContent = 'Check the search term';
    stateCard.className = 'card border-2 border-warning bg-warning-subtle mb-4';
    stateText.textContent = 'Household not found';
    stateHint.textContent = message;
    detailHouseholdCode.textContent = '--';
    detailHouseholdName.textContent = '--';
    detailBarangay.textContent = '--';
    detailCheckedTime.textContent = '--';
    updateCheckedUI(false);
}

async function loadHousehold(householdLookup) {
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
            renderCrossCheckState(data.data);
            hideSearchDropdown();
        } else {
            renderNotFound(data.message || 'Household not found.');
        }
    } catch (error) {
        console.error('Error loading household:', error);
        renderNotFound('Unable to load household right now.');
    }
}

async function updateCheckedStatus(nextChecked) {
    if (!currentHousehold || !currentHousehold.id) {
        return;
    }

    toggleCheckedBtn.disabled = true;

    try {
        const response = await fetch('api_update_rice_checked_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                household_id: currentHousehold.id,
                is_checked: nextChecked ? 1 : 0
            })
        });

        const data = await response.json();
        if (!data.success) {
            alert(data.message || 'Unable to update checked status.');
            toggleCheckedBtn.disabled = false;
            return;
        }

        currentHousehold.is_checked = Number(data.is_checked);
        currentHousehold.modified = data.modified;
        updateCheckedUI(Number(data.is_checked) === 1);
        detailCheckedTime.textContent = formatDateTime(data.modified);
    } catch (error) {
        console.error('Error updating checked status:', error);
        alert('Unable to update checked status right now.');
        toggleCheckedBtn.disabled = false;
        return;
    }

    toggleCheckedBtn.disabled = false;
}

function showSearchDropdown() {
    searchDropdown.style.display = 'block';
}

function hideSearchDropdown() {
    searchDropdown.style.display = 'none';
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
                option.type = 'button';
                option.className = 'dropdown-item d-flex justify-content-between align-items-center';
                option.innerHTML = `
                    <div>
                        <strong>${item.household_code}</strong>
                        <div class="small text-muted">${item.household_name}</div>
                    </div>
                    <span class="badge ${item.is_claimed ? 'bg-success' : 'bg-warning text-dark'}">
                        ${item.is_claimed ? 'Claimed' : 'Not Claimed'}
                    </span>
                `;
                option.addEventListener('click', () => {
                    searchInput.value = item.household_code;
                    loadHousehold(item);
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

searchInput.addEventListener('input', (event) => {
    const query = event.target.value.trim();
    if (!query) {
        hideSearchDropdown();
        resetCrossCheckView();
        return;
    }

    searchSuggestions(query);
});

searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        const query = searchInput.value.trim();
        if (query.length >= 2) {
            loadHousehold(query);
        }
    }
});

searchBtn.addEventListener('click', () => {
    const query = searchInput.value.trim();
    if (query.length < 2) {
        renderNotFound('Please enter at least 2 characters to search.');
        return;
    }
    loadHousehold(query);
});

toggleCheckedBtn.addEventListener('click', () => {
    if (!currentHousehold) {
        return;
    }
    const nextChecked = Number(currentHousehold.is_checked) !== 1;
    updateCheckedStatus(nextChecked);
});

clearBtn.addEventListener('click', () => {
    searchInput.value = '';
    hideSearchDropdown();
    resetCrossCheckView();
    searchInput.focus();
});

document.addEventListener('click', (event) => {
    if (!searchDropdown.contains(event.target) && event.target !== searchInput) {
        hideSearchDropdown();
    }
});

resetCrossCheckView();
