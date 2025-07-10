<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>

<body>
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="border rounded p-4 shadow-sm" style="max-width: 1000px; width: 100%; height: 600px;">
            <div class="row">
                <!-- Left Side - Image and Title -->
                <div class="col-md-6 d-flex flex-column justify-content-center align-items-center text-center" style="margin-top: 30px;">
                    <div class="row align-items-center mb-3">
                        <div class="col-auto">
                            <img src="tagbi_seal.png" alt="Tagbi Seal" style="width: 74px; height: 74px; margin-right: 50px;">
                        </div>
                        <div class="col-auto">
                            <h2 class="mb-0" style="margin-top: 10px; font-size: 34px; font-weight: bold;">LogiSys</h2>
                            <p class="text" style="font-size: 12px; color: #35AE74;"> E-CGSO</p>
                        </div>
                        <div class="col-auto">
                            <img src="logo.png" alt="Logo" style="width: 74px; height: 74px; margin-left: 50px;">
                        </div>
                    </div>

                    <img src="person.png" alt="Logo" class="img-fluid mb-3" style="max-width: 1000px; width: 328px; height: 382px; margin-top:35px;">
                </div>

                <!-- Right Side - Login Fields -->
                <div class="col-md-6">
                    <h3 class="text-center mb-4" style="margin-top: 10px; font-size: 32px; font-weight: bold; margin-top: 100px;">Welcome!</h3>

                    <?php
                    session_start();
                    if (isset($_SESSION['login_error'])) {
                        echo '<div class="alert alert-danger text-center mb-3" style="width: 80%; margin-left: 40px;">' . $_SESSION['login_error'] . '</div>';
                        unset($_SESSION['login_error']);
                    }
                    ?>

                    <form method="POST" action="login_process.php">
                        <div class="mb-3">
                            <label for="username" class="form-label" style="margin-left: 40px; color: #6c757d;">Username</label>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" style="width: 80%; margin-left: 40px;">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label" style=" margin-left: 40px;color: #6c757d;">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" style="width: 80%; margin-left: 40px;">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe" style=" margin-left: 20px;">
                            <label class="form-check-label" for="rememberMe" style=" margin-left: 5px;">
                                Remember me
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 50%; margin-left: 110px; color: white; background-color: #35AE74; border-color: #35AE74;">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>

</html>