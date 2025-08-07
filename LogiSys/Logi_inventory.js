document.addEventListener("DOMContentLoaded", function () {
  // Existing code...

  // Print button click handler
  document.getElementById("printBtn").addEventListener("click", function () {
    // Open the print options modal
    var printOptionsModal = new bootstrap.Modal(
      document.getElementById("printOptionsModal")
    );
    printOptionsModal.show();
  });

  // Print Selected button click handler
  document
    .getElementById("printSelectedBtn")
    .addEventListener("click", function () {
      // Collect selected statuses
      var selectedStatuses = [];
      if (document.getElementById("printAvailable").checked)
        selectedStatuses.push("Available");
      if (document.getElementById("printLowStock").checked)
        selectedStatuses.push("Low Stock");
      if (document.getElementById("printOutOfStock").checked)
        selectedStatuses.push("Out of Stock");
      if (document.getElementById("printDiscontinued").checked)
        selectedStatuses.push("Discontinued");

      // Close the modal
      var printOptionsModal = bootstrap.Modal.getInstance(
        document.getElementById("printOptionsModal")
      );
      printOptionsModal.hide();

      // Send POST request to generate and preview PDF
      var formData = new FormData();
      selectedStatuses.forEach(function (status) {
        formData.append("statuses[]", status);
      });

      fetch("Logi_print_data_stock.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.blob())
        .then((blob) => {
          var url = window.URL.createObjectURL(blob);
          window.open(url, "_blank");
        })
        .catch((error) => {
          alert("Failed to generate PDF.");
          console.error(error);
        });
    });
});
document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM loaded, initializing bulk update functionality...");

  const confirmBulkUploadBtn = document.getElementById("confirmBulkUpload");
  const bulkUpdateDateInput = document.getElementById("bulkUpdateDate");

  // Debug: Check if elements exist
  if (!confirmBulkUploadBtn) {
    console.error("confirmBulkUpload button not found!");
    alert("Error: Update button element not found in DOM!");
    return;
  }

  if (!bulkUpdateDateInput) {
    console.error("bulkUpdateDate input not found!");
    alert("Error: Date input element not found in DOM!");
    return;
  }

  console.log("Elements found successfully");
  console.log("Button element:", confirmBulkUploadBtn);
  console.log("Date input element:", bulkUpdateDateInput);

  confirmBulkUploadBtn.addEventListener("click", function (e) {
    console.log("=== UPDATE BUTTON CLICKED ===");
    console.log("Event object:", e);
    console.log("Button state before:", {
      disabled: confirmBulkUploadBtn.disabled,
      textContent: confirmBulkUploadBtn.textContent,
    });

    // Prevent any default behavior
    e.preventDefault();
    e.stopPropagation();

    const effectiveDate = bulkUpdateDateInput.value.trim();
    console.log("Selected date:", effectiveDate);
    console.log("Date input value raw:", bulkUpdateDateInput.value);

    if (!effectiveDate) {
      console.warn("No date selected");
      alert("Please select an effective date.");
      return;
    }

    // Validate date format
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(effectiveDate)) {
      console.error("Invalid date format:", effectiveDate);
      alert("Invalid date format. Please use YYYY-MM-DD format.");
      return;
    }

    // Disable button to prevent multiple clicks
    confirmBulkUploadBtn.disabled = true;
    confirmBulkUploadBtn.textContent = "Processing...";
    console.log("Button disabled, starting request...");

    const formData = new FormData();
    formData.append("effectiveDate", effectiveDate);

    console.log("FormData contents:");
    for (let [key, value] of formData.entries()) {
      console.log(`${key}: ${value}`);
    }

    console.log("Sending request to bulk_update_inventory.php...");
    console.log("Request timestamp:", new Date().toISOString());

    // Add timeout to the fetch request
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
      controller.abort();
      console.error("Request timed out after 30 seconds");
    }, 30000);

    fetch("bulk_update_inventory.php", {
      method: "POST",
      body: formData,
      signal: controller.signal,
    })
      .then((response) => {
        clearTimeout(timeoutId);
        console.log("=== RESPONSE RECEIVED ===");
        console.log("Response object:", response);
        console.log("Response status:", response.status);
        console.log("Response statusText:", response.statusText);
        console.log("Response headers:", response.headers);
        console.log("Response URL:", response.url);
        console.log("Response type:", response.type);
        console.log("Response ok:", response.ok);

        if (!response.ok) {
          throw new Error(
            `HTTP error! status: ${response.status} - ${response.statusText}`
          );
        }

        // Check content type
        const contentType = response.headers.get("content-type");
        console.log("Content-Type:", contentType);

        if (!contentType || !contentType.includes("application/json")) {
          console.warn(
            "Response is not JSON, attempting to read as text first..."
          );
          return response.text().then((text) => {
            console.log("Raw response text:", text);
            try {
              return JSON.parse(text);
            } catch (parseError) {
              console.error("Failed to parse response as JSON:", parseError);
              throw new Error(
                `Server returned non-JSON response: ${text.substring(
                  0,
                  200
                )}...`
              );
            }
          });
        }

        return response.json();
      })
      .then((data) => {
        console.log("=== RESPONSE DATA PARSED ===");
        console.log("Response data:", data);
        console.log("Data type:", typeof data);
        console.log("Data keys:", Object.keys(data || {}));

        if (!data) {
          throw new Error("Received empty response from server");
        }

        if (data.success === true) {
          console.log("✅ SUCCESS:", data.message);
          console.log("Items processed:", data.items_processed);
          console.log("Processed items:", data.items);

          let alertMessage = "Bulk update successful!\n\n";
          alertMessage += "Details: " + data.message + "\n";
          if (data.items_processed) {
            alertMessage += "Items processed: " + data.items_processed + "\n";
          }

          alert(alertMessage);

          // Close modal
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("bulkUpdateModal")
          );
          if (modal) {
            console.log("Closing modal...");
            modal.hide();
          } else {
            console.warn("Modal instance not found");
          }

          // Optionally, reload the page or update the table
          // location.reload();
        } else {
          console.error("❌ FAILURE:", data.message);
          console.log("Full error response:", data);
          alert(
            "Bulk update failed: " + (data.message || "Unknown error occurred")
          );
        }
      })
      .catch((error) => {
        clearTimeout(timeoutId);
        console.error("=== ERROR CAUGHT ===");
        console.error("Error type:", error.name);
        console.error("Error message:", error.message);
        console.error("Error stack:", error.stack);
        console.error("Full error object:", error);

        let errorMessage = "An error occurred during the bulk update:\n\n";

        if (error.name === "AbortError") {
          errorMessage += "Request timed out (took longer than 30 seconds)";
        } else if (error.message.includes("HTTP error")) {
          errorMessage += "Server error: " + error.message;
        } else if (error.message.includes("Failed to fetch")) {
          errorMessage +=
            "Network error: Could not connect to server. Check if the server is running and the URL is correct.";
        } else {
          errorMessage += error.message;
        }

        alert(errorMessage);
      })
      .finally(() => {
        console.log("=== REQUEST COMPLETED ===");
        // Re-enable button
        confirmBulkUploadBtn.disabled = false;
        confirmBulkUploadBtn.textContent = "Update";
        console.log("Button re-enabled");
        console.log("Final timestamp:", new Date().toISOString());
      });
  });

  // Additional debugging: Log when modal is shown
  const bulkUpdateModal = document.getElementById("bulkUpdateModal");
  if (bulkUpdateModal) {
    bulkUpdateModal.addEventListener("shown.bs.modal", function () {
      console.log("Modal is now visible");
      // Set focus on date input when modal opens
      bulkUpdateDateInput.focus();
    });

    bulkUpdateModal.addEventListener("hidden.bs.modal", function () {
      console.log("Modal is now hidden");
      // Clear the date input when modal closes
      bulkUpdateDateInput.value = "";
    });
  } else {
    console.warn("bulkUpdateModal element not found for event listeners");
  }

  // Test the PHP endpoint availability
  console.log("Testing PHP endpoint availability...");
  fetch("bulk_update_inventory.php", {
    method: "GET",
  })
    .then((response) => {
      console.log("PHP endpoint test - Status:", response.status);
      if (response.status === 200) {
        console.log("✅ PHP file is accessible");
      } else {
        console.warn("⚠️ PHP file returned status:", response.status);
      }
    })
    .catch((error) => {
      console.error("❌ PHP file is not accessible:", error.message);
    });
});
