<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
    <style>
        .table td {
            font-size: 14px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #17C37B;">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" aria-disabled="true">Disabled</a>
                    </li>
                </ul>
                <form class="d-flex">
                    <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
                    <button class="btn btn-outline-light" type="submit">Search</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="d-flex flex-column align-items-center">
                    <div style="width: 350px; height: 218px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem; margin-bottom: 28px;">
                        <h5 id="cont1_title">Status</h5>
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <canvas id="statusChart" width="125" height="125" style="margin-top: 10px; margin-left:20px"></canvas>
                            <div style="margin-right: 1.5rem;">
                                <p style="font-size: 3rem; font-weight: bold; color: #17C37B;">75</p>
                                <p style="font-size: 1rem; color: #6c757d;">Out Of 100</p>
                            </div>
                        </div>
                    </div>
                    <div style="width: 375px; height: 549px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem;">
                        <h5>Contingents</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <input type="text" class="form-control" placeholder="Search..." style="width: 70%;">
                            <button id="scanQR" class="btn btn-primary"><i class="bi bi-qr-code-scan"></i></button>
                            <button id="checkAttendance" class="btn btn-success"><i class="bi bi-check-circle"></i></button>
                        </div>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th scope="row">1</th>
                                    <td>Bryan Laureano</td>
                                    <td>Dancer</td>
                                    <td><span class="badge bg-success">Present</span></td>
                                    <td><button class="viewBtn btn btn-primary btn-sm"><i class="bi bi-eye"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Scan Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrModalLabel">Scan QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <video id="previewModal" style="width: 100%; max-height: 400px; transform: scaleX(1);"></video>
                    <div id="scanResult" class="alert alert-success mt-3" style="display: none;" role="alert"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">Contingent Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="detailName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="detailName" value="Bryan Laureano" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="detailRole" class="form-label">Role</label>
                        <input type="text" class="form-control" id="detailRole" value="Dancer" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="detailStatus" class="form-label">Status</label>
                        <input type="text" class="form-control" id="detailStatus" value="Active" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="detailPhone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="detailPhone" value="+1234567890" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [75, 25],
                    backgroundColor: [
                        '#17C37B',
                        '#e0e0e0'
                    ],
                    borderWidth: 0
                }]
            },
            plugins: [{
                id: 'centerText',
                beforeDraw: function(chart) {
                    var ctx = chart.ctx;
                    var width = chart.width;
                    var height = chart.height;
                    ctx.restore();
                    ctx.font = 'bold 16px Arial';
                    ctx.fillStyle = '#17C37B';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText('75%', width / 2, height / 2);
                    ctx.save();
                }
            }],
            options: {
                responsive: false,
                cutout: '75 %',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false
                    }
                }
            }
        });
    </script>
    <script>
        document.getElementById('scanQR').addEventListener('click', function() {
            // Request camera permission first
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(stream) {
                    // Permission granted, stop the stream as we don't need it yet
                    stream.getTracks().forEach(track => track.stop());
                    // Reset modal content
                    document.getElementById('scanResult').style.display = 'none';
                    var myModal = new bootstrap.Modal(document.getElementById('qrModal'));
                    myModal.show();
                    // Create scanner after modal is shown
                    let scanner = new Instascan.Scanner({
                        video: document.getElementById('previewModal'),
                        facingMode: 'environment'
                    });
                    scanner.addListener('scan', function(content) {
                        const resultDiv = document.getElementById('scanResult');
                        resultDiv.textContent = 'Scanned QR Code: ' + content;
                        resultDiv.style.display = 'block';
                        // Hide after 1 second
                        setTimeout(() => {
                            resultDiv.style.display = 'none';
                        }, 500);
                    });
                    // Start scanning after modal is shown
                    setTimeout(() => {
                        Instascan.Camera.getCameras().then(function(cameras) {
                            if (cameras.length > 0) {
                                // Try to find back camera by name
                                let backCam = cameras.find(cam => cam.name.toLowerCase().includes('back')) || cameras[cameras.length - 1];
                                scanner.start(backCam);
                            } else {
                                alert('No cameras found.');
                            }
                        }).catch(function(e) {
                            console.error(e);
                            alert('Error accessing camera.');
                        });
                    }, 500); // Small delay to ensure modal is rendered
                })
                .catch(function(err) {
                    alert('Camera permission denied or not available.');
                    console.error('Camera permission error:', err);
                });
        });

        // Stop scanner when modal is hidden
        document.getElementById('qrModal').addEventListener('hidden.bs.modal', function() {
            if (typeof scanner !== 'undefined') {
                scanner.stop();
            }
        });

        // View details buttons
        document.querySelectorAll('.viewBtn').forEach(button => {
            button.addEventListener('click', function() {
                var modal = new bootstrap.Modal(document.getElementById('viewModal'));
                modal.show();
            });
        });
    </script>
</body>

</html>