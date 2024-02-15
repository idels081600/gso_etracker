<?php
 require_once 'dbh.php';
 session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Login</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  <link rel="stylesheet" href="mobile.css">
  <style>
    body {
      background-color: #f0f0f0;
    }

    * {
      margin: 0;
      padding: 0;
    }

    .background-div {
      background-image: url('bg_image.jpg');
      background-size: cover;
      background-repeat: no-repeat;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      margin: 0;
      padding: 0;
      background-color: rgba(0, 0, 0, 0.1!important); 
    }

    .background-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(255, 255, 255, 0.0); /* Change to white with desired opacity */
  z-index: 1; /* Place the overlay above the background image */
}
.container {
  position: relative; /* Ensure relative positioning for contained elements */
  z-index: 2; /* Place the container above the overlay */
}
    .background-div-mobile {
      display:none;
      background-image: url('bg_image.jpg');
      background-size: cover;
      background-repeat: no-repeat;
      height: 300px;
      border-radius: 10px 10px 30px 30px;
      overflow: hidden;
    
    }

    .form-container {
    background-color: rgba(255, 255, 255, 0.5);
    position: relative;
    border-radius: 10px;
    padding: 30px;
    width: 18rem;
    margin-top: -490px;
    margin-left: auto;
    margin-right: auto;
    z-index: 1;
}

    .text-center {
      text-align: center;
    }

    .form-control {
      background-color: rgba(255, 255, 255, 0.4);
      height: 30px;
      width: 100%;
      margin-bottom: 10px;
      border-radius: 2px;
      border: 1px solid #e9eced;
      font-size: 15px;
      padding: 4px;
    }

    .form-control:focus {
  background-color: rgba(255, 255, 255, 0.9);
  border: 20pxpx solid #e9eced;
  box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.2); /* Increase the shadow on focus */
}

    .btn {
      background-color: #007bff;
      color: white;
      width: 50%;
      border: none;
      cursor: pointer;
      opacity: 0.8;
      transition: opacity 0.3s;
    }

    .btn:hover {
      opacity: 1;
    }

    .btn-center {
      display: block;
      margin: 0 auto;
    }

    .navbar {
      background-color: transparent;
      position: fixed;
      width: 100%;
      z-index: 2;
    }

    .navbar-brand .logo-text {
      color: white;
      font-weight: bold;
      font-size: 24px;
      line-height: 40px;
    }

    .logo-img {
      border-radius: 50%;
      width: 50px;
      height: 50px;
      object-fit: cover;
    }

  @media screen and (max-width: 767px) {
    .background-div {
        display: none; /* Hide the desktop background */
      }
      .background-div-mobile {
        height: 300px;
        border-radius: 10px 10px 30px 30px;
        overflow: hidden;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0;
        padding: 0;
      }
  
  .form-container {
    background-color: rgba(255, 255, 255, 0.6); /* More opaque background */
    width: 75% !important; /* Make the form container wider */
    height:380px;
    padding: 30px; /* Increase padding, both top and bottom */
    margin: 10px auto !important; /* Center the form container and move it down */
    box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5); /* Add a rounded shadow */
    background-repeat: no-repeat; /* Prevent the background image from repeating */
    background-position: center center; /* Center the background image horizontally and vertically */
    border-radius: 10px; /* Add rounded corners */
  }
  .form-control:focus {
  background-color: rgba(255, 255, 255, 0.9);
  border: 20pxpx solid #e9eced;
  box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.2); /* Increase the shadow on focus */
}

  .form-control {
    height: 40px; /* Increase input field height */
    font-size: 14px; /* Adjust font size for mobile */
    
  }

  .btn-center {
    width: 80%; /* Make the login button wider */
  }

  .social-icons {
    text-align: center;
    margin-top: 10px !important; /* Add spacing to the top of social icons */
  }

  .social-icons a {
    color: #007bff; /* Change icon color */
    font-size: 24px; /* Adjust icon size */
    margin: 0 10px; /* Add spacing between icons */
  }
  #login_btn{
    width: 120px; /* Adjust the width as needed */
  height: 40px; /* Adjust the height as needed */
  background-color: #007bff; /* Button background color */
  color: white;
  border: none;
  cursor: pointer;
  font-size: 16px;
  
  }
}



</style>

</head><nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
  <a class="navbar-brand" href="login_v2.php">
    <img src="logo.png" alt="Logo" class="logo-img">
    <span class="logo-text">E-Pass </span>
  </a>
  </div>
</nav>
<body>
<div class="background-div"></div>
<div class="background-overlay"></div>
<div class="background-div-mobile"></div>
<div class="container">
  <div class="form-container">
    <form action="check_login.php" method="POST">
      <div class="text-center">
     
        <h2>Welcome!</h2>
        <?php if (isset($_SESSION['LoginMessage'])): ?>
                <div class="alert alert-danger" id="error-alert">
                    <?php echo $_SESSION['LoginMessage']; ?>
                </div>
                <?php unset($_SESSION['LoginMessage']); ?> 
            <?php endif; ?>

      </div>
      <div>
</div>
        
      <div class="form-group">
        <label for="usr">Username:</label>
        <input type="text" class="form-control" id="usr" name="username" required>
      </div>
      <div class="form-group">
        <label for="pwd">Password:</label>
        <input type="password" class="form-control" id="pwd" name="password" required>
      </div>
      <div class="text-center">
        <button type="submit" name="login" id = "login_btn"class="btn btn-primary btn-center">Login</button><br>
      <div class="text-center mt-3">
      <a href="https://www.facebook.com/tagbilarancitygso" target="_blank" rel="noopener noreferrer">
        <i class="fab fa-facebook fa-lg text-dark"></i>
      </a>
      <a href="https://cgsotagbilaran.com" target="_blank" rel="noopener noreferrer" class="ml-2">
        <i class="fas fa-globe fa-lg text-dark"></i>
      </a>
    </div>
        </div>
    </form>
   
    <script>
            setTimeout(function () {
                document.getElementById('error-alert').style.display = 'none';
            }, 1000); 
        </script>
</body>
</html>