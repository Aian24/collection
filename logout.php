<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["lname"])) {
    // If not logged in, redirect to login page
    header("Location: index.php");
    exit();
}



// Destroy session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>
