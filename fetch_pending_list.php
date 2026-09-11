<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['branch']) || empty($_GET['branch'])) {
    echo json_encode(['success' => false, 'pending' => []]);
    exit();
}

$branch = trim($_GET['branch']);
$date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$startOfDay = $date . ' 00:00:00';
$endOfDay = $date . ' 23:59:59';

$branchTable = '';
$collectedTable = '';

switch ($branch) {
    case 'Nova Market':
        $branchTable = 'nova';
        $collectedTable = 'collectednova';
        break;
    case 'Sanko Market':
        $branchTable = 'sanko';
        $collectedTable = 'collected';
        break;
    case 'APM':
        $branchTable = 'apm';
        $collectedTable = 'collectedapm';
        break;
    case 'ACC':
    case 'Ambulant':
        $branchTable = 'acc';
        $collectedTable = 'collectedacc';
        break;
}

if (!$branchTable || !$collectedTable) {
    echo json_encode(['success' => false, 'pending' => []]);
    exit();
}

include 'config.php';

// Find spaces in branchTable that are NOT in collectedTable for the given date range using fast LEFT JOIN
$query = "SELECT b.spacecode, b.tenantname 
          FROM `$branchTable` b
          LEFT JOIN `$collectedTable` c 
            ON b.spacecode = c.spacecode 
            AND c.collected_date BETWEEN ? AND ?
          WHERE c.spacecode IS NULL
          ORDER BY b.spacecode ASC";

$stmt = $conn->prepare($query);
$pending = [];

if ($stmt) {
    $stmt->bind_param("ss", $startOfDay, $endOfDay);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pending[] = $row;
        }
    }
    $stmt->close();
}

if (isset($conn) && $conn) {
    $conn->close();
}

echo json_encode(['success' => true, 'pending' => $pending]);
?>

