// Document Tracking System - JavaScript with API Integration

// API endpoint
const API_URL = 'api.php';

// Current scanned barcode
let currentBarcode = '';

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    loadDashboard();
    setDefaultDates();
    initBarcodeListener();
    initReturnDaysListener();
});

// Initialize barcode input listener for Enter key
function initBarcodeListener() {
    const barcodeInput = document.getElementById('barcodeInput');
    if (barcodeInput) {
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                processBarcode();
            }
        });
    }
}

// Set default dates to today
function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    const incomingDate = document.getElementById('incomingDate');
    if (incomingDate) {
        incomingDate.value = today;
    }
}

// Initialize return days listener
function initReturnDaysListener() {
    const returnDaysInput = document.getElementById('outgoingReturnDays');
    if (returnDaysInput) {
        returnDaysInput.addEventListener('input', updateExpectedReturnDate);
    }
}

// API helper function
async function apiRequest(action, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {}
    };
    
    let url = `${API_URL}?action=${action}`;
    
    if (method === 'POST' && data) {
        options.body = new FormData();
        for (const key in data) {
            options.body.append(key, data[key]);
        }
    } else if (method === 'GET' && data) {
        for (const key in data) {
            url += `&${key}=${encodeURIComponent(data[key])}`;
        }
    }
    
    try {
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error occurred' };
    }
}

// Load dashboard data
async function loadDashboard() {
    await updateStatistics();
    await loadIncomingDocuments();
    await loadOutgoingDocuments();
}

// Update statistics cards
async function updateStatistics() {
    const result = await apiRequest('statistics');
    
    if (result.success) {
        document.getElementById('incomingCount').textContent = result.data.incoming;
        document.getElementById('outgoingCount').textContent = result.data.outgoing;
        document.getElementById('pendingCount').textContent = result.data.pending;
        document.getElementById('processedCount').textContent = result.data.processed;
    }
}

// Process barcode - Step 1: Check barcode and show type selection modal
async function processBarcode() {
    const barcodeInput = document.getElementById('barcodeInput');
    const barcode = barcodeInput.value.trim();
    
    if (!barcode) {
        showAlert('Please scan or enter a barcode!', 'warning');
        barcodeInput.focus();
        return;
    }
    
    currentBarcode = barcode;
    
    // Check if barcode already exists
    const result = await apiRequest('check_barcode', 'GET', { barcode: barcode });
    
    if (result.success && result.exists) {
        // Barcode exists - show options modal for existing document
        const doc = result.data;
        showExistingBarcodeOptions(doc, result.history);
        return;
    }
    
    // New barcode - show type selection modal
    document.getElementById('scannedBarcode').textContent = barcode;
    new bootstrap.Modal(document.getElementById('selectTypeModal')).show();
}

// Show options for existing barcode
function showExistingBarcodeOptions(doc, history) {
    // Store document data for later use
    window.existingDoc = doc;
    window.existingDocHistory = history;
    
    // Update modal content
    document.getElementById('existingBarcodeDisplay').textContent = doc.barcode || doc.tracking_no;
    document.getElementById('existingTrackingNo').textContent = doc.tracking_no;
    document.getElementById('existingDescription').textContent = truncateText(doc.description, 50);
    document.getElementById('existingDocType').textContent = doc.doc_type;
    document.getElementById('existingStatus').innerHTML = getStatusBadge(doc.status);
    document.getElementById('existingDirection').textContent = doc.doc_direction === 'incoming' ? 'Incoming' : 'Outgoing';
    document.getElementById('existingDateReceived').textContent = formatDate(doc.date_received);
    
    // Show/hide destination for outgoing
    const destRow = document.getElementById('existingDestinationRow');
    if (doc.doc_direction === 'outgoing' && doc.destination) {
        destRow.style.display = 'flex';
        document.getElementById('existingDestination').textContent = doc.destination;
    } else {
        destRow.style.display = 'none';
    }
    
    // Show/hide deadline info
    const deadlineRow = document.getElementById('existingDeadlineRow');
    if (doc.date_deadline) {
        deadlineRow.style.display = 'flex';
        const isOverdue = new Date(doc.date_deadline) < new Date() && doc.status !== 'Returned';
        document.getElementById('existingDeadline').innerHTML = formatDate(doc.date_deadline) + 
            (isOverdue ? ' <span class="badge bg-danger ms-1">Overdue</span>' : '');
    } else {
        deadlineRow.style.display = 'none';
    }
    
    // Show/hide "Mark as Returned" button for returnable documents
    const returnBtn = document.getElementById('markReturnedBtn');
    if (doc.date_deadline && doc.status !== 'Returned') {
        returnBtn.style.display = 'inline-block';
    } else {
        returnBtn.style.display = 'none';
    }
    
    // Update current status in update form
    document.getElementById('updateDocId').value = doc.id;
    document.getElementById('updateStatus').value = doc.status;
    document.getElementById('updateDescription').value = doc.description;
    
    // Show existing barcode options modal
    new bootstrap.Modal(document.getElementById('existingBarcodeModal')).show();
}

