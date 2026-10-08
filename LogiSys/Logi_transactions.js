// Searchable item input functionality
document.addEventListener("DOMContentLoaded", function () {
  // Check if inventory data is available
  if (typeof window.inventoryItems === "undefined") {
    console.error("Inventory items data not loaded");
    return;
  }

  const searchInput = document.getElementById("stockInItemSearch");
  const dropdown = document.getElementById("stockInItemDropdown");
  const selectedItemName = document.getElementById("selectedItemName");
  const selectedItemNo = document.getElementById("selectedItemNo");
  const selectedCurrentBalance = document.getElementById(
    "selectedCurrentBalance"
  );
  const selectedItemIndicator = document.getElementById(
    "selectedItemIndicator"
  );
  const displaySelectedItem = document.getElementById("displaySelectedItem");
  const clearItemSelection = document.getElementById("clearItemSelection");
  const stockInForm = document.getElementById("stockInForm");

  let isItemSelected = false;
  let selectedItem = null;

  // Search input event listener
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      const searchTerm = this.value.toLowerCase().trim(); 

      // Reset selection if user types after selecting
      if (isItemSelected && this.value !== selectedItem.item_name) {
        clearSelection();
      }

      if (searchTerm.length === 0) {
        hideDropdown();
        return;
      }

      // Filter items based on search term
      const filteredItems = window.inventoryItems.filter(
        (item) =>
          item.item_name.toLowerCase().includes(searchTerm) ||
          item.item_no.toLowerCase().includes(searchTerm)
      );

      // Show suggestions
      showSuggestions(filteredItems, searchTerm);
    });
  }

  // Show suggestions dropdown
  function showSuggestions(items, searchTerm) {
    if (!dropdown) return;

    dropdown.innerHTML = "";

    if (items.length === 0) {
      dropdown.innerHTML = `
        <div class="p-3 text-center text-muted">
          <i class="fas fa-search"></i>
          <div>No items found for "${searchTerm}"</div>
        </div>
      `;
      dropdown.style.display = "block";
      return;
    }

    // Limit to first 10 results for performance
    const limitedItems = items.slice(0, 10);

    limitedItems.forEach((item) => {
      const suggestionDiv = document.createElement("div");
      suggestionDiv.className =
        "suggestion-item p-3 border-bottom cursor-pointer";
      suggestionDiv.style.cursor = "pointer";

      // Highlight matching text
      const highlightedName = highlightMatch(item.item_name, searchTerm);
      const highlightedItemNo = highlightMatch(item.item_no, searchTerm);

      suggestionDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
          <div class="flex-grow-1">
            <div class="fw-bold">${highlightedName}</div>
            <small class="text-muted">Item No: ${highlightedItemNo}</small>
          </div>
          <div class="text-end">
            <span class="badge bg-info">${item.current_balance}</span>
            <div><small class="text-muted">${item.unit}</small></div>
          </div>
        </div>
      `;

      // Add click event
      suggestionDiv.addEventListener("click", function (e) {
        e.preventDefault();
        selectItem(item);
      });

      // Add hover effects
      suggestionDiv.addEventListener("mouseenter", function () {
        this.style.backgroundColor = "#f8f9fa";
      });
      suggestionDiv.addEventListener("mouseleave", function () {
        this.style.backgroundColor = "white";
      });

      dropdown.appendChild(suggestionDiv);
    });

    // Show more results indicator if there are more items
    if (items.length > 10) {
      const moreDiv = document.createElement("div");
      moreDiv.className = "p-2 text-center text-muted bg-light";
      moreDiv.innerHTML = `<small>... and ${
        items.length - 10
      } more results</small>`;
      dropdown.appendChild(moreDiv);
    }

    dropdown.style.display = "block";
  }

  // Highlight matching text
  function highlightMatch(text, searchTerm) {
    if (!searchTerm) return text;
    const regex = new RegExp(`(${searchTerm})`, "gi");
    return text.replace(regex, '<mark class="bg-warning">$1</mark>');
  }

  // Select an item
  function selectItem(item) {
    selectedItem = item;
    isItemSelected = true;

    // Update input field
    searchInput.value = item.item_name;

    // Update hidden fields
    document.getElementById("selectedItemNameInput").value = item.item_name;
    document.getElementById("selectedItemNo").value = item.item_no;
    if (selectedCurrentBalance) selectedCurrentBalance.value = item.current_balance;

    // Show selection indicator
    if (displaySelectedItem) {
      displaySelectedItem.innerHTML = `
        <strong>${item.item_name}</strong> 
        <span class="badge bg-info ms-2">${item.current_balance} ${item.unit}</span>
      `;
    }
    if (selectedItemIndicator) selectedItemIndicator.style.display = "block";

    // Hide dropdown
    hideDropdown();

    // Update summary
    updateSummaryDisplay(item);

    // Remove required validation error if exists
    searchInput.setCustomValidity("");

    // After updateSummaryDisplay(item); in selectItem
    updateNewBalance();

    const quantityInput = document.getElementById('stockInQuantity');
    if (quantityInput) {
      quantityInput.addEventListener('input', updateNewBalance);
    }
  }

  // Clear selection
  function clearSelection() {
    isItemSelected = false;
    selectedItem = null;

    // Clear hidden fields
    if (selectedItemName) selectedItemName.value = "";
    if (selectedItemNo) selectedItemNo.value = "";
    if (selectedCurrentBalance) selectedCurrentBalance.value = "";

    const previousBalance = document.getElementById("previousBalance");
    if (previousBalance) previousBalance.value = "";

    // Clear input field
    if (searchInput) searchInput.value = "";

    // Hide selection indicator
    if (selectedItemIndicator) selectedItemIndicator.style.display = "none";

    // Reset summary display
    resetSummary();

    // Clear quantity input
    const quantityInput = document.getElementById("stockInQuantity");
    if (quantityInput) quantityInput.value = "";

    // Focus back on search input
    if (searchInput) searchInput.focus();
  }

  // Clear button event
  if (clearItemSelection) {
    clearItemSelection.addEventListener("click", function (e) {
      e.preventDefault();
      clearSelection();
    });
  }

  // Hide dropdown
  function hideDropdown() {
    if (dropdown) dropdown.style.display = "none";
  }

  // Hide dropdown when clicking outside
  document.addEventListener("click", function (e) {
    if (
      searchInput &&
      dropdown &&
      !searchInput.contains(e.target) &&
      !dropdown.contains(e.target)
    ) {
      hideDropdown();
    }
  });

  // Handle keyboard navigation
  if (searchInput) {
    searchInput.addEventListener("keydown", function (e) {
      const suggestions = dropdown.querySelectorAll(".suggestion-item");
      if (e.key === "ArrowDown") {
        e.preventDefault();
        if (suggestions.length > 0) {
          suggestions[0].focus();
        }
      } else if (e.key === "Escape") {
        hideDropdown();
      }
    });
  }

  // Form validation
  if (searchInput) {
    searchInput.addEventListener("invalid", function () {
      if (!isItemSelected) {
        this.setCustomValidity("Please select an item from the suggestions");
      }
    });

    searchInput.addEventListener("input", function () {
      if (isItemSelected) {
        this.setCustomValidity("");
      }
    });
  }

});

// Helper functions for summary update
function updateSummaryDisplay(item) {
  const selectedItemNameSummary = document.getElementById(
    "selectedItemNameSummary"
  );
  const currentBalanceSummary = document.getElementById(
    "currentBalanceSummary"
  );
  const previousBalance = document.getElementById("previousBalance");
  const addQuantity = document.getElementById("addQuantity");
  const newBalance = document.getElementById("newBalance");

  if (selectedItemNameSummary)
    selectedItemNameSummary.textContent = item.item_name;
  if (currentBalanceSummary)
    currentBalanceSummary.textContent = item.current_balance;
  if (previousBalance) previousBalance.value = item.current_balance;

  // Reset quantity and new balance when item changes
  if (addQuantity) addQuantity.textContent = "0";
  if (newBalance) newBalance.textContent = item.current_balance;

  // Update new balance calculation when quantity changes
  const quantityInput = document.getElementById("stockInQuantity");
  if (quantityInput) {
    // Remove existing event listeners by cloning
    const newQuantityInput = quantityInput.cloneNode(true);
    quantityInput.parentNode.replaceChild(newQuantityInput, quantityInput);

    // Add new event listener
    newQuantityInput.addEventListener("input", function () {
      const quantity = parseInt(this.value) || 0;
      const currentBalance = parseInt(item.current_balance) || 0;
      const calculatedNewBalance = currentBalance + quantity;

      if (addQuantity) addQuantity.textContent = quantity;
      if (newBalance) newBalance.textContent = calculatedNewBalance;
    });
  }
}

function resetSummary() {
  const selectedItemNameSummary = document.getElementById(
    "selectedItemNameSummary"
  );
  const currentBalanceSummary = document.getElementById(
    "currentBalanceSummary"
  );
  const addQuantity = document.getElementById("addQuantity");
  const newBalance = document.getElementById("newBalance");
  const previousBalance = document.getElementById("previousBalance");

  if (selectedItemNameSummary)
    selectedItemNameSummary.textContent = "None selected";
  if (currentBalanceSummary) currentBalanceSummary.textContent = "0";
  if (addQuantity) addQuantity.textContent = "0";
  if (newBalance) newBalance.textContent = "0";
  if (previousBalance) previousBalance.value = "";
}

// Stock Out Modal Functionality
document.addEventListener("DOMContentLoaded", function () {
  // Get DOM elements
  const stockOutItemSearch = document.getElementById("stockOutItemSearch");
  const stockOutItemDropdown = document.getElementById("stockOutItemDropdown");
  const stockOutSelectedItemIndicator = document.getElementById(
    "stockOutSelectedItemIndicator"
  );
  const stockOutDisplaySelectedItem = document.getElementById(
    "stockOutDisplaySelectedItem"
  );
  const stockOutClearItemSelection = document.getElementById(
    "stockOutClearItemSelection"
  );
  const stockOutQuantity = document.getElementById("stockOutQuantity");
  const stockOutQuantityError = document.getElementById(
    "stockOutQuantityError"
  );
  const stockOutReason = document.getElementById("stockOutReason");
  const stockOutCustomReasonDiv = document.getElementById(
    "stockOutCustomReasonDiv"
  );
  const stockOutCustomReason = document.getElementById("stockOutCustomReason");
  const stockOutForm = document.getElementById("stockOutForm");
  const stockOutModal = document.getElementById("stockOutModal");

  // Summary elements
  const stockOutSelectedItemNameSummary = document.getElementById(
    "stockOutSelectedItemNameSummary"
  );
  const stockOutCurrentBalanceSummary = document.getElementById(
    "stockOutCurrentBalanceSummary"
  );
  const stockOutRemoveQuantity = document.getElementById(
    "stockOutRemoveQuantity"
  );
  const stockOutNewBalance = document.getElementById("stockOutNewBalance");
  const stockOutWarning = document.getElementById("stockOutWarning");

  // Hidden input elements
  const stockOutSelectedItemName = document.getElementById(
    "stockOutSelectedItemName"
  );
  const stockOutSelectedItemNo = document.getElementById(
    "stockOutSelectedItemNo"
  );
  const stockOutSelectedCurrentBalance = document.getElementById(
    "stockOutSelectedCurrentBalance"
  );
  const stockOutPreviousBalance = document.getElementById(
    "stockOutPreviousBalance"
  );
  const stockOutCalculatedNewBalance = document.getElementById(
    "stockOutCalculatedNewBalance"
  );

  // Item search functionality
  stockOutItemSearch.addEventListener("input", function () {
    const searchTerm = this.value.toLowerCase();
    if (searchTerm.length < 1) {
      stockOutItemDropdown.style.display = "none";
      return;
    }

    const filteredItems = window.inventoryItems.filter(
      (item) =>
        item.item_name.toLowerCase().includes(searchTerm) ||
        item.item_no.toLowerCase().includes(searchTerm)
    );

    if (filteredItems.length > 0) {
      stockOutItemDropdown.innerHTML = filteredItems
        .map(
          (item) => `
                <div class="p-2 border-bottom item-option" 
                     data-item-no="${item.item_no}"
                     data-item-name="${item.item_name}"
                     data-current-balance="${item.current_balance}"
                     style="cursor: pointer;">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>${item.item_name}</strong>
                            <br>
                            <small class="text-muted">Item No: ${item.item_no}</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-info">Balance: ${item.current_balance}</span>
                        </div>
                    </div>
                </div>
            `
        )
        .join("");
      stockOutItemDropdown.style.display = "block";
    } else {
      stockOutItemDropdown.style.display = "none";
    }
  });

  // Handle item selection from dropdown
  stockOutItemDropdown.addEventListener("click", function (e) {
    const itemOption = e.target.closest(".item-option");
    if (itemOption) {
      const itemNo = itemOption.dataset.itemNo;
      const itemName = itemOption.dataset.itemName;
      const currentBalance = itemOption.dataset.currentBalance;

      // Update hidden inputs
      stockOutSelectedItemName.value = itemName;
      stockOutSelectedItemNo.value = itemNo;
      stockOutSelectedCurrentBalance.value = currentBalance;
      stockOutPreviousBalance.value = currentBalance;

      // Update display
      stockOutItemSearch.value = itemName;
      stockOutDisplaySelectedItem.textContent = `${itemName} (${itemNo})`;
      stockOutSelectedItemIndicator.style.display = "block";
      stockOutItemDropdown.style.display = "none";

      // Update summary
      stockOutSelectedItemNameSummary.textContent = itemName;
      stockOutCurrentBalanceSummary.textContent = currentBalance;
      updateStockOutSummary();
    }
  });

  // Clear item selection
  stockOutClearItemSelection.addEventListener("click", function () {
    stockOutItemSearch.value = "";
    stockOutSelectedItemName.value = "";
    stockOutSelectedItemNo.value = "";
    stockOutSelectedCurrentBalance.value = "";
    stockOutPreviousBalance.value = "";
    stockOutSelectedItemIndicator.style.display = "none";
    stockOutSelectedItemNameSummary.textContent = "None selected";
    stockOutCurrentBalanceSummary.textContent = "0";
    stockOutRemoveQuantity.textContent = "0";
    stockOutNewBalance.textContent = "0";
    stockOutWarning.style.display = "none";
    stockOutQuantity.value = "";
  });

  // Handle quantity input
  stockOutQuantity.addEventListener("input", function () {
    updateStockOutSummary();
  });

  // Update stock out summary
  function updateStockOutSummary() {
    const currentBalance = parseInt(stockOutSelectedCurrentBalance.value) || 0;
    const quantityToRemove = parseInt(stockOutQuantity.value) || 0;
    const newBalance = currentBalance - quantityToRemove;

    stockOutRemoveQuantity.textContent = quantityToRemove;
    stockOutNewBalance.textContent = newBalance;
    stockOutCalculatedNewBalance.value = newBalance;

    // Show warning if stock will be low or out
    if (newBalance <= 0) {
      stockOutQuantityError.style.display = "block";
      stockOutWarning.style.display = "block";
    } else if (newBalance <= 5) {
      stockOutQuantityError.style.display = "none";
      stockOutWarning.style.display = "block";
    } else {
      stockOutQuantityError.style.display = "none";
      stockOutWarning.style.display = "none";
    }
  }

  // Handle reason selection
  stockOutReason.addEventListener("change", function () {
    if (this.value === "Other") {
      stockOutCustomReasonDiv.style.display = "block";
      stockOutCustomReason.required = true;
    } else {
      stockOutCustomReasonDiv.style.display = "none";
      stockOutCustomReason.required = false;
    }
  });



  // Close dropdown when clicking outside
  document.addEventListener("click", function (e) {
    if (
      !stockOutItemSearch.contains(e.target) &&
      !stockOutItemDropdown.contains(e.target)
    ) {
      stockOutItemDropdown.style.display = "none";
    }
  });
});

// Handle reason selection for custom reason
const stockInReason = document.getElementById("stockInReason");
if (stockInReason) {
  stockInReason.addEventListener("change", function () {
    const customReasonDiv = document.getElementById("customReasonDiv");
    const customReasonInput = document.getElementById("customReason");
    if (this.value === "Other") {
      customReasonDiv.style.display = "block";
      customReasonInput.required = true;
    } else {
      customReasonDiv.style.display = "none";
      customReasonInput.required = false;
    }
  });
}

function updateNewBalance() {
  const quantity = parseInt(document.getElementById('stockInQuantity').value) || 0;
  const currentBalance = parseInt(document.getElementById('selectedCurrentBalance').value) || 0;
  const newBalance = currentBalance + quantity;
  document.getElementById('calculatedNewBalance').value = newBalance;
  // Optionally update the summary display too
  const newBalanceDisplay = document.getElementById('newBalance');
  if (newBalanceDisplay) newBalanceDisplay.textContent = newBalance;
}
function updateCachedInventoryBalance(itemNo, newBalance) {
  if (!itemNo || typeof window.inventoryItems === "undefined") return;

  const item = window.inventoryItems.find((inventoryItem) => inventoryItem.item_no === itemNo);
  if (item) {
    item.current_balance = parseInt(newBalance, 10) || 0;
  }
}
// Stock In form submission handler
const submitStockInBtn = document.getElementById('submitStockIn');
if (submitStockInBtn) {
  submitStockInBtn.addEventListener('click', function(e) {
    
    // Collect all required fields
    const itemNo = document.getElementById('selectedItemNo').value;
    const itemName = document.getElementById('selectedItemNameInput').value;
    const quantity = document.getElementById('stockInQuantity').value;
    const reasonSelect = document.getElementById('stockInReason');
    let reason = reasonSelect.value;
    if (reason === 'Other') {
      const customReason = document.getElementById('customReason').value;
      if (!customReason) {
        alert('Please enter a custom reason');
        return;
      }
      reason = customReason;
    }
    let transactionDate = document.getElementById('stockInDate').value;
    if (!transactionDate) {
      // Set default to today's date if no date selected
      const today = new Date();
      transactionDate = today.toISOString().split('T')[0];
    }
    const previousBalance = document.getElementById('previousBalance').value;
    const newBalance = document.getElementById('calculatedNewBalance').value;

    // Validate required fields
    if (!itemNo || !itemName || !quantity || !reason || !transactionDate || !previousBalance || !newBalance) {
      alert('Please fill in all required fields');
      return;
    }

    // Show loading state
    const submitButton = this;
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Prepare form data
    const formData = new FormData();
    formData.append('itemNo', itemNo);
    formData.append('itemName', itemName);
    formData.append('quantity', quantity);
    formData.append('reason', reason);
    formData.append('transaction_date', transactionDate);
    formData.append('previous_balance', previousBalance);
    formData.append('new_balance', newBalance);
    formData.append('transaction_type', 'Stock In');

    // Send AJAX request
    fetch('Logi_stock_in.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.text().then(text => {
        try {
          return JSON.parse(text);
        } catch (e) {
          throw new Error('Invalid JSON response: ' + text);
        }
      });
    })
    .then(data => {
      if (data.success) {
        updateCachedInventoryBalance(itemNo, data.new_balance);

        // Show success message using Bootstrap modal
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        document.getElementById('successMessage').textContent = data.message;
        successModal.show();

        // Reset form and clear selection
        stockInForm.reset();
        if (typeof clearSelection === 'function') clearSelection();
        if (typeof resetSummary === 'function') resetSummary();

        // Refresh the paginated table after the user sees the success feedback.
        setTimeout(() => {
          if (typeof window.refreshTransactionsTable === 'function') {
            window.refreshTransactionsTable();
          } else {
            location.reload();
          }
        }, 1500);
      } else {
        // Show error message using Bootstrap modal
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        document.getElementById('errorMessage').textContent = data.message || 'An error occurred while processing your request.';
        errorModal.show();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      // Show error message using Bootstrap modal
      const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
      document.getElementById('errorMessage').textContent = error.message || 'An error occurred while processing your request.';
      errorModal.show();
    })
    .finally(() => {
      // Reset button state
      submitButton.disabled = false;
      submitButton.innerHTML = originalButtonText;
    });
  });
}

// Stock Out form submission handler
const stockOutForm = document.getElementById('stockOutForm');
if (stockOutForm) {
  stockOutForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Collect all required fields
    const itemNo = document.getElementById('stockOutSelectedItemNo')?.value;
    const itemName = document.getElementById('stockOutSelectedItemName')?.value;
    const quantity = document.getElementById('stockOutQuantity')?.value;
    const reasonSelect = document.getElementById('stockOutReason');
    const requestorName = document.getElementById('stockOutRequestor')?.value;
    const previousBalance = document.getElementById('stockOutPreviousBalance')?.value;
    const newBalance = document.getElementById('stockOutCalculatedNewBalance')?.value;

    // Validate required fields
    if (!itemNo || !itemName || !quantity || !reasonSelect?.value || !requestorName || !previousBalance || !newBalance) {
      alert('Please fill in all required fields');
      return;
    }

    let reason = reasonSelect.value;
    if (reason === 'Other') {
      const customReason = document.getElementById('stockOutCustomReason')?.value;
      if (!customReason) {
        alert('Please enter a custom reason');
        return;
      }
      reason = customReason;
    }

    // Show loading state
    const submitButton = this.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Create form data
    const formData = new FormData();
    formData.append('itemNo', itemNo);
    formData.append('itemName', itemName);
    formData.append('quantity', quantity);
    formData.append('reason', reason);
    formData.append('previous_balance', previousBalance);
    formData.append('new_balance', newBalance);
    formData.append('requestor_name', requestorName);
    formData.append('transaction_type', 'DEDUCTION');

    // Send AJAX request
    fetch('Logi_stock_out.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.text().then(text => {
        try {
          return JSON.parse(text);
        } catch (e) {
          console.error('Server response:', text);
          throw new Error('Invalid JSON response from server');
        }
      });
    })
    .then(data => {
      if (data.success) {
        updateCachedInventoryBalance(itemNo, data.new_balance);

        // Show success message using Bootstrap modal
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        document.getElementById('successMessage').textContent = data.message;
        successModal.show();

        // Reset form and clear selection
        stockOutForm.reset();
        document.getElementById('stockOutSelectedItemIndicator').style.display = 'none';
        document.getElementById('stockOutCustomReasonDiv').style.display = 'none';
        
        // Reset summary display
        document.getElementById('stockOutSelectedItemNameSummary').textContent = 'None selected';
        document.getElementById('stockOutCurrentBalanceSummary').textContent = '0';
        document.getElementById('stockOutRemoveQuantity').textContent = '0';
        document.getElementById('stockOutNewBalance').textContent = '0';
        document.getElementById('stockOutWarning').style.display = 'none';

        // Close the stock out modal
        const stockOutModal = bootstrap.Modal.getInstance(document.getElementById('stockOutModal'));
        if (stockOutModal) {
          stockOutModal.hide();
        }

        // Refresh the paginated table after the user sees the success feedback.
        setTimeout(() => {
          if (typeof window.refreshTransactionsTable === 'function') {
            window.refreshTransactionsTable();
          } else {
            location.reload();
          }
        }, 1500);
      } else {
        // Show error message using Bootstrap modal
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        document.getElementById('errorMessage').textContent = data.message || 'An error occurred during stock out';
        errorModal.show();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      // Show error message using Bootstrap modal
      const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
      document.getElementById('errorMessage').textContent = error.message || 'An error occurred while processing your request';
      errorModal.show();
    })
    .finally(() => {
      // Reset button state
      submitButton.disabled = false;
      submitButton.innerHTML = originalButtonText;
    });
  });
}

// Transaction table pagination and server-side filtering
document.addEventListener('DOMContentLoaded', function() {
  const tableBody = document.getElementById('transactionsTableBody');
  const searchInput = document.getElementById('transactionSearchInput');
  const typeSelect = document.getElementById('transactionType');
  const dateFromInput = document.getElementById('dateFrom');
  const dateToInput = document.getElementById('dateTo');
  const perPageSelect = document.getElementById('transactionsPerPage');
  const pageInfo = document.getElementById('transactionsPageInfo');
  const prevButton = document.getElementById('transactionsPrevPage');
  const nextButton = document.getElementById('transactionsNextPage');
  const exportButton = document.getElementById('exportBtn');

  if (!tableBody) return;

  let currentPage = 1;
  let totalPages = 1;
  let totalRows = 0;
  let searchTimer = null;
  let lastRows = [];

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getFilters() {
    return {
      search: searchInput?.value.trim() || '',
      type: typeSelect?.value || '',
      date_from: dateFromInput?.value || '',
      date_to: dateToInput?.value || '',
      per_page: perPageSelect?.value || '25',
    };
  }

  function setLoadingState() {
    tableBody.innerHTML = `
      <tr>
        <td colspan="9" class="text-center py-4 text-muted">
          <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
          Loading transactions...
        </td>
      </tr>
    `;
    if (pageInfo) pageInfo.textContent = 'Loading transactions...';
    if (prevButton) prevButton.disabled = true;
    if (nextButton) nextButton.disabled = true;
  }

  function getSignedQuantity(row) {
    const quantity = Math.abs(parseInt(row.quantity, 10) || 0);
    const previousBalance = parseInt(row.previous_balance, 10) || 0;
    const newBalance = parseInt(row.new_balance, 10) || 0;
    return newBalance > previousBalance ? `+${quantity}` : `-${quantity}`;
  }

  function getTransactionBadge(row) {
    const previousBalance = parseInt(row.previous_balance, 10) || 0;
    const newBalance = parseInt(row.new_balance, 10) || 0;
    const isAddition = newBalance > previousBalance;
    return {
      className: isAddition ? 'bg-success' : 'bg-danger',
      icon: isAddition ? 'fas fa-plus' : 'fas fa-minus',
    };
  }

  function renderRows(rows) {
    lastRows = rows;
    if (!rows.length) {
      tableBody.innerHTML = `
        <tr id="noTransactionsRow">
          <td colspan="9" class="text-center">
            <div class="py-4">
              <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
              <h5 class="text-muted">No transactions found</h5>
              <p class="text-muted mb-0">Try adjusting the search, date range, or transaction type.</p>
            </div>
          </td>
        </tr>
      `;
      return;
    }

    tableBody.innerHTML = rows.map((row) => {
      const badge = getTransactionBadge(row);
      return `
        <tr>
          <td>${escapeHtml(row.created_at)}</td>
          <td>${escapeHtml(row.item_name)}</td>
          <td>${escapeHtml(getSignedQuantity(row))}</td>
          <td>${escapeHtml(row.previous_balance)}</td>
          <td>${escapeHtml(row.new_balance)}</td>
          <td>${escapeHtml(row.reason)}</td>
          <td>${escapeHtml(row.requestor)}</td>
          <td>
            <span class="badge ${badge.className}">
              <i class="${badge.icon}"></i> ${escapeHtml(row.transaction_type)}
            </span>
          </td>
          <td>
            <button class="btn btn-warning btn-sm undo-transaction-btn" data-transaction-id="${escapeHtml(row.id)}">
              <i class="fas fa-undo"></i> Undo
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  function updatePagination(data) {
    currentPage = data.page;
    totalPages = data.total_pages;
    totalRows = data.total;

    if (pageInfo) {
      const perPage = parseInt(data.per_page, 10) || 25;
      const start = totalRows === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
      const end = Math.min(currentPage * perPage, totalRows);
      pageInfo.textContent = `${start}-${end} of ${totalRows} transactions | Page ${currentPage} of ${totalPages}`;
    }

    if (prevButton) prevButton.disabled = currentPage <= 1;
    if (nextButton) nextButton.disabled = currentPage >= totalPages;
  }

  function fetchTransactions(page = 1) {
    currentPage = page;
    const filters = getFilters();
    const params = new URLSearchParams({
      page: String(currentPage),
      per_page: filters.per_page,
      search: filters.search,
      type: filters.type,
      date_from: filters.date_from,
      date_to: filters.date_to,
    });

    setLoadingState();

    fetch(`Logi_fetch_transactions.php?${params.toString()}`)
      .then((response) => {
        if (!response.ok) throw new Error('Failed to load transactions');
        return response.json();
      })
      .then((data) => {
        if (!data.success) throw new Error(data.message || 'Failed to load transactions');
        renderRows(data.rows || []);
        updatePagination(data);
      })
      .catch((error) => {
        tableBody.innerHTML = `
          <tr>
            <td colspan="9" class="text-center py-4 text-danger">
              <i class="fas fa-exclamation-triangle me-1"></i>
              ${escapeHtml(error.message)}
            </td>
          </tr>
        `;
        if (pageInfo) pageInfo.textContent = 'Unable to load transactions.';
      });
  }

  function resetAndFetch() {
    fetchTransactions(1);
  }

  window.refreshTransactionsTable = function() {
    fetchTransactions(currentPage);
  };

  searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(resetAndFetch, 300);
  });
  typeSelect?.addEventListener('change', resetAndFetch);
  dateFromInput?.addEventListener('change', resetAndFetch);
  dateToInput?.addEventListener('change', resetAndFetch);
  perPageSelect?.addEventListener('change', resetAndFetch);

  prevButton?.addEventListener('click', function() {
    if (currentPage > 1) fetchTransactions(currentPage - 1);
  });

  nextButton?.addEventListener('click', function() {
    if (currentPage < totalPages) fetchTransactions(currentPage + 1);
  });

  tableBody.addEventListener('click', function(event) {
    const button = event.target.closest('.undo-transaction-btn');
    if (!button) return;

    const transactionId = button.getAttribute('data-transaction-id');
    if (!transactionId || !confirm('Are you sure you want to undo this transaction?')) return;

    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Undoing...';

    fetch('Logi_undo_transaction.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'transaction_id=' + encodeURIComponent(transactionId)
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          alert('Transaction undone successfully!');
          fetchTransactions(currentPage);
        } else {
          alert('Failed to undo transaction: ' + (data.message || 'Unknown error'));
          fetchTransactions(currentPage);
        }
      })
      .catch((error) => {
        alert('Error: ' + error.message);
        fetchTransactions(currentPage);
      });
  });

  exportButton?.addEventListener('click', function() {
    if (!lastRows.length) {
      alert('No transactions to export on this page.');
      return;
    }

    const headers = ['Date & Time', 'Item Name', 'Quantity', 'Previous Balance', 'New Balance', 'Reason', 'Requestor', 'Transaction Type'];
    const csvRows = [headers].concat(lastRows.map((row) => [
      row.created_at,
      row.item_name,
      getSignedQuantity(row),
      row.previous_balance,
      row.new_balance,
      row.reason,
      row.requestor,
      row.transaction_type,
    ]));
    const csv = csvRows.map((row) => row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'transactions-page.csv';
    link.click();
    URL.revokeObjectURL(url);
  });

  fetchTransactions(1);
});

