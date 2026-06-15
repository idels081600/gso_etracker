(() => {
    'use strict';

    const config = window.chairsTableConfig || { types: [], statuses: [] };
    const state = { status: 'all', search: '', from: '', to: '', page: 1, perPage: 10 };
    const table = document.getElementById('deploymentTable');
    const rows = table ? Array.from(table.tBodies[0].rows) : [];
    const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
    const statusLabel = (status) => status === 'Deployed' ? 'Installed' : status;
    let toastTimer = null;

    function showToast(message, type = 'success', duration = 4200) {
        const toast = document.getElementById('system-status');
        if (!toast) return;
        window.clearTimeout(toastTimer);
        toast.classList.remove('is-success', 'is-error', 'is-warning', 'is-visible');
        toast.classList.add(`is-${type}`);
        toast.querySelector('span').textContent = message;
        toast.querySelector('i').className = `fas ${type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-check-circle'}`;
        toast.hidden = false;
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        toastTimer = window.setTimeout(() => dismissToast(), duration);
    }

    function dismissToast() {
        const toast = document.getElementById('system-status');
        if (!toast || toast.hidden) return;
        toast.classList.remove('is-visible');
        window.setTimeout(() => { toast.hidden = true; }, 180);
    }

    function reloadWithToast(message, type = 'success') {
        sessionStorage.setItem('chairsTableToast', JSON.stringify({ message, type }));
        window.location.reload();
    }

    const pendingToast = sessionStorage.getItem('chairsTableToast');
    if (pendingToast) {
        sessionStorage.removeItem('chairsTableToast');
        try {
            const toast = JSON.parse(pendingToast);
            showToast(toast.message, toast.type);
        } catch (_) {
            // Ignore malformed local feedback state.
        }
    } else if (!document.getElementById('system-status')?.hidden) {
        requestAnimationFrame(() => document.getElementById('system-status')?.classList.add('is-visible'));
        toastTimer = window.setTimeout(() => dismissToast(), 4200);
    }

    function showFeedback(container, message, error = false) {
        if (!container) return;
        container.textContent = message;
        container.classList.toggle('is-error', error);
        container.classList.toggle('is-success', !error);
    }

    function setLoading(button, loading, label = 'Working...') {
        if (!button) return;
        if (loading) {
            button.dataset.originalText = button.innerHTML;
            button.disabled = true;
            button.textContent = label;
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText || button.innerHTML;
        }
    }

    async function request(data, endpoint = 'update_data.php') {
        const formData = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData)) {
            Object.entries(data).forEach(([key, value]) => formData.append(key, value));
        }
        const response = await fetch(endpoint, { method: 'POST', body: formData, headers: { Accept: 'application/json' } });
        const payload = await response.json().catch(() => ({ success: false, message: 'The server returned an invalid response.' }));
        if (!response.ok || !payload.success) throw new Error(payload.message || 'The request could not be completed.');
        return payload.data;
    }

    function cleanupBackdrops() {
        if (document.querySelector('.modal.show')) return;
        document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
    }

    document.querySelectorAll('.modal').forEach((element) => element.addEventListener('hidden.bs.modal', cleanupBackdrops));
    window.addEventListener('pageshow', cleanupBackdrops);

    function matchesRow(row) {
        const statusMatch = state.status === 'all' || row.dataset.status === state.status;
        const searchMatch = !state.search || row.textContent.toLowerCase().includes(state.search);
        const installed = row.dataset.installedDate;
        const fromMatch = !state.from || installed >= state.from;
        const toMatch = !state.to || installed <= state.to;
        return statusMatch && searchMatch && fromMatch && toMatch;
    }

    function renderRows() {
        const dateError = document.getElementById('dateError');
        const invalidRange = state.from && state.to && state.from > state.to;
        dateError.textContent = invalidRange ? 'Start date must be before or equal to end date.' : '';
        const filtered = invalidRange ? [] : rows.filter(matchesRow);
        const pages = Math.max(1, Math.ceil(filtered.length / state.perPage));
        state.page = Math.min(state.page, pages);
        const start = (state.page - 1) * state.perPage;
        const visible = new Set(filtered.slice(start, start + state.perPage));
        rows.forEach((row) => { row.hidden = !visible.has(row); });
        document.getElementById('emptyState').hidden = filtered.length !== 0;
        document.getElementById('recordSummary').textContent = `${filtered.length} matching record${filtered.length === 1 ? '' : 's'}`;
        document.getElementById('pageSummary').textContent = filtered.length ? `Showing ${start + 1}-${Math.min(start + state.perPage, filtered.length)} of ${filtered.length}` : 'No records to show';
        renderPagination(pages);
    }

    function renderPagination(pages) {
        const container = document.getElementById('paginationButtons');
        container.replaceChildren();
        const add = (label, page, disabled, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = disabled;
            button.className = active ? 'is-active' : '';
            button.addEventListener('click', () => { state.page = page; renderRows(); });
            container.append(button);
        };
        add('‹', Math.max(1, state.page - 1), state.page === 1);
        for (let page = 1; page <= pages; page += 1) add(String(page), page, false, page === state.page);
        add('›', Math.min(pages, state.page + 1), state.page === pages);
    }

    document.querySelectorAll('.ct-filter-chip').forEach((button) => button.addEventListener('click', () => {
        document.querySelectorAll('.ct-filter-chip').forEach((chip) => chip.classList.remove('is-active'));
        button.classList.add('is-active');
        state.status = button.dataset.filter;
        state.page = 1;
        renderRows();
    }));
    document.getElementById('searchInput')?.addEventListener('input', (event) => { state.search = event.target.value.trim().toLowerCase(); state.page = 1; renderRows(); });
    document.getElementById('dateFrom')?.addEventListener('change', (event) => { state.from = event.target.value; state.page = 1; renderRows(); });
    document.getElementById('dateTo')?.addEventListener('change', (event) => { state.to = event.target.value; state.page = 1; renderRows(); });
    document.getElementById('clearDates')?.addEventListener('click', () => {
        state.from = ''; state.to = ''; state.page = 1;
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        renderRows();
    });

    function typesFor(category) {
        return category === 'All' ? config.types : config.types.filter((type) => type.category === category);
    }

    function createEquipmentRow(category, selectedId = '', quantity = 1) {
        const row = document.createElement('div');
        row.className = 'ct-equipment-row';
        const select = document.createElement('select');
        select.className = 'form-select';
        select.name = 'equipment_type_id[]';
        select.required = true;
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Choose subtype';
        select.append(placeholder);
        typesFor(category).forEach((type) => {
            const option = document.createElement('option');
            option.value = type.id;
            option.textContent = `${type.display_name} (${type.available_qty} available)`;
            option.dataset.available = type.available_qty;
            option.selected = String(type.id) === String(selectedId);
            select.append(option);
        });
        const input = document.createElement('input');
        input.className = 'form-control';
        input.type = 'number';
        input.name = 'quantity[]';
        input.min = '1';
        input.value = quantity;
        input.required = true;
        const available = document.createElement('span');
        available.className = 'ct-available-label';
        const syncAvailable = () => {
            const option = select.selectedOptions[0];
            available.textContent = option?.dataset.available ? `${option.dataset.available} available` : 'Select a subtype';
        };
        select.addEventListener('change', syncAvailable);
        syncAvailable();
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'ct-remove-row';
        remove.setAttribute('aria-label', 'Remove equipment row');
        remove.innerHTML = '<i class="fas fa-times"></i>';
        remove.addEventListener('click', () => {
            const container = row.parentElement;
            row.remove();
            updateEquipmentEmptyState(container);
        });
        row.append(select, input, available, remove);
        return row;
    }

    function updateEquipmentEmptyState(container) {
        const empty = container.closest('.ct-equipment-section')?.querySelector('.ct-equipment-empty');
        if (empty) empty.hidden = container.children.length > 0;
    }

    document.querySelectorAll('.ct-add-row').forEach((button) => button.addEventListener('click', () => {
        const container = button.closest('.ct-equipment-section').querySelector('.equipment-rows');
        container.append(createEquipmentRow(button.dataset.category));
        updateEquipmentEmptyState(container);
    }));

    function validateEquipmentRows(form, checkAvailability) {
        const selected = new Set();
        const selects = Array.from(form.querySelectorAll('[name="equipment_type_id[]"]'));
        if (!selects.length) throw new Error('Add at least one equipment subtype.');
        selects.forEach((select) => {
            if (!select.value) throw new Error('Choose a subtype for every equipment row.');
            if (selected.has(select.value)) throw new Error('The same equipment subtype cannot be selected more than once.');
            selected.add(select.value);
            const quantity = Number(select.closest('.ct-equipment-row').querySelector('[name="quantity[]"]').value);
            if (!Number.isInteger(quantity) || quantity < 1) throw new Error('Every quantity must be at least 1.');
            const available = Number(select.selectedOptions[0].dataset.available || 0);
            if (checkAvailability && quantity > available) throw new Error(`${select.selectedOptions[0].textContent.split(' (')[0]} only has ${available} available.`);
        });
    }

    const deployForm = document.getElementById('deployForm');
    deployForm?.addEventListener('submit', (event) => {
        try {
            validateEquipmentRows(deployForm, true);
            setLoading(deployForm.querySelector('[type="submit"]'), true, 'Saving...');
        } catch (error) {
            event.preventDefault();
            showFeedback(deployForm.querySelector('.ct-form-feedback'), error.message, true);
        }
    });
    document.getElementById('deployModal')?.addEventListener('show.bs.modal', () => {
        document.querySelectorAll('#deployForm .equipment-rows').forEach(updateEquipmentEmptyState);
    });
    document.querySelector('.location-select')?.addEventListener('change', (event) => {
        const field = event.target.closest('form').querySelector('.other-location');
        field.hidden = event.target.value !== 'Other';
        field.querySelector('input').required = event.target.value === 'Other';
    });

    document.querySelectorAll('.ct-inventory-edit-row').forEach((row) => {
        const input = row.querySelector('.inventory-available');
        const afterSave = row.querySelector('.ct-after-save');
        input.addEventListener('input', () => {
            const available = Number(input.value);
            const reserved = Number(row.dataset.reserved);
            afterSave.textContent = Number.isInteger(available) && available >= 0
                ? `${reserved} reserved · ${reserved + available} total after save`
                : 'Available balance must be zero or greater';
            afterSave.classList.toggle('is-error', !Number.isInteger(available) || available < 0);
        });
    });
    document.getElementById('addEquipmentForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const feedback = form.querySelector('.ct-form-feedback');
        const button = form.querySelector('[type="submit"]');
        try {
            setLoading(button, true, 'Adding...');
            const data = new FormData(form);
            data.append('action', 'create_equipment_type');
            await request(data);
            showFeedback(feedback, 'Equipment type added successfully.');
            window.setTimeout(() => reloadWithToast('Equipment type added successfully.'), 450);
        } catch (error) {
            showFeedback(feedback, error.message, true);
            setLoading(button, false);
        }
    });
    document.querySelectorAll('.save-equipment-type').forEach((button) => button.addEventListener('click', async () => {
        const row = button.closest('.ct-inventory-edit-row');
        const feedback = row.querySelector('.ct-form-feedback');
        try {
            const subtypeName = row.querySelector('.inventory-subtype').value.trim();
            const displayName = row.querySelector('.inventory-display-name').value.trim();
            const available = Number(row.querySelector('.inventory-available').value);
            if (!subtypeName || !displayName) throw new Error('Subtype and display name are required.');
            if (!Number.isInteger(available) || available < 0) throw new Error('Available balance must be a whole number of zero or greater.');
            setLoading(button, true, 'Saving...');
            await request({
                action: 'update_equipment_type',
                id: row.dataset.id,
                category: row.querySelector('.inventory-category').value,
                subtype_name: subtypeName,
                display_name: displayName,
                available_qty: available,
            });
            showFeedback(feedback, 'Equipment and balance updated.');
            window.setTimeout(() => reloadWithToast('Equipment type and balance updated successfully.'), 450);
        } catch (error) {
            showFeedback(feedback, error.message, true);
            setLoading(button, false);
        }
    }));
    document.querySelectorAll('.delete-equipment-type').forEach((button) => button.addEventListener('click', async () => {
        const row = button.closest('.ct-inventory-edit-row');
        const feedback = row.querySelector('.ct-form-feedback');
        const name = row.querySelector('.inventory-display-name').value.trim();
        if (!name) {
            showFeedback(feedback, 'Display name is required before deleting this equipment type.', true);
            return;
        }
        if (!window.confirm(`Delete ${name}? Equipment used in deployment history cannot be deleted.`)) return;
        try {
            setLoading(button, true, 'Deleting...');
            await request({ action: 'delete_equipment_type', id: row.dataset.id });
            reloadWithToast(`${name} deleted successfully.`);
        } catch (error) {
            showFeedback(feedback, error.message, true);
            showToast(error.message, 'error');
            setLoading(button, false);
        }
    }));

    document.querySelectorAll('.edit-record').forEach((button) => button.addEventListener('click', async () => {
        const form = document.getElementById('editForm');
        const feedback = form.querySelector('.ct-form-feedback');
        showFeedback(feedback, 'Loading deployment...');
        modal('editModal').show();
        try {
            const data = await request({ action: 'get_deployment', id: button.dataset.id });
            ['id', 'name', 'contact', 'purpose', 'location', 'address', 'date', 'retrieval_date', 'status'].forEach((name) => {
                const field = form.elements[name];
                const source = name === 'contact' ? 'contact_no' : name;
                field.value = data[source] ?? '';
            });
            const container = form.querySelector('.equipment-rows');
            container.replaceChildren(...data.items.map((item) => createEquipmentRow('All', item.equipment_type_id, item.quantity)));
            feedback.textContent = '';
        } catch (error) {
            showFeedback(feedback, error.message, true);
        }
    }));

    document.getElementById('editForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('[type="submit"]');
        const feedback = form.querySelector('.ct-form-feedback');
        try {
            validateEquipmentRows(form, false);
            setLoading(button, true, 'Saving...');
            await request(new FormData(form));
            showFeedback(feedback, 'Deployment updated successfully.');
            window.setTimeout(() => reloadWithToast('Deployment updated successfully.'), 450);
        } catch (error) {
            showFeedback(feedback, error.message, true);
            setLoading(button, false);
        }
    });

    async function updateStatus(id, status) {
        await request({ action: 'update_status', id, status });
        window.location.reload();
    }
    document.querySelectorAll('.install-record').forEach((button) => button.addEventListener('click', async () => {
        if (!window.confirm('Mark this pending deployment as installed?')) return;
        button.disabled = true;
        try { await request({ action: 'update_status', id: button.dataset.id, status: 'Deployed' }); reloadWithToast('Deployment marked as Installed.'); } catch (error) { showToast(error.message, 'error'); button.disabled = false; }
    }));
    document.querySelectorAll('.retrieve-record').forEach((button) => button.addEventListener('click', async () => {
        if (!window.confirm('Mark this deployment as retrieved and restore its inventory?')) return;
        button.disabled = true;
        try { await request({ action: 'update_status', id: button.dataset.id, status: 'Retrieved' }); reloadWithToast('Deployment marked as Retrieved. Inventory restored.'); } catch (error) { showToast(error.message, 'error'); button.disabled = false; }
    }));
    let archiveId = null;
    document.querySelectorAll('.archive-record').forEach((button) => button.addEventListener('click', () => {
        archiveId = button.dataset.id;
        document.getElementById('archiveRecordLabel').textContent = `Deployment #${archiveId}`;
        document.querySelector('#archiveModal .ct-form-feedback').textContent = '';
        modal('archiveModal').show();
    }));
    document.getElementById('confirmArchive')?.addEventListener('click', async (event) => {
        const feedback = document.querySelector('#archiveModal .ct-form-feedback');
        try {
            if (!archiveId) throw new Error('No deployment is selected.');
            setLoading(event.currentTarget, true, 'Archiving...');
            await request({ action: 'delete_deployment', id: archiveId });
            reloadWithToast('Deployment archived successfully.');
        } catch (error) {
            showFeedback(feedback, error.message, true);
            setLoading(event.currentTarget, false);
        }
    });

    function createBulkRow(deployment) {
        const row = document.createElement('label');
        row.className = 'ct-bulk-row';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = deployment.id;
        const details = document.createElement('span');
        const title = document.createElement('strong');
        title.textContent = `#${deployment.id} ${deployment.name}`;
        const subtitle = document.createElement('small');
        subtitle.textContent = `${deployment.location} · ${statusLabel(deployment.status)}`;
        details.append(title, subtitle);
        const select = document.createElement('select');
        select.className = 'form-select';
        config.statuses.forEach((status) => {
            const option = document.createElement('option');
            option.textContent = statusLabel(status);
            option.value = status;
            option.selected = status === deployment.status;
            select.append(option);
        });
        row.append(checkbox, details, select);
        return row;
    }

    document.getElementById('openBulkModal')?.addEventListener('click', async () => {
        const container = document.getElementById('bulkRows');
        container.textContent = 'Loading deployments...';
        modal('bulkModal').show();
        try {
            const deployments = await request({ action: 'get_deployments' });
            container.replaceChildren(...deployments.map(createBulkRow));
        } catch (error) {
            container.textContent = error.message;
        }
    });
    document.getElementById('applyBulk')?.addEventListener('click', async (event) => {
        const feedback = document.querySelector('#bulkModal .ct-form-feedback');
        const updates = Array.from(document.querySelectorAll('#bulkRows .ct-bulk-row')).filter((row) => row.querySelector('input').checked).map((row) => ({ id: row.querySelector('input').value, status: row.querySelector('select').value }));
        try {
            if (!updates.length) throw new Error('Select at least one deployment.');
            setLoading(event.currentTarget, true, 'Applying...');
            await request({ action: 'bulk_update_status', updates: JSON.stringify(updates) });
            reloadWithToast('Selected deployment statuses updated successfully.');
        } catch (error) {
            showFeedback(feedback, error.message, true);
            setLoading(event.currentTarget, false);
        }
    });

    function createHistoryRow(deployment) {
        const row = document.createElement('article');
        row.className = 'ct-history-row';
        const details = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = `#${deployment.id} ${deployment.name}`;
        const subtitle = document.createElement('span');
        subtitle.textContent = `${deployment.location} · retrieved ${deployment.retrieval_date}`;
        const items = document.createElement('small');
        items.textContent = deployment.items.map((item) => `${item.display_name} x${item.quantity}`).join(', ');
        details.append(title, subtitle, items);
        const undo = document.createElement('button');
        undo.type = 'button';
        undo.className = 'ct-btn';
        undo.innerHTML = '<i class="fas fa-undo"></i> Reactivate';
        undo.addEventListener('click', async () => {
            try { setLoading(undo, true, 'Reactivating...'); await request({ action: 'undo_retrieved', id: deployment.id }); reloadWithToast('Deployment reactivated for retrieval.'); }
            catch (error) { showToast(error.message, 'error'); setLoading(undo, false); }
        });
        row.append(details, undo);
        return row;
    }
    document.getElementById('openRetrieved')?.addEventListener('click', async () => {
        const container = document.getElementById('historyRows');
        container.textContent = 'Loading history...';
        modal('historyModal').show();
        try {
            const history = await request({ action: 'get_retrieved' });
            container.replaceChildren(...(history.length ? history.map(createHistoryRow) : [Object.assign(document.createElement('p'), { textContent: 'No retrieved deployments yet.' })]));
        } catch (error) { container.textContent = error.message; }
    });

    document.querySelectorAll('.ct-toast button').forEach((button) => button.addEventListener('click', dismissToast));
    document.querySelectorAll('.ct-overdue-alert button').forEach((button) => button.addEventListener('click', () => button.parentElement.remove()));
    renderRows();
    window.setInterval(async () => {
        try {
            const data = await request({}, 'update_status_duration.php');
            if (data.updated_count > 0) window.location.reload();
        } catch (_) {
            // Silent background refresh; user actions still surface useful errors.
        }
    }, 60000);
})();
