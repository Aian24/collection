<?php
// Include your database connection file
ob_start(); // Start output buffering
include '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    // Redirect to login page if not logged in
    header("Location: ../index.php");
    exit();
}

// Release session lock immediately to prevent session deadlocks
session_write_close();

// Initialize data array
$data = array();

// Function to extract and sum charges
function extractCharges($charges) {
    $total = 0;
    // Assuming charges are stored as a comma-separated string of key-value pairs
    $chargesArray = explode(', ', $charges);

    foreach ($chargesArray as $charge) {
        list($item, $amount) = explode(': ', $charge);
        $total += floatval($amount); // Add up all charges
    }

    return $total;
}

// Fetch total paid balance
$paidBalQuery = "SELECT SUM(paidbal) AS total_paid_bal FROM collected";
$paidBalResult = mysqli_query($conn, $paidBalQuery);
if ($paidBalResult) {
    $paidBalRow = mysqli_fetch_assoc($paidBalResult);
    $data['paid_bal'] = $paidBalRow['total_paid_bal'] ? $paidBalRow['total_paid_bal'] : 0;
} else {
    $data['paid_bal'] = 0; // Handle error case
}

// Fetch total paid rent
$paidRentQuery = "SELECT SUM(paidrent) AS total_paid_rent FROM collected";
$paidRentResult = mysqli_query($conn, $paidRentQuery);
if ($paidRentResult) {
    $paidRentRow = mysqli_fetch_assoc($paidRentResult);
    $data['paid_rent'] = $paidRentRow['total_paid_rent'] ? $paidRentRow['total_paid_rent'] : 0;
} else {
    $data['paid_rent'] = 0; // Handle error case
}



// Close connection
mysqli_close($conn);

// Output JSON
header('Content-Type: application/json');
echo json_encode($data);
?>
