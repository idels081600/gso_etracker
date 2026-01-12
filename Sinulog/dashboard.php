<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    <style>
        .table td {
            font-size: 14px;
        }

        #qr-reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }

        #qr-reader__dashboard_section_swaplink {
            display: none !important;
        }

        #qr-reader video {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .scanner-overlay {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 3px;
            margin-bottom: 1rem;
        }

        .scanner-inner {
            background: white;
            border-radius: 10px;
            padding: 1rem;
        }

        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: #17C37B;
            top: 50%;
            animation: scanLine 2s ease-in-out infinite;
            z-index: 1;
        }

        @keyframes scanLine {

            0%,
            100% {
                transform: translateY(-100px);
            }

            50% {
                transform: translateY(100px);
            }
        }

        .scanner-corner {
            position: absolute;
            width: 40px;
            height: 40px;
            border: 3px solid #17C37B;
        }

        .scanner-corner.top-left {
            top: 20px;
            left: 20px;
            border-right: none;
            border-bottom: none;
        }

        .scanner-corner.top-right {
            top: 20px;
            right: 20px;
            border-left: none;
            border-bottom: none;
        }

        .scanner-corner.bottom-left {
            bottom: 20px;
            left: 20px;
            border-right: none;
            border-top: none;
        }

        .scanner-corner.bottom-right {
            bottom: 20px;
            right: 20px;
            border-left: none;
            border-top: none;
        }

        .modal-body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        #scanResult {
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
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
                        <a class="nav-link active" href="#" id="addMemberLink">add member</a>
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
                    <div style="width: 350px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem; margin-bottom: 28px;">
                        <h5 style="margin-bottom: 1rem;">Team Overview</h5>

                        <!-- Dancers -->
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #dee2e6;">
                            <div>
                                <p style="font-size: 1.1rem; font-weight: 600; margin: 0; color: #333;">Dancers</p>
                            </div>
                            <div style="text-align: right;">
                                <p style="font-size: 1.5rem; font-weight: bold; color: #17C37B; margin: 0;" id="dancersCount">1</p>
                                <p style="font-size: 0.875rem; color: #6c757d; margin: 0;">Out Of 100</p>
                            </div>
                        </div>

                        <!-- Propsmen -->
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #dee2e6;">
                            <div>
                                <p style="font-size: 1.1rem; font-weight: 600; margin: 0; color: #333;">Propsmen</p>
                            </div>
                            <div style="text-align: right;">
                                <p style="font-size: 1.5rem; font-weight: bold; color: #FFA500; margin: 0;" id="propsmenCount">1</p>
                                <p style="font-size: 0.875rem; color: #6c757d; margin: 0;">Out Of 100</p>
                            </div>
                        </div>

                        <!-- Instrumentals -->
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0;">
                            <div>
                                <p style="font-size: 1.1rem; font-weight: 600; margin: 0; color: #333;">Instrumentals</p>
                            </div>
                            <div style="text-align: right;">
                                <p style="font-size: 1.5rem; font-weight: bold; color: #0D6EFD; margin: 0;" id="instrumentalsCount">1</p>
                                <p style="font-size: 0.875rem; color: #6c757d; margin: 0;">Out Of 100</p>
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
                                    <th scope="col">Number</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody id="membersTableBody">
                                <tr>
                                    <td colspan="7" class="text-center">Loading...</td>
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
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="qrModalLabel">
                        <i class="bi bi-qr-code-scan me-2"></i>Scan QR Code
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="scanner-overlay">
                        <div class="scanner-inner">
                            <div id="qr-reader" style="position: relative;"></div>
                            <div class="scanner-corner top-left"></div>
                            <div class="scanner-corner top-right"></div>
                            <div class="scanner-corner bottom-left"></div>
                            <div class="scanner-corner bottom-right"></div>
                        </div>
                    </div>
                    <div id="scanResult" class="alert alert-success mt-3" style="display: none;" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>Success!</strong> <span id="scanResultText"></span>
                    </div>
                    <p class="text-muted mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Position the QR code within the frame
                    </p>
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

    <!-- Add Member Modal -->
    <div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMemberModalLabel">Add New Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addMemberForm">
                        <div class="mb-3">
                            <label for="memberName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="memberName" required>
                        </div>
                        <div class="mb-3">
                            <label for="memberNumber" class="form-label">Number</label>
                            <input type="number" class="form-control" id="memberNumber" required>
                        </div>
                        <div class="mb-3">
                            <label for="memberRole" class="form-label">Role</label>
                            <select class="form-control" id="memberRole" required>
                                <option value="">Select Role</option>
                                <option value="Dancer">Dancer</option>
                                <option value="Propsmen">Propsmen</option>
                                <option value="Instrumental">Instrumental</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="memberPhone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="memberPhone" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveMemberBtn">Save Member</button>
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
                cutout: '75%',
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
    <script src="dashboard.js"></script>
</body>

</html>