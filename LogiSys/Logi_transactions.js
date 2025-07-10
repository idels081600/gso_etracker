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

// Stock In form submission handler
const stockInForm = document.getElementById('stockInForm');
if (stockInForm) {
  stockInForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
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
    const previousBalance = document.getElementById('previousBalance').value;
    const newBalance = document.getElementById('calculatedNewBalance').value;

    // Validate required fields
    if (!itemNo || !itemName || !quantity || !reason || !previousBalance || !newBalance) {
      alert('Please fill in all required fields');
      return;
    }

    // Show loading state
    const submitButton = this.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Prepare form data
    const formData = new FormData();
    formData.append('itemNo', itemNo);
    formData.append('itemName', itemName);
    formData.append('quantity', quantity);
    formData.append('reason', reason);
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
        // Show success message using Bootstrap modal
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        document.getElementById('successMessage').textContent = data.message;
        successModal.show();

        // Reset form and clear selection
        stockInForm.reset();
        if (typeof clearSelection === 'function') clearSelection();
        if (typeof resetSummary === 'function') resetSummary();

        // Reload page after 1.5 seconds to show updated data
        setTimeout(() => {
          location.reload();
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

        // Reload page after 1.5 seconds to show updated data
        setTimeout(() => {
          location.reload();
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

// Transaction search bar filter

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('transactionSearchInput');
    const tableBody = document.getElementById('transactionsTableBody');
    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = tableBody.querySelectorAll('tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let rowText = row.textContent.toLowerCase();
                // Also specifically check cell 0 (date)
                let dateText = '';
                if (cells.length > 0) {
                    dateText = cells[0].textContent.toLowerCase();
                }
                if (rowText.includes(searchTerm) || dateText.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});

// Transaction type filter

document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('transactionType');
    const tableBody = document.getElementById('transactionsTableBody');
    if (typeSelect && tableBody) {
        typeSelect.addEventListener('change', function() {
            const selectedType = this.value.toLowerCase();
            const rows = tableBody.querySelectorAll('tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let typeText = '';
                if (cells.length >= 7) {
                    typeText = cells[7].textContent.trim().toLowerCase();
                }
                if (!selectedType || typeText.includes(selectedType)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
