class BarcodeScanner {
  constructor() {
    this.codeReader = new ZXing.BrowserMultiFormatReader();
    this.scanning = false;
    this.currentItem = null;
    this.transactions = [];
    this.initializeElements();
    this.bindEvents();
    this.loadTransactionHistory();
  }

  initializeElements() {
    this.video = document.getElementById("video");
    this.startBtn = document.getElementById("startScan");
    this.stopBtn = document.getElementById("stopScan");
    this.scannerStatus = document.getElementById("scannerStatus");
    this.scanResult = document.getElementById("scanResult");
    this.scannedCode = document.getElementById("scannedCode");
    this.manualBarcode = document.getElementById("manualBarcode");
    this.manualSubmit = document.getElementById("manualSubmit");
    this.itemInfo = document.getElementById("itemInfo");
    this.noItemMessage = document.getElementById("noItemMessage");

    // Deduct elements
    this.deductBtn = document.getElementById("deductBtn");
    this.deductQuantity = document.getElementById("deductQuantity");
    this.deductRequestor = document.getElementById("deductRequestor");

    // Add elements
    this.addBtn = document.getElementById("addBtn");
    this.addQuantity = document.getElementById("addQuantity");
    this.addReference = document.getElementById("addReference"); // Make sure this line exists

    this.transactionHistory = document.getElementById("transactionHistory");
    this.clearHistory = document.getElementById("clearHistory");
  }

  bindEvents() {
    this.startBtn.addEventListener("click", () => this.startScanning());
    this.stopBtn.addEventListener("click", () => this.stopScanning());
    this.manualSubmit.addEventListener("click", () =>
      this.searchManualBarcode()
    );
    this.manualBarcode.addEventListener("keypress", (e) => {
      if (e.key === "Enter") this.searchManualBarcode();
    });
    this.deductBtn.addEventListener("click", () => this.deductItems());
    this.addBtn.addEventListener("click", () => this.addItems());
    this.clearHistory.addEventListener("click", () =>
      this.clearTransactionHistory()
    );
  }