// Edit Quantity Modal functionality
document.addEventListener('DOMContentLoaded', function () {
  const editQuantityModal = document.getElementById('editQuantityModal');
  
  if (editQuantityModal) {
    // Populate modal when edit button is clicked
    editQuantityModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget; // Button that triggered the modal
      
      // Extract info from data-* attributes
      const requestId = button.getAttribute('data-id');
      const itemName = button.getAttribute('data-item-name');
      const officeName = button.getAttribute('data-office-name');
      const approvedQuantity = button.getAttribute('data-approved-quantity');
      
      // Update modal fields
      document.getElementById('editRequestId').value = requestId;
      document.getElementById('editItemName').textContent = itemName;
      document.getElementById('editOfficeName').textContent = officeName;
      document.getElementById('editApprovedQuantity').value = approvedQuantity;
    });

    // Handle form submission
    const editQuantityForm = document.getElementById('editQuantityForm');
    if (editQuantityForm) {
      editQuantityForm.addEventListener('submit', function (e) {
        e.preventDefault();
        
        const requestId = document.getElementById('editRequestId').value;
        const newQuantity = document.getElementById('editApprovedQuantity').value;
        
        // Validate
        if (!requestId || !newQuantity) {
          const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
          document.getElementById('errorMessage').textContent = 'Please enter a valid quantity.';
          errorModal.show();
          return;
        }
        
        // Show loading state
        const submitButton = document.getElementById('saveQuantityChange');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        // Prepare form data
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('approved_quantity', newQuantity);
        
        // Send AJAX request
        fetch('Logi_update_approved_quantity.php', {
          method: 'POST',
          body: formData
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.text().then(text => {
            try {
              return JSON.parse(text);
            } catch (e) {
              throw new Error('Invalid JSON response: ' + text);
            }
          });
        })
        .then(data => {
          if (data.success) {
            // Close edit modal
            const editModalInstance = bootstrap.Modal.getInstance(editQuantityModal);
            if (editModalInstance) {
              editModalInstance.hide();
            }
            
            // Show success modal
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('successMessage').textContent = data.message || 'Quantity updated successfully!';
            successModal.show();
            
            // Update the table cell directly
            const quantityCell = document.querySelector(`.approved-quantity-cell[data-id="${requestId}"]`);
            if (quantityCell) {
              quantityCell.textContent = newQuantity;
            }
            
            // Also update the edit button's data attribute
            const editBtn = document.querySelector(`.edit-quantity-btn[data-id="${requestId}"]`);
            if (editBtn) {
              editBtn.setAttribute('data-approved-quantity', newQuantity);
            }
            
            // Refresh the bulk table data from server (optional - for full refresh)
            // Uncomment the line below if you want to reload the page instead
            // location.reload();
          } else {
            // Show error modal
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            document.getElementById('errorMessage').textContent = data.message || 'Failed to update quantity.';
            errorModal.show();
          }
        })
        .catch(error => {
          console.error('Error:', error);
          const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
          document.getElementById('errorMessage').textContent = error.message || 'An error occurred while updating the quantity.';
          errorModal.show();
        })
        .finally(() => {
          // Reset button state
          submitButton.disabled = false;
          submitButton.innerHTML = originalButtonText;
        });
      });
    }
  }
});

