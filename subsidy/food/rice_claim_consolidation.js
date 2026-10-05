const consolidationConfig = window.RICE_CONSOLIDATION_CONFIG || {};
const pageState = { page: 1, filter: 'all', query: '', lastRequestKey: '', totalPages: 1, selectedCode: '', selectedPair: null, pendingDirection: '', reviewMode: false, reviewQueue: [], reviewIndex: 0, reviewSummary: { total: 540, pending: 540, reviewed: 0 } };
let searchTimer = null;
let listController = null;

const pairsBody = document.getElementById('claimPairsBody');
const pairSearch = document.getElementById('pairSearch');
const pairFilters = document.getElementById('pairFilters');
const paginationInfo = document.getElementById('pairsPaginationInfo');
const pageLabel = document.getElementById('pairsPageLabel');
const prevBtn = document.getElementById('pairsPrevBtn');
const nextBtn = document.getElementById('pairsNextBtn');
const mediumReviewCount = document.getElementById('mediumReviewPendingCount');
const mediumReviewStrip = document.getElementById('mediumReviewStrip');
const mediumReviewActions = document.getElementById('mediumReviewActions');
const pairModal = new bootstrap.Modal(document.getElementById('claimPairModal'));
const copyModal = new bootstrap.Modal(document.getElementById('copySignatureModal'));
const toast = new bootstrap.Toast(document.getElementById('consolidationToast'), { delay: 3500 });

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatDate(value) {
    if (!value) return 'N/A';
    const parsed = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString();
}

function waveLabel(wave) {
    return {
        first_wave: 'First Wave',
        second_wave: 'Second Wave',
        third_wave: 'Third Wave',
        fourth_wave: 'Fourth Wave',
    }[wave] || wave;
}

function showToast(message) {
    document.getElementById('consolidationToastMessage').textContent = message;
    toast.show();
}

function statePresentation(state) {
    const states = {
        all_four: ['All Four', 'text-bg-success'],
        partial_claims: ['Partial Claims', 'text-bg-info'],
        name_difference: ['Name Difference', 'text-bg-warning'],
    };
    return states[state] || ['Unknown', 'text-bg-secondary'];
}

function proofSummary(prefix, record) {
    const claimId = record[`${prefix}_claim_id`];
    if (!claimId) return '<span class="text-muted">No claim record</span>';
    const hasSignature = Number(record[`${prefix}_has_signature`]) === 1;
    return `
        <div class="fw-semibold">${escapeHtml(record[`${prefix}_claimant_name`] || 'Unnamed claimant')}</div>
        <div class="claim-meta">${escapeHtml(formatDate(record[`${prefix}_claim_date`]))}</div>
        <span class="badge ${hasSignature ? 'text-bg-success' : 'text-bg-danger'} mt-1">${hasSignature ? 'Signature saved' : 'No signature'}</span>
    `;
}

function renderRows(records) {
    if (!records.length) {
        pairsBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted loading-cell">No claimed records match this search and filter.</td></tr>';
        return;
    }

    pairsBody.innerHTML = records.map((record) => {
        const [stateText, stateClass] = statePresentation(record.pair_state);
        const name = record.first_household_name || record.second_household_name || record.third_household_name || record.fourth_household_name || 'Unknown household';
        const address = record.first_address || record.second_address || record.third_address || record.fourth_address || 'No address';
        return `
            <tr>
                <td class="ps-3">
                    <div class="fw-bold text-rice-teal">${escapeHtml(record.household_code)}</div>
                    <div>${escapeHtml(name)}</div>
                    <div class="claim-meta">${escapeHtml(address)}</div>
                </td>
                <td><span class="badge status-badge ${stateClass}">${stateText}</span></td>
                <td>${proofSummary('first', record)}</td>
                <td>${proofSummary('second', record)}</td>
                <td>${proofSummary('third', record)}</td>
                <td>${proofSummary('fourth', record)}</td>
                <td class="text-end pe-3"><button type="button" class="btn btn-outline-success btn-sm review-pair-btn" data-code="${escapeHtml(record.household_code)}"><i class="bi bi-eye me-1"></i>Review</button></td>
            </tr>`;
    }).join('');

    pairsBody.querySelectorAll('.review-pair-btn').forEach((button) => {
        button.addEventListener('click', () => {
            exitMediumReviewMode();
            openPair(button.dataset.code);
        });
    });
}

function renderSummary(summary) {
    document.querySelectorAll('[data-count]').forEach((badge) => {
        badge.textContent = Number(summary[badge.dataset.count] || 0).toLocaleString();
    });
}

