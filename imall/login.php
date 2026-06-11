<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
include 'navadmin.php';
session_start();


error_reporting(0);


if(isset($_POST['submit'])){

   $username = mysqli_real_escape_string($conn, $_POST['username']);
   $pass = ($_POST['password']);
   $user_type = $_POST['user_type'];

   $select = "SELECT * FROM users WHERE username = '$username'";
   $result = mysqli_query($conn, $select);

   if(mysqli_num_rows($result) > 0){
      $row = mysqli_fetch_array($result);

      if ($row['password'] == $pass) {
        // Password matches, proceed with login
        if ($row['user_type'] == 'admin') {
          $_SESSION['admin_name'] = $row['username'];
          header('location: admin_page.php');
        } elseif ($row['user_type'] == 'user') {
          $_SESSION['user_name'] = $row['username'];
          $_SESSION['position'] =  $row['position'];
          $_SESSION['fullname'] =  $row['fullname'];
          $_SESSION['branch'] =  $row['branch'];
          header('location:CreateAR.php');
        }
      } else {
        $error = 'Incorrect password'; // Password is incorrect
        echo "<script>alert('$error');</script>"; // Display an alert message
      }
   } else {
      $error = 'User does not exist'; // User does not exist
      echo "<script>alert('$error');</script>"; // Display an alert message
   }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://kit.fontawesome.com/64d58efce2.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="signin-signup.css">
  <script src="loader.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>


  <title>Sign in & Sign up Form</title>
 
  <style>
    /* Add your CSS styles here */
    .input-field {
      position: relative;
    }

    .eye-toggle {
      padding:20px;
      position: absolute;
      top: 50%;
      right: 10px;
      transform: translateY(-50%);
      cursor: pointer;
    }


  </style>

  
</head>
<body>

  <div class="container">
    <div class="forms-container">
      <div class="signin-signup">
        <form action="login.php" method="POST" class="sign-in-form">
          <h2 class="title">Sign in</h2>
          <?php
            if(isset($error)){
              foreach($error as $error){
                echo '<span class="error-msg">'.$error.'</span>';
              };
            };
          ?>
          <div class="input-field">
            <i class="fas fa-user"></i>
            <input type="name" name="username" required placeholder="Enter your username">
          </div>
          <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" required placeholder="Enter your password" id="passwordInput">
            <span toggle="#passwordInput" class="eye-toggle">
              <i class="fas fa-eye-slash" id="togglePassword"></i>
            </span>
          </div>
          <br>
          <input type="submit" name="submit" value="Login" class="btn solid" />
          <p> Forgot password? click here</p>
          <button style="border:none; outline:none;"> <a href="forgot-password.php"> Forgot Password </a> </button>
        </form>
      </div>
    </div>
  </div>

  <div class="panels-container">
    <div class="panel left-panel">
      <div class="content">
        <h3>Not registered yet?</h3>
        <p>Please Register to continue! Click Sign up button below</p>
        <br>
        <a href="register_form.php" class="btn transparent" id="sign-in-btn" style="padding:10px 20px;text-decoration:none">
          Sign up
        </a>
      </div>
      <img src="img/imall1.png" class="image" alt="" />
    </div>
  </div>

  <script>
    const togglePassword = document.querySelector("#togglePassword");
    const passwordInput = document.querySelector("#passwordInput");

    togglePassword.addEventListener("click", function () {
      const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
      passwordInput.setAttribute("type", type);
      this.classList.toggle("fa-eye");
      this.classList.toggle("fa-eye-slash");
    });
  </script>
</body>
</html>
