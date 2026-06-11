<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Get user email from session
$user_email = $_SESSION["email"];

// Get the current date
$currentDate = date('Y-m-d');

// Fetch the daily total from the collected table for the specific user email
$dailyTotalQuery = $conn->prepare("SELECT SUM(paidrent) AS total_paid_rent, SUM(paidbal) AS total_paid_balance FROM collected WHERE DATE(collected_date) = ? AND user_email = ?");
$dailyTotalQuery->bind_param("ss", $currentDate, $user_email);
$dailyTotalQuery->execute();
$dailyTotalResult = $dailyTotalQuery->get_result()->fetch_assoc();
$dailyTotalQuery->close();

// Calculate the daily total
$totalPaidRent = $dailyTotalResult['total_paid_rent'] ?? 0;
$totalPaidBalance = $dailyTotalResult['total_paid_balance'] ?? 0;
$dailyTotal = $totalPaidRent + $totalPaidBalance;

// Output the daily total
echo json_encode(array('daily_total' => $dailyTotal));
?>
