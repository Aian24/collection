<?php
include '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Release session lock immediately - this prevents session deadlocks
// that cause infinite loading when multiple AJAX requests queue up
session_write_close();

// Get parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$branch = isset($_GET['branch']) ? $_GET['branch'] : '';

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}

// Sanitize branch input
$escaped_branch = mysqli_real_escape_string($conn, $branch);

// Determine the table based on branch
$table = 'collected';
if ($branch === 'Nova Market') {
    $table = 'collectednova';
} elseif ($branch === 'APM') {
    $table = 'collectedapm';
}

// Initialize response array
$response = [
    'totalCollection' => 0,
    'totalTransactions' => 0,
    'totalTenants' => 0,
    'totalUsers' => 0,
    'monthlyChartData' => ['labels' => [], 'data' => []],
    'yearlyChartData' => ['labels' => [], 'data' => []],
    'transactions' => []
];

// Function to extract charges
function extractCharges($chargesStr) {
    $total = 0;
    if (empty($chargesStr)) {
        return $total;
    }
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

// Get total users
$query_users = "SELECT COUNT(*) AS total_users FROM users";
$result_users = mysqli_query($conn, $query_users);
if ($result_users && $row = mysqli_fetch_assoc($result_users)) {
    $response['totalUsers'] = $row['total_users'];
}

// Get monthly data (last 12 months)
$end_month = new DateTime();
$start_month = clone $end_month;
$start_month->modify('-11 months');

// Initialize arrays with zeros for all months
for ($i = 0; $i < 12; $i++) {
    $response['monthlyChartData']['labels'][] = $start_month->format('M Y');
    $response['monthlyChartData']['data'][] = 0;
    $start_month->modify('+1 month');
}

// Query for monthly data
if ($branch) {
    $monthly_query = "SELECT 
        DATE_FORMAT(collected_date, '%Y-%m') as month,
        (paidrent + paidbal) as base_total,
        charges
        FROM $table 
        WHERE collected_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        AND branch = '$escaped_branch'";
} else {
    $monthly_query = "
            SELECT 
                DATE_FORMAT(collected_date, '%Y-%m') as month,
                (paidrent + paidbal) as base_total,
                charges
            FROM collected
            WHERE collected_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            UNION ALL
            SELECT 
                DATE_FORMAT(collected_date, '%Y-%m') as month,
                (paidrent + paidbal) as base_total,
                charges
            FROM collectednova
            WHERE collected_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            UNION ALL
            SELECT 
                DATE_FORMAT(collected_date, '%Y-%m') as month,
                (paidrent + paidbal) as base_total,
                charges
            FROM collectedapm
            WHERE collected_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
}

$monthly_result = mysqli_query($conn, $monthly_query);
if ($monthly_result) {
    while ($row = mysqli_fetch_assoc($monthly_result)) {
        $month_date = new DateTime($row['month'] . '-01');
        $month_index = array_search($month_date->format('M Y'), $response['monthlyChartData']['labels']);
        if ($month_index !== false) {
            $total = (float)$row['base_total'] + extractCharges($row['charges']);
            $response['monthlyChartData']['data'][$month_index] += $total;
        }
    }
}

// Get yearly data (last 5 years)
$current_year = date('Y');
for ($i = 4; $i >= 0; $i--) {
    $year = $current_year - $i;
    $response['yearlyChartData']['labels'][] = $year;
    $response['yearlyChartData']['data'][] = 0;
}

// Query for yearly data
if ($branch) {
    $yearly_query = "SELECT 
        YEAR(collected_date) as year,
        (paidrent + paidbal) as base_total,
        charges
        FROM $table 
        WHERE YEAR(collected_date) >= YEAR(CURDATE()) - 4
        AND branch = '$escaped_branch'";
} else {
    $yearly_query = "
            SELECT 
                YEAR(collected_date) as year,
                (paidrent + paidbal) as base_total,
                charges
            FROM collected
            WHERE YEAR(collected_date) >= YEAR(CURDATE()) - 4
            UNION ALL
            SELECT 
                YEAR(collected_date) as year,
                (paidrent + paidbal) as base_total,
                charges
            FROM collectednova
            WHERE YEAR(collected_date) >= YEAR(CURDATE()) - 4
            UNION ALL
            SELECT 
                YEAR(collected_date) as year,
                (paidrent + paidbal) as base_total,
                charges
            FROM collectedapm
            WHERE YEAR(collected_date) >= YEAR(CURDATE()) - 4";
}

$yearly_result = mysqli_query($conn, $yearly_query);
if ($yearly_result) {
    while ($row = mysqli_fetch_assoc($yearly_result)) {
        $year_index = array_search($row['year'], $response['yearlyChartData']['labels']);
        if ($year_index !== false) {
            $total = (float)$row['base_total'] + extractCharges($row['charges']);
            $response['yearlyChartData']['data'][$year_index] += $total;
        }
    }
}

// Get transactions for the table AND calculate totals
if ($branch) {
    $query_transactions = "SELECT * FROM $table 
        WHERE DATE(collected_date) BETWEEN '$start_date' AND '$end_date' 
        AND branch = '$escaped_branch' 
        ORDER BY collected_date ASC, transaction_number ASC";
} else {
    $query_transactions = "(SELECT * FROM collected 
        WHERE DATE(collected_date) BETWEEN '$start_date' AND '$end_date')
        UNION ALL 
        (SELECT * FROM collectednova 
        WHERE DATE(collected_date) BETWEEN '$start_date' AND '$end_date')
        UNION ALL 
        (SELECT * FROM collectedapm 
        WHERE DATE(collected_date) BETWEEN '$start_date' AND '$end_date')
        ORDER BY collected_date ASC, transaction_number ASC";
}

$tenant_codes = [];
$result_transactions = mysqli_query($conn, $query_transactions);
if ($result_transactions) {
    while ($row = mysqli_fetch_assoc($result_transactions)) {
        $response['transactions'][] = [
            'spacecode' => $row['spacecode'],
            'transaction_number' => $row['transaction_number'],
            'collector' => $row['collector'],
            'collected_date' => date('M d, Y', strtotime($row['collected_date'])),
            'branch' => $row['branch'],
            'tenantname' => $row['tenantname'],
            'paidrent' => number_format($row['paidrent'], 2),
            'paidbal' => number_format($row['paidbal'], 2)
        ];
        
        $response['totalTransactions']++;
        $tenant_codes[$row['tenantcode']] = true;
        $response['totalCollection'] += (float)$row['paidrent'] + (float)$row['paidbal'] + extractCharges($row['charges']);
    }
    $response['totalTenants'] = count($tenant_codes);
}

mysqli_close($conn);

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);