// Dual-table voucher selection system with server-side pagination
let currentVendor = null;
let allVouchers = [];
let selectedVouchers = []; // Tracks order of selection
const VOUCHER_VALUE = 200;
const VOUCHER_PAGE_LIMIT = 50;
const MIN_VOUCHER_SEARCH_LENGTH = 2;
const VENDOR_SEARCH_DEBOUNCE_MS = 350;
const VOUCHER_SEARCH_DEBOUNCE_MS = 500;

// Pagination state
let currentPage = 1;
let totalPages = 1;
let currentSearchTerm = '';
let vendorSearchTimeout;
let vendorSearchController;
let voucherSearchController;

// User-specific draft key
const getDraftKey = () => {
  const username = window.USER_INFO?.username || 'anonymous';
  return `food_voucher_draft_${username}`;
};

// Initialize
document.addEventListener("DOMContentLoaded", function () {
  setupEventListeners();
  loadDraftFromStorage();
});

// Draft management functions
function saveDraftToStorage() {
  if (currentVendor && selectedVouchers.length > 0) {
    const draft = {
      vendor: currentVendor,
      selectedVouchers: selectedVouchers,
      timestamp: new Date().toISOString()
    };
    localStorage.setItem(getDraftKey(), JSON.stringify(draft));
  } else {
    clearDraftFromStorage();
  }
}

function loadDraftFromStorage() {
  const draft = localStorage.getItem(getDraftKey());
  if (draft) {
    try {
      const parsedDraft = JSON.parse(draft);
      // Only load draft if it's recent (within 24 hours)
      const draftTime = new Date(parsedDraft.timestamp);
      const now = new Date();
      const hoursDiff = (now - draftTime) / (1000 * 60 * 60);

      if (hoursDiff < 24) {
        currentVendor = parsedDraft.vendor;
        selectedVouchers = parsedDraft.selectedVouchers || [];

        // Restore vendor info if we have a draft
        if (currentVendor) {
          displayVendorInfo(currentVendor);
          document.getElementById("vendorSerial").value = currentVendor.vendor_serial;
          document.getElementById("voucherSection").classList.remove("d-none");
          document.getElementById("noVendorMessage").classList.add("d-none");

          // Render selected vouchers from draft immediately
          renderSelectedVouchers();
          updateTotals();

          // Show search prompt instead of auto-loading (search-first pattern)
          showVoucherSearchPrompt();
        }
      } else {
        clearDraftFromStorage();
      }
    } catch (e) {
      console.error('Error loading draft:', e);
      clearDraftFromStorage();
    }
  }
}

