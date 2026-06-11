<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
include 'navadmin.php';
session_start();


// Define an associative array to map branches to their respective addresses
$branchAddresses = [
    "iMall Antipolo" => "ML Quezon Avenue corner Carigma St. Antipolo City Rizal",
    "iMall Canlubang" => "Jose Yulo Sr. Avenue Cor. Cecilia Araneta Ave. Canlubang",
    "iMall Camarin" => "1422 Camarin Rd, Barangay 178, Caloocan, Metro Manila",
    "iMall Famy" => "Imall Famy Manila East Road Tunhac Famy Laguna",
    "Cogeo Town Plaza" => "Cogeo Town Center, Brgy. Bagong Nayon, Antipolo City, Rizal,",
    "APM Commercial" => "New Antipolo Public Market, Sumulong Hiway Dela Paz Antipolo City",
    "CITI Centre" => "J. P. Rizal St, Nangka, Marikina"
];

if (isset($_POST['submit'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = ($_POST['password']);
    $user_type = $_POST['user_type'];
    $email = $_POST['email'];
    $position = $_POST['position'];
    $fullname = $_POST['fullname'];
    $branch = $_POST['branch'];
    $address = $_POST['address'];

    // Check for empty fields
    if (empty($username) || empty($pass) || empty($user_type) || empty($email) || empty($position) || empty($fullname) || empty($branch)) {
        $error[] = 'All fields are required';
    } else {
        // Use prepared statements to prevent SQL injection
        $select = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $select);
        mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error[] = 'User with this username or email already exists!';
        } else {
            $insert = "INSERT INTO users (username, password, user_type, email, position, fullname, branch,address) VALUES (?, ?, ?, ?, ?, ?, ?,?)";
            $stmt = mysqli_prepare($conn, $insert);

            mysqli_stmt_bind_param($stmt, 'ssssssss', $username, $pass, $user_type, $email, $position, $fullname, $branch,$address);

            if (mysqli_stmt_execute($stmt)) {
                // Registration successful, add a success message
                echo '<script>alert("Adding User successful");</script>';
                echo '<script>window.location.href = "users.php";</script>';
            } else {
                $error[] = 'Registration failed';
            }
        }

        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
}
?>






<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>

    <!-- Include Font Awesome for the eye icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <!-- Custom CSS file link -->
    <link rel="stylesheet" href="admin.css">
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
    <center>
    <div class="form-container">
        <form action="adduser.php" method="post" onsubmit="return validateForm()">
        <?php
if (!empty($error)) {
    echo '<div class="error-msg">';
    foreach ($error as $msg) {
        echo '<p>' . $msg . '</p>';
    }
    echo '</div>';
}
?>
            <h3>Add User</h3>
            <input type="text" name="username" required placeholder="Enter your username">
            <input type="text" name="fullname" required placeholder="Enter your fullname">
            <input type="email" name="email" required placeholder="Enter your email">
            <div class="password-input-container" style="position: relative;">
                <input type="password" name="password" id="password" required placeholder="Enter your password">
                <i style="padding: 10px" class="far fa-eye password-toggle-icon" id="password-toggle-icon"
                    onclick="togglePasswordVisibility()"></i>
            </div>
            <div id="passwordWeakMessage"></div>
            <div id="passwordStrengthMessage"></div>
            <input type="text" name="position" required placeholder="Enter your position">
                <select name="branch" required onchange="fillAddress(this)">
                    <option value="" disabled selected>Select Branch</option>
                    <option value="iMall Antipolo">iMall Antipolo</option>
                    <option value="iMall Canlubang">iMall Canlubang</option>
                    <option value="iMall Camarin">iMall Camarin</option>
                    <option value="iMall Famy">iMall Famy</option>
                    <option value="Cogeo Town Plaza">Cogeo Town Plaza</option>
                    <option value="APM Commercial">APM Commercial</option>
                    <option value="CITI Centre">CITI Centre</option>
                </select>
            <select name="user_type" required>
            <option value="" disabled selected>Select User/Admin</option>
                <option value="user">user</option>
                <option value="admin">admin</option>
            </select>
            <input type="text" name="address" required placeholder="Branch Address" readonly>
            <input type="submit" name="submit" value="Add User" class="form-btn">
            <p>Already have an account? <a href="logout.php">Login now</a></p>
        </form>

        <script>

function fillAddress(branchSelect) {
                    var addressInput = document.getElementsByName("address")[0];

                    // Get the selected branch value
                    var selectedBranch = branchSelect.value;

                    // Use the selected branch value to fetch the corresponding address from the array
                    var branchAddress = <?php echo json_encode($branchAddresses); ?>;
                    var selectedAddress = branchAddress[selectedBranch];

                    // Fill the address input field
                    addressInput.value = selectedAddress;
                }

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

            passwordInput.addEventListener("input", function () {
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
                const passwordStrength = calculatePasswordStrength(passwordInput.value);
                if (passwordStrength === "weak") {
                    alert("Password is weak. Choose another password.");
                    return false;
                }
                return true;
            }
        </script>



    </div>

        </center>
</body>

</html>