// Change Item Modal functionality
document.addEventListener('DOMContentLoaded', function () {
  const changeItemModal = document.getElementById('changeItemModal');
  
  if (changeItemModal) {
    const changeNewItemSearch = document.getElementById('changeNewItemSearch');
    const changeItemDropdown = document.getElementById('changeItemDropdown');
    
    // Populate modal when change button is clicked
    changeItemModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      
      const requestId = button.getAttribute('data-id');
      const itemName = button.getAttribute('data-item-name');
      const officeName = button.getAttribute('data-office-name');
      const approvedQuantity = button.getAttribute('data-approved-quantity');
      
      document.getElementById('changeRequestId').value = requestId;
      document.getElementById('changeCurrentItemName').textContent = itemName;
      document.getElementById('changeOfficeName').textContent = officeName;
      document.getElementById('changeCurrentQuantity').textContent = approvedQuantity;
      
      // Reset search field
      if (changeNewItemSearch) changeNewItemSearch.value = '';
      document.getElementById('changeNewItemNo').value = '';
      document.getElementById('changeNewItemName').value = '';
      document.getElementById('changeApprovedQuantity').value = '';
    });

    // Item search functionality
    if (changeNewItemSearch) {
      changeNewItemSearch.addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase();
        if (searchTerm.length < 1) {
          changeItemDropdown.style.display = 'none';
          return;
        }

        const filteredItems = window.inventoryItems.filter(
          (item) =>
            item.item_name.toLowerCase().includes(searchTerm) ||
            item.item_no.toLowerCase().includes(searchTerm)
        );

        if (filteredItems.length > 0) {
          changeItemDropdown.innerHTML = filteredItems
            .map(
              (item) => `
                <div class="p-2 border-bottom item-option"
                     data-item-no="${item.item_no}"
                     data-item-name="${item.item_name}"
                     style="cursor: pointer;">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>${item.item_name}</strong>
                            <br>
                            <small class="text-muted">Item No: ${item.item_no}</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-info">Balance: ${item.current_balance}</span>
                        </div>
                    </div>
                </div>
            `).join('');
          changeItemDropdown.style.display = 'block';
        } else {
          changeItemDropdown.innerHTML = '<div class="p-2 text-muted">No items found</div>';
          changeItemDropdown.style.display = 'block';
        }
      });

      // Handle item selection
      changeItemDropdown.addEventListener('click', function (e) {
        const itemOption = e.target.closest('.item-option');
        if (itemOption) {
          const itemNo = itemOption.dataset.itemNo;
          const itemName = itemOption.dataset.itemName;
          
          document.getElementById('changeNewItemNo').value = itemNo;
          document.getElementById('changeNewItemName').value = itemName;
          changeNewItemSearch.value = itemName;
          changeItemDropdown.style.display = 'none';
        }
      });
    }

    // Handle form submission
    const changeItemForm = document.getElementById('changeItemForm');
    if (changeItemForm) {
      changeItemForm.addEventListener('submit', function (e) {
        e.preventDefault();
        
        const requestId = document.getElementById('changeRequestId').value;
        const newItemNo = document.getElementById('changeNewItemNo').value;
        const newItemName = document.getElementById('changeNewItemName').value;
        const approvedQuantity = document.getElementById('changeApprovedQuantity').value;
        
        if (!requestId || !newItemNo || !newItemName || !approvedQuantity) {
          const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
          document.getElementById('errorMessage').textContent = 'Please select a new item and enter approved quantity.';
          errorModal.show();
          return;
        }
        
        const submitButton = document.getElementById('saveItemChange');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('new_item_no', newItemNo);
        formData.append('new_item_name', newItemName);
        formData.append('approved_quantity', approvedQuantity);
        
        fetch('Logi_change_item.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const editModalInstance = bootstrap.Modal.getInstance(changeItemModal);
            if (editModalInstance) editModalInstance.hide();
            
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('successMessage').textContent = data.message;
            successModal.show();
            
            setTimeout(() => location.reload(), 1500);
          } else {
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            document.getElementById('errorMessage').textContent = data.message || 'Failed to change item.';
            errorModal.show();
          }
        })
        .catch(error => {
          const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
          document.getElementById('errorMessage').textContent = error.message || 'An error occurred.';
          errorModal.show();
        })
        .finally(() => {
          submitButton.disabled = false;
          submitButton.innerHTML = originalButtonText;
        });
      });
    }
  }
});

