<?php
ob_start(); // Start output buffering
include '../config.php';
include '../nav.php';
session_start();

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve edited data from the form
    $id = $_POST["editUserId"] ?? "";
    $email = $_POST["editEmail"] ?? "";
    $user_type = $_POST["editUserType"] ?? "";
    $username = $_POST["editUsername"] ?? "";
    $fname = $_POST["editFname"] ?? "";
    $lname = $_POST["editLname"] ?? "";
    $branch = $_POST["editBranch"] ?? "";
    $password = $_POST["editPassword"] ?? ""; // Note: Be sure to handle password securely, this is just a placeholder

    // Validate input data (you can add more validation as needed)
    if (empty($id) || empty($email) || empty($user_type) || empty($username) || empty($fname) || empty($lname) || empty($branch)) {
        // Handle empty fields
        $_SESSION['edit_user_error'] = "All fields are required.";
        header("Location: {$_SERVER['HTTP_REFERER']}");
        exit();
    }

    // Update user in the database
    $updateQuery = "UPDATE users SET email='$email', user_type='$user_type', username='$username',fname='$fname', lname='$lname', branch='$branch', password='$password' WHERE id='$id'";
    $updateResult = mysqli_query($conn, $updateQuery);

    // Check if update was successful
    if ($updateResult) {
        // Set session variable for success
        $_SESSION['user_updated'] = true;
        // Redirect to the previous page
        header("Location: {$_SERVER['HTTP_REFERER']}");
        exit();
    } else {
        // Handle the case where update fails
        $_SESSION['edit_user_error'] = "Failed to update user. Please try again.";
        header("Location: {$_SERVER['HTTP_REFERER']}");
        exit();
    }
} else {
    // Handle the case where form is not submitted
    $_SESSION['edit_user_error'] = "Invalid request.";
    header("Location: {$_SERVER['HTTP_REFERER']}");
    exit();
}
?>
