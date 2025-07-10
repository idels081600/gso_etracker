let requestItems = [];

function addToRequest(itemId, itemName, maxQty, unit, qtyFromModal) {
    // Check if item already exists in request
    const existingItem = requestItems.find(item => item.id === itemId);
    if (existingItem) {
        alert('Item already added to request');
        return;
    }

    let qty = qtyFromModal;
    if (typeof qty === 'undefined') {
        // fallback to prompt if called from old button
        const quantity = prompt(`Enter quantity for ${itemName}:`);
        if (quantity === null || quantity === '') return;
        qty = parseInt(quantity);
    }

    if (isNaN(qty) || qty <= 0) {
        alert('Please enter a valid quantity (1 or more)');
        return;
    }

    // Add to request
    requestItems.push({
        id: itemId,
        name: itemName,
        quantity: qty,
        unit: unit,
        maxQty: maxQty
    });

    updateRequestDisplay();
}

function removeFromRequest(itemId) {
    requestItems = requestItems.filter(item => item.id !== itemId);
    updateRequestDisplay();
}

function updateRequestDisplay() {
    const container = document.getElementById('requestItems');
    const actions = document.getElementById('requestActions');

    if (requestItems.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No items selected yet</p>';
        actions.style.display = 'none';
    } else {
        let html = '';
        requestItems.forEach(item => {
            html += `
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${item.name}</strong><br>
                            <small class="text-muted">Qty: ${item.quantity} ${item.unit}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromRequest('${item.id}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
        actions.style.display = 'block';
    }
}

function submitRequest() {
    if (requestItems.length === 0) {
        alert('Please add items to your request');
        return;
    }

    const reason = document.getElementById('requestReason').value;
    // Remarks are now optional, so no required check

    // Prepare request data
    const requestData = {
        items: requestItems,
        reason: reason,
        user_id: userId,
        username: username,
        office_id: officeId,
        office_name: officeName
    };

    // Send request to backend
    fetch('Logi_submit_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Request submitted successfully! Your request has been sent to the administrator.');
                clearRequest();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error submitting request. Please try again.');
        });
}

function clearRequest() {
    requestItems = [];
    document.getElementById('requestReason').value = '';
    updateRequestDisplay();
}

function openAddToRequestModal(itemId, itemName, currentBalance, unit) {
    document.getElementById('modalItemId').value = itemId;
    document.getElementById('modalItemName').value = itemName;
    document.getElementById('modalItemUnit').value = unit;
    document.getElementById('modalItemCurrentBalance').value = currentBalance;
    document.getElementById('modalItemQty').value = 1;

    // Show the modal (Bootstrap 5)
    var modal = new bootstrap.Modal(document.getElementById('addToRequestModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('confirmAddToRequest').addEventListener('click', function() {
        const itemId = document.getElementById('modalItemId').value;
        const itemName = document.getElementById('modalItemName').value;
        const unit = document.getElementById('modalItemUnit').value;
        const qty = parseInt(document.getElementById('modalItemQty').value, 10);

        if (isNaN(qty) || qty < 1) {
            alert('Please enter a valid quantity (1 or more)');
            return;
        }

        addToRequest(itemId, itemName, 999999, unit, qty); // 999999 as dummy maxQty

        // Hide the modal (Bootstrap 5)
        var modalEl = document.getElementById('addToRequestModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
    });
});