async function loadPairs({ force = false } = {}) {
    const requestKey = [pageState.page, pageState.filter, pageState.query].join('|');
    if (!force && requestKey === pageState.lastRequestKey) return;

    if (listController) listController.abort();
    listController = new AbortController();
    pairsBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted loading-cell"><span class="spinner-border spinner-border-sm me-2"></span>Loading claimed records...</td></tr>';

    const params = new URLSearchParams({ page: pageState.page, filter: pageState.filter, q: pageState.query });
    try {
        const response = await fetch(`api_get_rice_claim_pairs.php?${params}`, { cache: 'no-store', signal: listController.signal });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load claimed records.');

        pageState.lastRequestKey = requestKey;
        pageState.totalPages = payload.pagination.total_pages;
        pageState.page = payload.pagination.page;
        renderRows(payload.data);
        if (payload.summary) renderSummary(payload.summary);

        const total = payload.pagination.total;
        const start = total ? ((pageState.page - 1) * payload.pagination.per_page) + 1 : 0;
        const end = Math.min(pageState.page * payload.pagination.per_page, total);
        paginationInfo.textContent = `Showing ${start.toLocaleString()} to ${end.toLocaleString()} of ${total.toLocaleString()} records`;
        pageLabel.textContent = `Page ${pageState.page} of ${pageState.totalPages}`;
        prevBtn.disabled = pageState.page <= 1;
        nextBtn.disabled = pageState.page >= pageState.totalPages;
    } catch (error) {
        if (error.name === 'AbortError') return;
        pairsBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger loading-cell">${escapeHtml(error.message)}</td></tr>`;
    }
}

function setSignature(prefix, signature) {
    const image = document.getElementById(`${prefix}Signature`);
    const empty = document.getElementById(`${prefix}SignatureEmpty`);
    if (signature) {
        image.src = signature;
        image.classList.remove('d-none');
        empty.classList.add('d-none');
    } else {
        image.removeAttribute('src');
        image.classList.add('d-none');
        empty.classList.remove('d-none');
    }
}

function setConfirmationSignature(imageId, emptyId, signature) {
    const image = document.getElementById(imageId);
    const empty = document.getElementById(emptyId);
    if (signature) {
        image.src = signature;
        image.classList.remove('d-none');
        empty.classList.add('d-none');
    } else {
        image.removeAttribute('src');
        image.classList.add('d-none');
        empty.classList.remove('d-none');
    }
}
function renderWave(prefix, wave, editable) {
    const exists = wave && wave.claim_id;
    document.getElementById(`${prefix}WaveState`).className = `badge ${exists ? 'text-bg-success' : 'text-bg-secondary'}`;
    document.getElementById(`${prefix}WaveState`).textContent = exists ? 'Claimed' : 'Not claimed';
    document.getElementById(`${prefix}HouseholdName`).textContent = wave?.household_name || 'Not found';
    const claimantInput = document.getElementById(`${prefix}ClaimantName`);
    claimantInput.value = wave?.claimant_name || '';
    claimantInput.disabled = !editable || !exists;
    document.getElementById(`save${prefix[0].toUpperCase()}${prefix.slice(1)}ClaimantBtn`).disabled = !editable || !exists;
    document.getElementById(`${prefix}ClaimDate`).textContent = formatDate(wave?.claim_date);
    document.getElementById(`${prefix}Verifier`).textContent = wave?.verifier_name || 'N/A';
    setSignature(prefix, wave?.e_signature || '');
}

function renderFirstHouseholdNameEditor(wave) {
    const editable = Boolean(wave?.household_id && wave?.claim_id);
    document.getElementById('firstBeneficiaryFirstName').value = wave?.first_name || '';
    document.getElementById('firstBeneficiaryLastName').value = wave?.last_name || '';
    document.getElementById('firstBeneficiaryFirstName').disabled = !editable;
    document.getElementById('firstBeneficiaryLastName').disabled = !editable;
    document.getElementById('saveFirstHouseholdNameBtn').disabled = !editable;
    document.getElementById('swapFirstHouseholdNameBtn').disabled = !editable;
}

function updateMediumReviewCount() {
    mediumReviewCount.textContent = Number(pageState.reviewSummary.pending || 0).toLocaleString();
    document.getElementById('startMediumNameReviewBtn').disabled = Number(pageState.reviewSummary.pending || 0) === 0;
}

function currentMediumCandidate() {
    return pageState.reviewMode ? pageState.reviewQueue[pageState.reviewIndex] || null : null;
}

