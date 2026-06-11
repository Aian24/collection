<?php
ob_start(); // Start output buffering
include '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}

// Release session lock immediately to prevent session deadlocks
session_write_close();

// Get parameters from the request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$branch = isset($_GET['branch']) ? $_GET['branch'] : 'collected';

// Determine the table to query
$tableName = ($branch === 'Nova Market') ? 'collectednova' : (($branch === 'APM') ? 'collectedapm' : 'collected');

// Prepare response arrays
$response = [
    'monthly' => ['labels' => [], 'data' => []],
    'yearly' => ['labels' => [], 'data' => []]
];

// Initialize the monthly data
$months = [];
for ($i = 1; $i <= 12; $i++) {
    $months[$i] = 0; // Set initial collection for each month to 0
    $response['monthly']['labels'][] = date("F", mktime(0, 0, 0, $i, 1)); // Get month names
}

// Fetch total collection for each month
$queryMonthly = "SELECT MONTH(collected_date) AS month, 
                        SUM(paidrent) AS total_paidrent, 
                        SUM(paidbal) AS total_paidbal
                 FROM $tableName 
                 WHERE DATE(collected_date) BETWEEN '$start_date' AND '$end_date' 
                 GROUP BY MONTH(collected_date)";

$resultMonthly = mysqli_query($conn, $queryMonthly);
if ($resultMonthly) {
    while ($row = mysqli_fetch_assoc($resultMonthly)) {
        $month = (int)$row['month'];
        $months[$month] = $row['total_paidrent'] + $row['total_paidbal']; // Update the total for the month
    }
}

// Fill the response data with the collected totals
$response['monthly']['data'] = array_values($months); // Use only the values

// Fetch total collection for each year
$queryYearly = "SELECT YEAR(collected_date) AS year, 
                       SUM(paidrent) AS total_paidrent, 
                       SUM(paidbal) AS total_paidbal
                FROM $tableName 
                WHERE DATE(collected_date) BETWEEN '$start_date' AND '$end_date' 
                GROUP BY YEAR(collected_date)";

$resultYearly = mysqli_query($conn, $queryYearly);
if ($resultYearly) {
    while ($row = mysqli_fetch_assoc($resultYearly)) {
        $response['yearly']['labels'][] = $row['year'];
        $response['yearly']['data'][] = $row['total_paidrent'] + $row['total_paidbal']; // Total collection
    }
}

// Return the JSON response
header('Content-Type: application/json');
echo json_encode($response);

mysqli_close($conn);
?>
