document.addEventListener('DOMContentLoaded', function() {
    // Existing code...

    // Print button click handler
    document.getElementById('printBtn').addEventListener('click', function() {
        // Open the print options modal
        var printOptionsModal = new bootstrap.Modal(document.getElementById('printOptionsModal'));
        printOptionsModal.show();
    });

    // Print Selected button click handler
    document.getElementById('printSelectedBtn').addEventListener('click', function() {
        // Collect selected statuses
        var selectedStatuses = [];
        if (document.getElementById('printAvailable').checked) selectedStatuses.push('Available');
        if (document.getElementById('printLowStock').checked) selectedStatuses.push('Low Stock');
        if (document.getElementById('printOutOfStock').checked) selectedStatuses.push('Out of Stock');
        if (document.getElementById('printDiscontinued').checked) selectedStatuses.push('Discontinued');

        // Close the modal
        var printOptionsModal = bootstrap.Modal.getInstance(document.getElementById('printOptionsModal'));
        printOptionsModal.hide();

        // Send POST request to generate and preview PDF
        var formData = new FormData();
        selectedStatuses.forEach(function(status) {
            formData.append('statuses[]', status);
        });

        fetch('Logi_print_data_stock.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.blob())
        .then(blob => {
            var url = window.URL.createObjectURL(blob);
            window.open(url, '_blank');
        })
        .catch(error => {
            alert('Failed to generate PDF.');
            console.error(error);
        });
    });
});
