// Dual-table voucher selection system
let currentVendor = null;
let allVouchers = [];
let selectedVouchers = []; // Tracks order of selection
const VOUCHER_VALUE = 200;

// Initialize
document.addEventListener("DOMContentLoaded", function () {
  setupEventListeners();
});

function setupEventListeners() {
  // Vendor search
  document
    .getElementById("searchVendorBtn")
    .addEventListener("click", searchVendor);
  document.getElementById("vendorSerial").addEventListener("keypress", (e) => {
    if (e.key === "Enter") searchVendor();
  });

  // Live vendor search suggestion
  document
    .getElementById("vendorSerial")
    .addEventListener("input", liveVendorSearch);

  // Voucher search
  document
    .getElementById("voucherSearch")
    .addEventListener("keyup", filterAvailableVouchers);
  document
    .getElementById("clearVoucherSearch")
    .addEventListener("click", () => {
      document.getElementById("voucherSearch").value = "";
      filterAvailableVouchers();
    });

  // Redeem button
  document
    .getElementById("redeemBtn")
    .addEventListener("click", showConfirmModal);
  document
    .getElementById("confirmRedeemBtn")
    .addEventListener("click", confirmRedeem);

  // Refresh button
  document.getElementById("refreshBtn").addEventListener("click", () => {
    if (currentVendor) {
      loadVouchers(currentVendor.id);
    }
  });
}

// Live search suggestion as user types with dropdown
function liveVendorSearch() {
  const vendorSerial = this.value.trim();
  const inputField = document.getElementById("vendorSerial");
  const suggestionsContainer = document.getElementById("vendorSuggestions");

  // Clear if empty
  if (vendorSerial.length < 2) {
    inputField.classList.remove("is-valid", "is-invalid");
    suggestionsContainer.classList.add("d-none");
    return;
  }

  fetch(
    "api_search_vendor.php?vendor_serial=" + encodeURIComponent(vendorSerial),
  )
    .then((res) => res.json())
    .then((data) => {
      if (data.success && data.vendors && data.vendors.length > 0) {
        // Show green indicator
        inputField.classList.remove("is-invalid");
        inputField.classList.add("is-valid");

        // Build suggestions dropdown
        suggestionsContainer.innerHTML = "";
        data.vendors.forEach((vendor) => {
          const item = document.createElement("a");
          item.href = "#";
          item.className = "list-group-item list-group-item-action py-2";
          item.innerHTML = `<div class="fw-bold">${vendor.vendor_name}</div><small class="text-muted">${vendor.vendor_serial} | ${vendor.stall_no || "N/A"}</small>`;

          // Click handler
          item.addEventListener("click", (e) => {
            e.preventDefault();
            document.getElementById("vendorSerial").value =
              vendor.vendor_serial;
            suggestionsContainer.classList.add("d-none");
            searchVendor();
          });

          suggestionsContainer.appendChild(item);
        });

        suggestionsContainer.classList.remove("d-none");
      } else {
        // No matches - show red indicator
        inputField.classList.remove("is-valid");
        inputField.classList.add("is-invalid");
        suggestionsContainer.classList.add("d-none");
      }
    })
    .catch((err) => {
      inputField.classList.remove("is-valid", "is-invalid");
      suggestionsContainer.classList.add("d-none");
    });
}

// Hide dropdown when clicking outside
document.addEventListener("click", function (e) {
  if (!e.target.closest(".input-group")) {
    document.getElementById("vendorSuggestions").classList.add("d-none");
  }
});

// Search for vendor
function searchVendor() {
  const vendorSerial = document.getElementById("vendorSerial").value.trim();

  if (!vendorSerial) {
    alert("Please enter vendor serial");
    return;
  }

  const searchBtn = document.getElementById("searchVendorBtn");
  const originalText = searchBtn.innerHTML;
  searchBtn.disabled = true;
  searchBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span> Searching...';

  // Fetch vendor data
  fetch(
    "api_search_vendor.php?vendor_serial=" + encodeURIComponent(vendorSerial),
  )
    .then((res) => res.json())
    .then((data) => {
      searchBtn.disabled = false;
      searchBtn.innerHTML = originalText;

      if (data.success) {
        const vendor =
          data.match_type === "exact" ? data.vendor : data.vendors[0];
        currentVendor = vendor;
        displayVendorInfo(vendor);
        loadVouchers(vendor.id);
        document
          .getElementById("vendorSerial")
          .classList.remove("is-valid", "is-invalid");
      } else {
        alert(data.message || "Vendor not found");
        currentVendor = null;
      }
    })
    .catch((err) => {
      console.error("Error searching vendor:", err);
      searchBtn.disabled = false;
      searchBtn.innerHTML = originalText;
      alert("Error searching vendor");
    });
}

function displayVendorInfo(vendor) {
  document.getElementById("vendorId").textContent = vendor.vendor_serial;
  document.getElementById("vendorName").textContent = vendor.vendor_name;
  document.getElementById("vendorStallNo").textContent = vendor.stall_no || "-";
  document.getElementById("vendorSection").textContent = vendor.section || "-";
  document.getElementById("vendorInfo").classList.remove("d-none");
}