function renderMediumReviewContext() {
    const candidate = currentMediumCandidate();
    const active = Boolean(candidate && candidate.household_code === pageState.selectedCode);
    mediumReviewStrip.classList.toggle('d-none', !active);
    mediumReviewActions.classList.toggle('d-none', !active);
    if (!active) return;

    const reviewed = Number(pageState.reviewSummary.total) - pageState.reviewQueue.length;
    document.getElementById('mediumReviewProgress').textContent = `Review ${reviewed + 1} of ${Number(pageState.reviewSummary.total).toLocaleString()}`;
    document.getElementById('mediumReviewAddress').textContent = candidate.address || 'No address';
    document.getElementById('mediumReviewCurrentName').textContent = candidate.current_name;
    document.getElementById('mediumReviewSuggestedName').textContent = candidate.suggested_name;
}

function exitMediumReviewMode() {
    pageState.reviewMode = false;
    mediumReviewStrip.classList.add('d-none');
    mediumReviewActions.classList.add('d-none');
}

async function loadMediumReviewQueue(openFirst = false) {
    const button = document.getElementById('startMediumNameReviewBtn');
    if (openFirst) button.disabled = true;
    try {
        const response = await fetch('api_get_rice_name_review_queue.php', { cache: 'no-store' });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load the name review queue.');
        pageState.reviewQueue = payload.data || [];
        pageState.reviewSummary = payload.summary || pageState.reviewSummary;
        pageState.reviewIndex = 0;
        updateMediumReviewCount();
        if (!openFirst) return;
        if (!pageState.reviewQueue.length) {
            showToast('All medium-confidence names have been reviewed.');
            return;
        }
        pageState.reviewMode = true;
        await openPair(pageState.reviewQueue[0].household_code);
    } catch (error) {
        if (openFirst) alert(error.message);
        mediumReviewCount.textContent = '!';
        if (openFirst) button.disabled = false;
    }
}

function historyLabel(action) {
    const labels = {
        copy_signature: 'Signature copied',
        update_claimant: 'Claimant updated',
        restore_signature: 'Signature restored',
        restore_claimant: 'Claimant restored',
        update_household_name: 'First-wave household name updated',
        swap_household_name: 'First/last names swapped',
        keep_household_name: 'Household name reviewed and kept',
        restore_household_name: 'Household name restored',
    };
    return labels[action] || action;
}

function renderHistory(history) {
    const container = document.getElementById('claimHistoryList');
    if (!history.length) {
        container.innerHTML = '<div class="text-muted small py-3">No consolidation changes recorded.</div>';
        return;
    }

    container.innerHTML = history.map((entry) => `
        <div class="history-row d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="fw-semibold">${escapeHtml(historyLabel(entry.action_type))}</div>
                <div class="small text-muted">${escapeHtml(waveLabel(entry.target_wave))} · ${escapeHtml(entry.operator_name)} · ${escapeHtml(formatDate(entry.created_at))}</div>
                ${entry.has_been_restored ? '<span class="badge text-bg-secondary mt-1">Restored</span>' : ''}
            </div>
            ${Number(entry.restorable) === 1 ? `<button type="button" class="btn btn-outline-warning btn-sm restore-audit-btn" data-audit-id="${entry.id}"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button>` : ''}
        </div>`).join('');

    container.querySelectorAll('.restore-audit-btn').forEach((button) => {
        button.addEventListener('click', () => restoreAudit(Number(button.dataset.auditId), button));
    });
}

function renderPairDetail(pair) {
    pageState.selectedPair = pair;
    pageState.selectedCode = pair.household_code;
    document.getElementById('pairModalCode').textContent = pair.household_code;
    document.getElementById('pairNameWarning').classList.toggle('d-none', Number(pair.name_difference) !== 1);
    const canConsolidate = Number(pair.can_consolidate) === 1;
    document.getElementById('pairReadOnlyWarning').classList.toggle('d-none', canConsolidate);

    renderWave('first', pair.first_wave, true);
    renderWave('second', pair.second_wave, true);
    renderWave('third', pair.third_wave, true);
    renderWave('fourth', pair.fourth_wave, true);
    document.getElementById('fourthLockedNotice').classList.toggle('d-none', Number(pair.fourth_wave_locked) !== 1);
    renderFirstHouseholdNameEditor(pair.first_wave);
    renderMediumReviewContext();
    document.getElementById('copyFirstToSecondBtn').disabled = Number(pair.can_consolidate) !== 1 || !pair.first_wave?.e_signature;
    document.getElementById('copySecondToFirstBtn').disabled = Number(pair.can_consolidate) !== 1 || !pair.second_wave?.e_signature;
    document.getElementById('prepareCopySignatureBtn').disabled = !canConsolidate;
    renderHistory(pair.history || []);
}

