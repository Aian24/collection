<?php
require_once '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}
session_write_close(); // Release session lock to prevent deadlocks

// Check if required parameters are provided
if ((!isset($_POST['tenant_code']) && !isset($_POST['space_code'])) || !isset($_POST['branch']) || !isset($_POST['start_date']) || !isset($_POST['end_date'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing required parameters. Need either tenant_code or space_code']);
    exit();
}

// Get parameters
$tenant_code = isset($_POST['tenant_code']) ? $_POST['tenant_code'] : '';
$space_code = isset($_POST['space_code']) ? $_POST['space_code'] : '';
$branch = $_POST['branch'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];

// At least one identifier (tenant code or space code) is required
if (empty($tenant_code) && empty($space_code)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Either tenant_code or space_code must be provided']);
    exit();
}

// Log the request
$identifier = !empty($tenant_code) ? "Tenant=$tenant_code" : "Space=$space_code";
error_log("Calculating missing days for: $identifier, Branch=$branch, Start=$start_date, End=$end_date");

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid date format']);
    exit();
}

// Ensure dates include time for proper comparison
$start_date_time = date('Y-m-d 00:00:00', strtotime($start_date));
$end_date_time = date('Y-m-d 23:59:59', strtotime($end_date));

// Determine the table to use based on branch
$table = 'collected'; // Default table
if ($branch === 'Nova Market') {
    $table = 'collectednova';
} elseif ($branch === 'APM') {
    $table = 'collectedapm';
}

// Create array of all dates in the range
$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    (new DateTime($end_date))->modify('+1 day')
);

$all_dates = [];
$weekday_map = [
    '0' => 'Sunday',
    '1' => 'Monday',
    '2' => 'Tuesday',
    '3' => 'Wednesday',
    '4' => 'Thursday',
    '5' => 'Friday',
    '6' => 'Saturday'
];

// Store all dates with their day of week for better display
foreach ($period as $date) {
    $date_str = $date->format('Y-m-d');
    $day_num = $date->format('w'); // 0 (Sunday) to 6 (Saturday)
    $day_name = $weekday_map[$day_num];
    $all_dates[$date_str] = [
        'date' => $date_str,
        'day_name' => $day_name,
        'day_number' => $day_num
    ];
}

// Prepare the query based on whether we have tenant code or space code
if ($branch === 'APM') {
    // For APM branch, check both tenant code and space code if available
    $query = "SELECT DISTINCT DATE(collected_date) as transaction_date 
          FROM $table 
          WHERE branch = ? 
          AND collected_date BETWEEN ? AND ?";
          
    // Add conditions for tenant and space codes
    $params = [];
    $types = "sss"; // branch, start_date, end_date types
    $params[] = $branch;
    $params[] = $start_date_time;
    $params[] = $end_date_time;
    
    $conditions = [];
    if (!empty($tenant_code)) {
        $conditions[] = "tenantcode = ?";
        $types .= "s";
        $params[] = $tenant_code;
    }
    
    if (!empty($space_code)) {
        $conditions[] = "spacecode = ?";
        $types .= "s";
        $params[] = $space_code;
    }
    
    if (count($conditions) > 0) {
        $query .= " AND (" . implode(" OR ", $conditions) . ")";
    }
    
    $query .= " ORDER BY transaction_date";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
        exit();
    }
    
    // Dynamic parameter binding
    $stmt->bind_param($types, ...$params);
    
    // Log the special case for APM
    error_log("Using special APM branch query logic for tenant_code=$tenant_code, space_code=$space_code");
} elseif (!empty($tenant_code)) {
    // Original code for tenant code
    $query = "SELECT DISTINCT DATE(collected_date) as transaction_date 
          FROM $table 
          WHERE tenantcode = ? 
          AND branch = ? 
          AND collected_date BETWEEN ? AND ?
          ORDER BY transaction_date";
          
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("ssss", $tenant_code, $branch, $start_date_time, $end_date_time);
} else {
    // Original code for space code
    $query = "SELECT DISTINCT DATE(collected_date) as transaction_date 
          FROM $table 
          WHERE spacecode = ? 
          AND branch = ? 
          AND collected_date BETWEEN ? AND ?
          ORDER BY transaction_date";
          
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("ssss", $space_code, $branch, $start_date_time, $end_date_time);
}

