<?php
include 'config.php';
session_start();

if (!isset($_SESSION["branch"])) {
    echo json_encode(['success' => false]);
    exit();
}

$branch = $_SESSION["branch"];
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Determine tables
$table = '';
$collectedTable = '';
switch ($branch) {
    case 'Nova Market':
        $table = 'nova';
        $collectedTable = 'collectednova';
        break;
    case 'Sanko Market':
        $table = 'sanko';
        $collectedTable = 'collected';
        break;
    case 'APM':
        $table = 'apm';
        $collectedTable = 'collectedapm';
        break;
    default:
        echo json_encode(['success' => false]);
        exit();
}

// Get total unique spaces in branch (excluding Ambulant)
$totalQuery = $conn->prepare("SELECT COUNT(DISTINCT spacecode) as total FROM $table WHERE spacecode != 'Ambulant'");
$totalQuery->execute();
$totalResult = $totalQuery->get_result()->fetch_assoc();
$totalCount = $totalResult['total'];

// Get unique spaces already collected today
$paidQuery = $conn->prepare("SELECT COUNT(DISTINCT spacecode) as paid FROM $collectedTable WHERE LEFT(collected_date, 10) = ? AND spacecode != 'Ambulant'");
$paidQuery->bind_param("s", $date);
$paidQuery->execute();
$paidResult = $paidQuery->get_result()->fetch_assoc();
$paidCount = $paidResult['paid'];

// Get list of unpaid spaces (limit to a reasonable number or just count)
$unpaidListQuery = $conn->prepare("SELECT spacecode FROM $table WHERE spacecode != 'Ambulant' AND spacecode NOT IN (SELECT spacecode FROM $collectedTable WHERE LEFT(collected_date, 10) = ?) ORDER BY spacecode ASC");
$unpaidListQuery->bind_param("s", $date);
$unpaidListQuery->execute();
$listResult = $unpaidListQuery->get_result();
$unpaidSpaces = [];
while ($row = $listResult->fetch_assoc()) {
    $unpaidSpaces[] = $row['spacecode'];
}

echo json_encode([
    'success' => true,
    'total' => $totalCount,
    'paid' => $paidCount,
    'unpaid' => $totalCount - $paidCount,
    'unpaidSpaces' => $unpaidSpaces
]);

$conn->close();
?>
