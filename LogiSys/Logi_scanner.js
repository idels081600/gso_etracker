class QRScanner {
  constructor() {
    // Initialize elements
    this.video = document.getElementById("scanner-video");
    this.startBtn = document.getElementById("startBtn");
    this.stopBtn = document.getElementById("stopBtn");
    this.scannerStatus = document.getElementById("scannerStatus");
    this.manualQRInput = document.getElementById("manualQRInput");
    this.searchManualQR = document.getElementById("searchManualQR");
    this.clearHistoryBtn = document.getElementById("clearHistoryBtn");
    this.transactionList = document.getElementById("transactionList");

    // Item info elements
    this.itemNo = document.getElementById("itemNo");
    this.itemName = document.getElementById("itemName");
    this.currentBalance = document.getElementById("currentBalance");
    this.itemUnit = document.getElementById("itemUnit");
    this.rackNo = document.getElementById("rackNo");
    this.itemStatus = document.getElementById("itemStatus");

    // Scan result elements
    this.scannedCode = document.getElementById("scannedCode");
    this.scanResult = document.getElementById("scanResult");

    // Initialize ZXing code reader
    this.codeReader = new ZXing.BrowserQRCodeReader();
    this.scanning = false;
    this.selectedItem = null;
    this.lastScannedCode = null;
    this.scanCooldown = false;

    // Bind events
    this.bindEvents();

    // Load transaction history
    this.loadTransactionHistory();
  }

  bindEvents() {
    // Start scanner button
    this.startBtn.addEventListener("click", () => {
      this.requestCameraPermission();
    });

    // Stop scanner button
    this.stopBtn.addEventListener("click", () => {
      this.stopScanning();
    });

    // Manual QR search
    this.searchManualQR.addEventListener("click", () => {
      const qrCode = this.manualQRInput.value.trim();
      if (qrCode) {
        this.searchItem(qrCode);
      }
    });

    // Manual QR input enter key
    this.manualQRInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        const qrCode = this.manualQRInput.value.trim();
        if (qrCode) {
          this.searchItem(qrCode);
        }
      }
    });

    // Clear history button
    this.clearHistoryBtn.addEventListener("click", () => {
      this.clearTransactionHistory();
    });
  }

  async requestCameraPermission() {
    try {
      // First check if the browser supports getUserMedia
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error(
          "Your browser does not support camera access. Please try using Chrome, Firefox, or Safari."
        );
      }

      this.scannerStatus.textContent = "Requesting camera access...";
      this.scannerStatus.className = "scanner-status status-active";

      // Try to get available video devices
      const devices = await navigator.mediaDevices.enumerateDevices();
      const videoDevices = devices.filter(
        (device) => device.kind === "videoinput"
      );

      if (videoDevices.length === 0) {
        throw new Error("No camera found on your device.");
      }

      // Try different camera constraints
      const constraints = [
        {
          video: {
            facingMode: "environment",
            width: { ideal: 1280 },
            height: { ideal: 720 },
          },
        },
        {
          video: {
            facingMode: "user",
            width: { ideal: 1280 },
            height: { ideal: 720 },
          },
        },
        { video: { width: { ideal: 1280 }, height: { ideal: 720 } } },
        { video: true },
      ];

      let stream = null;
      for (const constraint of constraints) {
        try {
          stream = await navigator.mediaDevices.getUserMedia(constraint);
          console.log("Camera accessed with constraint:", constraint);
          break;
        } catch (error) {
          console.log("Failed with constraint:", constraint, error);
          continue;
        }
      }

      if (!stream) {
        throw new Error("Failed to access camera with any configuration.");
      }

      this.video.srcObject = stream;
      this.video.style.display = "block";

      // Wait for video to load and play
      await new Promise((resolve, reject) => {
        this.video.onloadedmetadata = () => {
          this.video.play().then(resolve).catch(reject);
        };
        this.video.onerror = reject;
      });

      this.scannerStatus.textContent =
        "Camera started - Ready for continuous scanning";
      this.scannerStatus.className = "scanner-status status-active";
      this.startBtn.disabled = true;
      this.stopBtn.disabled = false;

      // Start continuous scanning
      setTimeout(() => {
        this.startScanning();
      }, 1000);
    } catch (error) {
      console.error("Camera error:", error);
      this.scannerStatus.textContent = error.message;
      this.scannerStatus.className = "scanner-status status-inactive";
      this.showError(error.message);
    }
  }

  async startScanning() {
    if (this.scanning) return;

    try {
      this.scanning = true;
      this.scannerStatus.textContent = "Continuous scanning active...";
      this.scannerStatus.className = "scanner-status status-active";

      // Configure the code reader
      const hints = new Map();
      hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
      hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
        ZXing.BarcodeFormat.QR_CODE,
      ]);

      // Start continuous scanning
      this.codeReader.decodeFromVideoDevice(
        null,
        this.video,
        (result, err) => {
          if (result && this.scanning) {
            console.log("QR code detected:", result.text);
            this.handleScanResult(result.text);
          }
          if (err && !(err instanceof ZXing.NotFoundException)) {
            console.error("QR scan error:", err);
          }
        },
        hints
      );
    } catch (error) {
      console.error("Error starting scanner:", error);
      this.scannerStatus.textContent = "Failed to start scanner";
      this.scannerStatus.className = "scanner-status status-inactive";
      this.showError("Failed to start scanner. Please try again.");
      this.scanning = false;
      this.startBtn.disabled = false;
      this.stopBtn.disabled = true;
    }
  }

  stopScanning() {
    if (!this.scanning) return;

    try {
      this.codeReader.reset();
      this.scanning = false;
      this.scanCooldown = false;
      this.lastScannedCode = null;

      this.scannerStatus.textContent = "Scanner stopped";
      this.scannerStatus.className = "scanner-status status-inactive";
      this.startBtn.disabled = false;
      this.stopBtn.disabled = true;

      // Stop all video tracks
      if (this.video.srcObject) {
        const tracks = this.video.srcObject.getTracks();
        tracks.forEach((track) => track.stop());
        this.video.srcObject = null;
      }
      this.video.style.display = "none";
    } catch (error) {
      console.error("Error stopping scanner:", error);
      this.showError("Error stopping scanner");
    }
  }

  handleScanResult(qrCode) {
    // Stop scanning after a successful scan
    this.stopScanning();

    // Update scan result display if elements exist
    if (this.scannedCode) {
      this.scannedCode.textContent = qrCode;
    }
    if (this.scanResult) {
      this.scanResult.style.display = "block";
    }

    // Search for the item
    this.searchItem(qrCode);

    // Show brief success feedback
    this.scannerStatus.textContent = `Scanned: ${qrCode.substring(0, 20)}${
      qrCode.length > 20 ? "..." : ""
    }`;
    // Reset status message after 2 seconds
    setTimeout(() => {
      if (!this.scanning) {
        this.scannerStatus.textContent = "Scanner stopped";
      }
    }, 2000);
  }

  async searchItem(qrCode) {
    try {
      const response = await fetch("Logi_qr_search_item.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ qr_code: qrCode }),
      });

      const data = await response.json();

      if (data.success) {
        this.selectedItem = data.item;
        this.displayItemInfo(data.item);
        // Show success notification without stopping scanning
        this.showSuccess(`Item found: ${data.item.item_name}`);
      } else {
        this.showError(data.message || "Item not found");
        this.clearItemInfo();
      }
    } catch (error) {
      console.error("Error searching item:", error);
      this.showError("Failed to search item. Please try again.");
    }
  }

  displayItemInfo(item) {
    this.itemNo.textContent = item.item_no;
    this.itemName.textContent = item.item_name;
    this.currentBalance.textContent = item.current_balance;
    this.itemUnit.textContent = item.unit;
    this.rackNo.textContent = item.rack_no;
    this.itemStatus.textContent = item.status;
    this.itemStatus.className = `info-value ${item.status.toLowerCase()}`;
  }

  clearItemInfo() {
    this.itemNo.textContent = "-";
    this.itemName.textContent = "-";
    this.currentBalance.textContent = "-";
    this.itemUnit.textContent = "-";
    this.rackNo.textContent = "-";
    this.itemStatus.textContent = "-";
    this.itemStatus.className = "info-value";
    this.selectedItem = null;
  }

  async loadTransactionHistory() {
    try {
      const response = await fetch("Logi_qr_get_transactions.php");
      if (!response.ok) {
        throw new Error("Failed to fetch transactions");
      }

      const data = await response.json();
      console.log("Transaction data:", data);

      if (data.success && Array.isArray(data.transactions)) {
        this.renderTransactionHistory(data.transactions);
      } else {
        console.error("Invalid transaction data format:", data);
        this.renderTransactionHistory([]);
      }
    } catch (error) {
      console.error("Error loading transactions:", error);
      this.renderTransactionHistory([]);
    }
  }

  renderTransactionHistory(transactions) {
    if (!this.transactionList) {
      console.error("Transaction list element not found");
      return;
    }

    this.transactionList.innerHTML = "";

    if (!Array.isArray(transactions) || transactions.length === 0) {
      this.transactionList.innerHTML = `
        <div class="text-center text-muted py-3">
          <i class="fas fa-history fa-2x mb-2"></i>
          <p>No recent transactions</p>
        </div>
      `;
      return;
    }

    transactions.forEach((transaction) => {
      if (!transaction) return;

      const transactionElement = document.createElement("div");
      transactionElement.className = "transaction-item";

      let transactionType = transaction.type || "";
      transactionType = transactionType.trim();

      let typeClass, typeIcon;
      if (transactionType.toLowerCase() === "addition") {
        typeClass = "type-addition";
        typeIcon = "plus-circle";
      } else if (transactionType.toLowerCase() === "deduction") {
        typeClass = "type-deduction";
        typeIcon = "minus-circle";
      } else {
        typeClass = "type-deduction";
        typeIcon = "minus-circle";
        console.warn("Unknown transaction type:", transactionType);
      }

      const itemName = transaction.item_name || "Unknown Item";
      const itemNo = transaction.item_no || "N/A";
      const quantity = transaction.quantity || 0;
      const unit = transaction.unit || "pcs";
      const previousBalance = transaction.previous_balance || 0;
      const newBalance = transaction.new_balance || 0;
      const requestor = transaction.requestor || "";
      const referenceNo = transaction.reference_no || "";

      let dateStr = "Unknown date";
      try {
        if (transaction.timestamp) {
          dateStr = new Date(transaction.timestamp).toLocaleString();
        }
      } catch (e) {
        console.error("Error formatting date:", e);
      }

      transactionElement.innerHTML = `
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <span class="transaction-type ${typeClass}">
              <i class="fas fa-${typeIcon}"></i>
              ${transactionType}
            </span>
            <div class="mt-1">${itemName}</div>
            <small class="text-muted">Item No: ${itemNo}</small>
          </div>
          <div class="text-end">
            <div class="fw-bold">${quantity} ${unit}</div>
            <small class="text-muted">${dateStr}</small>
          </div>
        </div>
        <div class="d-flex justify-content-between text-muted small">
          <div>Previous: ${previousBalance}</div>
          <div>New: ${newBalance}</div>
        </div>
        ${
          requestor
            ? `<div class="text-muted small mt-1">Requestor: ${requestor}</div>`
            : ""
        }
        ${
          referenceNo
            ? `<div class="text-muted small">Reference: ${referenceNo}</div>`
            : ""
        }
      `;

      this.transactionList.appendChild(transactionElement);
    });
  }

  clearTransactionHistory() {
    if (!this.transactionList) {
      console.error("Transaction list element not found");
      return;
    }

    if (confirm("Are you sure you want to clear the transaction history?")) {
      this.transactionList.innerHTML = `
        <div class="text-center text-muted py-3">
          <i class="fas fa-history fa-2x mb-2"></i>
          <p>No recent transactions</p>
        </div>
      `;
    }
  }

  showError(message) {
    const errorModal = new bootstrap.Modal(
      document.getElementById("errorModal")
    );
    document.getElementById("errorMessage").textContent = message;
    errorModal.show();
  }

  showSuccess(message) {
    const successModal = new bootstrap.Modal(
      document.getElementById("successModal")
    );
    document.getElementById("successMessage").textContent = message;
    successModal.show();
  }

  async addItem({ item_no, quantity, reference_no, reason }) {
    try {
      const response = await fetch("Logi_barcode_add_item.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ item_no, quantity, reference_no, reason }),
      });
      const data = await response.json();
      if (data.success) {
        this.showSuccess(data.message || "Item added successfully!");
        this.loadTransactionHistory();
      } else {
        this.showError(data.message || "Failed to add item.");
      }
    } catch (error) {
      this.showError("Failed to add item.");
    }
  }

  async deductItem({ item_no, quantity, requestor, reason }) {
    try {
      const response = await fetch("Logi_barcode_deduct_item.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ item_no, quantity, requestor, reason }),
      });
      const data = await response.json();
      if (data.success) {
        this.showSuccess(data.message || "Item deducted successfully!");
        this.loadTransactionHistory();
      } else {
        this.showError(data.message || "Failed to deduct item.");
      }
    } catch (error) {
      this.showError("Failed to deduct item.");
    }
  }
}

