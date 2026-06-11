<?php
ob_start(); // Start output buffering
include '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: ../index.php");
    exit();
}
session_write_close(); // Release session lock to prevent deadlocks

// Get the selected branch from the session or request
$branch = isset($_GET['branch']) ? $_GET['branch'] : '';

// Initialize arrays for monthly and yearly data
$monthlyData = [
    'labels' => [],
    'data' => array_fill(0, 12, 0) // For each month
];

$yearlyData = [
    'labels' => [],
    'data' => []
];

// Function to extract total charges from the charges string
function extractCharges($chargesStr)
{
    $total = 0;
    $chargesArray = explode(',', $chargesStr);
    foreach ($chargesArray as $charge) {
        $parts = explode(':', $charge);
        if (isset($parts[1])) {
            $amount = trim($parts[1]);
            if (is_numeric($amount)) {
                $total += floatval($amount);
            }
        }
    }
    return $total;
}

// Determine which table to query based on the branch
$tableName = $branch === 'Nova Market' ? 'collectednova' : 'collected';

// Fetch total collection for each month
$queryMonthly = "SELECT MONTH(collected_date) AS month, 
                        SUM(paidrent) AS total_paidrent, 
                        SUM(paidbal) AS total_paidbal, 
                        GROUP_CONCAT(charges SEPARATOR ',') AS all_charges
                 FROM $tableName 
                 WHERE branch = '$branch'"; // Include branch filter
if ($branch) {
    $queryMonthly .= " AND branch = '$branch'";
}
$queryMonthly .= " GROUP BY MONTH(collected_date)";

$resultMonthly = mysqli_query($conn, $queryMonthly);

if (!$resultMonthly) {
    die('Query failed: ' . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($resultMonthly)) {
    $monthIndex = (int) $row['month'] - 1; // Convert month to index (0-11)
    if ($monthIndex >= 0 && $monthIndex < 12) { // Ensure valid month index
        $totalCharges = extractCharges($row['all_charges']); // Use extractCharges function

        $totalCollection = $row['total_paidrent'] + $row['total_paidbal'] + $totalCharges;
        $monthlyData['data'][$monthIndex] = $totalCollection; // Store total collection for each month
    }
}

// Create month labels only once at the beginning
$monthlyData['labels'] = array_map(function($month) {
    return date('F', mktime(0, 0, 0, $month, 1));
}, range(1, 12));

// Fetch total collection for each year
$queryYearly = "SELECT YEAR(collected_date) AS year, 
                       SUM(paidrent) AS total_paidrent, 
                       SUM(paidbal) AS total_paidbal, 
                       GROUP_CONCAT(charges SEPARATOR ',') AS all_charges
                FROM $tableName 
                WHERE branch = '$branch'"; // Include branch filter
if ($branch) {
    $queryYearly .= " AND branch = '$branch'";
}
$queryYearly .= " GROUP BY YEAR(collected_date)";

$resultYearly = mysqli_query($conn, $queryYearly);

if (!$resultYearly) {
    die('Query failed: ' . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($resultYearly)) {
    $totalCharges = extractCharges($row['all_charges']); // Use extractCharges function

    $totalCollection = $row['total_paidrent'] + $row['total_paidbal'] + $totalCharges;
    $yearlyData['labels'][] = $row['year'];
    $yearlyData['data'][] = $totalCollection; // Store total collection for each year
}

// Handle case when there is no data
if (empty($yearlyData['data'])) {
    $yearlyData['data'][] = 0; // Ensure at least one entry for the chart
}
if (empty($monthlyData['data'])) {
    $monthlyData['data'] = array_fill(0, 12, 0); // Fill with zeros for each month
}

// Close the database connection
mysqli_close($conn);

// Set the content type to JSON and output the data
header('Content-Type: application/json');
echo json_encode(['monthly' => $monthlyData, 'yearly' => $yearlyData]);
?>
