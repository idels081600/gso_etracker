// Gas Checker - JavaScript
// Verifies gas issuance against actual fuel-up data

let signatureCanvas = null;
let signatureContext = null;
let isSigning = false;
let hasSignature = false;
let currentGasRecord = null;
let qrScannerStream = null;
let qrScannerDetector = null;
let qrScannerTimer = null;

// ====== NOTIFICATION SYSTEM ======
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[character]));
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-4 shadow`;
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
            <span>${escapeHtml(message)}</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(notification);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function updateCurrentCheckerVehicle(record = null) {
    const card = document.querySelector('.checker-current-card');
    const value = document.getElementById('currentCheckerVehicle');
    const help = document.getElementById('currentCheckerHelp');
    if (!card || !value || !help) {
        return;
    }

    if (!record) {
        card.classList.remove('is-loaded');
        value.textContent = '----';
        help.textContent = 'Search to load';
        return;
    }

    card.classList.add('is-loaded');
    value.textContent = record.plate_no || record.vehicle || 'Loaded';
    help.textContent = record.vehicle || 'Voucher loaded';
}

// ====== SIGNATURE PAD ======
function initSignaturePad() {
    signatureCanvas = document.getElementById('checkSignaturePad');
    if (!signatureCanvas) {
        return;
    }

    signatureContext = signatureCanvas.getContext('2d');
    signatureContext.lineCap = 'round';
    signatureContext.lineJoin = 'round';
    signatureContext.lineWidth = 3;
    signatureContext.strokeStyle = '#212529';

    resizeSignaturePad();
    window.addEventListener('resize', resizeSignaturePad);

    signatureCanvas.addEventListener('pointerdown', startSignature);
    signatureCanvas.addEventListener('pointermove', drawSignature);
    signatureCanvas.addEventListener('pointerup', stopSignature);
    signatureCanvas.addEventListener('pointercancel', stopSignature);
    signatureCanvas.addEventListener('pointerleave', stopSignature);
}

function resizeSignaturePad() {
    if (!signatureCanvas || !signatureContext) {
        return;
    }

    const dataUrl = hasSignature ? signatureCanvas.toDataURL('image/png') : null;
    const rect = signatureCanvas.getBoundingClientRect();
    const ratio = Math.max(window.devicePixelRatio || 1, 1);

    signatureCanvas.width = rect.width * ratio;
    signatureCanvas.height = rect.height * ratio;
    signatureContext.setTransform(ratio, 0, 0, ratio, 0, 0);
    signatureContext.lineCap = 'round';
    signatureContext.lineJoin = 'round';
    signatureContext.lineWidth = 3;
    signatureContext.strokeStyle = '#212529';

    if (dataUrl) {
        const image = new Image();
        image.onload = () => {
            signatureContext.drawImage(image, 0, 0, rect.width, rect.height);
        };
        image.src = dataUrl;
    }
}

function getSignaturePosition(event) {
    const rect = signatureCanvas.getBoundingClientRect();
    return {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top
    };
}

function startSignature(event) {
    if (!signatureCanvas || signatureCanvas.classList.contains('is-disabled')) {
        return;
    }

    event.preventDefault();
    isSigning = true;
    signatureCanvas.setPointerCapture(event.pointerId);
    const position = getSignaturePosition(event);
    signatureContext.beginPath();
    signatureContext.moveTo(position.x, position.y);
}

function drawSignature(event) {
    if (!isSigning) {
        return;
    }

    event.preventDefault();
    const position = getSignaturePosition(event);
    signatureContext.lineTo(position.x, position.y);
    signatureContext.stroke();
    markSignatureSigned();
}

function stopSignature(event) {
    if (!isSigning) {
        return;
    }

    event.preventDefault();
    isSigning = false;
    signatureContext.closePath();
    updateSignatureValue();
}

function markSignatureSigned() {
    hasSignature = true;
    document.getElementById('checkSignaturePad')?.parentElement.classList.add('has-signature');
    document.getElementById('checkSignaturePad')?.parentElement.classList.remove('is-invalid');
    document.getElementById('signatureFeedback')?.classList.add('d-none');
}

function updateSignatureValue() {
    const signatureInput = document.getElementById('checkSignature');
    if (signatureInput && signatureCanvas && hasSignature) {
        signatureInput.value = signatureCanvas.toDataURL('image/png');
    }
}

function clearSignaturePad() {
    if (!signatureCanvas || !signatureContext) {
        return;
    }

    const rect = signatureCanvas.getBoundingClientRect();
    signatureContext.clearRect(0, 0, rect.width, rect.height);
    hasSignature = false;
    document.getElementById('checkSignature').value = '';
    signatureCanvas.parentElement.classList.remove('has-signature', 'is-invalid');
    document.getElementById('signatureFeedback')?.classList.add('d-none');
}

function validateSignature() {
    updateSignatureValue();

    const signatureValue = String(document.getElementById('checkSignature')?.value || '').trim();
    if (hasSignature && signatureValue !== '') {
        return true;
    }

    const signatureWrap = document.getElementById('checkSignaturePad')?.parentElement;
    signatureWrap?.classList.add('is-invalid');
    document.getElementById('signatureFeedback')?.classList.remove('d-none');
    return false;
}

// ====== QR SCANNER ======
function setQrScannerStatus(message, type = 'info') {
    const status = document.getElementById('qrScannerStatus');
    if (!status) {
        return;
    }

    status.className = `alert alert-${type} mt-3 mb-0`;
    status.textContent = message;
}

function extractIssuanceIdFromQr(value) {
    const text = String(value || '').trim();
    if (!text) {
        return '';
    }

    try {
        const url = new URL(text);
        return (url.searchParams.get('issuance_id') || url.searchParams.get('serial_no') || url.searchParams.get('id') || '').trim();
    } catch (error) {
        return text;
    }
}

function stopQrScanner() {
    if (qrScannerTimer) {
        window.clearTimeout(qrScannerTimer);
        qrScannerTimer = null;
    }

    if (qrScannerStream) {
        qrScannerStream.getTracks().forEach((track) => track.stop());
        qrScannerStream = null;
    }

    const video = document.getElementById('qrScannerVideo');
    if (video) {
        video.srcObject = null;
    }
}

function finishQrScan(value) {
    const issuanceId = extractIssuanceIdFromQr(value);
    if (!issuanceId) {
        setQrScannerStatus('QR code did not contain a gas issuance ID.', 'warning');
        return;
    }

    document.getElementById('issuanceIdSearch').value = issuanceId.toUpperCase();
    setQrScannerStatus('QR found. Searching issuance...', 'success');
    stopQrScanner();

    const modalElement = document.getElementById('qrScannerModal');
    if (modalElement && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalElement).hide();
    }

    searchGasIssuance();
}

async function scanQrFrame() {
    const video = document.getElementById('qrScannerVideo');
    if (!video || !qrScannerDetector || !qrScannerStream) {
        return;
    }

    try {
        const codes = await qrScannerDetector.detect(video);
        if (codes && codes.length > 0) {
            finishQrScan(codes[0].rawValue);
            return;
        }
    } catch (error) {
        setQrScannerStatus('Scanning paused. Keep the QR code inside the frame.', 'warning');
    }

    qrScannerTimer = window.setTimeout(scanQrFrame, 350);
}

async function startQrScanner() {
    stopQrScanner();

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        setQrScannerStatus('Camera scanning is not supported in this browser.', 'danger');
        return;
    }

    if (!('BarcodeDetector' in window)) {
        setQrScannerStatus('QR scanning is not supported in this browser. Use Chrome or Edge, or enter the ID manually.', 'warning');
        return;
    }

    try {
        qrScannerDetector = new BarcodeDetector({ formats: ['qr_code'] });
        qrScannerStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' }
            },
            audio: false
        });

        const video = document.getElementById('qrScannerVideo');
        video.srcObject = qrScannerStream;
        await video.play();
        setQrScannerStatus('Point the camera at the gas issuance QR code.', 'info');
        scanQrFrame();
    } catch (error) {
        setQrScannerStatus('Unable to open camera. Allow camera permission, then try again.', 'danger');
    }
}

// ====== SEARCH GAS ISSUANCE ======
function searchGasIssuance() {
    const searchInput = document.getElementById('issuanceIdSearch');
    const issuanceId = searchInput.value.trim().toUpperCase();

    if (!issuanceId) {
        showNotification('Please enter a Gas Issuance ID', 'warning');
        return;
    }

    searchInput.classList.add('checker-loading');
    const searchBtn = document.getElementById('searchIssuanceBtn');
    const originalHTML = searchBtn.innerHTML;
    searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
    searchBtn.disabled = true;

    fetch(`gas_checker_lookup.php?issuance_id=${encodeURIComponent(issuanceId)}`)
        .then((response) => response.json().then((data) => {
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to search gas issuance records.');
            }
            return data;
        }))
        .then((data) => {
            const record = data.record;
            updateCurrentCheckerVehicle(record);
            showCheckerForm(record);
            showNotification(`Issuance #${issuanceId} found!`, 'success');
        })
        .catch((error) => {
            hideAllSections();
            updateCurrentCheckerVehicle();
            showNotification(error.message || `No record found for Issuance ID: ${issuanceId}`, 'danger');
        })
        .finally(() => {
            searchBtn.innerHTML = originalHTML;
            searchBtn.disabled = false;
            searchInput.classList.remove('checker-loading');
        });
}