  async startScanning() {
    try {
      this.scanning = true;
      this.updateScannerStatus("active");
      this.startBtn.disabled = true;
      this.stopBtn.disabled = false;
      this.video.style.display = "block";
      const stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: "environment",
        },
      });
      this.video.srcObject = stream;
      this.codeReader.decodeFromVideoDevice(null, this.video, (result, err) => {
        if (result && this.scanning) {
          this.handleScanResult(result.text);
        }
      });
    } catch (error) {
      console.error("Error starting scanner:", error);
      this.showError("Failed to start camera. Please check permissions.");
      this.stopScanning();
    }
  }

  stopScanning() {
    this.scanning = false;
    this.updateScannerStatus("inactive");
    this.startBtn.disabled = false;
    this.stopBtn.disabled = true;
    this.video.style.display = "none";
    if (this.video.srcObject) {
      this.video.srcObject.getTracks().forEach((track) => track.stop());
      this.video.srcObject = null;
    }
    this.codeReader.reset();
  }

  updateScannerStatus(status) {
    if (status === "active") {
      this.scannerStatus.className = "scanner-status status-active";
      this.scannerStatus.textContent =
        "Scanner Active - Point camera at barcode";
    } else {
      this.scannerStatus.className = "scanner-status status-inactive";
      this.scannerStatus.textContent = "Scanner Inactive";
    }
  }

  handleScanResult(barcode) {
    this.scannedCode.textContent = barcode;
    this.scanResult.style.display = "block";
    this.searchItem(barcode);
    // Auto-stop scanning after successful scan
    setTimeout(() => this.stopScanning(), 1000);
  }

  searchManualBarcode() {
    const barcode = this.manualBarcode.value.trim();
    if (barcode) {
      this.handleScanResult(barcode);
      this.manualBarcode.value = "";
    }
  }

  async searchItem(barcode) {
    try {
      // Show loading state
      this.showItemLoading();
      // Replace this with your actual API endpoint
      const response = await fetch("Logi_barcode_search_item.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          barcode: barcode,
        }),
      });
      const data = await response.json();
      if (data.success && data.item) {
        this.currentItem = data.item;
        this.displayItemInfo(data.item);
      } else {
        this.showItemNotFound(barcode);
      }
    } catch (error) {
      console.error("Error searching item:", error);
      this.showError("Failed to search item. Please try again.");
      this.hideItemInfo();
    }
  }

  showItemLoading() {
    this.itemInfo.style.display = "none";
    this.noItemMessage.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Searching item...</p>
            </div>
        `;
  }

  displayItemInfo(item) {
    document.getElementById("itemCode").textContent =
      item.item_no || item.barcode;
    document.getElementById("itemName").textContent =
      item.item_name || item.name;
    document.getElementById("currentStock").textContent =
      item.current_balance || item.stock;
    document.getElementById("itemUnit").textContent = item.unit || "pcs";
    this.itemInfo.style.display = "block";
    this.noItemMessage.style.display = "none";

    // Reset forms with safety checks
    if (this.deductQuantity) {
      this.deductQuantity.value = 1;
      this.deductQuantity.max = item.current_balance || item.stock;
    }

    if (this.deductRequestor) {
      this.deductRequestor.value = "";
    }

    if (this.addQuantity) {
      this.addQuantity.value = 1;
    }

    if (this.addReference) {
      this.addReference.value = ""; // Reset reference field
    }
  }

  showItemNotFound(barcode) {
    this.hideItemInfo();
    this.noItemMessage.innerHTML = `
            <div class="text-center text-warning">
                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                <h6>Item Not Found</h6>
                <p>Barcode: <strong>${barcode}</strong></p>
                <p>This item is not in the inventory database.</p>
            </div>
        `;
  }

  hideItemInfo() {
    this.itemInfo.style.display = "none";
    this.noItemMessage.style.display = "block";
    this.currentItem = null;
  }

  async deductItems() {
    if (!this.currentItem) {
      this.showError("No item selected for deduction.");
      return;
    }

    // Check if elements exist
    if (!this.deductQuantity) {
      this.showError("Deduct quantity element not found.");
      return;
    }

    if (!this.deductRequestor) {
      this.showError("Deduct requestor element not found.");
      return;
    }

    const quantity = parseInt(this.deductQuantity.value);
    const requestor = this.deductRequestor.value.trim();
    const currentStock = parseInt(
      this.currentItem.current_balance || this.currentItem.stock
    );

    // Validation
    if (quantity <= 0) {
      this.showError("Please enter a valid quantity greater than 0.");
      return;
    }
    if (quantity > currentStock) {
      this.showError(
        `Insufficient stock. Available: ${currentStock}, Requested: ${quantity}`
      );
      return;
    }
    if (!requestor) {
      this.showError("Please enter the requestor name.");
      return;
    }

    // Store original button state OUTSIDE the try block
    const originalText = this.deductBtn.innerHTML;
    const originalDisabled = this.deductBtn.disabled;

    try {
      // Show loading on button
      this.deductBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Processing...';
      this.deductBtn.disabled = true;

      // Make API call to deduct items
      const response = await fetch("Logi_barcode_deduct_item.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          item_no: this.currentItem.item_no,
          quantity: quantity,
          requestor: requestor,
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Update current item stock
        this.currentItem.current_balance = data.transaction.new_balance;

        // Add to transaction history with requestor
        this.addTransaction({
          item_name: this.currentItem.item_name,
          item_code: this.currentItem.item_no,
          quantity: quantity,
          requestor: requestor,
          timestamp: new Date(),
          old_balance: currentStock,
          new_balance: data.transaction.new_balance,
          type: "deduct",
        });

        // Update display
        this.displayItemInfo(this.currentItem);

        // Show success message
        this.showSuccess(data.message);

        // Reset form
        this.deductQuantity.value = 1;
        this.deductRequestor.value = "";
      } else {
        this.showError(
          data.message || "Failed to deduct items. Please try again."
        );
      }
    } catch (error) {
      console.error("Error deducting items:", error);
      this.showError(
        "Failed to deduct items. Please check your connection and try again."
      );
    } finally {
      // Restore button using variables defined outside try block
      this.deductBtn.innerHTML = originalText;
      this.deductBtn.disabled = originalDisabled;
    }
  }
  async addItems() {
    if (!this.currentItem) {
      this.showError("No item selected for addition.");
      return;
    }

    // Check if elements exist
    if (!this.addQuantity) {
      this.showError("Add quantity element not found.");
      return;
    }

    if (!this.addReference) {
      this.showError("Add reference element not found.");
      return;
    }

    const quantity = parseInt(this.addQuantity.value);
    const reference = this.addReference.value.trim();
    const currentStock = parseInt(
      this.currentItem.current_balance || this.currentItem.stock
    );

    // Validation
    if (quantity <= 0) {
      this.showError("Please enter a valid quantity greater than 0.");
      return;
    }

    if (!reference) {
      this.showError("Please enter PO No./IB No.");
      return;
    }

    // Store original button state OUTSIDE the try block
    const originalText = this.addBtn.innerHTML;
    const originalDisabled = this.addBtn.disabled;

    try {
      // Show loading on button
      this.addBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Processing...';
      this.addBtn.disabled = true;

      // Make API call to add items
      const response = await fetch("Logi_barcode_add_item.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          item_no: this.currentItem.item_no,
          quantity: quantity,
          reference_no: reference,
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Update current item stock
        this.currentItem.current_balance = data.transaction.new_balance;

        // Add to transaction history with reference
        this.addTransaction({
          item_name: this.currentItem.item_name,
          item_code: this.currentItem.item_no,
          quantity: quantity,
          reference_no: reference,
          timestamp: new Date(),
          old_balance: currentStock,
          new_balance: data.transaction.new_balance,
          type: "add",
        });

        // Update display
        this.displayItemInfo(this.currentItem);

        // Show success message
        this.showSuccess(data.message);

        // Reset form
        this.addQuantity.value = 1;
        this.addReference.value = "";
      } else {
        this.showError(
          data.message || "Failed to add items. Please try again."
        );
      }
    } catch (error) {
      console.error("Error adding items:", error);
      this.showError(
        "Failed to add items. Please check your connection and try again."
      );
    } finally {
      // Restore button using variables defined outside try block
      this.addBtn.innerHTML = originalText;
      this.addBtn.disabled = originalDisabled;
    }
  }

  addTransaction(transaction) {
    this.transactions.unshift(transaction);
    this.saveTransactionHistory();
    this.renderTransactionHistory();
  }

  renderTransactionHistory() {
    if (this.transactions.length === 0) {
      this.transactionHistory.innerHTML = `
            <div class="text-center text-muted">
                <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                <p>No transactions yet</p>
            </div>
        `;
      return;
    }

    let html = "";
    this.transactions.forEach((transaction, index) => {
      const date = new Date(transaction.timestamp);
      const timeString = date.toLocaleString();
      const isAdd = transaction.type === "add";
      const badgeClass = isAdd ? "bg-success" : "bg-warning";
      const badgeText = isAdd
        ? `+${transaction.quantity}`
        : `-${transaction.quantity}`;

      html += `
            <div class="transaction-item">
                <div class="row">
                    <div class="col-md-8">
                        <h6 class="mb-1">${transaction.item_name}</h6>
                        <small class="text-muted">Code: ${
                          transaction.item_code
                        }</small>
                        ${
                          transaction.requestor
                            ? `<br><small class="text-muted">Requestor: ${transaction.requestor}</small>`
                            : ""
                        }
                        ${
                          transaction.reference_no
                            ? `<br><small class="text-muted">Ref: ${transaction.reference_no}</small>`
                            : ""
                        }
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge ${badgeClass}">${badgeText}</span>
                    </div>
                </div>
                <div class="row mt-1">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> ${timeString}
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">
                            Stock: ${transaction.old_balance} → ${
        transaction.new_balance
      }
                        </small>
                    </div>
                </div>
            </div>
        `;
    });
    this.transactionHistory.innerHTML = html;
  }

  saveTransactionHistory() {
    try {
      localStorage.setItem(
        "barcode_transactions",
        JSON.stringify(this.transactions)
      );
    } catch (error) {
      console.error("Error saving transaction history:", error);
    }
  }

  loadTransactionHistory() {
    try {
      const saved = localStorage.getItem("barcode_transactions");
      if (saved) {
        this.transactions = JSON.parse(saved);
        this.renderTransactionHistory();
      }
    } catch (error) {
      console.error("Error loading transaction history:", error);
      this.transactions = [];
    }
  }

  clearTransactionHistory() {
    if (confirm("Are you sure you want to clear all transaction history?")) {
      this.transactions = [];
      this.saveTransactionHistory();
      this.renderTransactionHistory();
    }
  }

  showSuccess(message) {
    try {
      const successMessageElement = document.getElementById("successMessage");
      const successModalElement = document.getElementById("successModal");

      if (successMessageElement && successModalElement) {
        successMessageElement.textContent = message;

        // Check if Bootstrap is available
        if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
          const modal = new bootstrap.Modal(successModalElement);
          modal.show();
        } else {
          // Fallback: show alert if Bootstrap modal is not available
          alert("Success: " + message);
        }
      } else {
        // Fallback: show alert if modal elements are not found
        alert("Success: " + message);
      }
    } catch (error) {
      console.error("Error showing success modal:", error);
      alert("Success: " + message);
    }
  }

  showError(message) {
    try {
      const errorMessageElement = document.getElementById("errorMessage");
      const errorModalElement = document.getElementById("errorModal");

      if (errorMessageElement && errorModalElement) {
        errorMessageElement.textContent = message;

        // Check if Bootstrap is available
        if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
          const modal = new bootstrap.Modal(errorModalElement);
          modal.show();
        } else {
          // Fallback: show alert if Bootstrap modal is not available
          alert("Error: " + message);
        }
      } else {
        // Fallback: show alert if modal elements are not found
        alert("Error: " + message);
      }
    } catch (error) {
      console.error("Error showing error modal:", error);
      alert("Error: " + message);
    }
  }
}

// Initialize the scanner when page loads
document.addEventListener("DOMContentLoaded", function () {
  const scanner = new BarcodeScanner();

  // Initialize modals after DOM is ready - use getElementById instead
  try {
    const successModalElement = document.getElementById("successModal");
    const errorModalElement = document.getElementById("errorModal");

    if (
      successModalElement &&
      typeof bootstrap !== "undefined" &&
      bootstrap.Modal
    ) {
      scanner.successModal = new bootstrap.Modal(successModalElement, {
        backdrop: true,
        keyboard: true,
        focus: true,
      });
    }

    if (
      errorModalElement &&
      typeof bootstrap !== "undefined" &&
      bootstrap.Modal
    ) {
      scanner.errorModal = new bootstrap.Modal(errorModalElement, {
        backdrop: true,
        keyboard: true,
        focus: true,
      });
    }
  } catch (error) {
    console.error("Error initializing modals:", error);
  }
});
