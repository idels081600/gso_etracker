(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function toast(message) {
    const stack = document.querySelector('[data-toast-stack]');
    if (!stack) return;
    const item = document.createElement('div');
    item.className = 'toast';
    item.textContent = message;
    stack.appendChild(item);
    setTimeout(() => item.remove(), 4200);
  }

  document.querySelector('[data-nav-toggle]')?.addEventListener('click', () => {
    document.querySelector('[data-nav]')?.classList.toggle('is-open');
  });

  const table = document.querySelector('[data-select-table]');
  const modal = document.querySelector('[data-batch-modal]');
  const openBatch = document.querySelector('[data-open-batch]');
  const selectedIdsInput = document.querySelector('[data-selected-ids]');
  const selectionSummary = document.querySelector('[data-selection-summary]');
  const selectAll = document.querySelector('[data-select-all]');

  function selectedChecks() {
    return Array.from(document.querySelectorAll('[data-row-check]:checked'));
  }

  function syncSelection() {
    const checks = selectedChecks();
    if (openBatch) openBatch.disabled = checks.length === 0;
    if (selectedIdsInput) {
      selectedIdsInput.value = JSON.stringify(checks.map((check) => check.value));
    }
    if (selectionSummary) {
      selectionSummary.textContent = checks.length
        ? checks.length + ' request(s) selected for processing.'
        : 'No requests selected.';
    }
  }

  table?.addEventListener('change', (event) => {
    if (event.target.matches('[data-row-check]')) {
      syncSelection();
    }
  });

  selectAll?.addEventListener('change', (event) => {
    document.querySelectorAll('[data-row-check]').forEach((check) => {
      check.checked = event.target.checked;
    });
    syncSelection();
  });

  openBatch?.addEventListener('click', () => {
    syncSelection();
    if (modal?.showModal) modal.showModal();
  });

  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => modal?.close());
  });

  const statusSelect = document.querySelector('[data-status-select]');
  function syncBatchFields() {
    const declined = statusSelect?.value === 'Declined';
    document.querySelectorAll('[data-time-field]').forEach((field) => {
      field.hidden = declined;
    });
    const declineField = document.querySelector('[data-decline-field]');
    if (declineField) declineField.hidden = !declined;
  }
  statusSelect?.addEventListener('change', syncBatchFields);
  syncBatchFields();
  syncSelection();

  const scannerInput = document.querySelector('#scannerInput');
  const scanResult = document.querySelector('[data-scan-result]');
  const scanHistory = document.querySelector('[data-scan-history]');
  let scanTimer = null;
  let isScanning = false;

  function showScanResult(success, heading, detail) {
    if (!scanResult) return;
    scanResult.classList.toggle('success', success);
    scanResult.classList.toggle('error', !success);
    scanResult.innerHTML = '<strong>' + heading + '</strong><span>' + detail + '</span>';
  }

  function addScanHistory(name, message) {
    if (!scanHistory) return;
    const item = document.createElement('article');
    item.className = 'list-item';
    item.innerHTML = '<strong>' + name + '</strong><span>' + message + '</span><small>' + new Date().toLocaleTimeString() + '</small>';
    scanHistory.prepend(item);
    while (scanHistory.children.length > 8) {
      scanHistory.lastElementChild.remove();
    }
  }

  async function processScan(value) {
    const scannedData = value.trim();
    if (!scannedData || isScanning) return;

    isScanning = true;
    showScanResult(true, 'Processing', scannedData);

    try {
      const body = new URLSearchParams();
      body.set('action', 'scan');
      body.set('scannedData', scannedData);

      const response = await fetch('api.php?action=scan', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
        body
      });
      const data = await response.json();

      showScanResult(Boolean(data.success), data.success ? data.name || scannedData : 'Scan Failed', data.message || 'No response message.');
      addScanHistory(data.name || scannedData, data.message || data.status || 'Processed');
      if (!data.success) toast(data.message || 'Scan failed.');
    } catch (error) {
      showScanResult(false, 'Connection Error', 'The scanner could not reach the server.');
      toast('Scanner connection error.');
    } finally {
      setTimeout(() => {
        isScanning = false;
        scannerInput.value = '';
        scannerInput.focus();
      }, 1100);
    }
  }

  scannerInput?.addEventListener('input', (event) => {
    clearTimeout(scanTimer);
    const value = event.target.value;
    if (value.trim().length < 3) return;
    scanTimer = setTimeout(() => processScan(value), 280);
  });

  scannerInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      clearTimeout(scanTimer);
      processScan(event.target.value);
    }
  });

  if (scannerInput) {
    document.addEventListener('click', () => {
      if (!isScanning) scannerInput.focus();
    });
  }
})();
