$(document).ready(function() {
    $("#searchInput").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#tableBody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});

$(document).ready(function() {
    var selectedClientId; // Declare within document ready scope
    var currentStatus; // Store the current status

    // Function to populate status dropdown based on current status
    function populateStatusDropdown(status) {
        var statusDropdown = $('#clientStatus');
        statusDropdown.empty(); // Clear existing options
        
        if (status === 'Pending') {
            statusDropdown.append('<option value="Installed">Install</option>');
        } else if (status === 'Installed') {
            statusDropdown.append('<option value="Retrieved">Retrieve</option>');
            statusDropdown.append('<option value="For Retrieval">For Retrieval</option>');
        } else if (status === 'For Retrieval') {
            statusDropdown.append('<option value="Retrieved">Retrieved</option>');
        } else if (status === 'Retrieved') {
            // For retrieved items, you might want to allow changing back to other statuses
            // or keep it as is. Adjust based on your business logic
            statusDropdown.append('<option value="Retrieved" selected>Retrieved</option>');
        } else {
            // Default fallback - show all options
            statusDropdown.append('<option value="Installed">Installed</option>');
            statusDropdown.append('<option value="Retrieved">Retrieved</option>');
            statusDropdown.append('<option value="For Retrieval">For Retrieval</option>');
            statusDropdown.append('<option value="Long Term">Long Term</option>');
        }
    }

    // Edit button click handler
    $('.btn-primary').click(function() {
        var row = $(this).closest('tr');
        var status = row.find('td:eq(4)').text().trim(); // Status is now in the 5th column (index 4)
        var name = row.find('td:eq(0)').text();
        var address = row.find('td:eq(1)').text();
        var contact = $(this).data('contact'); // Get contact from data attribute
        var noOfTents = $(this).data('no_of_tents'); // Get number of tents from data attribute
        selectedClientId = $(this).data('id'); // Use data() method
        tent_installed = $(this).data('tent_no');
        currentStatus = status; // Store current status

        console.log('Client ID on click:', selectedClientId);
        console.log('Current Status:', currentStatus);
        console.log('No. of Tents:', noOfTents);

        $('#clientName').val(name);
        $('#clientAddress').val(address);
        $('#clientContact').val(contact);
        $('#noOfTents').val(noOfTents); // Set the number of tents field
        $('#tentNumber').val(tent_installed);
        
        // Populate status dropdown based on current status
        populateStatusDropdown(currentStatus);

        $('#editModal').modal('show');
    });

    // Form submission handler using the same selectedClientId
    $('#editForm').on('submit', function(e) {
        e.preventDefault();

        const statusValue = $('#clientStatus').val();
        const tentValue = $('#tentNumber').val();

        console.log('Form submission - Client ID:', selectedClientId);
        console.log('Status Value:', statusValue);
        console.log('Tent Value:', tentValue);

        $.ajax({
            url: 'update_tent_installer.php',
            method: 'POST',
            dataType: 'json',
            data: {
                clientStatus: statusValue,
                tentNumber: tentValue,
                clientId: selectedClientId // Use selectedClientId directly
            },
            success: function(result) {
                console.log('Debug Info:', result.debug);
                if (result.success) {
                    console.log('Update successful');
                    // Update the table row with the new data
                    var row = $('button[data-id="' + selectedClientId + '"]').closest('tr');
                    row.find('td:eq(0)').text($('#clientName').val());
                    row.find('td:eq(1)').text($('#clientAddress').val());
                    row.find('td:eq(2)').text($('#noOfTents').val()); // Update number of tents column
                    row.find('td:eq(4)').text(statusValue); // Update status column
                    $('#editModal').modal('hide');
                    
                    // Optionally reload the page to reflect changes
                    // location.reload();
                } else {
                    console.log('Update failed:', result);
                    alert('Update failed: ' + (result.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                alert('An error occurred while updating the record.');
            }
        });
    });

    // Box click handler
    $('.box').click(function() {
        if ($(this).css('background-color') === 'rgb(40, 167, 69)') {
            var tentNumber = $(this).text();
            var currentValue = $('#tentNumber').val();

            if ($(this).hasClass('selected')) {
                $(this).removeClass('selected');
                var numbers = currentValue.split(',');
                numbers = numbers.filter(num => num.trim() !== tentNumber);
                $('#tentNumber').val(numbers.join(','));
            } else {
                $(this).addClass('selected');
                if (currentValue) {
                    if (!currentValue.split(',').includes(tentNumber)) {
                        $('#tentNumber').val(currentValue + ',' + tentNumber);
                    }
                } else {
                    $('#tentNumber').val(tentNumber);
                }
            }
        }
    });

    // Handle dynamic button binding for newly loaded content
    $(document).on('click', '.btn-primary', function() {
        var row = $(this).closest('tr');
        var status = row.find('td:eq(4)').text().trim(); // Status is in the 5th column (index 4)
        var name = row.find('td:eq(0)').text();
        var address = row.find('td:eq(1)').text();
        var contact = $(this).data('contact'); // Get contact from data attribute
        var noOfTents = $(this).data('no_of_tents'); // Get number of tents from data attribute
        selectedClientId = $(this).data('id'); // Use data() method
        tent_installed = $(this).data('tent_no');
        currentStatus = status; // Store current status

        console.log('Client ID on click:', selectedClientId);
        console.log('Current Status:', currentStatus);

        $('#clientName').val(name);
        $('#clientAddress').val(address);
        $('#clientContact').val(contact);
        $('#noOfTents').val(noOfTents); // Set the number of tents field
        $('#tentNumber').val(tent_installed);
        
        // Populate status dropdown based on current status
        populateStatusDropdown(currentStatus);

        $('#editModal').modal('show');
    });
});

// Global variables for filter state
let lastFilter = 'showAll'; // 'showAll' or 'today'
let today = new Date().toISOString().split('T')[0];

// Global functions for message handling
function showNoResultsMessage(message) {
    // Remove existing message if any
    removeNoResultsMessage();
    
    // Create new row with message
    const tableBody = document.getElementById('tableBody');
    const noResultsRow = document.createElement('tr');
    noResultsRow.id = 'noResultsRow';
    noResultsRow.innerHTML = `<td colspan="6" class="text-center text-muted">${message}</td>`;
    
    // Add to table body
    if (tableBody) {
        tableBody.appendChild(noResultsRow);
    }
}

function removeNoResultsMessage() {
    const existingMessage = document.getElementById('noResultsRow');
    if (existingMessage) {
        existingMessage.remove();
    }
}

// JavaScript code for Today and Show All buttons
document.addEventListener('DOMContentLoaded', function() {
    const todayBtn = document.getElementById('todayBtn');
    const showAllBtn = document.getElementById('showAllBtn');
    const tableBody = document.getElementById('tableBody');
    const table = document.querySelector('.table');
    const rows = table.getElementsByTagName('tr');
    
    // Get today's date in YYYY-MM-DD format
    today = new Date().toISOString().split('T')[0];
    
    // On page load, hide all .pending-row
    document.querySelectorAll('.pending-row').forEach(function(row) {
        row.style.display = 'none';
    });
    
    // Today button click event
    todayBtn.addEventListener('click', function() {
        todayBtn.classList.add('active');
        showAllBtn.classList.remove('active');
        lastFilter = 'today';
        filterTableForToday();
    });
    
    // Show All button click event
    showAllBtn.addEventListener('click', function() {
        showAllBtn.classList.add('active');
        todayBtn.classList.remove('active');
        lastFilter = 'showAll';
        // Show 'For Retrieval' and 'Installed' records
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            if (row.classList.contains('pending-row')) {
                row.style.display = 'none';
                continue;
            }
            if (row.classList.contains('for-retrieval-row') || row.classList.contains('installed-row')) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
        removeNoResultsMessage();
        checkIfNoResults();
    });
    
    // Function to filter table for today's pending records
    function filterTableForToday() {
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            if (row.classList.contains('pending-row')) {
                const cells = row.getElementsByTagName('td');
                const dateCell = cells[3]; // Date column (index 3)
                const statusCell = cells[4]; // Status column (index 4)
                if (dateCell && statusCell) {
                    const rowDate = dateCell.textContent.trim();
                    const rowStatus = statusCell.textContent.trim();
                    if (rowDate === today && rowStatus === 'Pending') {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            } else {
                row.style.display = 'none';
            }
        }
        checkIfNoResults();
    }
    
    // Function to check if no results are visible and show message
    function checkIfNoResults() {
        let visibleRows = 0;
        
        for (let i = 1; i < rows.length; i++) {
            if (rows[i].style.display !== 'none') {
                visibleRows++;
            }
        }
        
        if (visibleRows === 0) {
            showNoResultsMessage("No pending records found for today (" + today + ")");
        } else {
            removeNoResultsMessage();
        }
    }
    

    
    // Initialize with Show All active by default
    showAllBtn.classList.add('active');
    
    // Also integrate with existing search functionality if it exists
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            // If search is being used, reset button states
            if (searchInput.value.trim() !== '') {
                todayBtn.classList.remove('active');
                showAllBtn.classList.remove('active');
            }
        });
    }
});
// Enhanced search functionality that works with the filter buttons
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const table = document.querySelector('.table');
    const rows = table.getElementsByTagName('tr');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const filter = searchInput.value.toLowerCase();
            if (filter === '') {
                // Reapply last filter
                if (lastFilter === 'today') {
                    filterTableForToday();
                } else {
                    // Show 'For Retrieval' and 'Installed' records only
                    for (let i = 1; i < rows.length; i++) {
                        const row = rows[i];
                        if (row.classList.contains('for-retrieval-row') || row.classList.contains('installed-row')) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                    removeNoResultsMessage();
                    checkIfNoResults();
                }
                return;
            }
            
            // Search within the context of the current filter
            let visibleRows = 0;
            
            // First, hide all rows that don't match the current filter
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                
                if (lastFilter === 'today') {
                    // For today filter, only show pending rows for today
                    if (!row.classList.contains('pending-row')) {
                        row.style.display = 'none';
                        continue;
                    }
                    
                    const dateCell = cells[3];
                    const statusCell = cells[4];
                    if (dateCell && statusCell) {
                        const rowDate = dateCell.textContent.trim();
                        const rowStatus = statusCell.textContent.trim();
                        if (rowDate !== today || rowStatus !== 'Pending') {
                            row.style.display = 'none';
                            continue;
                        }
                    } else {
                        row.style.display = 'none';
                        continue;
                    }
                } else {
                    // For show all filter, EXCLUDE pending rows and show only For Retrieval and Installed rows
                    if (row.classList.contains('pending-row')) {
                        row.style.display = 'none';
                        continue;
                    }
                    
                    if (!row.classList.contains('for-retrieval-row') && !row.classList.contains('installed-row')) {
                        row.style.display = 'none';
                        continue;
                    }
                }
                
                // Now check if the remaining visible rows match the search term
                let matchesSearch = false;
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent.toLowerCase();
                    if (cellText.includes(filter)) {
                        matchesSearch = true;
                        break;
                    }
                }
                
                if (matchesSearch) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            }
            
            if (visibleRows === 0) {
                showNoResultsMessage('No results found for "' + filter + '" in current filter');
            } else {
                removeNoResultsMessage();
            }
        });
    }
    
    // Function to filter table for today's pending records
    function filterTableForToday() {
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            if (row.classList.contains('pending-row')) {
                const cells = row.getElementsByTagName('td');
                const dateCell = cells[3]; // Date column (index 3)
                const statusCell = cells[4]; // Status column (index 4)
                if (dateCell && statusCell) {
                    const rowDate = dateCell.textContent.trim();
                    const rowStatus = statusCell.textContent.trim();
                    if (rowDate === today && rowStatus === 'Pending') {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            } else {
                row.style.display = 'none';
            }
        }
        checkIfNoResults();
    }
    
    // Function to check if no results are visible and show message
    function checkIfNoResults() {
        let visibleRows = 0;
        
        for (let i = 1; i < rows.length; i++) {
            if (rows[i].style.display !== 'none') {
                visibleRows++;
            }
        }
        
        if (visibleRows === 0) {
            if (lastFilter === 'today') {
                showNoResultsMessage("No pending records found for today (" + today + ")");
            } else {
                showNoResultsMessage("No installed or for retrieval records found");
            }
        } else {
            removeNoResultsMessage();
        }
    }
});