// Add Item Modal functionality
document.addEventListener('DOMContentLoaded', function () {
  const addItemModal = document.getElementById('addItemModal');
  
  if (addItemModal) {
    const addItemSearch = document.getElementById('addItemSearch');
    const addItemDropdown = document.getElementById('addItemDropdown');
    
    // Reset form when modal opens
    addItemModal.addEventListener('show.bs.modal', function () {
      document.getElementById('addItemForm').reset();
      document.getElementById('addItemNo').value = '';
      document.getElementById('addItemName').value = '';
      if (addItemSearch) addItemSearch.value = '';
    });

    // Item search functionality
    if (addItemSearch) {
      addItemSearch.addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase();
        if (searchTerm.length < 1) {
          addItemDropdown.style.display = 'none';
          return;
        }

        const filteredItems = window.inventoryItems.filter(
          (item) =>
            item.item_name.toLowerCase().includes(searchTerm) ||
            item.item_no.toLowerCase().includes(searchTerm)
        );

        if (filteredItems.length > 0) {
          addItemDropdown.innerHTML = filteredItems
            .map(
              (item) => `
                <div class="p-2 border-bottom item-option"
                     data-item-no="${item.item_no}"
                     data-item-name="${item.item_name}"
                     style="cursor: pointer;">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>${item.item_name}</strong>
                            <br>
                            <small class="text-muted">Item No: ${item.item_no}</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-info">Balance: ${item.current_balance}</span>
                        </div>
                    </div>
                </div>
            `).join('');
          addItemDropdown.style.display = 'block';
        } else {
          addItemDropdown.innerHTML = '<div class="p-2 text-muted">No items found</div>';
          addItemDropdown.style.display = 'block';
        }
      });

      // Handle item selection
      addItemDropdown.addEventListener('click', function (e) {
        const itemOption = e.target.closest('.item-option');
        if (itemOption) {
          const itemNo = itemOption.dataset.itemNo;
          const itemName = itemOption.dataset.itemName;
          
          document.getElementById('addItemNo').value = itemNo;
          document.getElementById('addItemName').value = itemName;
          addItemSearch.value = itemName;
          addItemDropdown.style.display = 'none';
        }
      });
    }

    // Handle form submission
    const addItemForm = document.getElementById('addItemForm');
    if (addItemForm) {
      addItemForm.addEventListener('submit', function (e) {
        e.preventDefault();
        
        const officeName = document.getElementById('addItemOfficeName').value;
        const itemNo = document.getElementById('addItemNo').value;
        const itemName = document.getElementById('addItemName').value;
        const approvedQuantity = document.getElementById('addApprovedQuantity').value;
        const dateRequested = document.getElementById('addDateRequested').value;
        
        if (!officeName || !itemNo || !itemName || !approvedQuantity || !dateRequested) {
          const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
          document.getElementById('errorMessage').textContent = 'Please fill in all required fields.';
          errorModal.show();
          return;
        }
        
        const submitButton = document.getElementById('saveNewItem');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        
        const formData = new FormData();
        formData.append('office_name', officeName);
        formData.append('item_no', itemNo);
        formData.append('item_name', itemName);
        formData.append('approved_quantity', approvedQuantity);
        formData.append('date_requested', dateRequested);
        formData.append('status', 'Approved');
        
        fetch('Logi_add_item_request.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const modalInstance = bootstrap.Modal.getInstance(addItemModal);
            if (modalInstance) modalInstance.hide();
            
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('successMessage').textContent = data.message;
            successModal.show();
            
            setTimeout(() => location.reload(), 1500);
          } else {
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            document.getElementById('errorMessage').textContent = data.message || 'Failed to add item.';
            errorModal.show();
          }
        })
        .catch(error => {
          const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
          document.getElementById('errorMessage').textContent = error.message || 'An error occurred.';
          errorModal.show();
        })
        .finally(() => {
          submitButton.disabled = false;
          submitButton.innerHTML = originalButtonText;
        });
      });
    }
  }
});

