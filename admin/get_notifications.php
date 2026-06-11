<?php
include '../config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get today's date
$today = date('Y-m-d');

// Log for debugging
error_log("Fetching notifications for date: $today");

// Determine which tables to query based on branch filter
$branch_filter = isset($_GET['branch']) ? $_GET['branch'] : '';

$queries = [];
$count_queries = [];

if ($branch_filter === 'Sanko Market') {
    $queries[] = "(SELECT transaction_number, tenantname, paidrent, paidbal, charges, collected_date, branch, 'collected' as source_table FROM collected WHERE DATE(collected_date) = '$today')";
    $count_queries[] = "SELECT transaction_number FROM collected WHERE DATE(collected_date) = '$today'";
} elseif ($branch_filter === 'Nova Market') {
    $queries[] = "(SELECT transaction_number, tenantname, paidrent, paidbal, charges, collected_date, branch, 'collectednova' as source_table FROM collectednova WHERE DATE(collected_date) = '$today')";
    $count_queries[] = "SELECT transaction_number FROM collectednova WHERE DATE(collected_date) = '$today'";
} elseif ($branch_filter === 'APM') {
    $queries[] = "(SELECT transaction_number, tenantname, paidrent, paidbal, charges, collected_date, branch, 'collectedapm' as source_table FROM collectedapm WHERE DATE(collected_date) = '$today')";
    $count_queries[] = "SELECT transaction_number FROM collectedapm WHERE DATE(collected_date) = '$today'";
} else {
    // All branches
    $queries[] = "(SELECT transaction_number, tenantname, paidrent, paidbal, charges, collected_date, branch, 'collected' as source_table FROM collected WHERE DATE(collected_date) = '$today')";
    $queries[] = "(SELECT transaction_number, tenantname, paidrent, paidbal, charges, collected_date, branch, 'collectednova' as source_table FROM collectednova WHERE DATE(collected_date) = '$today')";
    $queries[] = "(SELECT transaction_number, tenantname, paidrent, paidbal, charges, collected_date, branch, 'collectedapm' as source_table FROM collectedapm WHERE DATE(collected_date) = '$today')";
    
    $count_queries[] = "SELECT transaction_number FROM collected WHERE DATE(collected_date) = '$today'";
    $count_queries[] = "SELECT transaction_number FROM collectednova WHERE DATE(collected_date) = '$today'";
    $count_queries[] = "SELECT transaction_number FROM collectedapm WHERE DATE(collected_date) = '$today'";
}

$query = implode(" UNION ALL ", $queries) . " ORDER BY collected_date DESC LIMIT 10";

// Log the query for debugging
error_log("Notification query: $query");

$result = mysqli_query($conn, $query);

if (!$result) {
    error_log("Query error: " . mysqli_error($conn));
}

$notifications = array();

if ($result) {
    $row_count = mysqli_num_rows($result);
    error_log("Found $row_count notifications");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $message = '';
        $amount = 0;
        
        // Build notification message based on what was paid
        if ($row['paidrent'] > 0) {
            $message = "paid rent";
            $amount = $row['paidrent'];
        }
        if ($row['paidbal'] > 0) {
            $message = $message ? $message . " and balance" : "paid balance";
            $amount += $row['paidbal'];
        }
        if (!empty($row['charges'])) {
            // Extract total charges
            $charges_array = explode(',', $row['charges']);
            $charges_total = 0;
            foreach ($charges_array as $charge) {
                $parts = explode(':', $charge);
                if (isset($parts[1])) {
                    $charges_total += floatval(trim($parts[1]));
                }
            }
            if ($charges_total > 0) {
                $message = $message ? $message . " and charges" : "paid charges";
                $amount += $charges_total;
            }
        }

        // Format the time to be more readable
        $time_diff = time() - strtotime($row['collected_date']);
        if ($time_diff < 60) {
            $time = "just now";
        } elseif ($time_diff < 3600) {
            $mins = floor($time_diff / 60);
            $time = $mins . " min" . ($mins > 1 ? "s" : "") . " ago";
        } else {
            $time = date('h:i A', strtotime($row['collected_date']));
        }

        $notifications[] = array(
            'message' => $row['tenantname'] . " " . $message,
            'amount' => $amount,
            'time' => $time,
            'branch' => $row['branch'],
            'transaction_number' => $row['transaction_number']
        );
    }
    mysqli_free_result($result);
}

// Get total number of transactions today
$count_query = "SELECT COUNT(*) as total FROM (" . implode(" UNION ALL ", $count_queries) . ") as combined_transactions";

$count_result = mysqli_query($conn, $count_query);
$total_transactions = 0;

if ($count_result && $row = mysqli_fetch_assoc($count_result)) {
    $total_transactions = $row['total'];
    error_log("Total transactions today: $total_transactions");
    mysqli_free_result($count_result);
} else {
    error_log("Count query error: " . mysqli_error($conn));
}

mysqli_close($conn);

// Return JSON response
header('Content-Type: application/json');
$response = array(
    'notifications' => $notifications,
    'total' => $total_transactions,
    'date' => $today // Include the date in the response for debugging
);
echo json_encode($response);
?> 