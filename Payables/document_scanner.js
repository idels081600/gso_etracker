document.addEventListener("DOMContentLoaded", function () {
  const csrfToken =
    document.querySelector('meta[name="payables-csrf-token"]')?.content || "";
  const modeButtons = document.querySelectorAll("[data-scan-direction]");
  const officeSelect = document.getElementById("scannerOffice");
  const form = document.getElementById("scannerForm");
  const input = document.getElementById("scannerInput");
  const statusEl = document.getElementById("scannerStatus");
  const emptyResult = document.getElementById("emptyResult");
  const resultPanel = document.getElementById("scanResult");
  const resultCard = document.getElementById("scanResultCard");
  const latestScanTime = document.getElementById("latestScanTime");
  const matchChoicePanel = document.getElementById("matchChoicePanel");
  const matchChoices = document.getElementById("matchChoices");
  const historyBody = document.getElementById("scanHistoryBody");
  const toggleCameraBtn = document.getElementById("toggleCameraBtn");
  const cameraPanel = document.getElementById("cameraPanel");
  const video = document.getElementById("scannerVideo");
  const cameraHelp = document.getElementById("cameraHelp");
  const bulkModeToggle = document.getElementById("bulkModeToggle");
  const bulkPanel = document.getElementById("bulkPanel");
  const bulkList = document.getElementById("bulkList");
  const bulkCount = document.getElementById("bulkCount");
  const clearBulkBtn = document.getElementById("clearBulkBtn");
  const saveBulkBtn = document.getElementById("saveBulkBtn");
  let activeDirection = "IN";
  let bulkItems = [];
  let cameraStream = null;
  let cameraDetector = null;
  let cameraRunning = false;
  let lastCameraCode = "";
  let lastCameraAt = 0;
  let usbScanTimer = null;
  let usbScanProcessing = false;

  function parseJsonResponse(response) {
    return response.text().then(function (text) {
      try {
        return JSON.parse(text);
      } catch (error) {
        throw new Error(
          text.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim() ||
            "Invalid server response."
        );
      }
    });
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function setStatus(message, type) {
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.classList.toggle("is-success", type === "success");
    statusEl.classList.toggle("is-error", type === "error");
  }

  function selectedOffice() {
    return officeSelect ? officeSelect.value : "BAC";
  }

  function setDirection(direction) {
    activeDirection = direction === "OUT" ? "OUT" : "IN";
    modeButtons.forEach(function (button) {
      button.classList.toggle("is-active", button.dataset.scanDirection === activeDirection);
    });
    input?.focus();
  }

  function resultValue(key, value) {
    const target = resultPanel?.querySelector('[data-result="' + key + '"]');
    if (target) target.textContent = value || "-";
  }

  function showResult(event) {
    if (emptyResult) emptyResult.classList.add("d-none");
    if (resultPanel) resultPanel.classList.remove("d-none");
    if (matchChoicePanel) matchChoicePanel.classList.add("d-none");
    if (latestScanTime) latestScanTime.textContent = event.scanned_at || "Just now";

    resultValue("record_type", event.record_type);
    resultValue("document_no", event.document_no);
    resultValue("direction", event.direction);
    resultValue("office", event.office);
    resultValue("title", event.title);
    resultValue("scanned_by", event.scanned_by);
    resultCard?.animate(
      [
        { transform: "translateY(0)", boxShadow: "0 8px 22px rgba(15, 23, 42, 0.08)" },
        { transform: "translateY(-2px)", boxShadow: "0 14px 34px rgba(32, 167, 151, 0.18)" },
        { transform: "translateY(0)", boxShadow: "0 8px 22px rgba(15, 23, 42, 0.08)" },
      ],
      { duration: 420, easing: "ease-out" }
    );
  }

  function directionClass(direction) {
    return direction === "IN" ? "is-in" : "is-out";
  }

  function historyRow(event) {
    return (
      "<tr>" +
      '<td><span class="scanner-type-badge">' + escapeHtml(event.record_type) + "</span></td>" +
      '<td class="scanner-doc-cell"><strong>' + escapeHtml(event.document_no) + "</strong><span>" + escapeHtml(event.title) + "</span></td>" +
      '<td><span class="scanner-direction ' + directionClass(event.direction) + '">' + escapeHtml(event.direction) + "</span></td>" +
      "<td>" + escapeHtml(event.office) + "</td>" +
      "<td>" + escapeHtml(event.scanned_by) + "</td>" +
      "<td>" + escapeHtml(event.scanned_at) + "</td>" +
      "</tr>"
    );
  }

  function prependHistory(event) {
    if (!historyBody) return;
    historyBody.querySelector(".scanner-empty-row")?.remove();
    historyBody.insertAdjacentHTML("afterbegin", historyRow(event));
    while (historyBody.children.length > 60) {
      historyBody.lastElementChild?.remove();
    }
  }

  function isBulkMode() {
    return Boolean(bulkModeToggle && bulkModeToggle.checked);
  }

  function bulkKey(match) {
    return (match.record_type || "") + ":" + (match.record_id || "");
  }

  function updateBulkPanel() {
    if (!bulkPanel || !bulkList || !bulkCount || !saveBulkBtn) return;
    const enabled = isBulkMode();
    bulkPanel.classList.toggle("d-none", !enabled);
    bulkCount.textContent = bulkItems.length + (bulkItems.length === 1 ? " document ready" : " documents ready");
    saveBulkBtn.disabled = !enabled || bulkItems.length === 0;

    if (!bulkItems.length) {
      bulkList.innerHTML = '<div class="scanner-bulk-empty">No documents in batch yet.</div>';
      return;
    }

    bulkList.innerHTML = bulkItems
      .map(function (item, index) {
        return (
          '<div class="scanner-bulk-row">' +
          '<div><strong>' + escapeHtml(item.record_type + " " + item.document_no) + "</strong><span>" + escapeHtml(item.title || item.party || "Document") + "</span></div>" +
          '<button type="button" data-remove-bulk="' + index + '" aria-label="Remove ' + escapeHtml(item.document_no) + '"><i class="fas fa-times"></i></button>' +
          "</div>"
        );
      })
      .join("");
  }

  function addBulkItem(match, source) {
    const key = bulkKey(match);
    if (bulkItems.some(function (item) { return bulkKey(item) === key; })) {
      setStatus(match.document_no + " is already in the batch.", "error");
      input.value = "";
      input.focus();
      return;
    }

    bulkItems.push(Object.assign({}, match, { scan_source: source || "USB" }));
    updateBulkPanel();
    setStatus(match.document_no + " added to batch.", "success");
    if (emptyResult) emptyResult.classList.add("d-none");
    if (resultPanel) resultPanel.classList.remove("d-none");
    if (matchChoicePanel) matchChoicePanel.classList.add("d-none");
    if (latestScanTime) latestScanTime.textContent = "Pending batch";
    resultValue("record_type", match.record_type);
    resultValue("document_no", match.document_no);
    resultValue("direction", activeDirection + " pending");
    resultValue("office", selectedOffice());
    resultValue("title", match.title || match.party || "Document");
    resultValue("scanned_by", "Pending save");
    input.value = "";
    input.focus();
  }

  function clearBulkItems() {
    bulkItems = [];
    updateBulkPanel();
    setStatus("Batch cleared.", "");
    input.focus();
  }

  function saveBulkBatch() {
    if (!bulkItems.length) {
      setStatus("Scan at least one document before saving the batch.", "error");
      input.focus();
      return;
    }

    const body = new URLSearchParams();
    body.set("csrf_token", csrfToken);
    body.set("direction", activeDirection);
    body.set("office", selectedOffice());
    body.set("items", JSON.stringify(bulkItems));
    saveBulkBtn.disabled = true;
    setStatus("Saving " + bulkItems.length + " documents...", "");

    fetch("scan_bulk_save.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(parseJsonResponse)
      .then(function (data) {
        if (!data.success) {
          throw new Error(data.error || "Unable to save batch.");
        }
        (data.events || []).slice().reverse().forEach(function (event) {
          prependHistory(event);
        });
        if (data.events && data.events.length) {
          showResult(data.events[0]);
        }
        bulkItems = [];
        updateBulkPanel();
        setStatus((data.saved_count || 0) + " documents saved.", "success");
        input.focus();
      })
      .catch(function (error) {
        setStatus(error.message || "Unable to save batch.", "error");
      })
      .finally(function () {
        updateBulkPanel();
      });
  }

  function saveScan(match, source) {
    const body = new URLSearchParams();
    body.set("csrf_token", csrfToken);
    body.set("record_type", match.record_type || "");
    body.set("record_id", match.record_id || "");
    body.set("direction", activeDirection);
    body.set("office", selectedOffice());
    body.set("scan_source", source || "USB");

    setStatus("Saving scan...", "");
    return fetch("scan_save.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(parseJsonResponse)
      .then(function (data) {
        if (!data.success) {
          throw new Error(data.error || "Unable to save scan.");
        }
        showResult(data.event);
        prependHistory(data.event);
        setStatus(data.event.document_no + " saved " + data.event.direction + " at " + data.event.office + ".", "success");
        input.value = "";
        input.focus();
      });
  }

  function showMatches(matches, source) {
    if (!matchChoicePanel || !matchChoices) return;
    matchChoices.innerHTML = matches
      .map(function (match, index) {
        return (
          '<button type="button" data-match-index="' + index + '">' +
          "<strong>" + escapeHtml(match.record_type + " " + match.document_no) + "</strong>" +
          "<span>" + escapeHtml(match.title || match.party || "Document") + "</span>" +
          "</button>"
        );
      })
      .join("");
    matchChoicePanel.classList.remove("d-none");
    emptyResult?.classList.add("d-none");
    resultPanel?.classList.add("d-none");
    matchChoices.querySelectorAll("[data-match-index]").forEach(function (button) {
      button.addEventListener("click", function () {
        const match = matches[Number(button.dataset.matchIndex)];
        if (isBulkMode()) {
          addBulkItem(match, source);
          return;
        }
        saveScan(match, source).catch(function (error) {
          setStatus(error.message || "Unable to save scan.", "error");
        });
      });
    });
  }

  function processCode(code, source) {
    const value = String(code || "").trim();
    if (!value) {
      setStatus("Scan or enter a registered barcode.", "error");
      input.focus();
      return Promise.resolve();
    }

    setStatus("Checking barcode " + value + "...", "");
    return fetch("scan_lookup.php?code=" + encodeURIComponent(value), {
      headers: { Accept: "application/json" },
    })
      .then(parseJsonResponse)
      .then(function (data) {
        if (!data.success || !Array.isArray(data.matches) || !data.matches.length) {
          throw new Error(data.error || "No registered barcode found.");
        }
        if (data.matches.length === 1) {
          if (isBulkMode()) {
            addBulkItem(data.matches[0], source);
            return null;
          }
          return saveScan(data.matches[0], source);
        }
        setStatus("Choose the matching document.", "");
        showMatches(data.matches, source);
        return null;
      })
      .catch(function (error) {
        setStatus(error.message || "Unable to scan document.", "error");
        input.select();
      });
  }

  function splitScannedValues(value) {
    return String(value || "")
      .split(/[,\n\r\t]+/)
      .map(function (item) { return item.trim(); })
      .filter(Boolean);
  }

  function processUsbInputQueue() {
    if (!input || usbScanProcessing) return;
    const values = splitScannedValues(input.value);
    if (!values.length) return;

    usbScanProcessing = true;
    input.value = "";
    values
      .reduce(function (chain, value) {
        return chain.then(function () {
          return processCode(value, "USB");
        });
      }, Promise.resolve())
      .finally(function () {
        usbScanProcessing = false;
        input.focus();
      });
  }

  function scheduleUsbScanProcessing() {
    if (!isBulkMode() || !input) return;
    window.clearTimeout(usbScanTimer);
    usbScanTimer = window.setTimeout(processUsbInputQueue, 160);
  }

  function stopCamera() {
    cameraRunning = false;
    if (cameraStream) {
      cameraStream.getTracks().forEach(function (track) {
        track.stop();
      });
    }
    cameraStream = null;
    if (cameraPanel) cameraPanel.hidden = true;
    if (toggleCameraBtn) {
      toggleCameraBtn.innerHTML = '<i class="fas fa-camera"></i> Open Camera Scanner';
    }
  }

  function cameraLoop() {
    if (!cameraRunning || !cameraDetector || !video) return;
    cameraDetector
      .detect(video)
      .then(function (codes) {
        if (codes && codes.length) {
          const code = codes[0].rawValue || "";
          const now = Date.now();
          if (code && (code !== lastCameraCode || now - lastCameraAt > 2500)) {
            lastCameraCode = code;
            lastCameraAt = now;
            processCode(code, "CAMERA");
          }
        }
      })
      .catch(function () {
        if (cameraHelp) {
          cameraHelp.textContent = "Unable to read the camera frame. Try USB/manual scan.";
        }
      })
      .finally(function () {
        if (cameraRunning) {
          window.requestAnimationFrame(cameraLoop);
        }
      });
  }

  function startCamera() {
    if (!("BarcodeDetector" in window)) {
      setStatus("Camera barcode scanning is not supported in this browser.", "error");
      if (cameraHelp) cameraHelp.textContent = "Use a USB scanner or manual input on this browser.";
      return;
    }

    window.BarcodeDetector.getSupportedFormats()
      .then(function (formats) {
        const wantedFormats = formats.includes("code_128") ? ["code_128"] : formats;
        cameraDetector = new window.BarcodeDetector({ formats: wantedFormats });
        return navigator.mediaDevices.getUserMedia({
          video: { facingMode: "environment" },
          audio: false,
        });
      })
      .then(function (stream) {
        cameraStream = stream;
        video.srcObject = stream;
        return video.play();
      })
      .then(function () {
        cameraPanel.hidden = false;
        cameraRunning = true;
        if (toggleCameraBtn) {
          toggleCameraBtn.innerHTML = '<i class="fas fa-stop"></i> Close Camera Scanner';
        }
        if (cameraHelp) {
          cameraHelp.textContent = "Point the camera at a registered sticker barcode.";
        }
        cameraLoop();
      })
      .catch(function (error) {
        stopCamera();
        setStatus(error.message || "Unable to open camera.", "error");
      });
  }

  modeButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      setDirection(button.dataset.scanDirection);
    });
  });

  form?.addEventListener("submit", function (event) {
    event.preventDefault();
    window.clearTimeout(usbScanTimer);
    processUsbInputQueue();
  });

  input?.addEventListener("input", function () {
    if (!isBulkMode()) return;
    if (/[,\n\r\t]/.test(input.value)) {
      window.clearTimeout(usbScanTimer);
      processUsbInputQueue();
      return;
    }
    scheduleUsbScanProcessing();
  });

  toggleCameraBtn?.addEventListener("click", function () {
    if (cameraRunning) {
      stopCamera();
    } else {
      startCamera();
    }
  });

  bulkModeToggle?.addEventListener("change", function () {
    updateBulkPanel();
    setStatus(isBulkMode() ? "Bulk Scan Mode is on." : "Ready to scan", isBulkMode() ? "success" : "");
    input?.focus();
  });

  clearBulkBtn?.addEventListener("click", clearBulkItems);
  saveBulkBtn?.addEventListener("click", saveBulkBatch);
  bulkList?.addEventListener("click", function (event) {
    const removeButton = event.target.closest("[data-remove-bulk]");
    if (!removeButton) return;
    bulkItems.splice(Number(removeButton.dataset.removeBulk), 1);
    updateBulkPanel();
    input?.focus();
  });

  window.addEventListener("beforeunload", stopCamera);
  updateBulkPanel();
  setDirection("IN");
});
