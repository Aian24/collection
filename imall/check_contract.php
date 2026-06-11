<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
session_start();

if (isset($_POST['contract'])) {
    $contract = $_POST['contract'];
    // Perform a MySQL query to check if the contract exists in the database
    $query = "SELECT COUNT(*) FROM tenants WHERE contract = '$contract'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        $count = mysqli_fetch_row($result)[0];
        if ($count > 0) {
            echo "valid";
        } else {
            echo "invalid";
        }
    } else {
        echo "invalid";
    }
} else {
    echo "invalid";
}
?>