// Delete Item functionality
document.addEventListener('DOMContentLoaded', function () {
  const confirmDeleteModal = document.getElementById('confirmDeleteModal');
  
  if (confirmDeleteModal) {
    // Handle delete button clicks in the table using event delegation
    document.addEventListener('click', function(e) {
      if (e.target.closest('.delete-item-btn')) {
        const btn = e.target.closest('.delete-item-btn');
        const requestId = btn.getAttribute('data-id');
        document.getElementById('deleteRequestId').value = requestId;
        
        const deleteModal = new bootstrap.Modal(confirmDeleteModal);
        deleteModal.show();
      }
    });

    // Handle confirm delete
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener('click', function() {
        const requestId = document.getElementById('deleteRequestId').value;
        
        if (!requestId) {
          return;
        }
        
        const originalButtonText = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        
        const formData = new FormData();
        formData.append('request_id', requestId);
        
        fetch('Logi_delete_item_request.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const modalInstance = bootstrap.Modal.getInstance(confirmDeleteModal);
            if (modalInstance) modalInstance.hide();
            
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('successMessage').textContent = data.message;
            successModal.show();
            
            setTimeout(() => location.reload(), 1500);
          } else {
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            document.getElementById('errorMessage').textContent = data.message || 'Failed to delete item.';
            errorModal.show();
          }
        })
        .catch(error => {
          const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
          document.getElementById('errorMessage').textContent = error.message || 'An error occurred.';
          errorModal.show();
        })
        .finally(() => {
          this.disabled = false;
          this.innerHTML = originalButtonText;
        });
      });
    }
  }
});