async function openPair(householdCode) {
    pageState.selectedCode = householdCode;
    document.getElementById('pairModalCode').textContent = householdCode;
    pairModal.show();
    document.getElementById('claimHistoryList').innerHTML = '<div class="text-muted small py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading proof details...</div>';
    try {
        const response = await fetch(`api_get_rice_claim_pair.php?household_code=${encodeURIComponent(householdCode)}`, { cache: 'no-store' });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load claim proof.');
        renderPairDetail(payload.data);
    } catch (error) {
        document.getElementById('claimHistoryList').innerHTML = `<div class="text-danger small py-3">${escapeHtml(error.message)}</div>`;
    }
}

async function mutate(body, button, { refreshPair = true, refreshList = true } = {}) {
    const originalHtml = button?.innerHTML;
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }
    try {
        const response = await fetch('api_manage_rice_claim_consolidation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...body, csrf_token: consolidationConfig.csrfToken }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to save the consolidation change.');
        showToast(payload.message);
        const refreshes = [];
        if (refreshPair) refreshes.push(openPair(pageState.selectedCode));
        if (refreshList) refreshes.push(loadPairs({ force: true }));
        await Promise.all(refreshes);
        return true;
    } catch (error) {
        alert(error.message);
        return false;
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }
}

async function advanceMediumReview() {
    pageState.reviewQueue.splice(pageState.reviewIndex, 1);
    pageState.reviewSummary.pending = pageState.reviewQueue.length;
    pageState.reviewSummary.reviewed = Number(pageState.reviewSummary.total) - pageState.reviewQueue.length;
    updateMediumReviewCount();
    loadPairs({ force: true });

    if (!pageState.reviewQueue.length) {
        exitMediumReviewMode();
        pairModal.hide();
        showToast('All medium-confidence names have been reviewed.');
        return;
    }
    if (pageState.reviewIndex >= pageState.reviewQueue.length) pageState.reviewIndex = 0;
    await openPair(pageState.reviewQueue[pageState.reviewIndex].household_code);
}

async function keepMediumNameAndNext(button) {
    const candidate = currentMediumCandidate();
    if (!candidate) return;
    const success = await mutate({
        action: 'keep_first_wave_household_name',
        household_code: candidate.household_code,
        expected_name: candidate.current_name,
    }, button, { refreshPair: false, refreshList: false });
    if (success) await advanceMediumReview();
}

async function swapMediumNameAndNext(button) {
    const candidate = currentMediumCandidate();
    if (!candidate) return;
    const success = await mutate({
        action: 'update_first_wave_household_name',
        household_code: candidate.household_code,
        mode: 'swap',
    }, button, { refreshPair: false, refreshList: false });
    if (success) await advanceMediumReview();
}

async function saveClaimant(wave, button) {
    const prefix = { first_wave: 'first', second_wave: 'second', third_wave: 'third', fourth_wave: 'fourth' }[wave];
    const claimantName = document.getElementById(`${prefix}ClaimantName`).value.trim();
    if (!claimantName) {
        alert('Claimant name is required.');
        return;
    }
    await mutate({ action: 'update_claimant', household_code: pageState.selectedCode, wave, claimant_name: claimantName }, button);
}

async function saveFirstHouseholdName(button) {
    const firstName = document.getElementById('firstBeneficiaryFirstName').value.trim();
    const lastName = document.getElementById('firstBeneficiaryLastName').value.trim();
    if (!firstName || !lastName) {
        alert('First name and last name are required.');
        return;
    }
    const reviewMode = pageState.reviewMode;
    const success = await mutate({
        action: 'update_first_wave_household_name',
        household_code: pageState.selectedCode,
        mode: 'edit',
        first_name: firstName,
        last_name: lastName,
    }, button, reviewMode ? { refreshPair: false, refreshList: false } : {});
    if (success && reviewMode) await advanceMediumReview();
}

async function swapFirstHouseholdName(button) {
    const firstName = document.getElementById('firstBeneficiaryFirstName').value.trim();
    const lastName = document.getElementById('firstBeneficiaryLastName').value.trim();
    if (!firstName || !lastName) {
        alert('Both first name and last name are required before swapping.');
        return;
    }
    if (!confirm(`Swap first name "${firstName}" with last name "${lastName}" for the first batch?`)) return;
    const reviewMode = pageState.reviewMode;
    const success = await mutate({
        action: 'update_first_wave_household_name',
        household_code: pageState.selectedCode,
        mode: 'swap',
    }, button, reviewMode ? { refreshPair: false, refreshList: false } : {});
    if (success && reviewMode) await advanceMediumReview();
}

