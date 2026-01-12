
        let scanner = new Instascan.Scanner({ video: document.getElementById('previewModal'), facingMode: 'environment' });
        scanner.addListener('scan', function (content) {
            const resultDiv = document.getElementById('scanResult');
            resultDiv.textContent = 'Scanned QR Code: ' + content;
            resultDiv.style.display = 'block';
            // Hide after 1 second
            setTimeout(() => {
                resultDiv.style.display = 'none';
            }, 500);
        });

        document.getElementById('scanQR').addEventListener('click', function() {
            // Reset modal content
            document.getElementById('scanResult').style.display = 'none';
            var myModal = new bootstrap.Modal(document.getElementById('qrModal'));
            myModal.show();
            // Start scanning after modal is shown
            setTimeout(() => {
                Instascan.Camera.getCameras().then(function (cameras) {
                    if (cameras.length > 0) {
                        // Try to find back camera by name
                        let backCam = cameras.find(cam => cam.name.toLowerCase().includes('back')) || cameras[cameras.length - 1];
                        scanner.start(backCam);
                    } else {
                        alert('No cameras found.');
                    }
                }).catch(function (e) {
                    console.error(e);
                    alert('Error accessing camera.');
                });
            }, 500); // Small delay to ensure modal is rendered
        });

        // Stop scanner when modal is hidden
        document.getElementById('qrModal').addEventListener('hidden.bs.modal', function () {
            scanner.stop();
        });

        // View details buttons
        document.querySelectorAll('.viewBtn').forEach(button => {
            button.addEventListener('click', function() {
                var modal = new bootstrap.Modal(document.getElementById('viewModal'));
                modal.show();
            });
        });
   