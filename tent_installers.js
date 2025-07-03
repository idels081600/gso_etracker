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
        var status = row.find('td:eq(6)').text().trim(); // Status is now in the 7th column (index 6)
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
                    row.find('td:eq(2)').text($('#tentNumber').val());
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
        selectedClientId = $(this).data('id'); // Use data() method
        tent_installed = $(this).data('tent_no');
        currentStatus = status; // Store current status

        console.log('Client ID on click:', selectedClientId);
        console.log('Current Status:', currentStatus);

        $('#clientName').val(name);
        $('#clientAddress').val(address);
        $('#clientContact').val(contact);
        $('#clientId').val(selectedClientId);
        $('#tentNumber').val(tent_installed);
        
        // Populate status dropdown based on current status
        populateStatusDropdown(currentStatus);

        $('#editModal').modal('show');
    });
});
