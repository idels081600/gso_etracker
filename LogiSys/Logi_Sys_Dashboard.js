// Auto-submit form when filters change
document.addEventListener("DOMContentLoaded", function () {
  const dateInput = document.getElementById("date");
  const statusSelect = document.getElementById("status");
  const officeSelect = document.getElementById("office");

  dateInput.addEventListener("change", function () {
    this.form.submit();
  });

  statusSelect.addEventListener("change", function () {
    this.form.submit();
  });

  officeSelect.addEventListener("change", function () {
    this.form.submit();
  });
});

// Function to update request status
function updateRequestStatus(requestId, status) {
  if (
    confirm(`Are you sure you want to ${status.toLowerCase()} this request?`)
  ) {
    fetch("update_request_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        request_id: requestId,
        status: status,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          location.reload();
        } else {
          alert("Error: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Error updating request status");
      });
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const selectAllCheckbox = document.getElementById("selectAllOffices");
  const checkboxes = document.querySelectorAll(".office-checkbox");

  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener("change", () => {
      checkboxes.forEach((cb) => (cb.checked = selectAllCheckbox.checked));
    });
  }
});

function handlePrint(type) {
  const selectedOffices = Array.from(
    document.querySelectorAll(".office-checkbox:checked")
  ).map((cb) => cb.value);

  const selectedDate = document.getElementById("printDate").value;

  if (selectedOffices.length === 0) {
    alert("Please select at least one office.");
    return;
  }

  if (!selectedDate) {
    alert("Please select a date.");
    return;
  }

  const form = document.createElement("form");
  form.method = "POST";
  form.action = "print_request.php"; // Your print handler
  form.target = "_blank";

  // Create and append type input
  const typeInput = document.createElement("input");
  typeInput.type = "hidden";
  typeInput.name = "type";
  typeInput.value = type;
  form.appendChild(typeInput);

  // Create and append date input
  const dateInput = document.createElement("input");
  dateInput.type = "hidden";
  dateInput.name = "print_date";
  dateInput.value = selectedDate;
  form.appendChild(dateInput);

  // Create and append office inputs
  selectedOffices.forEach((office) => {
    const officeInput = document.createElement("input");
    officeInput.type = "hidden";
    officeInput.name = "offices[]";
    officeInput.value = office;
    form.appendChild(officeInput);
  });

  // Append the form to the document, submit, and remove
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
}