// Load vouchers from backend
function loadVouchers(vendorId) {
  const tbody = document.getElementById("availableVoucherBody");
  tbody.innerHTML =
    '<tr><td colspan="3" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';

  fetch(
    "api_get_vendor_vouchers.php?vendor_serial=" +
      encodeURIComponent(currentVendor.vendor_serial),
  )
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        allVouchers = data.vouchers;
        selectedVouchers = [];
        renderAvailableVouchers();
        renderSelectedVouchers();
        updateTotals();
        document.getElementById("voucherSection").classList.remove("d-none");
        document.getElementById("noVendorMessage").classList.add("d-none");
      } else {
        alert(data.message || "Error loading vouchers");
      }
    })
    .catch((err) => {
      console.error("Error loading vouchers:", err);
      alert("Error loading vouchers");
    });
}

// Render available vouchers table
function renderAvailableVouchers() {
  const tbody = document.getElementById("availableVoucherBody");
  tbody.innerHTML = "";

  // Filter: only show vouchers not selected yet
  const selectedIds = selectedVouchers.map((v) => v.id);
  const available = allVouchers.filter((v) => !selectedIds.includes(v.id));

  if (available.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="3" class="text-center text-muted py-3">No verified vouchers available</td></tr>';
    document.getElementById("availableCount").textContent = "0 available";
    return;
  }

  available.forEach((voucher) => {
    const row = document.createElement("tr");

    // Format voucher code with styled sequence number (red)
    const beneficiaryCode = voucher.beneficiary_code || "N/A";
    const sequenceNumber = String(voucher.voucher_number).padStart(3, "0"); // Ensures 3-digit format like 001
    const voucherCodeHtml = `<strong>${beneficiaryCode}-<span style="color: #dc3545; font-weight: bold;">${sequenceNumber}</span></strong>`;

    const isSelected = selectedIds.includes(voucher.id);
    const isRedeemed = voucher.is_redeemed === 1;
    const isVerified = voucher.is_verified === 1;

    let buttonHtml = "";
    if (isSelected) {
      buttonHtml = `<button class="btn btn-sm btn-success" disabled title="Already selected">
                <i class="bi bi-check-circle"></i>
            </button>`;
      row.classList.add("table-success");
    } else if (isRedeemed) {
      buttonHtml = `<button class="btn btn-sm btn-secondary" disabled title="Already redeemed">
                <i class="bi bi-lock"></i>
            </button>`;
      row.classList.add("table-secondary");
    } else {
      buttonHtml = `<button class="btn btn-sm btn-primary" onclick="selectVoucher(${voucher.id})" title="Select">
                <i class="bi bi-plus-circle"></i>
            </button>`;
    }

    // Status indicators
    const verifiedIcon = isVerified
      ? '<i class="bi bi-check-circle-fill text-success"></i>'
      : '<i class="bi bi-x-circle-fill text-danger"></i>';
    const redeemedIcon = isRedeemed
      ? '<i class="bi bi-check-circle-fill text-success"></i>'
      : '<i class="bi bi-circle text-muted"></i>';

    row.innerHTML = `
        <td><strong style="font-size: 1.1em;">${voucherCodeHtml}</strong></td>
        <td><small>${voucher.beneficiary_name || voucher.claimant_name}</small></td>
        <td class="text-center">${verifiedIcon}</td>
        <td class="text-center">${redeemedIcon}</td>
        <td>${buttonHtml}</td>
    `;
    tbody.appendChild(row);
  });

  document.getElementById("availableCount").textContent =
    available.length + " available";
}

// Render selected vouchers table (with order)
function renderSelectedVouchers() {
  const tbody = document.getElementById("selectedVoucherBody");
  tbody.innerHTML = "";

  if (selectedVouchers.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="4" class="text-center text-muted py-3">No vouchers selected</td></tr>';
    document.getElementById("selectedCount").textContent = "0 selected";
    return;
  }

  selectedVouchers.forEach((voucher, index) => {
   const row = document.createElement("tr");
row.classList.add("table-success");

// Format voucher code with styled sequence number (red)
const beneficiaryCode = voucher.beneficiary_code || "N/A";
const sequenceNumber = String(voucher.voucher_number).padStart(3, "0"); // Ensures 3-digit format like 001
const voucherCodeHtml = `<strong>${beneficiaryCode}-<span style="color: #dc3545; font-weight: bold;">${sequenceNumber}</span></strong>`;

row.innerHTML = `
        <td><strong>${index + 1}</strong></td>
        <td><strong style="font-size: 1.1em;">${voucherCodeHtml}</strong></td>
        <td><small>${voucher.beneficiary_name || voucher.claimant_name}</small></td>
        <td>
            <button class="btn btn-sm btn-danger" onclick="removeVoucher(${voucher.id})" title="Remove">
                <i class="bi bi-dash-circle"></i>
            </button>
        </td>
    `;
tbody.appendChild(row);
  });

  document.getElementById("selectedCount").textContent =
    selectedVouchers.length + " selected";
}