// ====== SHOW CHECKER FORM ======
function showCheckerForm(record) {
    const formSection = document.getElementById('checkerFormSection');
    const form = document.getElementById('checkerForm');
    formSection.classList.add('show');
    requestAnimationFrame(resizeSignaturePad);

    // Fill reference info
    document.getElementById('checkVehicle').textContent = `${record.vehicle} (${record.plate_no})`;
    document.getElementById('checkFuelType').textContent = record.fuel_type;
    document.getElementById('checkLitersIssued').textContent = `${Number(record.liters_issued || 0).toFixed(2)} L`;
    document.getElementById('checkIssuanceRef').textContent = record.id;

    const pastOdometer = Number(record.past_odometer || 0);
    const odometerInput = document.getElementById('checkOdometer');

    // Reset input fields
    odometerInput.value = '';
    odometerInput.min = String(pastOdometer);
    odometerInput.placeholder = `Minimum ${pastOdometer.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} km`;
    odometerInput.classList.remove('is-invalid');
    document.getElementById('checkDriver').value = '';
    document.getElementById('checkActual').value = '';
    document.getElementById('checkResult').innerHTML = '';
    clearSignaturePad();
    document.getElementById('checkSignaturePad')?.classList.remove('is-disabled');
    document.getElementById('clearSignatureBtn').disabled = false;

    // Reset validation
    form.classList.remove('was-validated');

    // Store data for submission
    form.dataset.recordId = record.id;
    form.dataset.recordDbId = record.db_id || '';
    form.dataset.pastOdometer = pastOdometer;
    currentGasRecord = record;

    updateStepIndicator(3);
    formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ====== SUBMIT CHECK ======
function submitCheck() {
    const form = document.getElementById('checkerForm');

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        showNotification('Please fill in all required fields', 'warning');
        return;
    }

    const odometer = document.getElementById('checkOdometer').value;
    const driver = document.getElementById('checkDriver').value;
    const actualFuel = document.getElementById('checkActual').value;
    const issuanceRef = document.getElementById('checkIssuanceRef').textContent;
    const currentOdometer = Number(odometer);
    const pastOdometer = Number(form.dataset.pastOdometer || 0);
    const signatureValue = String(document.getElementById('checkSignature')?.value || '').trim();

    if (!validateSignature() || signatureValue === '') {
        showNotification('Please provide the driver e-signature before submitting.', 'warning');
        return;
    }

    if (currentOdometer < pastOdometer) {
        document.getElementById('checkOdometer').classList.add('is-invalid');
        showNotification(`Odometer cannot be lower than the past odometer (${pastOdometer.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} km).`, 'warning');
        return;
    }
    document.getElementById('checkOdometer').classList.remove('is-invalid');

    const submitBtn = document.getElementById('submitCheckBtn');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    submitBtn.disabled = true;

    fetch('gas_checker_submit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            db_id: form.dataset.recordDbId || '',
            serial_no: issuanceRef,
            current_odometer: odometer,
            driver_name: driver,
            actual_liters_fueled: actualFuel,
            signature: signatureValue
        })
    })
        .then((response) => response.json().then((data) => {
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to submit fuel-up record.');
            }
            return data;
        }))
        .then(() => {
            showNotification('Fuel-up record submitted. Ready for the next search.', 'success');
            updateStepIndicator(3, true);
            window.setTimeout(resetAll, 800);
        })
        .catch((error) => {
            showNotification(error.message || 'Unable to submit fuel-up record.', 'danger');
            submitBtn.innerHTML = originalHTML;
            submitBtn.disabled = false;
        });
}