// Mark document as returned
async function markDocumentAsReturned() {
    const doc = window.existingDoc;
    
    if (!doc) {
        showAlert('No document selected!', 'danger');
        return;
    }
    
    const data = {
        id: doc.id,
        status: 'Returned',
        date_released: new Date().toISOString().split('T')[0]
    };
    
    const result = await apiRequest('update_status', 'POST', data);
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('existingBarcodeModal')).hide();
        clearBarcodeInput();
        showAlert('Document marked as returned successfully!', 'success');
        loadDashboard();
    } else {
        showAlert(result.message || 'Error marking document as returned', 'danger');
    }
}

// Show update form for existing document
function showUpdateDocumentForm() {
    // Hide existing barcode modal
    bootstrap.Modal.getInstance(document.getElementById('existingBarcodeModal')).hide();
    
    const doc = window.existingDoc;
    
    // Populate update form
    document.getElementById('updateDocId').value = doc.id;
    document.getElementById('updateDescription').value = doc.description;
    document.getElementById('updateDocType').value = doc.doc_type;
    document.getElementById('updateDateReceived').value = doc.date_received;
    document.getElementById('updateStatus').value = doc.status;
    document.getElementById('updateDateReleased').value = doc.date_released || '';
    
    if (doc.doc_direction === 'outgoing') {
        document.getElementById('updateDestination').value = doc.destination || '';
        document.getElementById('updateDestinationRow').style.display = 'flex';
        
        if (doc.date_deadline) {
            document.getElementById('updateDeadlineRow').style.display = 'flex';
            document.getElementById('updateDateDeadline').value = doc.date_deadline;
        } else {
            document.getElementById('updateDeadlineRow').style.display = 'none';
        }
    } else {
        document.getElementById('updateDestinationRow').style.display = 'none';
        document.getElementById('updateDeadlineRow').style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('updateDocumentModal')).show();
}

// Update existing document
async function updateExistingDocument() {
    const id = document.getElementById('updateDocId').value;
    const description = document.getElementById('updateDescription').value;
    const doc_type = document.getElementById('updateDocType').value;
    const date_received = document.getElementById('updateDateReceived').value;
    const status = document.getElementById('updateStatus').value;
    const date_released = document.getElementById('updateDateReleased').value;
    const destination = document.getElementById('updateDestination').value;
    const date_deadline = document.getElementById('updateDateDeadline').value;
    
    if (!description || !doc_type || !date_received) {
        showAlert('Please fill in all required fields!', 'danger');
        return;
    }
    
    const data = {
        id: id,
        description: description,
        doc_type: doc_type,
        date_received: date_received,
        status: status,
        date_released: date_released,
        destination: destination,
        date_deadline: date_deadline
    };
    
    const result = await apiRequest('update', 'POST', data);
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('updateDocumentModal')).hide();
        clearBarcodeInput();
        showAlert('Document updated successfully!', 'success');
        loadDashboard();
    } else {
        showAlert(result.message || 'Error updating document', 'danger');
    }
}

