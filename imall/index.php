<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

$error = array(); // Initialize error array

if (isset($_GET['logout'])) {
    // Handle logout
    $user_id = $_SESSION['user_id'];

    // Update user's online status to 0 (offline)
    $conn->query("UPDATE `users` SET `online_status` = 0 WHERE `id` = $user_id");

    // Destroy the session
    session_destroy();
    header('location: index.php'); // Redirect to the login page or any other page
    exit();
}

if (isset($_SESSION['user_name'])) {
    // Regular user is already logged in, redirect to the user page
    header('location: CreateAR.php');
    exit();
} elseif (isset($_SESSION['admin_name'])) {
    // Admin is already logged in, redirect to the admin page
    header('location: admin_page.php');
    exit();
}

// Fetch user data
$userDataQuery = "SELECT id, online_status FROM users";
$userDataResult = mysqli_query($conn, $userDataQuery);
$userData = mysqli_fetch_all($userDataResult, MYSQLI_ASSOC);

if (isset($_POST['submit'])) {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = ($_POST['password']);
    $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : ''; // Check if user_type is set

    $select = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $select);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result);

        if ($row['password'] == $pass) {
            // Password matches, proceed with login
            if ($row['user_type'] == 'admin') {
                $_SESSION['admin_name'] = $row['username'];
                header('location: admin_page.php');
                exit();
            } elseif ($row['user_type'] == 'user') {
                $_SESSION['email'] = $row['email'];
                $_SESSION['user_name'] = $row['username'];
                $_SESSION['position'] = $row['position'];
                $_SESSION['fullname'] = $row['fullname'];
                $_SESSION['branch'] = $row['branch'];
                $_SESSION['address'] = $row['address'];
                $_SESSION['user_id'] = $row['id'];
                $user_id = $row['id'];
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $current_datetime = date('Y-m-d H:i:s');

                // Update user's last login, IP address, and set online status to 1 (online)
                $conn->query("UPDATE `users` SET `last_login` = '$current_datetime', `ip_address` = '$ip_address', `online_status` = 1 WHERE `id` = $user_id");

                // Set online status to 1 for the current session
                $_SESSION['online_status'] = 1;

                header('location: CreateAR.php');
                exit();
            }
        } else {
            $error[] = 'Incorrect password'; // Password is incorrect
        }
    } else {
        $error[] = 'User does not exist'; // User does not exist
    }
}
?>


<!DOCTYPE HTML>

<html>

<head>
	<title>iMall</title>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
	<link rel="stylesheet" href="assets/css/main.css" />
	<link rel="stylesheet" href="signin-signup.css" />
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

	
</head>

<body class="is-preload">
	


      <div class="signin-signup">
        <form action="index.php" method="POST" class="sign-in-form" autocomplete="off">
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

          <p style="font-size:18px;">forgot password? <a href="forgot-password.php">Click here</a></p>
        </form>
      </div>


	<!-- Footer -->
	<footer id="footer">
		<ul class="icons">
			<li></li>
			<li><a href="https://www.facebook.com/people/IMall-Antipolo/100063989017161/"
					class="icon brands fa-facebook"><span class="label">facebook</span></a></li>
			<li></li>
			<li></li>
			<li><a href="https://www.facebook.com/p/Imall-Camarin-Page-100057235055451/"
					class="icon brands fa-facebook"><span class="label">Facebook</span></a></li>
			<li></li>
			<li></li>
			<li><a href="https://www.facebook.com/p/IMALL-Canlubang-100077331828612/"
					class="icon brands fa-facebook"><span class="label">Facebook</span></a></li>

		</ul>
		<ul class="copyright">
			<li>Antipolo</li>
			<li>Camarin</li>
			<li>Canlubang</li>
		</ul>
	</footer>

	<!-- Scripts -->
	<script src="assets/js/main.js"></script>
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

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script>
  $(document).ready(function () {
    function updateUserStatus(userId, status) {
        $.ajax({
            type: "POST",
            url: "update_user_status.php",
            data: { user_id: userId, online_status: status },
            success: function (response) {
                console.log(response);
            },
            error: function (error) {
                console.error("AJAX error:", error);
            }
        });
    }

    // Check if the user is logged in
    <?php if (isset($_SESSION['user_name'])) : ?>
        var userId = <?php echo $user_id; ?>;
        updateUserStatus(userId, 1); // Set online status to 1 for the current session
    <?php endif; ?>

    // Update user status to 0 (offline) when the user closes or refreshes the page
    $(window).on('unload', function () {
        <?php if (isset($_SESSION['user_name'])) : ?>
            var userId = <?php echo $user_id; ?>;
            updateUserStatus(userId, 0);
        <?php endif; ?>
    });

    // Periodically update user's online status
    setInterval(function () {
        <?php if (isset($_SESSION['user_name']) && $_SESSION['online_status'] == 1) : ?>
            var userId = <?php echo $user_id; ?>;
            updateUserStatus(userId, 1);
        <?php endif; ?>
    }, 5000);
});

</script>



</body>

</html>