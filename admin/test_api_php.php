<?php
session_start();
$_SESSION['username'] = 'admin';
$_SESSION['lname'] = 'Admin';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
$_GET['fromDate'] = '2026-03-01';
$_GET['toDate'] = '2026-03-31';
$_GET['branchFilter'] = 'APM';

ob_start();
include 'collectionapm.php';
$output = ob_get_clean();

if (strpos($output, '{') !== false) {
    $jsonStr = substr($output, strpos($output, '{'));
    $data = json_decode($jsonStr, true);
    if (isset($data['totalRecords'])) {
        echo "Total Records: " . $data['totalRecords'] . "\n";
    } else {
        echo "No totalRecords. JSON: " . substr($jsonStr, 0, 200) . "...\n";
    }
} else {
    echo "No JSON found in output.";
}
?>