// Track existing document
function trackExistingDocument() {
    // Hide existing barcode modal
    bootstrap.Modal.getInstance(document.getElementById('existingBarcodeModal')).hide();
    
    const doc = window.existingDoc;
    document.getElementById('trackingNumber').value = doc.tracking_no;
    trackDocument();
    new bootstrap.Modal(document.getElementById('trackModal')).show();
    clearBarcodeInput();
}

// Show mark as outgoing form for existing document
function showOutgoingForm() {
    // Hide existing barcode modal
    bootstrap.Modal.getInstance(document.getElementById('existingBarcodeModal')).hide();
    
    const doc = window.existingDoc;
    
    // Populate mark as outgoing form
    document.getElementById('markOutgoingDocId').value = doc.id;
    document.getElementById('markOutgoingTrackingNo').textContent = doc.tracking_no;
    document.getElementById('markOutgoingBarcode').textContent = doc.barcode || 'N/A';
    document.getElementById('markOutgoingDescription').value = doc.description;
    document.getElementById('markOutgoingDocType').value = doc.doc_type || '';
    document.getElementById('markOutgoingDestination').value = '';
    document.getElementById('markOutgoingStatus').value = 'In Transit';
    document.getElementById('markOutgoingReturnable').checked = false;
    document.getElementById('markOutgoingReturnDays').value = 7;
    document.getElementById('markOutgoingDeadlineRow').style.display = 'none';
    
    new bootstrap.Modal(document.getElementById('markAsOutgoingModal')).show();
}

// Toggle return days for mark as outgoing
function toggleMarkOutgoingReturnDays() {
    const checkbox = document.getElementById('markOutgoingReturnable');
    const returnDaysRow = document.getElementById('markOutgoingDeadlineRow');
    
    if (checkbox.checked) {
        returnDaysRow.style.display = 'flex';
        updateMarkOutgoingDeadline();
    } else {
        returnDaysRow.style.display = 'none';
    }
}

// Calculate deadline for mark as outgoing
function updateMarkOutgoingDeadline() {
    const returnDays = parseInt(document.getElementById('markOutgoingReturnDays').value) || 7;
    const today = new Date();
    today.setDate(today.getDate() + returnDays);
    const expectedDate = today.toISOString().split('T')[0];
    document.getElementById('markOutgoingDateDeadline').value = expectedDate;
}

// Mark document as outgoing (update existing document)
async function markDocumentAsOutgoing() {
    const id = document.getElementById('markOutgoingDocId').value;
    const destination = document.getElementById('markOutgoingDestination').value;
    const status = document.getElementById('markOutgoingStatus').value;
    const isReturnable = document.getElementById('markOutgoingReturnable').checked;
    const returnDays = isReturnable ? parseInt(document.getElementById('markOutgoingReturnDays').value) || 7 : null;
    
    // Validation
    if (!destination) {
        showAlert('Please enter a destination!', 'danger');
        return;
    }
    
    // Calculate deadline if returnable
    let date_deadline = null;
    if (isReturnable && returnDays) {
        const returnDate = new Date();
        returnDate.setDate(returnDate.getDate() + returnDays);
        date_deadline = returnDate.toISOString().split('T')[0];
    }
    
    const data = {
        id: id,
        description: document.getElementById('markOutgoingDescription').value,
        doc_type: document.getElementById('markOutgoingDocType').value,
        date_received: new Date().toISOString().split('T')[0],
        status: status,
        destination: destination,
        date_deadline: date_deadline,
        doc_direction: 'outgoing'
    };
    
    const result = await apiRequest('update_direction', 'POST', data);
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('markAsOutgoingModal')).hide();
        clearBarcodeInput();
        showAlert('Document marked as outgoing successfully!', 'success');
        loadDashboard();
    } else {
        showAlert(result.message || 'Error marking document as outgoing', 'danger');
    }
}

