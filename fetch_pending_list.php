<?php
include 'config.php';
header('Content-Type: application/json');

if (isset($_GET['branch'])) {
    $branch = $_GET['branch'];
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    $branchTable = ($branch === 'Nova Market') ? 'nova' : (($branch === 'Sanko Market') ? 'sanko' : 'apm');
    $collectedTable = ($branch === 'Nova Market') ? 'collectednova' : (($branch === 'Sanko Market') ? 'collected' : 'collectedapm');
    
    // Find spaces in branchTable that are NOT in collectedTable for the given date
    $query = "SELECT spacecode, tenantname FROM $branchTable 
              WHERE spacecode NOT IN (
                  SELECT spacecode FROM $collectedTable WHERE DATE(collected_date) = '$date'
              )
              ORDER BY spacecode ASC";
    
    $result = $conn->query($query);
    $pending = [];
    while ($row = $result->fetch_assoc()) {
        $pending[] = $row;
    }
    
    echo json_encode(['success' => true, 'pending' => $pending]);
    exit();
}
echo json_encode(['success' => false]);
?>
