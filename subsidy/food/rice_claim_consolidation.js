const consolidationConfig = window.RICE_CONSOLIDATION_CONFIG || {};
const pageState = { page: 1, filter: 'all', query: '', totalPages: 1, selectedCode: '', selectedPair: null, pendingDirection: '' };
let searchTimer = null;
let listController = null;

const pairsBody = document.getElementById('claimPairsBody');
const pairSearch = document.getElementById('pairSearch');
const pairFilters = document.getElementById('pairFilters');
const paginationInfo = document.getElementById('pairsPaginationInfo');
const pageLabel = document.getElementById('pairsPageLabel');
const prevBtn = document.getElementById('pairsPrevBtn');
const nextBtn = document.getElementById('pairsNextBtn');
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
    return wave === 'second_wave' ? 'Second Wave' : 'First Wave';
}

function showToast(message) {
    document.getElementById('consolidationToastMessage').textContent = message;
    toast.show();
}

function statePresentation(state) {
    const states = {
        both_waves: ['Both Waves', 'text-bg-success'],
        first_wave_only: ['First Only', 'text-bg-warning'],
        second_wave_only: ['Second Only', 'text-bg-info'],
        unmatched: ['Unmatched', 'text-bg-danger'],
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
        pairsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted loading-cell">No claimed records match this search and filter.</td></tr>';
        return;
    }

    pairsBody.innerHTML = records.map((record) => {
        const [stateText, stateClass] = statePresentation(record.pair_state);
        const name = record.first_household_name || record.second_household_name || 'Unknown household';
        const address = record.first_address || record.second_address || 'No address';
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
                <td class="text-end pe-3"><button type="button" class="btn btn-outline-success btn-sm review-pair-btn" data-code="${escapeHtml(record.household_code)}"><i class="bi bi-eye me-1"></i>Review</button></td>
            </tr>`;
    }).join('');

    pairsBody.querySelectorAll('.review-pair-btn').forEach((button) => {
        button.addEventListener('click', () => openPair(button.dataset.code));
    });
}

function renderSummary(summary) {
    document.querySelectorAll('[data-count]').forEach((badge) => {
        badge.textContent = Number(summary[badge.dataset.count] || 0).toLocaleString();
    });
}

async function loadPairs() {
    if (listController) listController.abort();
    listController = new AbortController();
    pairsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted loading-cell"><span class="spinner-border spinner-border-sm me-2"></span>Loading claimed records...</td></tr>';

    const params = new URLSearchParams({ page: pageState.page, filter: pageState.filter, q: pageState.query });
    try {
        const response = await fetch(`api_get_rice_claim_pairs.php?${params}`, { cache: 'no-store', signal: listController.signal });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load claimed records.');

        pageState.totalPages = payload.pagination.total_pages;
        pageState.page = payload.pagination.page;
        renderRows(payload.data);
        renderSummary(payload.summary);

        const total = payload.pagination.total;
        const start = total ? ((pageState.page - 1) * payload.pagination.per_page) + 1 : 0;
        const end = Math.min(pageState.page * payload.pagination.per_page, total);
        paginationInfo.textContent = `Showing ${start.toLocaleString()} to ${end.toLocaleString()} of ${total.toLocaleString()} records`;
        pageLabel.textContent = `Page ${pageState.page} of ${pageState.totalPages}`;
        prevBtn.disabled = pageState.page <= 1;
        nextBtn.disabled = pageState.page >= pageState.totalPages;
    } catch (error) {
        if (error.name === 'AbortError') return;
        pairsBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger loading-cell">${escapeHtml(error.message)}</td></tr>`;
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

function historyLabel(action) {
    const labels = {
        copy_signature: 'Signature copied',
        update_claimant: 'Claimant updated',
        restore_signature: 'Signature restored',
        restore_claimant: 'Claimant restored',
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
    const matched = Number(pair.is_matched) === 1;
    document.getElementById('pairReadOnlyWarning').classList.toggle('d-none', matched);

    renderWave('first', pair.first_wave, matched);
    renderWave('second', pair.second_wave, matched);
    document.getElementById('copyFirstToSecondBtn').disabled = Number(pair.can_consolidate) !== 1 || !pair.first_wave?.e_signature;
    document.getElementById('copySecondToFirstBtn').disabled = Number(pair.can_consolidate) !== 1 || !pair.second_wave?.e_signature;
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

async function mutate(body, button) {
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
        await Promise.all([openPair(pageState.selectedCode), loadPairs()]);
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

async function saveClaimant(wave, button) {
    const prefix = wave === 'first_wave' ? 'first' : 'second';
    const claimantName = document.getElementById(`${prefix}ClaimantName`).value.trim();
    if (!claimantName) {
        alert('Claimant name is required.');
        return;
    }
    await mutate({ action: 'update_claimant', household_code: pageState.selectedCode, wave, claimant_name: claimantName }, button);
}

function prepareCopy(direction) {
    const pair = pageState.selectedPair;
    if (!pair) return;
    const firstToSecond = direction === 'first_to_second';
    const source = firstToSecond ? pair.first_wave : pair.second_wave;
    const target = firstToSecond ? pair.second_wave : pair.first_wave;
    pageState.pendingDirection = direction;
    document.getElementById('copyConfirmationText').textContent = `${pair.household_code}: copy ${firstToSecond ? 'First Wave' : 'Second Wave'} signature to ${firstToSecond ? 'Second Wave' : 'First Wave'}?`;
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
    }, 300);
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

document.getElementById('refreshPairsBtn').addEventListener('click', () => loadPairs());
prevBtn.addEventListener('click', () => { if (pageState.page > 1) { pageState.page -= 1; loadPairs(); } });
nextBtn.addEventListener('click', () => { if (pageState.page < pageState.totalPages) { pageState.page += 1; loadPairs(); } });
document.getElementById('saveFirstClaimantBtn').addEventListener('click', (event) => saveClaimant('first_wave', event.currentTarget));
document.getElementById('saveSecondClaimantBtn').addEventListener('click', (event) => saveClaimant('second_wave', event.currentTarget));
document.getElementById('copyFirstToSecondBtn').addEventListener('click', () => prepareCopy('first_to_second'));
document.getElementById('copySecondToFirstBtn').addEventListener('click', () => prepareCopy('second_to_first'));
document.getElementById('confirmCopySignatureBtn').addEventListener('click', async (event) => {
    const success = await mutate({ action: 'copy_signature', household_code: pageState.selectedCode, direction: pageState.pendingDirection, confirmed: 1 }, event.currentTarget);
    if (success) copyModal.hide();
});

loadPairs();