// Select document type - Step 2: User selects incoming, outgoing, or track
function selectDocumentType(type) {
    // Hide type selection modal
    bootstrap.Modal.getInstance(document.getElementById('selectTypeModal')).hide();
    
    if (type === 'incoming') {
        // Set barcode in incoming form
        document.getElementById('incomingBarcode').textContent = currentBarcode;
        document.getElementById('incomingBarcodeHidden').value = currentBarcode;
        
        // Reset form and show modal
        document.getElementById('addIncomingForm').reset();
        setDefaultDates();
        new bootstrap.Modal(document.getElementById('addIncomingModal')).show();
    } else if (type === 'outgoing') {
        // Set barcode in outgoing form
        document.getElementById('outgoingBarcode').textContent = currentBarcode;
        document.getElementById('outgoingBarcodeHidden').value = currentBarcode;
        
        // Reset form and show modal
        document.getElementById('addOutgoingForm').reset();
        document.getElementById('returnDaysRow').style.display = 'none';
        new bootstrap.Modal(document.getElementById('addOutgoingModal')).show();
    } else if (type === 'track') {
        // Track document with the scanned barcode
        document.getElementById('trackingNumber').value = currentBarcode;
        trackDocument();
        new bootstrap.Modal(document.getElementById('trackModal')).show();
        clearBarcodeInput();
    }
}

// Clear barcode input
function clearBarcodeInput() {
    document.getElementById('barcodeInput').value = '';
    document.getElementById('barcodeInput').focus();
    currentBarcode = '';
}

// Toggle return days input
function toggleReturnDays() {
    const checkbox = document.getElementById('outgoingReturnable');
    const returnDaysRow = document.getElementById('returnDaysRow');
    
    if (checkbox.checked) {
        returnDaysRow.style.display = 'flex';
        updateExpectedReturnDate();
    } else {
        returnDaysRow.style.display = 'none';
    }
}

// Calculate expected return date
function updateExpectedReturnDate() {
    const returnDays = parseInt(document.getElementById('outgoingReturnDays').value) || 7;
    const today = new Date();
    today.setDate(today.getDate() + returnDays);
    const expectedDate = today.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('expectedReturnDate').value = expectedDate;
}

// Save incoming document
async function saveIncomingDocument() {
    const barcode = document.getElementById('incomingBarcodeHidden').value;
    const subject = document.getElementById('incomingSubject').value;
    const type = document.getElementById('incomingType').value;
    const date = document.getElementById('incomingDate').value;
    const status = document.getElementById('incomingStatus').value;

    // Validation
    if (!subject || !type || !date) {
        showAlert('Please fill in all required fields!', 'danger');
        return;
    }

    const data = {
        barcode: barcode,
        description: subject,
        doc_type: type,
        date_received: date,
        status: status,
        doc_direction: 'incoming'
    };

    const result = await apiRequest('create', 'POST', data);

    if (result.success) {
        // Reset and close
        document.getElementById('addIncomingForm').reset();
        bootstrap.Modal.getInstance(document.getElementById('addIncomingModal')).hide();
        clearBarcodeInput();

        showAlert('Incoming document saved successfully! Tracking #: ' + result.data.tracking_no, 'success');
        loadDashboard();
    } else {
        showAlert(result.message || 'Error saving document', 'danger');
    }
}

// Save outgoing document
async function saveOutgoingDocument() {
    const barcode = document.getElementById('outgoingBarcodeHidden').value;
    const subject = document.getElementById('outgoingSubject').value;
    const type = document.getElementById('outgoingType').value;
    const destination = document.getElementById('outgoingDestination').value;
    const status = document.getElementById('outgoingStatus').value;
    const isReturnable = document.getElementById('outgoingReturnable').checked;
    const returnDays = isReturnable ? parseInt(document.getElementById('outgoingReturnDays').value) || 7 : null;
    const date = new Date().toISOString().split('T')[0]; // Auto-set date to today

    // Validation
    if (!subject || !type || !destination) {
        showAlert('Please fill in all required fields!', 'danger');
        return;
    }

    // Calculate expected return date if returnable
    let date_deadline = null;
    if (isReturnable && returnDays) {
        const returnDate = new Date();
        returnDate.setDate(returnDate.getDate() + returnDays);
        date_deadline = returnDate.toISOString().split('T')[0];
    }

    const data = {
        barcode: barcode,
        description: subject,
        doc_type: type,
        date_received: date,
        destination: destination,
        status: status,
        date_deadline: date_deadline,
        doc_direction: 'outgoing'
    };

    const result = await apiRequest('create', 'POST', data);

    if (result.success) {
        // Reset and close
        document.getElementById('addOutgoingForm').reset();
        document.getElementById('returnDaysRow').style.display = 'none';
        bootstrap.Modal.getInstance(document.getElementById('addOutgoingModal')).hide();
        clearBarcodeInput();

        showAlert('Outgoing document saved successfully! Tracking #: ' + result.data.tracking_no, 'success');
        loadDashboard();
    } else {
        showAlert(result.message || 'Error saving document', 'danger');
    }
}