// Bulk Transactions modal logic
document.addEventListener('DOMContentLoaded', function () {
  const bulkModalElement = document.getElementById('bulkTransactionModal');
  if (!bulkModalElement) return; // Exit if modal is not present on the page

  // Elements inside modal
  const bulkFilterOfficeSelect = document.getElementById('bulkFilterOffice');
  const bulkFilterDateInput = document.getElementById('bulkFilterDate');
  const bulkTableBody = document.getElementById('bulkTransactionsTableBody');
  const headerSelectAllCheckbox = document.getElementById('selectAllCheckbox');
  const selectAllBulkButton = document.getElementById('selectAllBulk');
  const deselectAllBulkButton = document.getElementById('deselectAllBulk');
  const selectedCountBadge = document.getElementById('selectedCount');
  const processBulkButton = document.getElementById('processBulkTransactions');

  // Helper: get all row checkboxes
  function getAllRowCheckboxes() {
    return Array.from(bulkTableBody.querySelectorAll('input.bulk-transaction-checkbox'));
  }

  // Helper: visibility check for row (by filters)
  function rowMatchesFilters(row) {
    const checkbox = row.querySelector('input.bulk-transaction-checkbox');
    if (!checkbox) return false;
    const selectedOffice = (bulkFilterOfficeSelect?.value || '').trim();
    const selectedDate = (bulkFilterDateInput?.value || '').trim();
    const rowOffice = (checkbox.dataset.officeName || '').trim();
    const rowDate = (checkbox.dataset.date || '').trim();

    const officeOk = !selectedOffice || rowOffice === selectedOffice;
    const dateOk = !selectedDate || rowDate === selectedDate;
    return officeOk && dateOk;
  }

  // Render rows helper
  function renderBulkRows(rows) {
    if (!bulkTableBody) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      bulkTableBody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center">
            <div class="py-4">
              <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
              <p class="text-muted">No requests found</p>
            </div>
          </td>
        </tr>`;
    } else {
      const html = rows
        .map((r) => {
          const safeId = String(r.id ?? '');
          const safeOffice = String(r.office_name ?? '');
          const safeItem = String(r.item_name ?? '');
          const safeQty = String(r.approved_quantity ?? '');
          const safeDate = String(r.date ?? '');
          const safeStatus = String(r.status ?? '');
          const safeItemId = String(r.item_id ?? '');
          const safeUploaded = String(r.uploaded ?? '');
          return `
            <tr>
              <td>
                <input type="checkbox" class="form-check-input bulk-transaction-checkbox"
                  value="${safeId}"
                  data-office-name="${safeOffice.replace(/"/g, '"')}"
                  data-date="${safeDate}"
                  data-item-id="${safeItemId}"
                  data-uploaded="${safeUploaded}">
              </td>
              <td>${safeDate}</td>
              <td>${safeOffice}</td>
              <td>${safeItem}</td>
              <td class="approved-quantity-cell" data-id="${safeId}">${safeQty}</td>
              <td class="remaining-balance-cell" data-item-id="${safeItemId}">
                <span class="badge bg-info">${r.current_balance || 0}</span>
              </td>
              <td>${safeStatus}</td>
              <td>
                <button type="button" class="btn btn-sm btn-primary edit-quantity-btn"
                  data-id="${safeId}"
                  data-item-name="${safeItem.replace(/"/g, '"')}"
                  data-office-name="${safeOffice.replace(/"/g, '"')}"
                  data-approved-quantity="${safeQty}"
                  data-bs-toggle="modal" data-bs-target="#editQuantityModal">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <button type="button" class="btn btn-sm btn-success change-item-btn"
                  data-id="${safeId}"
                  data-item-name="${safeItem.replace(/"/g, '"')}"
                  data-office-name="${safeOffice.replace(/"/g, '"')}"
                  data-approved-quantity="${safeQty}"
                  data-bs-toggle="modal" data-bs-target="#changeItemModal">
                  <i class="fas fa-exchange-alt"></i> Change
                </button>
                <button type="button" class="btn btn-sm btn-danger delete-item-btn"
                  data-id="${safeId}">
                  <i class="fas fa-ban"></i> Reject
                </button>
              </td>
            </tr>`;
        })
        .join('');
      bulkTableBody.innerHTML = html;
    }
    // After re-rendering, reset header checkbox and counts
    if (headerSelectAllCheckbox) {
      headerSelectAllCheckbox.checked = false;
      headerSelectAllCheckbox.indeterminate = false;
      headerSelectAllCheckbox.disabled = !rows || rows.length === 0;
    }
    updateSelectedCountAndButton();
  }

  // Fetch filtered rows from server
  function fetchBulkRows() {
    const formData = new FormData();
    if (bulkFilterOfficeSelect) formData.append('office', bulkFilterOfficeSelect.value || '');
    if (bulkFilterDateInput) formData.append('date', bulkFilterDateInput.value || '');

    // Optional: show a lightweight loading state
    if (bulkTableBody) {
      bulkTableBody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center text-muted py-3">
            <i class="fas fa-spinner fa-spin"></i> Loading...
          </td>
        </tr>`;
    }

    fetch('Logi_fetch_bulk_candidates.php', { method: 'POST', body: formData })
      .then(async (response) => {
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON response: ' + text); }
        if (!response.ok || data.success === false) {
          throw new Error(data && data.message ? data.message : 'Failed to load requests');
        }
        return data;
      })
      .then((data) => {
        if (data.success) {
          renderBulkRows(data.rows || []);
        } else {
          renderBulkRows([]);
          const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
          document.getElementById('errorMessage').textContent = data.message || 'Failed to load requests.';
          errorModal.show();
        }
      })
      .catch((error) => {
        renderBulkRows([]);
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        document.getElementById('errorMessage').textContent = error.message || 'An error occurred while loading data.';
        errorModal.show();
      });
  }

  // Update header select all checkbox (checked/indeterminate) based on visible rows
  function updateHeaderCheckboxState() {
    if (!headerSelectAllCheckbox) return;
    const visibleRowCheckboxes = getAllRowCheckboxes().filter((cb) => cb.closest('tr')?.style.display !== 'none');
    const totalVisible = visibleRowCheckboxes.length;
    const checkedVisible = visibleRowCheckboxes.filter((cb) => cb.checked).length;

    headerSelectAllCheckbox.indeterminate = false;
    if (totalVisible === 0) {
      headerSelectAllCheckbox.checked = false;
      return;
    }
    if (checkedVisible === 0) {
      headerSelectAllCheckbox.checked = false;
    } else if (checkedVisible === totalVisible) {
      headerSelectAllCheckbox.checked = true;
    } else {
      headerSelectAllCheckbox.indeterminate = true;
    }
  }

  // Update selected count badge and process button state
  function updateSelectedCountAndButton() {
    const allRowCheckboxes = getAllRowCheckboxes();
    const totalSelected = allRowCheckboxes.filter((cb) => cb.checked).length;
    if (selectedCountBadge) selectedCountBadge.textContent = `${totalSelected} selected`;
    if (processBulkButton) processBulkButton.disabled = totalSelected === 0;
  }

  // Toggle selection for all visible rows
  function setAllVisibleRowsChecked(checked) {
    const visibleRowCheckboxes = getAllRowCheckboxes().filter((cb) => cb.closest('tr')?.style.display !== 'none');
    visibleRowCheckboxes.forEach((cb) => {
      cb.checked = checked;
    });
    updateHeaderCheckboxState();
    updateSelectedCountAndButton();
  }

  // Deselect all rows (visible and hidden)
  function clearAllSelections() {
    const allRowCheckboxes = getAllRowCheckboxes();
    allRowCheckboxes.forEach((cb) => (cb.checked = false));
    if (headerSelectAllCheckbox) {
      headerSelectAllCheckbox.checked = false;
      headerSelectAllCheckbox.indeterminate = false;
    }
    updateSelectedCountAndButton();
  }

  // Event: change filters
  bulkFilterOfficeSelect?.addEventListener('change', fetchBulkRows);
  bulkFilterDateInput?.addEventListener('change', fetchBulkRows);

  // Event: header select all checkbox
  headerSelectAllCheckbox?.addEventListener('change', function () {
    setAllVisibleRowsChecked(this.checked);
  });

  // Event: per-row checkbox change (delegate to tbody)
  bulkTableBody?.addEventListener('change', function (e) {
    const target = e.target;
    if (target && target.classList && target.classList.contains('bulk-transaction-checkbox')) {
      updateHeaderCheckboxState();
      updateSelectedCountAndButton();
    }
  });

  // Buttons: select/deselect all
  selectAllBulkButton?.addEventListener('click', function () {
    setAllVisibleRowsChecked(true);
  });
  deselectAllBulkButton?.addEventListener('click', function () {
    clearAllSelections();
  });

  // Process button handler
  processBulkButton?.addEventListener('click', function () {
    const selectedCheckboxes = getAllRowCheckboxes().filter((cb) => cb.checked);
    if (selectedCheckboxes.length === 0) return;

    // Prepare payload
    const selectedItems = selectedCheckboxes.map((cb) => ({
      id: cb.value,
      office_name: cb.dataset.officeName || '',
      date: cb.dataset.date || ''
    }));

    // Button loading state
    const originalHtml = processBulkButton.innerHTML;
    processBulkButton.disabled = true;
    processBulkButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Build form data to align with existing PHP handling patterns
    const formData = new FormData();
    formData.append('selected', JSON.stringify(selectedItems));
    if (bulkFilterOfficeSelect) formData.append('filter_office', bulkFilterOfficeSelect.value || '');
    if (bulkFilterDateInput) formData.append('filter_date', bulkFilterDateInput.value || '');

    fetch('Logi_process_bulk_transactions.php', {
      method: 'POST',
      body: formData
    })
      .then((response) => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text().then((text) => {
          try {
            return JSON.parse(text);
          } catch (err) {
            throw new Error('Invalid JSON response: ' + text);
          }
        });
      })
      .then((data) => {
        if (data.success) {
          const successModal = new bootstrap.Modal(document.getElementById('successModal'));
          document.getElementById('successMessage').textContent = data.message || 'Bulk transactions processed successfully.';
          successModal.show();

          // Optionally close bulk modal and refresh
          const instance = bootstrap.Modal.getInstance(bulkModalElement) || new bootstrap.Modal(bulkModalElement);
          instance.hide();
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
          document.getElementById('errorMessage').textContent = data.message || 'Failed to process bulk transactions.';
          errorModal.show();
        }
      })
      .catch((error) => {
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        document.getElementById('errorMessage').textContent = error.message || 'An error occurred while processing your request.';
        errorModal.show();
      })
      .finally(() => {
        processBulkButton.disabled = false;
        processBulkButton.innerHTML = originalHtml;
      });
  });

  // When modal is shown, reset UI and apply filters once
  bulkModalElement.addEventListener('shown.bs.modal', function () {
    // Reset header checkbox visuals
    if (headerSelectAllCheckbox) {
      headerSelectAllCheckbox.checked = false;
      headerSelectAllCheckbox.indeterminate = false;
    }
    updateSelectedCountAndButton();
    // Initial load from server using current filters
    fetchBulkRows();
  });
});