// ====== RESET ALL ======
function resetAll() {
    document.getElementById('issuanceIdSearch').value = '';
    document.getElementById('checkerFormSection').classList.remove('show');
    document.getElementById('checkResult').innerHTML = '';

    const form = document.getElementById('checkerForm');
    form.classList.remove('was-validated');
    form.reset();
    currentGasRecord = null;
    updateCurrentCheckerVehicle();
    clearSignaturePad();
    document.querySelectorAll('#checkerForm input').forEach(input => input.disabled = false);
    document.getElementById('checkSignaturePad')?.classList.remove('is-disabled');
    document.getElementById('clearSignatureBtn').disabled = false;

    const submitBtn = document.getElementById('submitCheckBtn');
    submitBtn.innerHTML = '<i class="fas fa-file-signature me-1"></i>Submit E-Signature';
    submitBtn.disabled = false;

    updateStepIndicator(1);
    document.getElementById('issuanceIdSearch').focus();
}

// ====== UPDATE STEP INDICATOR ======
function updateStepIndicator(currentStep, completed = false) {
    const steps = document.querySelectorAll('.step-item');
    const connectors = document.querySelectorAll('.step-connector');

    steps.forEach((step, index) => {
        const stepNum = index + 1;
        step.classList.remove('active', 'completed');

        if (completed && stepNum <= currentStep) {
            step.classList.add('completed');
        } else if (stepNum === currentStep) {
            step.classList.add('active');
        }
    });

    connectors.forEach((connector, index) => {
        if (completed && (index + 1) < currentStep) {
            connector.style.background = '#198754';
        } else {
            connector.style.background = '#dee2e6';
        }
    });
}

