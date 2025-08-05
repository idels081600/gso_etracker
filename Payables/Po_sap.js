document.querySelectorAll(".days-left-num").forEach(function (el) {
  var days = parseInt(el.textContent, 10);
  el.classList.remove("days-left-red", "days-left-orange");
  if (!isNaN(days)) {
    if (days <= 14) {
      el.classList.add("days-left-red");
    } else if (days <= 28) {
      el.classList.add("days-left-orange");
    }
  }
});

document.addEventListener("DOMContentLoaded", function () {
  // Edit button click handler
  document.querySelectorAll(".edit-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var id = this.getAttribute("data-id");
      // Fetch data for this ID
      fetch("fetch_rfq.php?id=" + encodeURIComponent(id))
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            document.getElementById("edit_id").value = data.row.id;
            document.getElementById("edit_rfq_no").value = data.row.RFQ_no;
            document.getElementById("edit_supplier").value = data.row.supplier;
            document.getElementById("edit_description").value =
              data.row.description;
            document.getElementById("edit_amount").value =
              data.row.amount &&
              data.row.amount !== "0" &&
              data.row.amount !== "0.00"
                ? parseFloat(data.row.amount).toLocaleString("en-US", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })
                : "";
            document.getElementById("edit_date_received").value = data.row
              .date_received
              ? data.row.date_received.split(" ")[0]
              : "";
            document.getElementById("edit_office").value = data.row.office;
            document.getElementById("edit_received_by").value =
              data.row.received_by;
            var editModal = new bootstrap.Modal(
              document.getElementById("editTransmittalModal")
            );
            editModal.show();
          } else {
            alert("Failed to fetch data.");
          }
        })
        .catch(() => alert("Error fetching data."));
    });
  });

  // Edit form submit handler (skeleton)
  document
    .getElementById("editTransmittalForm")
    .addEventListener("submit", function (e) {
      e.preventDefault();
      var form = e.target;
      var formData = new FormData(form);

      // Debug: Log all form data
      for (let pair of formData.entries()) {
        console.log(pair[0] + ": " + pair[1]);
      }

      fetch("update_rfq.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          console.log("Response from server:", data); // Debug
          if (data.success) {
            var editModal = bootstrap.Modal.getInstance(
              document.getElementById("editTransmittalModal")
            );
            if (editModal) editModal.hide();
            location.reload();
          } else {
            alert("Update failed: " + (data.error || "Unknown error"));
          }
        })
        .catch((err) => {
          console.error("Fetch error:", err); // Debug
          alert("Error updating transmittal.");
        });
    });

  // Delete button click handler
  document.querySelectorAll(".delete-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var id = this.getAttribute("data-id");
      if (confirm("Are you sure you want to delete this transmittal?")) {
        fetch("delete_rfq_row.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "id=" + encodeURIComponent(id),
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              location.reload();
            } else {
              alert("Delete failed: " + (data.error || "Unknown error"));
            }
          })
          .catch(() => alert("Error deleting transmittal."));
      }
    });
  });
});
document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("searchInput");
  const searchButton = document.getElementById("searchButton");
  const table = document.querySelector(".table");
  const rows = table.getElementsByTagName("tr");

  function performSearch() {
    const searchTerm = searchInput.value.toLowerCase();
    for (let i = 1; i < rows.length; i++) {
      const cells = rows[i].getElementsByTagName("td");
      let found = false;
      for (let j = 0; j < cells.length; j++) {
        const cellText = cells[j].textContent.toLowerCase();
        if (cellText.indexOf(searchTerm) > -1) {
          found = true;
          break;
        }
      }
      if (found) {
        rows[i].style.display = "";
      } else {
        rows[i].style.display = "none";
      }
    }
  }

  searchButton.addEventListener("click", performSearch);

  searchInput.addEventListener("keyup", function (event) {
    performSearch();
  });
});
