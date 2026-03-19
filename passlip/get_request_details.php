<?php
require_once 'dbh.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids'])) {
    // Decode the JSON string to get the array of IDs
    $ids = json_decode($_POST['ids'], true);
    
    if (is_array($ids) && !empty($ids)) {
        // Prepare the IDs for SQL (prevent SQL injection)
        $safeIds = array_map(function($id) use ($conn) {
            return mysqli_real_escape_string($conn, $id);
        }, $ids);
        
        // Join the IDs for the SQL query
        $idList = "'" . implode("','", $safeIds) . "'";
        
        // Get detailed information for all selected requests
        $sql = "SELECT * FROM request WHERE id IN ($idList)";
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            echo '<div class="accordion" id="requestAccordion">';
            
            $counter = 0;
            while ($data = mysqli_fetch_assoc($result)) {
                $counter++;
                ?>
                <div class="card">
                    <div class="card-header" id="heading<?= $counter ?>">
                        <h2 class="mb-0">
                            <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" 
                                    data-target="#collapse<?= $counter ?>" aria-expanded="<?= ($counter === 1) ? 'true' : 'false' ?>" 
                                    aria-controls="collapse<?= $counter ?>">
                                <?= $data['name'] ?> - <?= $data['destination'] ?>
                            </button>
                        </h2>
                    </div>

                    <div id="collapse<?= $counter ?>" class="collapse <?= ($counter === 1) ? 'show' : '' ?>" 
                         aria-labelledby="heading<?= $counter ?>" data-parent="#requestAccordion">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?= $data['name'] ?></p>
                                    <p><strong>Position:</strong> <?= $data['position'] ?></p>
                                    <p><strong>Date:</strong> <?= $data['date'] ?></p>
                                    <p><strong>Destination:</strong> <?= $data['destination'] ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Purpose:</strong> <?= $data['purpose'] ?></p>
                                    <p><strong>Time of Departure:</strong> <?= $data['timedept'] ?></p>
                                    <p><strong>Type of Request:</strong> <?= $data['typeofbusiness'] ?></p>
                                    <p><strong>Current Status:</strong> <?= $data['Status'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
            
            echo '</div>';
        } else {
            echo '<div class="alert alert-warning">No detailed information found for the selected requests.</div>';
        }
    } else {
        echo '<div class="alert alert-danger">No valid IDs provided.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid request.</div>';
}
?>
