<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
session_start();

if (isset($_POST['contract']) && isset($_POST['branch']) && isset($_POST['company']) && isset($_POST['stall']) && isset($_POST['trade'])) {
    $contract = $_POST['contract'];
    $branch = $_POST['branch'];
    $company = $_POST['company'];
    $stall = $_POST['stall'];
    $trade = $_POST['trade'];

    // Update the tenant record
    $updateQuery = "UPDATE `$branch` SET `company` = '$company', `stall` = '$stall', `trade` = '$trade' WHERE `contract` = '$contract'";
    $result = $conn->query($updateQuery);

    if ($result) {
        // Show prompt alert and redirect on success
        echo '<script>
                alert("Tenant updated successfully.");
                window.location.href = "update_tenants.php";
              </script>';
    } else {
        echo 'Error updating tenant: ' . $conn->error;
    }
}
?>
