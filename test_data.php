<?php
include 'config.php';
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sql = "SELECT transaction_number, username, collector, charges, total FROM collectedapm WHERE DATE(collected_date) = '2026-04-20' AND collector = 'Gasta' AND username != 'Gasta'";
$result = $conn->query($sql);
while($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
$conn->close();
?>
