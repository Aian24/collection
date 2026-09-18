<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['branch']) || empty($_GET['branch'])) {
    echo json_encode(['success' => false]);
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
    echo json_encode(['success' => false]);
    exit();
}

include 'config.php';

// Total spaces
$totalCount = 0;
$resTotal = $conn->query("SELECT COUNT(*) as total FROM `$branchTable`");
if ($resTotal) {
    $rowTotal = $resTotal->fetch_assoc();
    $totalCount = (int)($rowTotal['total'] ?? 0);
}

// Collected today (distinct spaces)
$collectedCount = 0;
$stmtCollected = $conn->prepare("SELECT COUNT(DISTINCT spacecode) as collected FROM `$collectedTable` WHERE collected_date BETWEEN ? AND ?");
if ($stmtCollected) {
    $stmtCollected->bind_param("ss", $startOfDay, $endOfDay);
    $stmtCollected->execute();
    $resCollected = $stmtCollected->get_result();
    if ($resCollected) {
        $rowCollected = $resCollected->fetch_assoc();
        $collectedCount = (int)($rowCollected['collected'] ?? 0);
    }
    $stmtCollected->close();
}

if (isset($conn) && $conn) {
    $conn->close();
}

echo json_encode([
    'success' => true,
    'total' => $totalCount,
    'collected' => $collectedCount,
    'pending' => max(0, $totalCount - $collectedCount)
]);
?>

