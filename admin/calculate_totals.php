<?php
ob_start(); // Start output buffering
include '../config.php';
session_start();

// Check if the connection is successful
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get filter criteria from AJAX request
$fromDate = $_POST['fromDate'];
$toDate = $_POST['toDate'];
$branch = $_POST['branch'];

// Construct SQL query to fetch filtered data
$sql = "SELECT * FROM collected WHERE 1=1";
if (!empty($fromDate)) {
    $sql .= " AND collected_date >= '$fromDate'";
}
if (!empty($toDate)) {
    $sql .= " AND collected_date <= '$toDate'";
}
if (!empty($branch)) {
    $sql .= " AND branch = '$branch'";
}

// Execute SQL query to fetch filtered data
$result = mysqli_query($conn, $sql);

// Check if query execution is successful
if (!$result) {
    die("Error fetching data: " . mysqli_error($conn));
}

// Fetch filtered data
$data = array();
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

// Calculate totals
$total_paid_rent = array_sum(array_column($data, 'paidrent'));
$total_paid_bal = array_sum(array_column($data, 'paidbal'));

// Return filtered data and totals as JSON
echo json_encode(array('data' => $data, 'total_paid_rent' => $total_paid_rent, 'total_paid_bal' => $total_paid_bal));

// Close the connection
mysqli_close($conn);
?>
