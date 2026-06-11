<?php
@include 'config.php';

if (isset($_POST['submit'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $pass = ($_POST['password']);
    $user_type = $_POST['user_type'];
    $position = $_POST['position'];
    $branch = $_POST['branch'];

    // Check if the username already exists
    $check_username = "SELECT * FROM users WHERE username = '$username'";
    $result_username = mysqli_query($conn, $check_username);

    // Check if the email already exists
    $check_email = "SELECT * FROM users WHERE email = '$email'";
    $result_email = mysqli_query($conn, $check_email);

    // Check if the full name already exists
    $check_fullname = "SELECT * FROM users WHERE fullname = '$fullname'";
    $result_fullname = mysqli_query($conn, $check_fullname);

    if (mysqli_num_rows($result_username) > 0 || mysqli_num_rows($result_email) > 0 || mysqli_num_rows($result_fullname) > 0) {
        // User, email, or full name already exists, display an alert message
        echo '<script>alert("User already exist. Choose different credentials.");</script>';
    } else {
        // All fields are unique, proceed with registration
        $insert = "INSERT INTO users (username, email, fullname, password, user_type, position,branch) VALUES ('$username', '$email', '$fullname', '$pass', '$user_type', '$position', '$branch')";
        if (mysqli_query($conn, $insert)) {
            // Registration successful, add a success message
            echo '<script>alert("Registration successful");</script>';
            // Redirect to index.php after successful registration
            echo '<script>window.location.href = "index.php";</script>';
        } else {
            $error[] = 'Registration failed';
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Register Form</title>

   <!-- Include Font Awesome for the eye icons -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
   
   <!-- Custom CSS file link -->
   <link rel="stylesheet" href="style.css">
   <style>
      /* CSS for password strength indicator and error message */
      .weak-password {
          border: 1px solid red;
      }
      .strong-password {
          border: 1px solid green;
      }
      .error-msg {
          color: red;
          margin-top: 5px;
      }

      /* Style for the password visibility toggle icon */
      .password-toggle-icon {
          position: absolute;
          right: 10px;
          top: 10px;
          cursor: pointer;
          z-index: 2;
      }
   </style>
</head>
<body>
<div class="form-container">
   <form action="register_form.php" method="post" onsubmit="return validateForm()">
      <h3>Register Now</h3>
      <input type="text" name="username" required placeholder="Enter your username">
      <input type="text" name="fullname" required placeholder="Enter your fullname">
      <input type="email" name="email" required placeholder="Enter your email">
      <div class="password-input-container" style="position: relative;">
          <input type="password" name="password" id="password" required placeholder="Enter your password">
          <i style="padding: 10px" class="far fa-eye password-toggle-icon" id="password-toggle-icon" onclick="togglePasswordVisibility()"></i>
      </div>
      <div id="passwordWeakMessage"></div>
      <div id="passwordStrengthMessage"></div>
      <input type="text" name="position" required placeholder="Enter your position">
      <select id="branch"  name="branch" required>
                            <option value="" disabled selected >Select Branch</option>
                            <option value="iMall Antipolo">iMall Antipolo</option>
                            <option value="iMall Canlubang">iMall Canlubang</option>
                            <option value="iMall Camarin">iMall Camarin</option>
                            <option value="Cogeo Town Plaza">Cogeo Town Plaza</option>
                            <option value="Antipolo Market">Antipolo Market</option>
                        </select>
      <input type="text" name="user_type" value="user" readonly>
      <input type="hidden" id="userExists" name="userExists" value="0"> <!-- Hidden input for user exists flag -->
      <input type="submit" name="submit" value="Register Now" class="form-btn" id="submitBtn">
      <p>Already have an account? <a href="login.php">Login now</a></p>
   </form>

   <script>
   // JavaScript for password strength indicator
const passwordInput = document.getElementById("password");
const passwordStrengthMessage = document.getElementById("passwordStrengthMessage");
const submitButton = document.getElementById("submitBtn");

function togglePasswordVisibility() {
    const passwordToggleIcon = document.getElementById("password-toggle-icon");

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        passwordToggleIcon.classList.remove("fa-eye");
        passwordToggleIcon.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        passwordToggleIcon.classList.remove("fa-eye-slash");
        passwordToggleIcon.classList.add("fa-eye");
    }
}

passwordInput.addEventListener("input", function() {
    const password = passwordInput.value;
    const passwordStrength = calculatePasswordStrength(password);

    if (passwordStrength === "weak") {
        passwordStrengthMessage.style.color = "red";
        passwordStrengthMessage.textContent = "Password is weak. At least 8 characters and no spaces.";
        submitButton.disabled = true;
    } else {
        passwordStrengthMessage.style.color = "green";
        passwordStrengthMessage.textContent = "Password is strong.";
        submitButton.disabled = false;
    }
});

function calculatePasswordStrength(password) {
    if (password.length >= 8 && !/\s/.test(password)) {
        return "strong";
    } else {
        return "weak";
    }
}

function validateForm() {
    if (document.getElementById("userExists").value === "1") {
        alert("User already exists. Choose a different username.");
        return false;
    }
    return true;
}
</script>
</div>
</body>
</html>