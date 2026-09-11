<?php
ob_start();
session_start();

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Get user email and branch from session
$username = $_SESSION["username"] ?? '';
$branch = $_SESSION["branch"] ?? '';

// Release session lock immediately
session_write_close();

if (empty($username) || empty($branch)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    exit;
}

include 'config.php';

// Get current date boundaries
$currentDate = date('Y-m-d');
$startOfDay = $currentDate . ' 00:00:00';
$endOfDay = $currentDate . ' 23:59:59';

// Determine the table based on the branch
$table = '';
if ($branch === 'Sanko Market') {
    $table = 'collected';
} elseif ($branch === 'APM') {
    $table = 'collectedapm';
} elseif ($branch === 'ACC' || $branch === 'Ambulant') {
    $table = 'collectedacc';
} else {
    $table = 'collectednova';
}

// Prepare and execute the query to fetch transactions for today and the logged-in user with indexed date range
$query = "SELECT * FROM `$table` WHERE collected_date BETWEEN ? AND ? AND username = ? ORDER BY id DESC";
$stmt = $conn->prepare($query);
$transactions = [];

if ($stmt) {
    $stmt->bind_param("sss", $startOfDay, $endOfDay, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
    $stmt->close();
}

if (isset($conn) && $conn) {
    $conn->close();
}

// Return JSON response containing the fetched transactions
header('Content-Type: application/json; charset=utf-8');
echo json_encode($transactions);
?>