// Select a voucher (move to selected list)
function selectVoucher(voucherId) {
  const voucher = allVouchers.find((v) => v.id === voucherId);
  if (voucher) {
    selectedVouchers.push(voucher);

    // ✅ DO NOT FULLY REFRESH TABLE - preserve search results
    // Just find and update the single row that was clicked
    const tbody = document.getElementById("availableVoucherBody");
    const rows = tbody.querySelectorAll("tr");

    for (let i = 0; i < rows.length; i++) {
      const btn = rows[i].querySelector(
        `button[onclick="selectVoucher(${voucherId})"]`,
      );
      if (btn) {
        // Update this row to selected state
        rows[i].classList.add("table-success");
        btn.outerHTML = `<button class="btn btn-sm btn-success" disabled title="Already selected">
                    <i class="bi bi-check-circle"></i>
                </button>`;
        break;
      }
    }

    // Only refresh right table and totals
    renderSelectedVouchers();
    updateTotals();
  }
}

// Remove a voucher (move back to available list)
function removeVoucher(voucherId) {
  selectedVouchers = selectedVouchers.filter((v) => v.id !== voucherId);
  renderAvailableVouchers();
  renderSelectedVouchers();
  updateTotals();
}

// Filter available vouchers by search
function filterAvailableVouchers() {
  const searchTerm = document
    .getElementById("voucherSearch")
    .value.toLowerCase();
  const tbody = document.getElementById("availableVoucherBody");
  const rows = tbody.querySelectorAll("tr");

  rows.forEach((row) => {
    if (
      row.textContent.toLowerCase().includes(searchTerm) ||
      searchTerm === ""
    ) {
      row.style.display = "";
    } else {
      row.style.display = "none";
    }
  });
}

// Update totals
function updateTotals() {
  const total = selectedVouchers.length * VOUCHER_VALUE;
  document.getElementById("totalAmount").textContent =
    "₱" +
    total.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  document.getElementById("totalItems").textContent = selectedVouchers.length;

  // Enable/disable redeem button
  document.getElementById("redeemBtn").disabled = selectedVouchers.length === 0;
}

// Show confirmation modal
function showConfirmModal() {
  if (selectedVouchers.length === 0) {
    alert("Please select vouchers to redeem");
    return;
  }

  document.getElementById("modalCount").textContent = selectedVouchers.length;
  document.getElementById("modalVendorName").textContent =
    currentVendor.vendor_name + " (" + currentVendor.vendor_serial + ")";

  const confirmModal = new bootstrap.Modal(
    document.getElementById("confirmModal"),
  );
  confirmModal.show();
}

// Confirm redemption and create batch
function confirmRedeem() {
  const confirmModal = bootstrap.Modal.getInstance(
    document.getElementById("confirmModal"),
  );
  confirmModal.hide();

  // Prepare ordered voucher IDs
  const voucherIds = selectedVouchers.map((v) => v.id);

  const payload = {
    vendor_serial: currentVendor.vendor_serial,
    voucher_ids: voucherIds,
  };

  // Show loading state
  const redeemBtn = document.getElementById("redeemBtn");
  const originalText = redeemBtn.innerHTML;
  redeemBtn.disabled = true;
  redeemBtn.innerHTML =
    '<i class="bi bi-hourglass-split me-1"></i> Processing...';

  // Call backend to create batch
  fetch("api_create_batch.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  })
    .then((res) => res.json())
    .then((data) => {
      redeemBtn.innerHTML = originalText;
      redeemBtn.disabled = false;

      if (data.success) {
        const batchId = data.batch.batch_id;

        // Show success modal
        const successModal = new bootstrap.Modal(
          document.getElementById("successModal"),
        );
        const formattedAmount =
          "₱" +
          data.batch.total_amount.toLocaleString("en-PH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          });
        document.getElementById("successMessage").innerHTML = `
                <strong>Batch #${data.batch.batch_number}</strong> created successfully<br>
                <small>${data.batch.total_vouchers} vouchers - ${formattedAmount}</small><br><br>
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="api_export_batch_pdf.php?batch_id=${batchId}" class="btn btn-danger btn-sm" target="_blank">
                        <i class="bi bi-file-pdf me-1"></i> Export PDF
                    </a>
                    <a href="api_export_batch_csv.php?batch_id=${batchId}" class="btn btn-success btn-sm" target="_blank">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                    </a>
                    <a href="generate_ar.php?batch_id=${batchId}" class="btn btn-primary btn-sm" target="_blank">
                        <i class="bi bi-file-earmark-text me-1"></i> Generate Acknowledgement Receipt
                    </a>
                </div>
            `;
        successModal.show();

        // Reset selection
        setTimeout(() => {
          selectedVouchers = [];
          renderAvailableVouchers();
          renderSelectedVouchers();
          updateTotals();
        }, 2000);
      } else {
        alert("Error: " + data.message);
      }
    })
    .catch((err) => {
      console.error("Error creating batch:", err);
      alert("Error creating batch");
      redeemBtn.innerHTML = originalText;
      redeemBtn.disabled = false;
    });
}
