<?php
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["branch"]) || empty($_SESSION["branch"])) {
    session_write_close();
    echo json_encode(['success' => false]);
    exit();
}

$branch = $_SESSION["branch"];
session_write_close(); // Release session lock immediately

$date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$startOfDay = $date . ' 00:00:00';
$endOfDay = $date . ' 23:59:59';

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
    case 'ACC':
    case 'Ambulant':
        $table = 'acc';
        $collectedTable = 'collectedacc';
        break;
    default:
        echo json_encode(['success' => false]);
        exit();
}

include 'config.php';

// Get total unique spaces in branch (excluding Ambulant)
$totalCount = 0;
$totalQuery = $conn->prepare("SELECT COUNT(DISTINCT spacecode) as total FROM `$table` WHERE spacecode != 'Ambulant'");
if ($totalQuery) {
    $totalQuery->execute();
    $totalResult = $totalQuery->get_result();
    if ($totalResult) {
        $totalCount = (int)($totalResult->fetch_assoc()['total'] ?? 0);
    }
    $totalQuery->close();
}

// Get unique spaces already collected today
$paidCount = 0;
$paidQuery = $conn->prepare("SELECT COUNT(DISTINCT spacecode) as paid FROM `$collectedTable` WHERE collected_date BETWEEN ? AND ? AND spacecode != 'Ambulant'");
if ($paidQuery) {
    $paidQuery->bind_param("ss", $startOfDay, $endOfDay);
    $paidQuery->execute();
    $paidResult = $paidQuery->get_result();
    if ($paidResult) {
        $paidCount = (int)($paidResult->fetch_assoc()['paid'] ?? 0);
    }
    $paidQuery->close();
}

// Get list of unpaid spaces using fast LEFT JOIN
$unpaidSpaces = [];
$unpaidListQuery = $conn->prepare("SELECT t.spacecode 
    FROM `$table` t
    LEFT JOIN `$collectedTable` c 
        ON t.spacecode = c.spacecode 
        AND c.collected_date BETWEEN ? AND ?
    WHERE t.spacecode != 'Ambulant' AND c.spacecode IS NULL
    ORDER BY t.spacecode ASC");

if ($unpaidListQuery) {
    $unpaidListQuery->bind_param("ss", $startOfDay, $endOfDay);
    $unpaidListQuery->execute();
    $listResult = $unpaidListQuery->get_result();
    if ($listResult) {
        while ($row = $listResult->fetch_assoc()) {
            $unpaidSpaces[] = $row['spacecode'];
        }
    }
    $unpaidListQuery->close();
}

if (isset($conn) && $conn) {
    $conn->close();
}

echo json_encode([
    'success' => true,
    'total' => $totalCount,
    'paid' => $paidCount,
    'unpaid' => max(0, $totalCount - $paidCount),
    'unpaidSpaces' => $unpaidSpaces
]);
?>