function clearDraftFromStorage() {
  localStorage.removeItem(getDraftKey());
}

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
    .addEventListener("input", queueLiveVendorSearch);

  const voucherSearchInput = document.getElementById("voucherSearch");

  function runVoucherSearch() {
    const term = voucherSearchInput.value.trim();

    if (!currentVendor) {
      alert("Please search and select a vendor first.");
      return;
    }

    if (!term) {
      currentSearchTerm = "";
      currentPage = 1;
      showVoucherSearchPrompt();
      return;
    }

    if (term.length < MIN_VOUCHER_SEARCH_LENGTH && !/^\d+$/.test(term)) {
      showVoucherSearchPrompt("Enter at least 2 characters, or a voucher sequence number.");
      return;
    }

    currentSearchTerm = term;
    currentPage = 1;
    loadVouchers(currentVendor.id, 1, term);
  }

  // Voucher search (server-side with debounce)
  let searchTimeout;
  voucherSearchInput.addEventListener("keyup", function (e) {
      clearTimeout(searchTimeout);
      const term = this.value.trim();
      
      // Visual feedback: highlight search input if text is present
      if (term.length > 0) {
        this.classList.add('is-active');
      } else {
        this.classList.remove('is-active');
      }

      if (!term) {
        currentSearchTerm = "";
        currentPage = 1;
        showVoucherSearchPrompt();
        return;
      }

      if (e.key === "Enter") {
        runVoucherSearch();
        return;
      }
      
      searchTimeout = setTimeout(() => {
        if (currentVendor && (term.length >= MIN_VOUCHER_SEARCH_LENGTH || /^\d+$/.test(term))) {
          runVoucherSearch();
        }
      }, VOUCHER_SEARCH_DEBOUNCE_MS);
    });

  document
    .getElementById("searchVoucherBtn")
    .addEventListener("click", runVoucherSearch);

  document
    .getElementById("clearVoucherSearch")
    .addEventListener("click", () => {
      voucherSearchInput.value = "";
      voucherSearchInput.classList.remove('is-active');
      currentSearchTerm = "";
      currentPage = 1;
      showVoucherSearchPrompt();
    });

  // Pagination buttons
  document.getElementById("prevPageBtn").addEventListener("click", () => {
    if (currentPage > 1 && currentVendor && currentSearchTerm) {
      loadVouchers(currentVendor.id, currentPage - 1, currentSearchTerm);
    }
  });
  document.getElementById("nextPageBtn").addEventListener("click", () => {
    if (currentPage < totalPages && currentVendor && currentSearchTerm) {
      loadVouchers(currentVendor.id, currentPage + 1, currentSearchTerm);
    }
  });

  // Redeem button
  document
    .getElementById("redeemBtn")
    .addEventListener("click", showConfirmModal);
  document
    .getElementById("confirmRedeemBtn")
    .addEventListener("click", confirmRedeem);

  // Refresh button
  // Refresh button - only refresh if actively searching
  document.getElementById("refreshBtn").addEventListener("click", () => {
    if (currentVendor) {
      if (currentSearchTerm) {
        // Refresh current search
        loadVouchers(currentVendor.id, 1, currentSearchTerm);
      } else {
        // Show prompt if not searching
        showVoucherSearchPrompt();
      }
    }
  });

  // Save draft button
  document.getElementById("saveDraftBtn").addEventListener("click", () => {
    saveDraftToStorage();
    // Show feedback
    const btn = document.getElementById("saveDraftBtn");
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Saved!';
    btn.classList.remove('btn-outline-info');
    btn.classList.add('btn-success');
    setTimeout(() => {
      btn.innerHTML = originalText;
      btn.classList.remove('btn-success');
      btn.classList.add('btn-outline-info');
    }, 2000);
  });
}

function queueLiveVendorSearch() {
  clearTimeout(vendorSearchTimeout);

  const vendorSerial = this.value.trim();
  const inputField = document.getElementById("vendorSerial");
  const suggestionsContainer = document.getElementById("vendorSuggestions");

  if (vendorSerial.length < 2) {
    if (vendorSearchController) {
      vendorSearchController.abort();
    }
    inputField.classList.remove("is-valid", "is-invalid");
    suggestionsContainer.classList.add("d-none");
    return;
  }

  vendorSearchTimeout = setTimeout(() => {
    liveVendorSearch(vendorSerial);
  }, VENDOR_SEARCH_DEBOUNCE_MS);
}

