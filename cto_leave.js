document.addEventListener("DOMContentLoaded", function () {
  const calendarElement = document.getElementById("calendar");
  const monthSelect = document.getElementById("monthSelect");
  const yearSelect = document.getElementById("yearSelect");

  // Fetch both Leave and CTO data
  Promise.all([
    fetch("get_leave_events.php").then((response) => response.json()),
    fetch("get_cto_events.php").then((response) => response.json()),
  ])
    .then(([leaveEvents, ctoEvents]) => {
      const monthNames = [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
      ];

      function generateCalendar(month, year) {
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startDay = firstDay.getDay();
        const currentMonth = monthNames[month];

        calendarElement.innerHTML = "";
        const calendarTitle = document.createElement("h3");
        calendarTitle.textContent = `${currentMonth} ${year}`;
        calendarElement.appendChild(calendarTitle);

        const calendarGrid = document.createElement("div");
        calendarGrid.classList.add("calendar-days");

        for (let i = 0; i < startDay; i++) {
          const emptyCell = document.createElement("div");
          calendarGrid.appendChild(emptyCell);
        }

        for (let day = 1; day <= daysInMonth; day++) {
          const dayCell = document.createElement("div");
          dayCell.classList.add("day");
          dayCell.textContent = day;

          // Display Leave events with categories (SPL, FL)
          if (leaveEvents[year] && leaveEvents[year][currentMonth]) {
            const leaveForDay = leaveEvents[year][currentMonth].filter(
              (event) => event.day == day
            );

            if (leaveForDay.length > 0) {
              // Add title for categorization (SPL, FL)
              const leaveCategoryTitle = document.createElement("div");
              leaveCategoryTitle.classList.add("leave-category-title");
              dayCell.appendChild(leaveCategoryTitle);

              leaveForDay.forEach((event) => {
                const leaveDiv = document.createElement("div");
                leaveDiv.classList.add("leave-event");
                leaveDiv.textContent = `${event.type}: ${event.name}`;
                dayCell.appendChild(leaveDiv);
              });
            }
          }

          // Display CTO events
          if (
            ctoEvents[year] &&
            ctoEvents[year][currentMonth] &&
            ctoEvents[year][currentMonth].some((event) => event.day == day)
          ) {
            const ctoEventNames = ctoEvents[year][currentMonth]
              .filter((event) => event.day == day)
              .map((event) => event.name)
              .join(", ");
            const ctoDiv = document.createElement("div");
            ctoDiv.classList.add("cto-event");
            ctoDiv.textContent = `CTO: ${ctoEventNames}`;
            dayCell.appendChild(ctoDiv);
          }

          calendarGrid.appendChild(dayCell);
        }

        calendarElement.appendChild(calendarGrid);
      }

      // Modify year selection range (2025 to 2050)
      for (let year = 2025; year <= 2050; year++) {
        const yearOption = document.createElement("option");
        yearOption.value = year;
        yearOption.textContent = year;
        yearSelect.appendChild(yearOption);
      }

      const today = new Date();
      let currentMonth = today.getMonth();
      let currentYear = today.getFullYear();

      yearSelect.value = currentYear;

      monthSelect.addEventListener("change", function () {
        generateCalendar(
          parseInt(monthSelect.value),
          parseInt(yearSelect.value)
        );
      });

      yearSelect.addEventListener("change", function () {
        generateCalendar(
          parseInt(monthSelect.value),
          parseInt(yearSelect.value)
        );
      });

      generateCalendar(currentMonth, currentYear);
    })
    .catch((error) => {
      console.error("Error fetching events:", error);
    });
});

//credits
document
  .getElementById("ctoCreditSearch")
  .addEventListener("input", function () {
    const searchTerm = this.value.trim();

    if (searchTerm.length > 0) {
      searchEmployees(searchTerm);
    } else {
      // Optionally clear the table when there's no search term
      document.getElementById("ctoCreditTableBody").innerHTML =
        '<tr><td colspan="4" class="text-center">Start typing to search</td></tr>';
    }
  });

