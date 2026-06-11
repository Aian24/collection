<?php
include '../config.php';

// Get request parameters from DataTables
$draw = $_POST['draw'];
$start = $_POST['start'];
$length = $_POST['length'];
$searchValue = $_POST['search']['value'];
$orderColumnIndex = $_POST['order'][0]['column'];
$orderDirection = $_POST['order'][0]['dir'];
$branchFilter = $_POST['branchFilter'];
$fromDate = $_POST['fromDate'];
$toDate = $_POST['toDate'];

// Define columns
$columns = [
    'branch', 'collected_date', 'transaction_number', 'spacecode', 'collector', 'tenantcode', 'tenantname',
    'paidrent', 'paidbal', 'aircon', 'cusa', 'electricity', 'water', 'table_tennis', 'pay_toilet', 'pay_parking',
    'ice_water', 'ulam_vendor', 'gas', 'famylihan', 'garbage_haul', 'photocopy', 'tenant_id', 'function_room',
    'tables_chairs', 'overnight_works', 'vendo_sale', 'zumba', 'secdep', 'meterdep', 'miscellaneous', 'total', 'balance'
];

// Get the column name to sort by
$orderColumn = $columns[$orderColumnIndex];

// Base SQL query
$sql = "SELECT * FROM collected"; // Replace with your table name

// Add filters
$whereClauses = [];
if (!empty($branchFilter)) {
    $whereClauses[] = "branch = '$branchFilter'";
}
if (!empty($fromDate)) {
    $whereClauses[] = "collected_date >= '$fromDate 00:00:00'";
}
if (!empty($toDate)) {
    $whereClauses[] = "collected_date <= '$toDate 23:59:59'";
}
if (!empty($searchValue)) {
    $whereClauses[] = "(branch LIKE '%$searchValue%' OR collected_date LIKE '%$searchValue%' OR transaction_number LIKE '%$searchValue%')";
}
if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}

// Add sorting
$sql .= " ORDER BY $orderColumn $orderDirection";

// Add pagination
$sql .= " LIMIT $start, $length";

// Execute the query
$result = mysqli_query($conn, $sql);

// Fetch data into an array
$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

// Get total number of records
$totalRecordsQuery = "SELECT COUNT(*) as total FROM collected"; // Replace with your table name
$totalRecordsResult = mysqli_query($conn, $totalRecordsQuery);
$totalRecordsRow = mysqli_fetch_assoc($totalRecordsResult);
$totalRecords = $totalRecordsRow['total'];

// Get total number of filtered records
if (!empty($whereClauses)) {
    $filteredRecordsQuery = "SELECT COUNT(*) as total FROM collected WHERE " . implode(' AND ', $whereClauses);
    $filteredRecordsResult = mysqli_query($conn, $filteredRecordsQuery);
    $filteredRecordsRow = mysqli_fetch_assoc($filteredRecordsResult);
    $filteredRecords = $filteredRecordsRow['total'];
} else {
    $filteredRecords = $totalRecords;
}

// Prepare the response
$response = [
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($filteredRecords),
    "data" => $data
];

// Return the response as JSON
header('Content-Type: application/json');
echo json_encode($response);

mysqli_close($conn);
?>