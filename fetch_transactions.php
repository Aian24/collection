<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Get user email and branch from session
$username = $_SESSION["username"];
$branch = $_SESSION["branch"];

// Get the current date in the Philippines timezone
$currentDate = date('Y-m-d');

// Determine the table based on the branch
$table = '';
if ($branch === 'Sanko Market') {
    $table = 'collected';
} elseif ($branch === 'APM') {
    $table = 'collectedapm'; // Add the APM table
} else {
    $table = 'collectednova';
}

// Prepare and execute the query to fetch transactions for today and the logged-in user
$query = "SELECT * FROM $table WHERE DATE(collected_date) = ? AND username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $currentDate, $username);
$stmt->execute();
$result = $stmt->get_result();

// Store fetched transactions in an array
$transactions = array();
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

// Close statement and connection
$stmt->close();
$conn->close();

// Return JSON response containing the fetched transactions
header('Content-Type: application/json');
echo json_encode($transactions);
?>
