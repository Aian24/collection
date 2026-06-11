<?php

$token = $_GET["token"];

$token_hash = hash("sha256", $token);

$mysqli = require __DIR__ . "/config.php";

$sql = "SELECT * FROM users
        WHERE reset_token_hash = ?";

// Establish a database connection
$mysqli = new mysqli("localhost", "wqxgzpmy_imall", "R4styL0p3z", "wqxgzpmy_imall");

// Check the connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
$stmt = $mysqli->prepare($sql);

$stmt->bind_param("s", $token_hash);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

if ($user === null) {
    die("token not found");
}

if (strtotime($user["reset_token_expires_at"]) <= time()) {
    die("token has expired");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
     <!-- Include Font Awesome for the eye icons -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
<center>
<div class="forgot-container">
      <div class="logo-container">
       Reset Password
      </div>
    <br>
    <form method="post" action="process-reset-password.php" onsubmit="return validateForm();">


        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="forgot-group">
          <label for="password">New Password</label>
          <input type="password" id="password" name="password" placeholder="Enter your password" required="">
          <i style="margin-top: -30px; margin-left:300px;" class="far fa-eye password-toggle-icon" id="password-toggle-icon" onclick="togglePasswordVisibility()"></i>
     <br>
         <div id="passwordWeakMessage"></div>
          <div id="passwordStrengthMessage"></div>
       <br>
       <label for="password_confirmation">Repeat password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Repeat your password" required>
        <i style="margin-top: -30px; margin-left: 300px;" class="far fa-eye password-toggle-icon" id="password-confirmation-toggle-icon" onclick="togglePasswordConfirmationVisibility()"></i>
        <div id="passwordMismatchMessage" style="color: red;"></div>


        </div>

        <button class="forgot-submit-btn" type="submit">Reset Password</button>

    </form>
</center>


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

// JavaScript for password visibility toggle in "Repeat password" field
const passwordConfirmationInput = document.getElementById("password_confirmation");
const passwordConfirmationToggleIcon = document.getElementById("password-confirmation-toggle-icon");

function togglePasswordConfirmationVisibility() {
    if (passwordConfirmationInput.type === "password") {
        passwordConfirmationInput.type = "text";
        passwordConfirmationToggleIcon.classList.remove("fa-eye");
        passwordConfirmationToggleIcon.classList.add("fa-eye-slash");
    } else {
        passwordConfirmationInput.type = "password";
        passwordConfirmationToggleIcon.classList.remove("fa-eye-slash");
        passwordConfirmationToggleIcon.classList.add("fa-eye");
    }
}


function validateForm() {
    const passwordInput = document.getElementById("password");
    const passwordConfirmationInput = document.getElementById("password_confirmation");
    const passwordMismatchMessage = document.getElementById("passwordMismatchMessage");

    if (passwordInput.value !== passwordConfirmationInput.value) {
        passwordMismatchMessage.textContent = "Password does not match!";
        return false;
    } else {
        passwordMismatchMessage.textContent = ""; // Clear any previous error message
        const passwordStrength = calculatePasswordStrength(passwordInput.value);
        if (passwordStrength === "weak") {
            alert("Password is weak. Choose another password.");
            return false;
        }
    }

    return true;
}

</script>
</body>
</html>