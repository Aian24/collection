<?php
// Server-side processing for void transactions DataTable
session_start();
include '../config.php';
session_write_close(); // Release session lock to prevent deadlocks

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get DataTables parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

// Get custom filter parameters
$from_date = isset($_POST['from_date']) ? $_POST['from_date'] : '';
$to_date = isset($_POST['to_date']) ? $_POST['to_date'] : '';
$branch = isset($_POST['branch']) ? $_POST['branch'] : '';

// If no dates are provided, default to today
if (empty($from_date) && empty($to_date)) {
    $today = date('Y-m-d');
    $from_date = $today;
    $to_date = $today;
}

// Build the query
$query = "SELECT * FROM void";
$count_query = "SELECT COUNT(*) as count FROM void";

// Add WHERE conditions
$where_conditions = [];
$params = [];
$types = "";

// Date filters
if (!empty($from_date)) {
    $where_conditions[] = "DATE(void_date) >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if (!empty($to_date)) {
    $where_conditions[] = "DATE(void_date) <= ?";
    $params[] = $to_date;
    $types .= "s";
}

// Branch filter
if (!empty($branch)) {
    $where_conditions[] = "branch = ?";
    $params[] = $branch;
    $types .= "s";
}

// Search filter
if (!empty($search)) {
    $search_conditions = [];
    $search_fields = ['transaction_number', 'branch', 'spacecode', 'note', 'void_date', 'collector', 'tenantname'];
    
    foreach ($search_fields as $field) {
        $search_conditions[] = "$field LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    
    $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
}

// Add WHERE clause if needed
if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
    $query .= $where_clause;
    $count_query .= $where_clause;
}

// Add ordering
$order_column_index = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 5; // Default to void_date
$order_direction = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'desc';

$columns = ['', 'transaction_number', 'branch', 'spacecode', 'note', 'void_date', 'rent', 'rentbal', 'runningbal', 'paidrent', 'paidbal', 'charges', 'collector', 'tenantname'];
$order_column = isset($columns[$order_column_index]) ? $columns[$order_column_index] : 'void_date';

$query .= " ORDER BY $order_column $order_direction";

// Add limit for pagination
$query .= " LIMIT ?, ?";
$params[] = $start;
$params[] = $length;
$types .= "ii";

// Prepare and execute count query
$stmt = $conn->prepare($count_query);
if (!empty($params) && !empty($types)) {
    $stmt->bind_param(substr($types, 0, strlen($types)-2), ...array_slice($params, 0, count($params)-2));
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_records = $count_result->fetch_assoc()['count'];

// Prepare and execute main query
$stmt = $conn->prepare($query);
if (!empty($params) && !empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Process data
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Close connection
$conn->close();

// Return JSON response
$response = [
    "draw" => $draw,
    "recordsTotal" => $total_records,
    "recordsFiltered" => $total_records,
    "data" => $data
];

header('Content-Type: application/json');
echo json_encode($response);
?>