// Log the query for debugging
$param_query = str_replace('?', "'%s'", $query);
if (!empty($tenant_code)) {
    $debug_query = sprintf($param_query, $tenant_code, $branch, $start_date_time, $end_date_time);
} else {
    $debug_query = sprintf($param_query, $space_code, $branch, $start_date_time, $end_date_time);
}
error_log("Query: " . $debug_query);

$stmt->execute();
$result = $stmt->get_result();

$transaction_dates = [];
$transaction_dates_with_day = [];
while ($row = $result->fetch_assoc()) {
    $date_str = $row['transaction_date'];
    $transaction_dates[] = $date_str;
    
    // Add day of week information for transaction dates
    $date_obj = new DateTime($date_str);
    $day_num = $date_obj->format('w');
    $day_name = $weekday_map[$day_num];
    
    $transaction_dates_with_day[] = [
        'date' => $date_str,
        'day_name' => $day_name,
        'day_number' => $day_num
    ];
}

error_log("Found " . count($transaction_dates) . " transaction dates for $identifier in branch $branch");
error_log("Transaction dates: " . implode(", ", $transaction_dates));

// Find missing days (all days in range minus transaction dates)
$missing_days = array_diff(array_keys($all_dates), $transaction_dates);

// Build missing days array with day of week information
$missing_days_with_info = [];
foreach ($missing_days as $missing_date) {
    $missing_days_with_info[] = $all_dates[$missing_date];
}

error_log("Found " . count($missing_days) . " missing days for $identifier in branch $branch");

// Get transaction counts by day of week for analysis
$day_counts = [0, 0, 0, 0, 0, 0, 0]; // Sun, Mon, Tue, Wed, Thu, Fri, Sat

foreach ($transaction_dates_with_day as $date_info) {
    $day_number = $date_info['day_number'];
    $day_counts[$day_number]++;
}

// Double-check data integrity by querying directly - update this for APM too
if ($branch === 'APM') {
    $check_query = "SELECT COUNT(*) as total_count FROM $table 
               WHERE branch = '$branch' 
               AND collected_date BETWEEN '$start_date_time' AND '$end_date_time'
               AND (";
               
    $conditions = [];
    if (!empty($tenant_code)) {
        $conditions[] = "tenantcode = '" . $conn->real_escape_string($tenant_code) . "'";
    }
    if (!empty($space_code)) {
        $conditions[] = "spacecode = '" . $conn->real_escape_string($space_code) . "'";
    }
    
    $check_query .= implode(" OR ", $conditions) . ")";
} else {
    // Original code
    if (!empty($tenant_code)) {
        $check_query = "SELECT COUNT(*) as total_count FROM $table 
                   WHERE tenantcode = '$tenant_code' 
                   AND branch = '$branch' 
                   AND collected_date BETWEEN '$start_date_time' AND '$end_date_time'";
    } else {
        $check_query = "SELECT COUNT(*) as total_count FROM $table 
                   WHERE spacecode = '$space_code' 
                   AND branch = '$branch' 
                   AND collected_date BETWEEN '$start_date_time' AND '$end_date_time'";
    }
}

$check_result = $conn->query($check_query);
$check_row = $check_result->fetch_assoc();
$total_records = $check_row['total_count'];

error_log("Double-check: Found $total_records total records for $identifier in branch $branch");

// Return the results as JSON
echo json_encode([
    'tenant_code' => $tenant_code,
    'space_code' => $space_code,
    'branch' => $branch,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'missing_days' => array_values($missing_days), // Flat array of missing dates
    'missing_days_with_info' => $missing_days_with_info, // Missing dates with day info
    'transaction_dates' => $transaction_dates, // Flat array of transaction dates
    'transaction_dates_with_day' => $transaction_dates_with_day, // Transaction dates with day info
    'day_of_week_stats' => [
        'labels' => array_values($weekday_map),
        'counts' => $day_counts
    ],
    'total_days' => count($all_dates),
    'total_missing_days' => count($missing_days),
    'total_transactions' => count($transaction_dates),
    'total_records_check' => $total_records
]);

// Close connections
$stmt->close();
$conn->close(); 