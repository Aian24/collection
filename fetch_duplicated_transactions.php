<?php
ob_start();
session_start();

// Set default timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Get user email and branch from session
$username = $_SESSION["username"] ?? '';
$branch = $_SESSION["branch"] ?? '';

// Release session lock immediately - critical for AJAX polling to never block other requests
session_write_close();

if (empty($username) || empty($branch)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    exit;
}

// Only connect to database after session check passes
include 'config.php';

// Set the current date boundaries
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

// Fetch duplicated transactions using high-performance INNER JOIN instead of correlated subquery
$query = "
    SELECT t1.* 
    FROM $table t1
    INNER JOIN (
        SELECT spacecode 
        FROM $table 
        WHERE collected_date BETWEEN ? AND ? AND username = ? 
        GROUP BY spacecode 
        HAVING COUNT(*) > 1
    ) t2 ON t1.spacecode = t2.spacecode
    WHERE t1.collected_date BETWEEN ? AND ? AND t1.username = ?
    ORDER BY t1.spacecode ASC, t1.collected_date DESC";

$duplicates = [];
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("ssssss", $startOfDay, $endOfDay, $username, $startOfDay, $endOfDay, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $duplicates[] = $row;
        }
    }
    $stmt->close();
}

if (isset($conn) && $conn) {
    $conn->close();
}

// Return JSON response containing the duplicated transactions
header('Content-Type: application/json; charset=utf-8');
echo json_encode($duplicates);
