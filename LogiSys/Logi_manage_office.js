let currentOfficeId = null;
let supplyItemCount = 1;

// Add new supply item
document.getElementById("addSupplyItem").addEventListener("click", function () {
  supplyItemCount++;
  const container = document.getElementById("suppliesContainer");
  const newItem = container.querySelector(".supply-item").cloneNode(true);

  // Clear values
  newItem.querySelectorAll("input, select, textarea").forEach((input) => {
    if (input.type === "date") {
      input.value = new Date().toISOString().split("T")[0];
    } else {
      input.value = "";
    }
  });

  // Enable remove button
  newItem.querySelector(".remove-supply").disabled = false;

  container.appendChild(newItem);
  updateRemoveButtons();
});

// Remove supply item
document.addEventListener("click", function (e) {
  if (e.target.closest(".remove-supply")) {
    e.target.closest(".supply-item").remove();
    supplyItemCount--;
    updateRemoveButtons();
  }
});

function updateRemoveButtons() {
  const items = document.querySelectorAll(".supply-item");
  items.forEach((item, index) => {
    const removeBtn = item.querySelector(".remove-supply");
    removeBtn.disabled = items.length === 1;
  });
}

// View office details
function viewOfficeDetails(officeId) {
  currentOfficeId = officeId;
  // Load office details via AJAX
  fetch(`Logi_get_office_details.php?id=${officeId}`)
    .then((response) => response.json())
    .then((data) => {
      document.getElementById("officeDetailsTitle").textContent =
        data.office.office_name + " - Details";
      document.getElementById("officeInfo").innerHTML = `
                        <p><strong>Department:</strong><br>${
                          data.office.department || "N/A"
                        }</p>
                        <p><strong>Contact Person:</strong><br>${
                          data.office.contact_person || "N/A"
                        }</p>
                        <p><strong>Email:</strong><br>${
                          data.office.contact_email || "N/A"
                        }</p>
                        <p><strong>Phone:</strong><br>${
                          data.office.contact_phone || "N/A"
                        }</p>
                    `;

      // Load assigned supplies
      let suppliesHtml = "";
      data.supplies.forEach((supply) => {
        const statusBadge = getStatusBadgeClass(supply.status);
        suppliesHtml += `
                            <tr>
                                <td>${supply.item_name}</td>
                                <td>${supply.quantity} ${supply.unit}</td>
                                <td>${supply.po_number}</td>
                                <td>${supply.assigned_date}</td>
                                <td><span class="badge ${statusBadge}">${supply.status}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editSupply(${supply.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="removeSupply(${supply.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
      });
      document.getElementById("assignedSuppliesTable").innerHTML =
        suppliesHtml ||
        '<tr><td colspan="6" class="text-center text-muted">No supplies assigned</td></tr>';

      new bootstrap.Modal(document.getElementById("officeDetailsModal")).show();
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Error loading office details");
    });
}

function getStatusBadgeClass(status) {
  switch (status) {
    case "Active":
      return "bg-success";
    case "Returned":
      return "bg-info";
    case "Damaged":
      return "bg-warning";
    case "Lost":
      return "bg-danger";
    default:
      return "bg-secondary";
  }
}

// Assign supplies to office
function assignSupplies(officeId) {
  document.getElementById("selectOffice").value = officeId;
  new bootstrap.Modal(document.getElementById("assignSuppliesModal")).show();
}

// Form submissions
document
  .getElementById("addOfficeForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    // Update your fetch URL to include the correct path
    fetch("Logi_add_office.php", {
      // or './Logi_add_office.php'
      method: "POST",
      body: formData,
    })
      .then((response) => {
        // Add this debug line to see what you're actually receiving
        console.log("Response status:", response.status);
        console.log("Response headers:", response.headers);

        // Check if response is ok before parsing JSON
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.text(); // Change to text() first to debug
      })
      .then((data) => {
        console.log("Raw response:", data); // See what you're actually getting

        try {
          const jsonData = JSON.parse(data);
          if (jsonData.success) {
            alert("Office added successfully!");
            location.reload();
          } else {
            alert("Error: " + jsonData.message);
          }
        } catch (e) {
          console.error("JSON Parse Error:", e);
          console.error("Response was:", data);
          alert("Server returned invalid response");
        }
      })
      .catch((error) => {
        console.error("Fetch Error:", error);
        alert("Error adding office: " + error.message);
      });
  });

document
  .getElementById("assignSuppliesForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch("Logi_assign_supplies.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          alert("Supplies assigned successfully!");
          location.reload();
        } else {
          alert("Error: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Error assigning supplies");
      });
  });