function searchEmployees(searchTerm) {
  const xhr = new XMLHttpRequest();
  xhr.open(
    "GET",
    `search_employees.php?search=${encodeURIComponent(searchTerm)}`,
    true
  );
  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4 && xhr.status === 200) {
      const response = JSON.parse(xhr.responseText);
      updateEmployeeTable(response);
    }
  };
  xhr.send();
}

function updateEmployeeTable(data) {
  const tableBody = document.getElementById("ctoCreditTableBody");
  tableBody.innerHTML = ""; // Clear existing rows

  if (data.length > 0) {
    data.forEach((employee) => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${employee.id}</td>
        <td>${employee.name}</td>
        <td id="creditAmount-${employee.id}">${employee.credits}</td>
        <td>
          <button type="button" class="btn btn-success btn-sm" onclick="addCredit(${employee.id})">+</button>
          <button type="button" class="btn btn-danger btn-sm me-1" onclick="subtractCredit(${employee.id})">−</button>
        </td>
      `;
      tableBody.appendChild(row);
    });
  } else {
    tableBody.innerHTML =
      '<tr><td colspan="4" class="text-center">No results found</td></tr>';
  }
}

function addCredit(id) {
  const creditAmountCell = document.getElementById(`creditAmount-${id}`);
  let currentAmount = parseInt(creditAmountCell.textContent);
  creditAmountCell.textContent = currentAmount + 1; // Adjust increment value as needed
}

function subtractCredit(id) {
  const creditAmountCell = document.getElementById(`creditAmount-${id}`);
  let currentAmount = parseInt(creditAmountCell.textContent);
  if (currentAmount > 0) {
    creditAmountCell.textContent = currentAmount - 1; // Adjust decrement value as needed
  }
}
//new code

$(document).ready(function () {
  $("#leaveModal .btn-primary").click(function () {
    // Get values from the modal inputs
    var leaveTitle = $("#leaveTitle").val();
    var leaveName = $("#leaveName").val();
    var leaveDates = $("#leaveDates").val();

    // Validate the inputs (basic validation)
    if (leaveTitle === "" || leaveName === "" || leaveDates === "") {
      alert("All fields are required.");
      return;
    }

    // Send data to the PHP script using AJAX
    $.ajax({
      url: "save_leave.php", // PHP file to handle saving the data
      type: "POST",
      data: {
        title: leaveTitle,
        name: leaveName,
        dates: leaveDates,
      },
      success: function (response) {
        // Handle the response (success message or error)
        alert(response);
        $("#leaveModal").modal("hide"); // Close the modal
        // Optionally, clear the form fields
        $("#leaveTitle").val("");
        $("#leaveName").val("");
        $("#leaveDates").val("");
      },
      error: function () {
        alert("Error saving data. Please try again.");
      },
    });
  });
});

$(document).ready(function () {
  $("#ctoModal .btn-primary").click(function () {
    // Get values from the modal inputs
    var ctoTitle = $("#ctoTitle").val();
    var ctoName = $("#ctoName").val();
    var ctoDates = $("#ctoDates").val();
    var ctoRemarks = $("#ctoRemarks").val();

    // Validate the inputs (basic validation)
    if (
      ctoTitle === "" ||
      ctoName === "" ||
      ctoDates === "" ||
      ctoRemarks === ""
    ) {
      alert("All fields are required.");
      return;
    }

    // Send data to the PHP script using AJAX
    $.ajax({
      url: "save_cto.php", // PHP file to handle saving the data
      type: "POST",
      data: {
        title: ctoTitle,
        name: ctoName,
        dates: ctoDates,
        remarks: ctoRemarks,
      },
      success: function (response) {
        // Handle the response (success message or error)
        alert(response);
        $("#ctoModal").modal("hide"); // Close the modal
        // Optionally, clear the form fields
        $("#ctoTitle").val("");
        $("#ctoName").val("");
        $("#ctoDates").val("");
        $("#ctoRemarks").val("");
      },
      error: function () {
        alert("Error saving data. Please try again.");
      },
    });
  });
});
//saving credit
document
  .getElementById("ctoCreditSearch")
  .addEventListener("input", function () {
    let searchTerm = this.value.trim();

    if (searchTerm !== "") {
      // Fetch data from the search_employees.php file
      fetch(`search_employees.php?search=${encodeURIComponent(searchTerm)}`)
        .then((response) => response.json())
        .then((data) => {
          let tableBody = document.getElementById("ctoCreditTableBody");
          tableBody.innerHTML = ""; // Clear the existing table content

          if (data.length > 0) {
            // Populate table with search results
            data.forEach((employee) => {
              let row = document.createElement("tr");
              row.innerHTML = `
                        <td>${employee.id}</td>
                        <td>${employee.name}</td>
                        <td id="creditAmount-${employee.id}">${employee.credits}</td>
                        <td>
                            <button type="button" class="btn btn-success btn-sm" onclick="addCredit(${employee.id})">+</button>
                            <button type="button" class="btn btn-danger btn-sm me-1" onclick="subtractCredit(${employee.id})">−</button>
                        </td>
                    `;
              tableBody.appendChild(row);
            });
          } else {
            let row = document.createElement("tr");
            row.innerHTML =
              '<td colspan="4" class="text-center">No results found</td>';
            tableBody.appendChild(row);
          }
        })
        .catch((error) => console.error("Error fetching data:", error));
    } else {
      // Clear the table if the search term is empty
      document.getElementById("ctoCreditTableBody").innerHTML = "";
    }
  });

function addCredit(employeeId) {
  let currentAmount = parseInt(
    document.getElementById(`creditAmount-${employeeId}`).innerText
  );
  let updatedAmount = currentAmount + 1;

  updateCredit(employeeId, updatedAmount);
}

function subtractCredit(employeeId) {
  let currentAmount = parseInt(
    document.getElementById(`creditAmount-${employeeId}`).innerText
  );
  let updatedAmount = currentAmount - 1;

  updateCredit(employeeId, updatedAmount);
}

function updateCredit(employeeId, updatedAmount) {
  // Send the updated credit to the server
  fetch("update_credit.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `id=${employeeId}&credits=${updatedAmount}`,
  })
    .then((response) => response.text())
    .then((result) => {
      if (result === "success") {
        // Update the table display after successful update
        document.getElementById(`creditAmount-${employeeId}`).innerText =
          updatedAmount;
      } else {
        console.error("Failed to update credit");
      }
    })
    .catch((error) => console.error("Error updating credit:", error));
}
//save leave
$(document).ready(function () {
  // Save Leave Data
  $("#leaveModal .btn-primary").click(function () {
    // Get values from the modal inputs
    var leaveTitle = $("#leaveTitle").val();
    var leaveName = $("#leaveName").val();
    var leaveDates = $("#leaveDates").val();

    // Validate the inputs (basic validation)
    if (leaveTitle === "" || leaveName === "" || leaveDates === "") {
      alert("All fields are required.");
      return;
    }

    // Send data to the PHP script using AJAX
    $.ajax({
      url: "save_leave.php", // PHP file to handle saving the data
      type: "POST",
      data: {
        title: leaveTitle,
        name: leaveName,
        dates: leaveDates,
      },
      success: function (response) {
        // Handle the response (success message or error)
        alert(response);
        if (response.trim() === "success") {
          location.reload(); // Reload the page on success
        } else {
          $("#leaveModal").modal("hide"); // Close the modal on failure
        }
        // Optionally, clear the form fields
        $("#leaveTitle").val("");
        $("#leaveName").val("");
        $("#leaveDates").val("");
      },
      error: function () {
        alert("Error saving data. Please try again.");
      },
    });
  });

  // Save CTO Data
  $("#ctoModal .btn-primary").click(function () {
    // Get values from the modal inputs
    var ctoTitle = $("#ctoTitle").val();
    var ctoName = $("#ctoName").val();
    var ctoDates = $("#ctoDates").val();
    var ctoRemarks = $("#ctoRemarks").val();

    // Validate the inputs (basic validation)
    if (
      ctoTitle === "" ||
      ctoName === "" ||
      ctoDates === "" ||
      ctoRemarks === ""
    ) {
      alert("All fields are required.");
      return;
    }

    // Send data to the PHP script using AJAX
    $.ajax({
      url: "save_cto.php", // PHP file to handle saving the data
      type: "POST",
      data: {
        title: ctoTitle,
        name: ctoName,
        dates: ctoDates,
        remarks: ctoRemarks,
      },
      success: function (response) {
        // Handle the response (success message or error)
        alert(response);
        if (response.trim() === "success") {
          location.reload(); // Reload the page on success
        } else {
          $("#ctoModal").modal("hide"); // Close the modal on failure
        }
        // Optionally, clear the form fields
        $("#ctoTitle").val("");
        $("#ctoName").val("");
        $("#ctoDates").val("");
        $("#ctoRemarks").val("");
      },
      error: function () {
        alert("Error saving data. Please try again.");
      },
    });
  });
});
//ADD CREDIT

document
  .getElementById("ctoCreditSearch")
  .addEventListener("input", function () {
    let searchTerm = this.value.trim();

    if (searchTerm !== "") {
      // Fetch data from the search_employees.php file
      fetch(`search_employees.php?search=${encodeURIComponent(searchTerm)}`)
        .then((response) => response.json())
        .then((data) => {
          let tableBody = document.getElementById("ctoCreditTableBody");
          tableBody.innerHTML = ""; // Clear the existing table content

          if (data.length > 0) {
            // Populate table with search results
            data.forEach((employee) => {
              let row = document.createElement("tr");
              row.innerHTML = `
                        <td>${employee.id}</td>
                        <td>${employee.name}</td>
                        <td id="creditAmount-${employee.id}">${employee.credits}</td>
                        <td>
                            <button type="button" class="btn btn-success btn-sm" onclick="addCredit(${employee.id})">+</button>
                            <button type="button" class="btn btn-danger btn-sm me-1" onclick="subtractCredit(${employee.id})">−</button>
                        </td>
                    `;
              tableBody.appendChild(row);
            });
          } else {
            let row = document.createElement("tr");
            row.innerHTML =
              '<td colspan="4" class="text-center">No results found</td>';
            tableBody.appendChild(row);
          }
        })
        .catch((error) => console.error("Error fetching data:", error));
    } else {
      // Clear the table if the search term is empty
      document.getElementById("ctoCreditTableBody").innerHTML = "";
    }
  });

function addCredit(employeeId) {
  let currentAmount = parseInt(
    document.getElementById(`creditAmount-${employeeId}`).innerText
  );
  let updatedAmount = currentAmount + 1;

  updateCredit(employeeId, updatedAmount);
}

function subtractCredit(employeeId) {
  let currentAmount = parseInt(
    document.getElementById(`creditAmount-${employeeId}`).innerText
  );
  let updatedAmount = currentAmount - 1;

  updateCredit(employeeId, updatedAmount);
}

function updateCredit(employeeId, updatedAmount) {
  // Send the updated credit to the server
  fetch("update_credit.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `id=${employeeId}&credits=${updatedAmount}`,
  })
    .then((response) => response.text())
    .then((result) => {
      if (result === "success") {
        // Update the table display after successful update
        document.getElementById(`creditAmount-${employeeId}`).innerText =
          updatedAmount;
      } else {
        console.error("Failed to update credit");
      }
    })
    .catch((error) => console.error("Error updating credit:", error));
}

//SEARCH LEAVE
document.getElementById("searchInput").addEventListener("input", function () {
  var searchTerm = this.value.trim();

  if (searchTerm.length > 0) {
    var xhr = new XMLHttpRequest();
    xhr.open(
      "GET",
      "search_all_leave.php?searchTerm=" + encodeURIComponent(searchTerm),
      true
    );
    xhr.onreadystatechange = function () {
      if (xhr.readyState == 4 && xhr.status == 200) {
        var results = JSON.parse(xhr.responseText);
        var tbody = document.getElementById("searchTableBody");
        tbody.innerHTML = "";

        for (var year in results) {
          for (var month in results[year]) {
            results[year][month].forEach(function (event) {
              var formattedDate = `${year}-${month}-${
                event.day < 10 ? "0" + event.day : event.day
              }`;
              var row = document.createElement("tr");
              row.innerHTML = `
                            <td>${event.id}</td>
                            <td>${event.title}</td>
                            <td>${event.name}</td>
                            <td>${formattedDate}</td>
                            <td>
                                <button class="btn btn-danger btn-sm delete-btn" data-id="${event.id}" data-title="${event.title}">Delete</button>
                            </td>
                        `;

              tbody.appendChild(row);
            });
          }
        }

        // Add event listeners for delete buttons
        document.querySelectorAll(".delete-btn").forEach(function (btn) {
          btn.addEventListener("click", function () {
            var id = this.getAttribute("data-id");
            var title = this.getAttribute("data-title"); // Get title from the button
            if (confirm("Are you sure you want to delete this record?")) {
              deleteRecord(id, title);
            }
          });
        });
      }
    };
    xhr.send();
  } else {
    var tbody = document.getElementById("searchTableBody");
    tbody.innerHTML = "";
  }
});

function deleteRecord(id, title) {
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "delete_leave.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.onreadystatechange = function () {
    if (xhr.readyState == 4 && xhr.status == 200) {
      if (xhr.responseText === "success") {
        alert("Record deleted successfully");
        document
          .getElementById("searchInput")
          .dispatchEvent(new Event("input"));
      } else {
        alert("Failed to delete record: " + xhr.responseText);
      }
    }
  };
  xhr.send(
    "id=" + encodeURIComponent(id) + "&title=" + encodeURIComponent(title)
  );
}

//export pdf
document.getElementById("exportPDF").addEventListener("click", function () {
  window.location.href = "export_leave_report.php"; // Replace with your PHP script path
});
//security
const PASSWORD = "123"; // Replace with your password
const ACCESS_KEY = "userAccessGranted";

// Show modal if user hasn't accessed
$(document).ready(function () {
  if (!localStorage.getItem(ACCESS_KEY)) {
    $("#passwordModal")
      .modal({
        backdrop: "static",
        keyboard: false,
      })
      .modal("show");
  }
});

// Handle password submission
$("#submitPassword").on("click", function () {
  const inputPassword = $("#passwordInput").val();
  if (inputPassword === PASSWORD) {
    localStorage.setItem(ACCESS_KEY, "true");
    $("#passwordModal").modal("hide");
  } else {
    $("#passwordError").removeClass("d-none");
  }
});

// Clear access state on logout
$(".logout-item").on("click", function () {
  localStorage.removeItem(ACCESS_KEY);
});
//dropdown
document.addEventListener("DOMContentLoaded", () => {
  const dropdownToggles = document.querySelectorAll(".dropdown-toggle");

  dropdownToggles.forEach((toggle) => {
    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      const dropdown = this.parentElement;
      dropdown.classList.toggle("open");

      // Close other dropdowns if needed
      document.querySelectorAll(".dropdown").forEach((item) => {
        if (item !== dropdown) {
          item.classList.remove("open");
        }
      });
    });
  });
});
//dropdown
document.addEventListener("DOMContentLoaded", function () {
  // Get all dropdown list items
  var dropdowns = document.querySelectorAll(".dropdown");

  // Loop through each dropdown list item
  dropdowns.forEach(function (dropdown) {
    // Add click event listener to toggle the dropdown menu
    dropdown.addEventListener("click", function (event) {
      // Toggle the 'active' class on the dropdown menu
      this.querySelector(".dropdown-menu").classList.toggle("active");
      this.classList.toggle("open"); // Toggle 'open' class on the dropdown item
    });
  });

  // Close dropdown menu when clicking outside
  document.addEventListener("click", function (event) {
    if (!event.target.closest(".dropdown")) {
      var activeDropdowns = document.querySelectorAll(".dropdown-menu.active");
      activeDropdowns.forEach(function (activeDropdown) {
        activeDropdown.classList.remove("active");
        activeDropdown.closest(".dropdown").classList.remove("open"); // Remove 'open' class
      });
    }
  });
});
