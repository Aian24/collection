<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
session_start();


if (isset($_POST['contract']) && isset($_POST['branch'])) {
    $contract = $_POST['contract'];
    $branch = $_POST['branch'];

    // Delete the selected tenant
    $conn->query("DELETE FROM `$branch` WHERE `contract` = '$contract'");

    // Redirect back to the tenant list page
    header("Location: update_tenants.php?branch=$branch");
    exit();
}
?>
