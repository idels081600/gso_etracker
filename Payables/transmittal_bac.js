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
      fetch("fetch_transmittal_row.php?id=" + encodeURIComponent(id))
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            document.getElementById("edit_id").value = data.row.id;
            document.getElementById("edit_ib_no").value = data.row.ib_no;
            document.getElementById("edit_project_name").value =
              data.row.project_name;
            document.getElementById("edit_date_received").value =
              data.row.date_received ? data.row.date_received.split(' ')[0] : '';
            document.getElementById("edit_transmittal_type").value =
              data.row.transmittal_type;
            document.getElementById("edit_office").value = data.row.office;
            document.getElementById("edit_received_by").value =
              data.row.received_by;
            document.getElementById("edit_winning_bidders").value =
              data.row.winning_bidders;
            document.getElementById("edit_NOA_no").value = data.row.NOA_no;
            document.getElementById("edit_COA_date").value = data.row.COA_date;
            document.getElementById("edit_notice_proceed").value =
              data.row.notice_proceed;
            document.getElementById("edit_deadline").value =
              data.row.calendar_days;
            document.getElementById("edit_amount").value = data.row.amount || '';
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
      fetch("update_transmittal_row.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Optionally close the modal
            var editModal = bootstrap.Modal.getInstance(
              document.getElementById("editTransmittalModal")
            );
            if (editModal) editModal.hide();
            // Reload the page or update the table row
            location.reload();
          } else {
            alert("Update failed: " + (data.error || "Unknown error"));
          }
        })
        .catch(() => alert("Error updating transmittal."));
    });

  // Delete button click handler
  document.querySelectorAll(".delete-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var id = this.getAttribute("data-id");
      if (confirm("Are you sure you want to delete this transmittal?")) {
        fetch("delete_transmittal_row.php", {
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
