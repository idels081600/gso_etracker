<?php

session_start();
$conn = require(__DIR__ . '/config/database.php');

// Security check - redirect to login if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login_v2.php");
    exit();
}

// Role-based redirection
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

if ($role === 'FOOD_REDEEMER') {
    header("Location: dashboard_food.php");
    exit();
}


// If station is already assigned for other roles, redirect to dashboard — do not allow re-selection
if (isset($_SESSION['station_id']) && !empty($_SESSION['station_id'])) {
    header("Location: releasing_food.php");
    exit();
}

// Get current station from session (set in check_login.php)
$current_station_id = isset($_SESSION['station_id']) ? (int)$_SESSION['station_id'] : null;
$current_station_name = null;

if ($current_station_id) {
    $check_sql = "SELECT market_name FROM food_markets WHERE id = $current_station_id AND is_active = 1";
    $check_result = mysqli_query($conn, $check_sql);
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $station_data = mysqli_fetch_assoc($check_result);
        $current_station_name = $station_data['market_name'];
        $_SESSION['station_name'] = $current_station_name;
    }
}

// Fetch all active food markets
$stations_sql = "SELECT * FROM food_markets WHERE is_active = 1 ORDER BY market_name";
$stations_result = mysqli_query($conn, $stations_sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Gasoline Station</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .station-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .station-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border-color: #198754;
        }
        .station-card.selected {
            border-color: #198754;
            background-color: #d1e7dd;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-store display-4 text-success"></i>
                            <h2 class="mt-3 fw-bold"><?php echo $current_station_id ? 'Change Gas Station' : 'Select Your Station'; ?></h2>
                            <?php if ($current_station_id): ?>
                            <p class="text-muted">Current station: <strong class="text-success"><?php echo htmlspecialchars($current_station_name); ?></strong></p>
                            <p class="text-muted small">Select a different station to switch.</p>
                            <?php else: ?>
                            <p class="text-muted">Choose the gasoline station you are assigned to.</p>
                            <?php endif; ?>
                        </div>
                        
                        <form id="stationForm">
                            <div class="row g-3" id="stationList">
                                <?php 
                                mysqli_data_seek($stations_result, 0); // Reset pointer
                                while ($station = mysqli_fetch_assoc($stations_result)): 
                                    $is_current = ($current_station_id == $station['id']);
                                ?>
                                <div class="col-12">
                                    <div class="card station-card <?php echo $is_current ? 'selected' : ''; ?>" data-station-id="<?php echo $station['id']; ?>" data-station-name="<?php echo htmlspecialchars($station['market_name']); ?>" data-current="<?php echo $is_current ? 'true' : 'false'; ?>">
                                        <div class="card-body d-flex align-items-center">
                                            <i class="bi bi-shop fs-3 text-warning me-3"></i>
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($station['market_name']); ?></h5>
                                                <small class="text-muted"><?php echo htmlspecialchars($station['market_code']); ?></small>
                                                <?php if ($is_current): ?>
                                                <span class="badge bg-success ms-2">Current</span>
                                                <?php endif; ?>
                                            </div>
                                            <i class="bi bi-check-circle-fill fs-4 text-success ms-auto <?php echo $is_current ? '' : 'd-none'; ?> check-icon"></i>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <input type="hidden" name="station_id" id="selectedStationId" required>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled>
                                    <i class="bi bi-check-lg me-2"></i>Confirm Selection
                                </button>
                            </div>
                            
                            <?php if ($current_station_id): ?>
                            <div class="text-center mt-3">
                                <a href="dashboard_fuel.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted small mb-0">
                                Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                            </p>
                            <a href="../../logout.php" class="text-danger small">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stationCards = document.querySelectorAll('.station-card');
            const selectedInput = document.getElementById('selectedStationId');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('stationForm');
            
            let selectedStationId = null;
            
            // Station card selection
            stationCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    stationCards.forEach(c => {
                        c.classList.remove('selected');
                        c.querySelector('.check-icon').classList.add('d-none');
                    });
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    this.querySelector('.check-icon').classList.remove('d-none');
                    
                    // Update hidden input
                    selectedStationId = this.dataset.stationId;
                    selectedInput.value = selectedStationId;
                    
                    // Enable submit button
                    submitBtn.disabled = false;
                });
            });
            
            // Form submission
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                if (!selectedStationId) {
                    alert('Please select a station.');
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
                
                try {
                    const response = await fetch('api_set_station.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            station_id: parseInt(selectedStationId)
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        window.location.href = 'releasing_food.php';
                    } else {
                        alert('Error: ' + data.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Confirm Selection';
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Confirm Selection';
                }
            });
        });
    </script>
</body>

</html>