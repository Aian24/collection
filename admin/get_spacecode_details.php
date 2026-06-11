<?php
include '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if required parameters are provided
if (!isset($_GET['spacecode']) || !isset($_GET['branch'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

$spacecode = $_GET['spacecode'];
$branch = $_GET['branch'];

// Determine the table based on branch
$table = '';
switch ($branch) {
    case 'Nova Market':
        $table = 'nova';
        break;
    case 'Sanko Market':
        $table = 'sanko';
        break;
    case 'APM':
        $table = 'apm';
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid branch']);
        exit();
}

// Prepare the SQL statement to get tenant details for the space code
$stmt = $conn->prepare("SELECT tenantname, tenantcode, spacecode, daily, rentbal, runningbal FROM $table WHERE spacecode = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("s", $spacecode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Format the response data
    $response = [
        'success' => true,
        'data' => [
            'tenant_name' => $row['tenantname'] ?? 'N/A',
            'tenant_code' => $row['tenantcode'] ?? 'N/A',
            'space_code' => $row['spacecode'] ?? 'N/A',
            'daily_rent' => number_format((float)$row['daily'], 2),
            'rent_balance' => number_format((float)$row['rentbal'], 2),
            'arrear_balance' => number_format((float)$row['runningbal'], 2)
        ]
    ];
    echo json_encode($response);
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'No tenant found for space code: ' . $spacecode
    ]);
}

$stmt->close();
$conn->close();
?> 