// Live search suggestion as user types with dropdown
function liveVendorSearch(vendorSerial) {
  const inputField = document.getElementById("vendorSerial");
  const suggestionsContainer = document.getElementById("vendorSuggestions");

  if (vendorSearchController) {
    vendorSearchController.abort();
  }

  vendorSearchController = new AbortController();

  fetch(
    "api_search_vendor.php?vendor_serial=" + encodeURIComponent(vendorSerial),
    { signal: vendorSearchController.signal }
  )
    .then((res) => res.json())
    .then((data) => {
      if (data.success && data.vendors && data.vendors.length > 0) {
        inputField.classList.remove("is-invalid");
        inputField.classList.add("is-valid");

        suggestionsContainer.innerHTML = "";
        data.vendors.forEach((vendor) => {
          const item = document.createElement("a");
          item.href = "#";
          item.className = "list-group-item list-group-item-action py-2";
          item.innerHTML = `<div class="fw-bold">${vendor.vendor_name}</div><small class="text-muted">${vendor.vendor_serial} | ${vendor.stall_no || "N/A"}</small>`;

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
        inputField.classList.remove("is-valid");
        inputField.classList.add("is-invalid");
        suggestionsContainer.classList.add("d-none");
      }
    })
    .catch((err) => {
      if (err.name === "AbortError") {
        return;
      }
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
        selectedVouchers = [];
        currentPage = 1;
        currentSearchTerm = '';
        document.getElementById("voucherSearch").value = '';
        document.getElementById("voucherSearch").classList.remove('is-active');
        clearDraftFromStorage();
        displayVendorInfo(vendor);
        // Search-first pattern: show prompt instead of auto-loading all vouchers
        showVoucherSearchPrompt();
        document.getElementById("voucherSection").classList.remove("d-none");
        document.getElementById("noVendorMessage").classList.add("d-none");
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

// Show search prompt message when vendor is selected but no search performed
function showVoucherSearchPrompt(message) {
  allVouchers = [];
  currentPage = 1;
  totalPages = 1;

  const tbody = document.getElementById("availableVoucherBody");
  tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">
    <div>
      <i class="bi bi-search" style="font-size: 2rem; color: #ccc;"></i>
      <div class="mt-2"><strong>Search for Vouchers</strong></div>
      <small>${message || "Enter voucher code, beneficiary code, name, or sequence number"}</small>
    </div>
  </td></tr>`;
  document.getElementById("availableCount").textContent = "0 available";
  document.getElementById("paginationControls").classList.add("d-none");
}

// Load vouchers from backend with pagination + search
function loadVouchers(vendorId, page, search) {
  const term = (search || "").trim();
  if (!term) {
    showVoucherSearchPrompt();
    return;
  }

  const tbody = document.getElementById("availableVoucherBody");
  tbody.innerHTML =
    '<tr><td colspan="6" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';

  const params = new URLSearchParams();
  params.set('vendor_serial', currentVendor.vendor_serial);
  params.set('page', page);
  params.set('limit', VOUCHER_PAGE_LIMIT);
  params.set('require_search', '1');
  params.set('search', term);

  if (voucherSearchController) {
    voucherSearchController.abort();
  }
  voucherSearchController = new AbortController();

  fetch("api_get_vendor_vouchers.php?" + params.toString(), {
    signal: voucherSearchController.signal
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        allVouchers = data.vouchers;
        currentPage = data.page;
        totalPages = data.total_pages;
        currentSearchTerm = term;

        // Validate that selected vouchers still exist (not redeemed by someone else)
        // We need a full list for validation, so fetch all IDs in parallel (optional)
        // For now, just validate against the current page's vouchers
        const validSelectedVouchers = selectedVouchers.filter(selectedVoucher =>
          !selectedVoucher.is_redeemed // trust the last known state
        );

        if (validSelectedVouchers.length !== selectedVouchers.length) {
          selectedVouchers = validSelectedVouchers;
          saveDraftToStorage();
        }

        renderAvailableVouchers();
        renderSelectedVouchers();
        updateTotals();
        updatePaginationControls();
        document.getElementById("voucherSection").classList.remove("d-none");
        document.getElementById("noVendorMessage").classList.add("d-none");
      } else {
        alert(data.message || "Error loading vouchers");
      }
    })
    .catch((err) => {
      if (err.name === "AbortError") {
        return;
      }
      console.error("Error loading vouchers:", err);
      if (selectedVouchers.length > 0) {
        renderSelectedVouchers();
        updateTotals();
      } else {
        alert("Error loading vouchers");
      }
    });
}

// Update pagination controls visibility and state
function updatePaginationControls() {
  const controls = document.getElementById("paginationControls");
  const prevBtn = document.getElementById("prevPageBtn");
  const nextBtn = document.getElementById("nextPageBtn");
  const pageInfo = document.getElementById("pageInfo");

  if (totalPages <= 1) {
    controls.classList.add("d-none");
    return;
  }

  controls.classList.remove("d-none");
  prevBtn.disabled = currentPage <= 1;
  nextBtn.disabled = currentPage >= totalPages;
  pageInfo.textContent = `Page ${currentPage} / ${totalPages}`;
}

// Render available vouchers table (from current page data)
function renderAvailableVouchers() {
  const tbody = document.getElementById("availableVoucherBody");
  tbody.innerHTML = "";

  // Filter out vouchers already selected
  const selectedIds = selectedVouchers.map((v) => v.id);
  const available = allVouchers.filter((v) => !selectedIds.includes(v.id));

  if (available.length === 0) {
    // Show "no results" message based on whether we're searching or not
    let msg = "No verified vouchers available";
    if (currentSearchTerm) {
      msg = `<i class="bi bi-search"></i> No results found for "<strong>${currentSearchTerm}</strong>"<br><small class="text-muted mt-2 d-block">Try: full voucher code (si-1271-001), beneficiary code, name, or sequence #</small>`;
    }
    tbody.innerHTML =
      `<tr><td colspan="6" class="text-center text-muted py-3">${msg}</td></tr>`;
    document.getElementById("availableCount").textContent = currentSearchTerm ? "0 matches" : "0 available";
    return;
  }

  available.forEach((voucher) => {
    const row = document.createElement("tr");

    const beneficiaryCode = voucher.beneficiary_code || "N/A";
    const sequenceNumber = String(voucher.voucher_number).padStart(3, "0");
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

    const verifiedIcon = isVerified
      ? '<i class="bi bi-check-circle-fill text-success"></i>'
      : '<i class="bi bi-x-circle-fill text-danger"></i>';
    const redeemedIcon = isRedeemed
      ? '<i class="bi bi-check-circle-fill text-success"></i>'
      : '<i class="bi bi-circle text-muted"></i>';

    // Show batch number for redeemed vouchers
    let batchHtml = '';
    if (isRedeemed && voucher.batch_number) {
      batchHtml = `<span class="badge bg-secondary" title="Batch #${voucher.batch_number}">${voucher.batch_number}</span>`;
    } else if (isRedeemed) {
      batchHtml = `<span class="badge bg-secondary">Redeemed</span>`;
    }

    row.innerHTML = `
        <td><strong style="font-size: 1.1em;">${voucherCodeHtml}</strong></td>
        <td><small>${voucher.beneficiary_name || voucher.claimant_name}</small></td>
        <td class="text-center">${verifiedIcon}</td>
        <td class="text-center">${redeemedIcon}</td>
        <td class="text-center"><small>${batchHtml}</small></td>
        <td>${buttonHtml}</td>
    `;
    tbody.appendChild(row);
  });

  const totalAvailable = currentSearchTerm ? '?' : available.length;
  document.getElementById("availableCount").textContent =
    `${available.length} available`;
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

    const beneficiaryCode = voucher.beneficiary_code || "N/A";
    const sequenceNumber = String(voucher.voucher_number).padStart(3, "0");
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

    // Update the specific row in the available table to "selected" state (no full re-render)
    const tbody = document.getElementById("availableVoucherBody");
    const rows = tbody.querySelectorAll("tr");

    for (let i = 0; i < rows.length; i++) {
      const btn = rows[i].querySelector(
        `button[onclick="selectVoucher(${voucherId})"]`,
      );
      if (btn) {
        rows[i].classList.add("table-success");
        btn.outerHTML = `<button class="btn btn-sm btn-success" disabled title="Already selected">
                    <i class="bi bi-check-circle"></i>
                </button>`;
        break;
      }
    }

    renderSelectedVouchers();
    updateTotals();
    saveDraftToStorage();
  }
}

// Remove a voucher (move back to available list)
function removeVoucher(voucherId) {
  selectedVouchers = selectedVouchers.filter((v) => v.id !== voucherId);

  // Re-fetch current page to show the newly available voucher in correct position
  if (currentVendor && currentSearchTerm) {
    loadVouchers(currentVendor.id, currentPage, currentSearchTerm);
  } else {
    showVoucherSearchPrompt();
    renderSelectedVouchers();
    updateTotals();
  }
  saveDraftToStorage();
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

  const hasSelection = selectedVouchers.length > 0;
  document.getElementById("redeemBtn").disabled = !hasSelection;
  document.getElementById("saveDraftBtn").disabled = !hasSelection;
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
          document.getElementById("voucherSearch").value = '';
          document.getElementById("voucherSearch").classList.remove('is-active');
          currentSearchTerm = '';
          currentPage = 1;
          if (currentVendor) {
            // Show search prompt after successful redemption (search-first pattern)
            showVoucherSearchPrompt();
          } else {
            renderAvailableVouchers();
            renderSelectedVouchers();
            updateTotals();
          }
          clearDraftFromStorage();
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