// Multi-item Stock In with one IB number
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("stockInBatchForm");
  if (!form || !Array.isArray(window.activeAdminIbs)) return;

  const search = document.getElementById("stockInBatchItemSearch");
  const suggestions = document.getElementById("stockInBatchSuggestions");
  const quantity = document.getElementById("stockInBatchQuantity");
  const addButton = document.getElementById("addStockInBatchItem");
  const linesBody = document.getElementById("stockInBatchLines");
  const countBadge = document.getElementById("stockInBatchCount");
  const lineTotal = document.getElementById("stockInLineTotal");
  const unitTotal = document.getElementById("stockInUnitTotal");
  const hint = document.getElementById("stockInSelectedHint");
  const submitButton = document.getElementById("submitStockInBatch");
  const ibButtons = [...document.querySelectorAll(".active-admin-ib-link[data-ib-id]")];
  const activeIbMap = new Map(window.activeAdminIbs.map((ib) => [Number(ib.id), ib]));
  const batchLines = new Map();
  let selectedCandidate = null;
  let selectedActiveIb = null;

  const escapeHtml = (value) => String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");

  function hideSuggestions() {
    suggestions.hidden = true;
    suggestions.replaceChildren();
  }

  function chooseItem(item) {
    selectedCandidate = item;
    search.value = item.item_name;
    const remaining = Math.max(0, Number(item.planned_quantity) - Number(item.delivered_quantity));
    hint.textContent = item.item_name + " — Stock No. " + item.item_no + " — " + remaining + " planned remaining";
    hint.className = "form-text mb-3 text-success";
    hideSuggestions();
    quantity.focus();
  }

  function showSuggestions(term) {
    suggestions.replaceChildren();
    const normalized = term.trim().toLowerCase();
    if (!normalized) {
      hideSuggestions();
      return;
    }
    if (!selectedActiveIb) {
      const empty = document.createElement("div");
      empty.className = "p-3 text-muted small";
      empty.textContent = "Select an Active ADMIN IB first.";
      suggestions.appendChild(empty);
      suggestions.hidden = false;
      return;
    }
    const matches = selectedActiveIb.items.filter((item) =>
      String(item.item_name).toLowerCase().includes(normalized) ||
      String(item.item_no).toLowerCase().includes(normalized)
    ).slice(0, 12);

    if (!matches.length) {
      const empty = document.createElement("div");
      empty.className = "p-3 text-muted small";
      empty.textContent = "No inventory items found.";
      suggestions.appendChild(empty);
    } else {
      matches.forEach((item) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "stock-in-suggestion";
        const identity = document.createElement("span");
        const name = document.createElement("strong");
        name.textContent = item.item_name;
        const code = document.createElement("small");
        code.textContent = "Stock No. " + item.item_no + " — " + (item.unit || "unit");
        identity.append(name, code);
        const balance = document.createElement("span");
        balance.className = "balance";
        const remaining = Math.max(0, Number(item.planned_quantity) - Number(item.delivered_quantity));
        balance.textContent = remaining + " planned remaining";
        button.append(identity, balance);
        button.addEventListener("click", () => chooseItem(item));
        suggestions.appendChild(button);
      });
    }
    suggestions.hidden = false;
  }

  function totals() {
    const itemCount = batchLines.size;
    const units = [...batchLines.values()].reduce((sum, line) => sum + line.quantity, 0);
    countBadge.textContent = itemCount + " item" + (itemCount === 1 ? "" : "s");
    lineTotal.textContent = itemCount;
    unitTotal.textContent = units;
  }

  function renderLines() {
    if (!batchLines.size) {
      linesBody.innerHTML = '<tr class="stock-in-empty"><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-box-open d-block fs-4 mb-2"></i>No items added yet.</td></tr>';
      totals();
      return;
    }

    linesBody.innerHTML = [...batchLines.values()].map((line) => {
      const next = Number(line.current_balance) + Number(line.quantity);
      return '<tr data-line-id="' + escapeHtml(line.line_id) + '">' +
        '<td><span class="line-item-name">' + escapeHtml(line.item_name) + '</span><span class="line-item-unit">' + escapeHtml(line.unit || "unit") + '</span></td>' +
        '<td>' + escapeHtml(line.item_no) + '</td>' +
        '<td class="text-end">' + escapeHtml(line.current_balance) + '</td>' +
        '<td><input type="number" class="form-control form-control-sm stock-in-line-quantity" min="1" step="1" value="' + escapeHtml(line.quantity) + '" aria-label="Quantity received for ' + escapeHtml(line.item_name) + '"></td>' +
        '<td class="text-end fw-bold stock-in-new-balance">' + escapeHtml(next) + '</td>' +
        '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger stock-in-remove-line" aria-label="Remove ' + escapeHtml(line.item_name) + '"><i class="fas fa-trash"></i></button></td>' +
      '</tr>';
    }).join("");
    totals();
  }

  function addSelectedItem() {
    const amount = Number.parseInt(quantity.value, 10);
    if (!selectedCandidate) {
      search.setCustomValidity("Select an item from the search results.");
      search.reportValidity();
      return;
    }
    search.setCustomValidity("");
    if (!Number.isInteger(amount) || amount <= 0) {
      quantity.setCustomValidity("Enter a quantity greater than zero.");
      quantity.reportValidity();
      return;
    }
    quantity.setCustomValidity("");

    const lineKey = String(selectedCandidate.line_id);
    const existing = batchLines.get(lineKey);
    batchLines.set(lineKey, {
      line_id: Number(selectedCandidate.line_id),
      item_no: String(selectedCandidate.item_no),
      item_name: selectedCandidate.item_name,
      unit: selectedCandidate.unit || "",
      current_balance: Number(selectedCandidate.current_balance) || 0,
      quantity: existing ? existing.quantity + amount : amount,
    });
    renderLines();
    selectedCandidate = null;
    search.value = "";
    quantity.value = "";
    hint.textContent = "Item added. Search for another item.";
    hint.className = "form-text mb-3 text-success";
    search.focus();
  }

  search.addEventListener("input", function () {
    selectedCandidate = null;
    search.setCustomValidity("");
    hint.textContent = "Select an item from the search results.";
    hint.className = "form-text mb-3";
    showSuggestions(search.value);
  });
  search.addEventListener("keydown", function (event) {
    if (event.key === "Escape") hideSuggestions();
    if (event.key === "Enter") {
      event.preventDefault();
      const first = suggestions.querySelector(".stock-in-suggestion");
      if (first) first.click();
      else addSelectedItem();
    }
  });
  quantity.addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      addSelectedItem();
    }
  });
  quantity.addEventListener("input", () => quantity.setCustomValidity(""));
  addButton.addEventListener("click", addSelectedItem);

  document.addEventListener("click", function (event) {
    if (!search.contains(event.target) && !suggestions.contains(event.target)) hideSuggestions();
  });

  linesBody.addEventListener("input", function (event) {
    if (!event.target.classList.contains("stock-in-line-quantity")) return;
    const row = event.target.closest("tr");
    const line = batchLines.get(row.dataset.lineId);
    const amount = Number.parseInt(event.target.value, 10);
    if (!line || !Number.isInteger(amount) || amount <= 0) return;
    line.quantity = amount;
    row.querySelector(".stock-in-new-balance").textContent = String(line.current_balance + amount);
    totals();
  });
  linesBody.addEventListener("click", function (event) {
    const remove = event.target.closest(".stock-in-remove-line");
    if (!remove) return;
    batchLines.delete(remove.closest("tr").dataset.lineId);
    renderLines();
  });

  function selectActiveIb(ibId) {
    selectedActiveIb = activeIbMap.get(Number(ibId)) || null;
    selectedCandidate = null;
    batchLines.clear();
    search.value = "";
    quantity.value = "";
    ibButtons.forEach((button) => {
      const selected = Number(button.dataset.ibId) === Number(ibId);
      button.classList.toggle("is-selected", selected);
      button.setAttribute("aria-pressed", selected ? "true" : "false");
      const action = button.querySelector(".active-admin-ib-action");
      if (action) action.innerHTML = selected ? '<i class="fas fa-check-circle" aria-hidden="true"></i> Selected' : '<i class="fas fa-circle" aria-hidden="true"></i> Select';
    });
    hint.textContent = selectedActiveIb ? "Search the planned ADMIN items in IB " + selectedActiveIb.ib_no + "." : "Select an Active ADMIN IB first.";
    hint.className = "form-text mb-3";
    renderLines();
  }

  ibButtons.forEach((button) => button.addEventListener("click", () => selectActiveIb(button.dataset.ibId)));

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!selectedActiveIb) {
      alert("Select an Active ADMIN IB before recording a delivery.");
      ibButtons[0]?.focus();
      return;
    }
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    if (!batchLines.size) {
      alert("Add at least one inventory item to this IB transaction.");
      search.focus();
      return;
    }
    const invalidLine = [...batchLines.values()].find((line) => !Number.isInteger(line.quantity) || line.quantity <= 0);
    if (invalidLine) {
      alert("Enter a valid quantity for " + invalidLine.item_name + ".");
      return;
    }

    const payload = {
      action: "record_delivery",
      csrf_token: window.ibActionCsrf,
      ib_id: Number(selectedActiveIb.id),
      delivery_date: document.getElementById("stockInDate").value,
      notes: document.getElementById("stockInNotes").value.trim(),
      idempotency_token: typeof crypto.randomUUID === "function" ? crypto.randomUUID() : "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (character) => {
        const random = Math.random() * 16 | 0;
        return (character === "x" ? random : (random & 3 | 8)).toString(16);
      }),
      items: [...batchLines.values()].map((line) => ({
        line_id: line.line_id,
        quantity: line.quantity,
      })),
    };
    payload.lines = payload.items;
    delete payload.items;

    const original = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
    try {
      const response = await fetch("Logi_ib_action.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || "Stock In failed.");

      const modalElement = document.getElementById("stockInModal");
      bootstrap.Modal.getInstance(modalElement)?.hide();
      document.getElementById("successMessage").textContent = data.message;
      new bootstrap.Modal(document.getElementById("successModal")).show();

      form.reset();
      batchLines.clear();
      selectedCandidate = null;
      hint.textContent = "Select an item from the search results.";
      hint.className = "form-text mb-3";
      renderLines();
      window.setTimeout(() => window.location.reload(), 900);
    } catch (error) {
      alert(error.message);
    } finally {
      submitButton.disabled = false;
      submitButton.innerHTML = original;
    }
  });

  if (ibButtons.length === 1) selectActiveIb(ibButtons[0].dataset.ibId);
  else renderLines();
});

