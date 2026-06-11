<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
include 'navadmin.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['admin_name'])) {
    header("Location: index.php"); // Redirect to the login page
    exit();
}

$success_message = ''; // Initialize a variable for success message

if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];

    // Retrieve user information from the database
    $result = $conn->query("SELECT * FROM `users` WHERE `id` = $user_id");
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    } else {
        // Handle the case where the user doesn't exist
        header("Location: users.php");
        exit();
    }
} else {
    // Handle the case where 'user_id' is not provided in the URL
    header("Location: users.php");
    exit();
}

// Define an associative array to map branches to their respective addresses
$branchAddresses = [
    "iMall Antipolo" => "ML Quezon Avenue corner Carigma St. Antipolo City Rizal",
    "iMall Canlubang" => "Jose Yulo Sr. Avenue Cor. Cecilia Araneta Ave. Canlubang",
    "iMall Camarin" => "1422 Camarin Rd, Barangay 178, Caloocan, Metro Manila",
    "iMall Famy" => "Imall Famy Manila East Road Tunhac Famy Laguna",
    "Cogeo Town Plaza" => "Cogeo Town Center, Brgy. Bagong Nayon, Antipolo City, Rizal,",
    "APM Commercial" => "New Antipolo Public Market, Sumulong Hiway Dela Paz Antipolo City",
    "CITI Centre" => "Di pa alam"
];


// Check if the update form is submitted
if (isset($_POST['update_user'])) {
    // Retrieve updated user data from the form
    $new_username = $_POST['new_username'];
    $new_fullname = $_POST['new_fullname'];
    $new_email = $_POST['new_email'];
    $new_password = $_POST['new_password'];
    $new_user_type = $_POST['new_user_type'];
    $new_position = $_POST['new_position'];
    $new_branch = $_POST['new_branch'];
    $new_address = $_POST['new_address'];

    // Perform the update operation (you should add appropriate validation and error handling here)
    $conn->query("UPDATE `users` SET `username` = '$new_username', `fullname` = '$new_fullname', `email` = '$new_email', `password` = '$new_password', `user_type` = '$new_user_type', `position` = '$new_position', `branch` = '$new_branch', `address` = '$new_address' WHERE `id` = $user_id");


    // Set the success message
    $success_message = 'User information updated successfully.';
}

?>
<!DOCTYPE html>
<html lang="en">

<!-- custom css file link  -->
<link rel="stylesheet" href="admin.css">

<head>
    <title> Updated/Edit User </title>
</head>

<body>
<script>
        // JavaScript function to display an alert and redirect
        function showAlertAndRedirect(message, redirectURL) {
            alert(message);
            window.location.href = redirectURL;
        }

        // JavaScript function to fill the address based on the selected branch
        function fillAddress(branchSelect) {
            var addressInput = document.getElementsByName("new_address")[0];
            var selectedBranch = branchSelect.value;
            var branchAddress = <?php echo json_encode($branchAddresses); ?>;
            var selectedAddress = branchAddress[selectedBranch];
            addressInput.value = selectedAddress;
        }
    </script>
    <?php
    // Display the success message and redirect using JavaScript
    if (!empty($success_message)) {
        echo '<script>showAlertAndRedirect("' . $success_message . '", "users.php");</script>';
    }
    ?>
    <center>
    <div class="form-container">
        <form action="edituser.php?user_id=<?php echo $user_id; ?>" method="post" class="updateform">
         <h3>Edit User</h3>
            <label for="new_name">Username:</label>
            <input type="text"  name="new_username"
                value="<?php echo $user_data['username']; ?>"><br>

            <label for="new_name">FullName:</label>
            <input type="text"  name="new_fullname"
                value="<?php echo $user_data['fullname']; ?>"><br>

            <label for="new_email">Email:</label>
            <input type="email"  name="new_email" value="<?php echo $user_data['email']; ?>"><br>

            <label for="new_password">Password:</label>
            <input type="text"  name="new_password"
                value="<?php echo $user_data['password']; ?>"><br>

            <label for="new_position">Position:</label>
            <input type="text" name="new_position"  value="<?php echo $user_data['position']; ?>">
            <label for="branch">Branch:</label>
            <select name="new_branch"  onchange="fillAddress(this)">
                <option value="" disabled>Select Branch</option>
                <option value="iMall Antipolo" <?php if ($user_data['branch'] == 'iMall Antipolo') echo 'selected'; ?>>iMall Antipolo</option>
                <option value="iMall Canlubang" <?php if ($user_data['branch'] == 'iMall Canlubang') echo 'selected'; ?>>iMall Canlubang</option>
                <option value="iMall Camarin" <?php if ($user_data['branch'] == 'iMall Camarin') echo 'selected'; ?>>iMall Camarin</option>
                <option value="iMall Famy" <?php if ($user_data['branch'] == 'iMall Famy') echo 'selected'; ?>>iMall Famy</option>
                <option value="Cogeo Town Plaza" <?php if ($user_data['branch'] == 'Cogeo Town Plaza') echo 'selected'; ?>>Cogeo Town Plaza</option>
                <option value="APM Commercial" <?php if ($user_data['branch'] == 'APM Commercial') echo 'selected'; ?>>APM Commercial</option>
                <option value="CITI Centre" <?php if ($user_data['branch'] == 'CITI Centre') echo 'selected'; ?>>CITI Centre</option>

            </select><br>

            <label for="new_address">Address:</label>
            <input type="text" name="new_address"  value="<?php echo $user_data['address']; ?>" readonly><br>


            <label for="new_user_type">User Type:</label>
            <select name="new_user_type" >
                <option value="user" <?php if ($user_data['user_type'] == 'user')
                    echo 'selected'; ?>>user</option>
                <option value="admin" <?php if ($user_data['user_type'] == 'admin')
                    echo 'selected'; ?>>admin</option>
            </select><br>
            <input type="submit" class="update-button" name="update_user" value="Update User">
        </form>
    </div>
</center>
</body>

</html>