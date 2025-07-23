let requestItems = [];

function addToRequest(itemId, itemName, unit, qtyFromModal) {
  // Check if item already exists in request
  const existingItem = requestItems.find((item) => item.id === itemId);
  if (existingItem) {
    alert("Item already added to request");
    return;
  }

  let qty = qtyFromModal;
  if (typeof qty === "undefined" || qty === null) {
    // fallback to prompt if called from old button
    const quantity = prompt(`Enter quantity for ${itemName}:`);
    if (quantity === null || quantity === "") return;
    qty = parseInt(quantity);
  }

  if (isNaN(qty) || qty <= 0) {
    alert("Please enter a valid quantity (1 or more)");
    return;
  }

  // Add to request
  requestItems.push({
    id: itemId,
    name: itemName,
    quantity: qty,
    unit: unit,
  });

  updateRequestDisplay();
}

function removeFromRequest(itemId) {
  requestItems = requestItems.filter((item) => item.id !== itemId);
  updateRequestDisplay();
}

function updateRequestDisplay() {
  const container = document.getElementById("requestItems");
  const actions = document.getElementById("requestActions");

  if (requestItems.length === 0) {
    container.innerHTML =
      '<p class="text-muted text-center">No items selected yet</p>';
    actions.style.display = "none";
  } else {
    let html = "";
    requestItems.forEach((item) => {
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
    actions.style.display = "block";
  }
}

// Bootstrap Toast function with auto-close after 3 seconds
function showBootstrapToast(message, type = 'success') {
  const toastElement = document.getElementById('liveToast');
  const toastIcon = document.getElementById('toast-icon');
  const toastTitle = document.getElementById('toast-title');
  const toastBody = document.getElementById('toast-body');
  
  // Configure toast based on type
  const config = {
    success: {
      icon: 'fas fa-check-circle text-success',
      title: 'Success',
      class: 'text-success'
    },
    error: {
      icon: 'fas fa-exclamation-circle text-danger',
      title: 'Error',
      class: 'text-danger'
    },
    warning: {
      icon: 'fas fa-exclamation-triangle text-warning',
      title: 'Warning',
      class: 'text-warning'
    }
  };
  
  const currentConfig = config[type] || config.success;
  
  toastIcon.className = currentConfig.icon;
  toastTitle.textContent = currentConfig.title;
  toastTitle.className = `me-auto ${currentConfig.class}`;
  toastBody.textContent = message;
  
  // Create toast with auto-hide after 3 seconds (3000ms)
  const toast = new bootstrap.Toast(toastElement, {
    autohide: true,
    delay: 3000
  });
  
  toast.show();
}
function submitRequest() {
  if (requestItems.length === 0) {
    showBootstrapToast("Please add items to your request", "warning");
    return;
  }

  const reason = document.getElementById("requestReason").value;
  // Remarks are now optional, so no required check

  // Prepare request data
  const requestData = {
    items: requestItems,
    reason: reason,
    user_id: userId,
    username: username,
    office_id: officeId,
    office_name: officeName,
  };

  // Log the data being sent to the backend
  console.log("Submitting request data:", requestData);
  console.log("Request items:", requestItems);
  console.log("User details:", {
    user_id: userId,
    username: username,
    office_id: officeId,
    office_name: officeName,
  });

  // Show loading toast
  showBootstrapToast("Submitting your request...", "warning");

  // Send request to backend
  fetch("Logi_submit_request.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(requestData),
  })
    .then((response) => {
      console.log("Response status:", response.status);
      console.log(
        "Response content type:",
        response.headers.get("content-type")
      );

      // Check if response is actually JSON
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        throw new Error("Server returned non-JSON response");
      }

      return response.text(); // Get as text first to debug
    })
    .then((text) => {
      console.log("Raw response text:", text);

      try {
        const data = JSON.parse(text);
        console.log("Parsed response data:", data);

        if (data.success) {
          showBootstrapToast(
            "Request submitted successfully! Your request has been sent to the administrator.",
            "success"
          );
          clearRequest();
        } else {
          showBootstrapToast("Error: " + data.message, "error");
        }
      } catch (parseError) {
        console.error("JSON parse error:", parseError);
        console.error("Response text that failed to parse:", text);
        showBootstrapToast(
          "Server returned invalid response. Please check the console for details.",
          "error"
        );
      }
    })
    .catch((error) => {
      console.error("Fetch error:", error);
      console.error("Full error details:", {
        message: error.message,
        stack: error.stack,
      });
      showBootstrapToast(
        "Error submitting request. Please try again.",
        "error"
      );
    });
}
function clearRequest() {
  requestItems = [];
  document.getElementById("requestReason").value = "";
  updateRequestDisplay();
}

function openAddToRequestModal(itemId, itemName, unit) {
  document.getElementById("modalItemId").value = itemId;
  document.getElementById("modalItemName").value = itemName;
  document.getElementById("modalItemUnit").value = unit;
  document.getElementById("modalItemQty").value = 1;

  // Show the modal (Bootstrap 5)
  var modal = new bootstrap.Modal(document.getElementById("addToRequestModal"));
  modal.show();
}

document.addEventListener("DOMContentLoaded", function () {
  document
    .getElementById("confirmAddToRequest")
    .addEventListener("click", function () {
      const itemId = document.getElementById("modalItemId").value;
      const itemName = document.getElementById("modalItemName").value;
      const unit = document.getElementById("modalItemUnit").value;
      const qty = parseInt(document.getElementById("modalItemQty").value, 10);

      if (isNaN(qty) || qty < 1) {
        alert("Please enter a valid quantity (1 or more)");
        return;
      }

      // Call addToRequest with the quantity from modal
      addToRequest(itemId, itemName, unit, qty);

      // Hide the modal (Bootstrap 5)
      var modalEl = document.getElementById("addToRequestModal");
      var modal = bootstrap.Modal.getInstance(modalEl);
      modal.hide();
    });
});