function prepareCopy(direction = '') {
    const pair = pageState.selectedPair;
    if (!pair) return;
    let sourceWave = document.getElementById('copySourceWave').value;
    let targetWave = document.getElementById('copyTargetWave').value;
    if (direction === 'first_to_second') [sourceWave, targetWave] = ['first_wave', 'second_wave'];
    if (direction === 'second_to_first') [sourceWave, targetWave] = ['second_wave', 'first_wave'];
    if (sourceWave === targetWave) {
        alert('Choose two different distributions.');
        return;
    }
    const source = pair[sourceWave];
    const target = pair[targetWave];
    if (!source?.claim_id || !target?.claim_id) {
        alert('Both selected distributions must have existing claim records.');
        return;
    }
    if (!source.e_signature) {
        alert('The selected source distribution has no signature to copy.');
        return;
    }
    pageState.pendingSourceWave = sourceWave;
    pageState.pendingTargetWave = targetWave;
    document.getElementById('copyConfirmationText').textContent = `${pair.household_code}: copy ${waveLabel(sourceWave)} signature to ${waveLabel(targetWave)}?`;
    setConfirmationSignature('copySourceSignature', 'copySourceSignatureEmpty', source.e_signature);
    setConfirmationSignature('copyTargetSignature', 'copyTargetSignatureEmpty', target.e_signature);
    copyModal.show();
}

async function restoreAudit(auditId, button) {
    if (!confirm('Restore the value that existed before this change?')) return;
    await mutate({ action: 'restore', audit_id: auditId }, button);
}

pairSearch.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        pageState.query = pairSearch.value.trim();
        pageState.page = 1;
        loadPairs();
    }, 250);
});

pairFilters.addEventListener('click', (event) => {
    const button = event.target.closest('[data-filter]');
    if (!button) return;
    const pageFilters = pairFilters.querySelectorAll('[data-filter]');
    pageFilters.forEach((item) => item.classList.toggle('active', item === button));
    pageState.filter = button.dataset.filter;
    pageState.page = 1;
    loadPairs();
});

document.getElementById('refreshPairsBtn').addEventListener('click', () => loadPairs({ force: true }));
document.getElementById('startMediumNameReviewBtn').addEventListener('click', () => loadMediumReviewQueue(true));
document.getElementById('keepMediumNameNextBtn').addEventListener('click', (event) => keepMediumNameAndNext(event.currentTarget));
document.getElementById('swapMediumNameNextBtn').addEventListener('click', (event) => swapMediumNameAndNext(event.currentTarget));
prevBtn.addEventListener('click', () => { if (pageState.page > 1) { pageState.page -= 1; loadPairs(); } });
nextBtn.addEventListener('click', () => { if (pageState.page < pageState.totalPages) { pageState.page += 1; loadPairs(); } });
document.getElementById('saveFirstClaimantBtn').addEventListener('click', (event) => saveClaimant('first_wave', event.currentTarget));
document.getElementById('saveSecondClaimantBtn').addEventListener('click', (event) => saveClaimant('second_wave', event.currentTarget));
document.getElementById('saveThirdClaimantBtn').addEventListener('click', (event) => saveClaimant('third_wave', event.currentTarget));
document.getElementById('saveFourthClaimantBtn').addEventListener('click', (event) => saveClaimant('fourth_wave', event.currentTarget));
document.getElementById('saveFirstHouseholdNameBtn').addEventListener('click', (event) => saveFirstHouseholdName(event.currentTarget));
document.getElementById('swapFirstHouseholdNameBtn').addEventListener('click', (event) => swapFirstHouseholdName(event.currentTarget));
document.getElementById('copyFirstToSecondBtn').addEventListener('click', () => prepareCopy('first_to_second'));
document.getElementById('copySecondToFirstBtn').addEventListener('click', () => prepareCopy('second_to_first'));
document.getElementById('prepareCopySignatureBtn').addEventListener('click', () => prepareCopy());
document.getElementById('confirmCopySignatureBtn').addEventListener('click', async (event) => {
    const success = await mutate({ action: 'copy_signature', household_code: pageState.selectedCode, source_wave: pageState.pendingSourceWave, target_wave: pageState.pendingTargetWave, confirmed: 1 }, event.currentTarget);
    if (success) copyModal.hide();
});

loadPairs();
loadMediumReviewQueue();
