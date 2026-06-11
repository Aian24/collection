<?php
ob_start(); // Start output buffering
include 'config.php';
session_start();

// Set default timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Get user email and branch from session
$username = $_SESSION["username"] ?? '';
$branch = $_SESSION["branch"] ?? '';

if (empty($username) || empty($branch)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Set the current date
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

// Fetch duplicated transactions
$query = "
    SELECT * FROM $table 
    WHERE DATE(collected_date) = ? AND username = ? 
    AND spacecode IN (
        SELECT spacecode FROM $table 
        WHERE DATE(collected_date) = ? AND username = ? 
        GROUP BY spacecode HAVING COUNT(*) > 1
    )";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssss", $currentDate, $username, $currentDate, $username);
$stmt->execute();
$result = $stmt->get_result();

$duplicates = array();
while ($row = $result->fetch_assoc()) {
    $duplicates[] = $row;
}

// Close statement and connection
$stmt->close();
$conn->close();

// Return JSON response containing the duplicated transactions
header('Content-Type: application/json');
echo json_encode($duplicates);
?>
