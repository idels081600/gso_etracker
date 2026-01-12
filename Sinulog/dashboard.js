let html5QrCode;

document.getElementById("scanQR").addEventListener("click", function () {
  var myModal = new bootstrap.Modal(document.getElementById("qrModal"));
  myModal.show();

  setTimeout(() => {
    html5QrCode = new Html5Qrcode("qr-reader");

    html5QrCode
      .start(
        {
          facingMode: "environment",
        },
        {
          fps: 10,
          qrbox: {
            width: 250,
            height: 250,
          },
        },
        (decodedText, decodedResult) => {
          document.getElementById("scanResultText").textContent = decodedText;
          document.getElementById("scanResult").style.display = "block";

          // Send scanned number to scan_members.php
          fetch("scan_members.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "number=" + encodeURIComponent(decodedText),
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                document.getElementById("scanResultText").textContent = decodedText + " - " + data.message;
                loadMembers(); // Refresh the table to show updated status
              } else {
                document.getElementById("scanResultText").textContent = "Error: " + data.message;
              }
            })
            .catch((error) => {
              console.error("Error:", error);
              document.getElementById("scanResultText").textContent = "System Error";
            });
        },
        (errorMessage) => {
          // Scanning errors are common and can be ignored
        }
      )
      .catch((err) => {
        alert("Unable to start camera: " + err);
      });
  }, 500);
});

document
  .getElementById("qrModal")
  .addEventListener("hidden.bs.modal", function () {
    if (html5QrCode) {
      html5QrCode.stop().catch((err) => {
        console.error("Error stopping scanner:", err);
      });
    }
    document.getElementById("scanResult").style.display = "none";
  });

// View details buttons
document.querySelectorAll(".viewBtn").forEach((button) => {
  button.addEventListener("click", function () {
    var modal = new bootstrap.Modal(document.getElementById("viewModal"));
    modal.show();
  });
});

// Add member link
document
  .getElementById("addMemberLink")
  .addEventListener("click", function (e) {
    e.preventDefault();
    var modal = new bootstrap.Modal(document.getElementById("addMemberModal"));
    modal.show();
  });

// Save member button
document.getElementById("saveMemberBtn").addEventListener("click", function () {
  var name = document.getElementById("memberName").value;
  var number = document.getElementById("memberNumber").value;
  var role = document.getElementById("memberRole").value;
  var phone = document.getElementById("memberPhone").value;

  if (name && number && role && phone) {
    console.log("Sending data:", {
      name,
      number,
      role,
      phone,
    });

    // Send AJAX request
    fetch("add_member.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body:
        "name=" +
        encodeURIComponent(name) +
        "&number=" +
        encodeURIComponent(number) +
        "&role=" +
        encodeURIComponent(role) +
        "&phone=" +
        encodeURIComponent(phone),
    })
      .then((response) => {
        console.log("Fetch response status:", response.status);

        // Check if response is ok
        if (!response.ok) {
          throw new Error("HTTP error! status: " + response.status);
        }

        return response.json();
      })
      .then((data) => {
        console.log("Response data:", data);

        if (data.success) {
          // Success case
          alert(data.message);

          var modal = bootstrap.Modal.getInstance(
            document.getElementById("addMemberModal")
          );
          modal.hide();

          // Reset form
          document.getElementById("addMemberForm").reset();

          // Optionally, refresh the table or add the new row
          // You can access the inserted data: data.data.id, data.data.name, etc.
        } else {
          // Error case - display detailed error
          let errorMessage = data.message;

          // If there are multiple validation errors, display them all
          if (data.errors && Array.isArray(data.errors)) {
            errorMessage += "\n\nDetails:\n- " + data.errors.join("\n- ");
          }

          // Log error type if available
          if (data.error_type) {
            console.error("Error Type:", data.error_type);
          }

          alert(errorMessage);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert(
          "An error occurred while adding the member.\n\nError: " +
            error.message
        );
      });
  } else {
    alert("Please fill all fields");
  }
});


function loadMembers() {
    fetch('fetch_members.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Loaded members:', data);
            
            if (data.success) {
                displayMembers(data.data);
                
                // Use pre-calculated counts from database
                if (data.roleCounts) {
                    document.getElementById('dancersCount').textContent = data.roleCounts.dancers;
                    document.getElementById('propsmenCount').textContent = data.roleCounts.propsmen;
                    document.getElementById('instrumentalsCount').textContent = data.roleCounts.instrumentals;
                } else {
                    // Fallback to client-side counting
                    updateRoleCounts(data.data);
                }
            } else {
                document.getElementById('membersTableBody').innerHTML = 
                    '<tr><td colspan="7" class="text-center text-danger">Error: ' + data.message + '</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading members:', error);
            document.getElementById('membersTableBody').innerHTML = 
                '<tr><td colspan="7" class="text-center text-danger">Failed to load members</td></tr>';
        });
}

// Function to display members in the table
function displayMembers(members) {
  const tbody = document.getElementById("membersTableBody");

  if (members.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="7" class="text-center">No members found</td></tr>';
    return;
  }

  let html = "";
  members.forEach((member, index) => {
    const badgeClass = member.status === 'Present' ? 'bg-success' : 'bg-danger';
    html += `
            <tr>
                <td>${escapeHtml(member.number)}</td>
                <td>${escapeHtml(member.name)}</td>
                <td>${escapeHtml(member.role)}</td>
                <td><span class="badge ${badgeClass}">${escapeHtml(member.status)}</span></td>
                <td>
                    <button class="viewBtn btn btn-primary btn-sm" data-id="${
                      member.id
                    }" data-member='${JSON.stringify(member)}'>
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
  });

  tbody.innerHTML = html;
}

// Helper function to escape HTML and prevent XSS
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
}

// Function to count members by role (only active status)
function updateRoleCounts(members) {
  let dancers = 0;
  let propsmen = 0;
  let instrumentals = 0;

  members.forEach((member) => {
    // Only count if status is active
    const status = member.status ? member.status.toLowerCase() : "";

    if (status === "active") {
      const role = member.role.toLowerCase();

      if (role.includes("dancer")) {
        dancers++;
      } else if (role.includes("props")) {
        propsmen++;
      } else if (role.includes("instrument")) {
        instrumentals++;
      }
    }
  });

  // Update the display
  document.getElementById("dancersCount").textContent = dancers;
  document.getElementById("propsmenCount").textContent = propsmen;
  document.getElementById("instrumentalsCount").textContent = instrumentals;
}
// Load members when page loads
document.addEventListener("DOMContentLoaded", function () {
  loadMembers();
});