// Load incoming documents table
async function loadIncomingDocuments() {
    const tbody = document.getElementById('incomingTableBody');
    
    const result = await apiRequest('read', 'GET', { doc_direction: 'incoming' });
    
    if (!result.success || !result.data || result.data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No incoming documents yet
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = result.data.map(doc => `
        <tr>
            <td><span class="badge bg-primary">${doc.tracking_no}</span></td>
            <td>${truncateText(doc.description, 25)}</td>
            <td>${doc.doc_type}</td>
            <td>${formatDate(doc.date_received)}</td>
            <td>${getStatusBadge(doc.status)}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick="viewDocument('incoming', '${doc.id}')" title="View">
                    <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-info" onclick="trackDocumentById('${doc.tracking_no}')" title="Track">
                    <i class="bi bi-geo-alt"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Load outgoing documents table
async function loadOutgoingDocuments() {
    const tbody = document.getElementById('outgoingTableBody');
    
    const result = await apiRequest('read', 'GET', { doc_direction: 'outgoing' });
    
    if (!result.success || !result.data || result.data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No outgoing documents yet
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = result.data.map(doc => {
        // Check if document is returnable and overdue
        let returnableBadge = '';
        if (doc.date_deadline && doc.status !== 'Returned') {
            const isOverdue = doc.date_deadline && new Date(doc.date_deadline) < new Date();
            if (isOverdue) {
                returnableBadge = '<span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle"></i> Overdue</span>';
            } else {
                returnableBadge = '<span class="badge bg-info ms-1"><i class="bi bi-arrow-return-left"></i> Returnable</span>';
            }
        }
        
        return `
            <tr>
                <td><span class="badge bg-success">${doc.tracking_no}</span></td>
                <td>${truncateText(doc.description, 25)}</td>
                <td>${doc.destination || '-'}</td>
                <td>${formatDate(doc.date_received)}</td>
                <td>${getStatusBadge(doc.status)}${returnableBadge}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="viewDocument('outgoing', '${doc.id}')" title="View">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-info" onclick="trackDocumentById('${doc.tracking_no}')" title="Track">
                        <i class="bi bi-geo-alt"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// View document details
async function viewDocument(type, id) {
    const result = await apiRequest('read_one', 'GET', { id: id });

    if (!result.success || !result.data) {
        showAlert('Document not found!', 'danger');
        return;
    }

    const doc = result.data;
    viewDocumentFromData(doc, null);
}

// View document from data
function viewDocumentFromData(doc, history) {
    const modalBody = document.getElementById('viewModalBody');
    
    if (doc.doc_direction === 'incoming') {
        modalBody.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Tracking #:</strong> <span class="badge bg-primary">${doc.tracking_no}</span></p>
                    <p><strong>Barcode:</strong> <span class="badge bg-secondary">${doc.barcode || 'N/A'}</span></p>
                    <p><strong>Description:</strong> ${doc.description}</p>
                    <p><strong>Type:</strong> ${doc.doc_type}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Date Received:</strong> ${formatDate(doc.date_received)}</p>
                    <p><strong>Status:</strong> ${getStatusBadge(doc.status)}</p>
                    ${doc.date_released ? `<p><strong>Date Released:</strong> ${formatDate(doc.date_released)}</p>` : ''}
                </div>
            </div>
        `;
    } else {
        let returnableInfo = '';
        if (doc.date_deadline) {
            const isOverdue = doc.date_deadline && new Date(doc.date_deadline) < new Date() && doc.status !== 'Returned';
            returnableInfo = `
                <div class="alert ${isOverdue ? 'alert-danger' : 'alert-info'} mt-3 mb-0">
                    <i class="bi bi-arrow-return-left me-2"></i><strong>Returnable Document</strong>
                    <br>
                    <small>Expected Return: ${formatDate(doc.date_deadline)}</small>
                    ${isOverdue ? '<br><strong class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>OVERDUE</strong>' : ''}
                    ${doc.status === 'Returned' ? '<br><strong class="text-success"><i class="bi bi-check-circle me-1"></i>Returned</strong>' : ''}
                </div>
            `;
        }
        
        modalBody.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Tracking #:</strong> <span class="badge bg-success">${doc.tracking_no}</span></p>
                    <p><strong>Barcode:</strong> <span class="badge bg-secondary">${doc.barcode || 'N/A'}</span></p>
                    <p><strong>Description:</strong> ${doc.description}</p>
                    <p><strong>Type:</strong> ${doc.doc_type}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Date Sent:</strong> ${formatDate(doc.date_received)}</p>
                    <p><strong>Destination:</strong> ${doc.destination || '-'}</p>
                    <p><strong>Status:</strong> ${getStatusBadge(doc.status)}</p>
                    ${doc.date_released ? `<p><strong>Date Released:</strong> ${formatDate(doc.date_released)}</p>` : ''}
                </div>
            </div>
            ${returnableInfo}
        `;
    }

    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

// Track document by ID
function trackDocumentById(trackingNo) {
    document.getElementById('trackingNumber').value = trackingNo;
    trackDocument();
    new bootstrap.Modal(document.getElementById('trackModal')).show();
}

// Track document
async function trackDocument() {
    const trackingNumber = document.getElementById('trackingNumber').value.trim();

    if (!trackingNumber) {
        showAlert('Please enter a tracking number or barcode!', 'warning');
        return;
    }

    const result = await apiRequest('track', 'GET', { search: trackingNumber });

    if (!result.success || !result.data) {
        showAlert('Document not found! Please check the tracking number or barcode.', 'danger');
        document.getElementById('trackingResult').style.display = 'none';
        return;
    }

    // Display tracking result
    const timeline = document.getElementById('trackingTimeline');
    const history = result.history || [];
    
    timeline.innerHTML = history.map(item => `
        <div class="timeline-item ${item.status}">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>${item.action}</strong>
                    <br>
                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>${item.location}</small>
                </div>
                <div class="text-end">
                    <small class="text-muted">${item.date !== '-' ? formatDate(item.date) : '-'}</small>
                    <br>
                    <span class="badge ${item.status === 'completed' ? 'bg-success' : item.status === 'overdue' ? 'bg-danger' : 'bg-warning'}">${item.status === 'completed' ? 'Completed' : item.status === 'overdue' ? 'Overdue' : 'Pending'}</span>
                </div>
            </div>
        </div>
    `).join('');

    document.getElementById('trackingResult').style.display = 'block';
}

// Helper function: Get status badge
function getStatusBadge(status) {
    const statusClasses = {
        'Pending': 'bg-warning text-dark',
        'In Progress': 'bg-info text-dark',
        'Processed': 'bg-success',
        'In Transit': 'bg-info',
        'Delivered': 'bg-success',
        'Received': 'bg-success',
        'Returned': 'bg-primary'
    };
    return `<span class="badge ${statusClasses[status] || 'bg-secondary'} status-badge">${status}</span>`;
}

// Helper function: Format date
function formatDate(dateString) {
    if (!dateString || dateString === '-') return '-';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Helper function: Truncate text
function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text || '';
    return text.substring(0, maxLength) + '...';
}

// Helper function: Show alert
function showAlert(message, type) {
    const alertContainer = document.createElement('div');
    alertContainer.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertContainer.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
    alertContainer.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertContainer);

    // Auto dismiss after 3 seconds
    setTimeout(() => {
        alertContainer.remove();
    }, 3000);
}