// Initialize scanner when the page loads
document.addEventListener("DOMContentLoaded", () => {
  window.qrScanner = new QRScanner();
});
document.addEventListener("DOMContentLoaded", function () {
  // ADD ITEM BUTTON
  document.getElementById("addItemBtn").addEventListener("click", function () {
    // Get values from your input fields
    const item_no = document.getElementById("itemNo").textContent.trim();
    const quantityElem = document.getElementById('addCardQuantity');
    const ibNoElem = document.getElementById('addCardIBNo');
    const reasonElem = document.getElementById('addCardReason');

    if (!quantityElem || !ibNoElem || !reasonElem) {
      alert('One or more input fields are missing in the DOM.');
      return;
    }

    const quantity = parseInt(quantityElem.value, 10);
    const reference_no = ibNoElem.value.trim();
    const reason = reasonElem.value.trim();

    if (!item_no || !quantity || !reference_no) {
      alert("Please fill in all fields for adding an item.");
      return;
    }

    window.qrScanner.addItem({ item_no, quantity, reference_no, reason });
  });

  // DEDUCT ITEM BUTTON
  document
    .getElementById("deductItemBtn")
    .addEventListener("click", function () {
      // Get values from your input fields
      const item_no = document.getElementById("itemNo").textContent.trim();
      const quantityElem = document.getElementById('deductCardQuantity');
      const requestorElem = document.getElementById('deductCardRequestor');
      const reasonElem = document.getElementById('deductCardReason');

      if (!quantityElem || !requestorElem || !reasonElem) {
        alert('One or more input fields are missing in the DOM.');
        return;
      }

      const quantity = parseInt(quantityElem.value, 10);
      const requestor = requestorElem.value.trim();
      const reason = reasonElem.value.trim();

      if (!item_no || !quantity || !requestor) {
        alert("Please fill in all fields for deducting an item.");
        return;
      }

      window.qrScanner.deductItem({
        item_no,
        quantity,
        requestor,
        reason
      });
    });
});
