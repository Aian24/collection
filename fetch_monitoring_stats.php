<?php
include 'config.php';
header('Content-Type: application/json');

if (isset($_GET['branch'])) {
    $branch = $_GET['branch'];
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
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
    }
    
    if ($branchTable && $collectedTable) {
        // Total spaces
        $resTotal = $conn->query("SELECT COUNT(*) as total FROM $branchTable");
        $totalCount = $resTotal->fetch_assoc()['total'];
        
        // Collected today (distinct spaces)
        $resCollected = $conn->query("SELECT COUNT(DISTINCT spacecode) as collected FROM $collectedTable WHERE DATE(collected_date) = '$date'");
        $collectedCount = $resCollected->fetch_assoc()['collected'];
        
        echo json_encode([
            'success' => true,
            'total' => (int)$totalCount,
            'collected' => (int)$collectedCount,
            'pending' => (int)$totalCount - (int)$collectedCount
        ]);
        exit();
    }
}
echo json_encode(['success' => false]);
?>
