<?php
include 'config.php';
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
$query = "SELECT transaction_number, paidrent, paidbal, charges, total FROM collectedapm";
$res = $conn->query($query);
while($row = $res->fetch_assoc()) {
    $db_total = (float)$row['total'];
    $r = (float)$row['paidrent'];
    $b = (float)$row['paidbal'];
    
    // mimic printsummary calculation
    preg_match_all('/([^:,]+):\s*([\d,]+(\.\d{1,2})?)/', $row['charges'], $matches);
    $c = 0;
    if (count($matches[0]) > 0) {
        foreach ($matches[1] as $index => $charge_type) {
            $c += (float) str_replace(',', '', $matches[2][$index]);
        }
    }
    
    $calc_total = $r + $b + $c;
    if (abs($db_total - $calc_total) > 0.01) {
        echo "Tx: {$row['transaction_number']} | DB Total: $db_total | Calc Total: $calc_total | Diff: " . ($db_total - $calc_total) . " | Charges String: {$row['charges']}\n";
    }
}
?>