// ====== HELPER FUNCTIONS ======
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString + 'T00:00:00').toLocaleDateString('en-US', options);
}

function hideAllSections() {
    document.getElementById('checkerFormSection').classList.remove('show');
    document.getElementById('checkResult').innerHTML = '';
    updateCurrentCheckerVehicle();
    updateStepIndicator(1);
}

// ====== KEYBOARD SHORTCUTS ======
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && document.activeElement === document.getElementById('issuanceIdSearch')) {
        e.preventDefault();
        searchGasIssuance();
    }
    if (e.key === 'Escape' && !document.querySelector('.modal.show')) {
        resetAll();
    }
});

// ====== INITIALIZATION ======
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('issuanceIdSearch').focus();
    initSignaturePad();

    document.getElementById('searchIssuanceBtn')?.addEventListener('click', searchGasIssuance);
    document.getElementById('submitCheckBtn')?.addEventListener('click', submitCheck);
    document.getElementById('clearSignatureBtn')?.addEventListener('click', clearSignaturePad);
    document.querySelectorAll('.js-reset-all').forEach((button) => {
        button.addEventListener('click', resetAll);
    });

    const qrModal = document.getElementById('qrScannerModal');
    if (qrModal) {
        qrModal.addEventListener('shown.bs.modal', startQrScanner);
        qrModal.addEventListener('hidden.bs.modal', stopQrScanner);
    }

    document.getElementById('restartQrScannerBtn')?.addEventListener('click', startQrScanner);

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (el) {
            return new bootstrap.Tooltip(el);
        });
    }
    console.log('Gas Checker initialized');
});
