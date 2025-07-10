<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logi_Sys_Dashboard</title>
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="Logi_Sys.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo">
            <span class="role">Admin</span>
            <span class="user-name">Lou March Cordovan</span>
        </div>
        <hr class="divider">
        <ul>
            <li><a href="./Logi_Sys_Dashboard.php"><i class="fas fa-home icon-size"></i> Dashboard</a></li>
            <li><a href="Logi_inventory.php"><i class="fas fa-box icon-size"></i> Inventory</a></li>
            <li><a href="Logi_mang.php"><i class="fas fa-users icon-size"></i> Office Balances</a></li>
            <li><a href="Logi_manage_office.php"><i class="fas fa-truck icon-size"></i> Request</a></li>
            <li><a href="Logi_transactions.php"><i class="fas fa-exchange-alt icon-size"></i> Transactions</a></li>
            <li><a href="create_report.php"><i class="fas fa-chart-line icon-size"></i> Report</a></li>
        </ul>
        <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
    </div>
    <div class="main_content">
        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="container" id="firstContainer">
                        <div class="tally">
                            <!-- First tally container content here -->
                        </div>
                        <div class="tally">
                            <!-- Second tally container content here -->
                        </div>
                        <div class="tally">
                            <!-- Third tally container content here -->
                        </div>
                        <div class="tally">
                            <!-- Fourth tally container content here -->
                        </div>
                    </div>

                    <div class="container" id="secondContainer">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tent ID</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>TENT-001</td>
                                        <td>Tagbilaran City</td>
                                        <td>Installed</td>
                                        <td>2024-01-15</td>
                                    </tr>

                                    <tr>
                                        <td>TENT-002</td>
                                        <td>Panglao Island</td>
                                        <td>On Stock</td>
                                        <td>2024-01-10</td>
                                    </tr>

                                    <tr>
                                        <td>TENT-003</td>
                                        <td>Baclayon</td>
                                        <td>Retrieval</td>
                                        <td>2024-01-08</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>


                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>

</html>