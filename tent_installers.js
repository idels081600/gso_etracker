
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

    // Edit button click handler
    $('.btn-primary').click(function() {
        var row = $(this).closest('tr');
        var status = row.find('td:eq(3)').text().trim();
        var name = row.find('td:eq(0)').text();
        var address = row.find('td:eq(1)').text();
        var contact = row.find('td:eq(2)').text();
        selectedClientId = $(this).data('id'); // Use data() method
        tent_installed = $(this).data('tent_no');

        console.log('Client ID on click:', selectedClientId);

        $('#clientName').val(name);
        $('#clientAddress').val(address);
        $('#clientContact').val(contact);
        $('#clientId').val(selectedClientId);
        $('#tentNumber').val(tent_installed);
        if (status === 'Pending') {
            $('#clientStatus').html('<option value="Installed">Installed</option>');
        } else if (status === 'Installed') {
            $('#clientStatus').html('<option value="Retrieved">Retrieved</option>');
        }

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
                    row.find('td:eq(2)').text($('#clientContact').val());
                    row.find('td:eq(3)').text(statusValue);
                    $('#editModal').modal('hide');
                } else {
                    console.log('Update failed:', result);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
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
});
