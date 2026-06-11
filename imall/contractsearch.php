<?php
// Include your database connection file
ob_start(); // Start output buffering
session_start();

// (A) CONNECT TO DATABASE -
$dbhost = "localhost";
$dbname = "wqxgzpmy_imall";
$dbchar = "utf8mb4";
$dbuser = "wqxgzpmy_imall";
$dbpass = "R4styL0p3z";
$pdo = new PDO(
  "mysql:host=$dbhost;dbname=$dbname;charset=$dbchar",
  $dbuser, $dbpass, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// (B) GET TARGET TABLE BASED ON BRANCH
$location = $_SESSION['branch'];
switch ($location) {
    case 'iMall Antipolo':
        $targetTable = 'imallantipolotenants';
        break;
    case 'iMall Canlubang':
        $targetTable = 'imallcanlubangtenants';
        break;
    case 'iMall Camarin':
        $targetTable = 'imallcamarintenants';
        break;
    case 'Cogeo Town Plaza':
        $targetTable = 'cogeotownplazatenants';
        break;
    case 'Antipolo Market':
        $targetTable = 'antipolomarkettenants';
        break;
    default:
        echo "Invalid branch selected.";
        return;
}

// (C) DO SEARCH
$data = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM `$targetTable` WHERE `contract` LIKE ?");
    $stmt->execute(["%".$_POST["search"]."%"]);

    while ($r = $stmt->fetch()) {
        $data[] = ["D" => $r["contract"], "company" => $r["company"], "stall" => $r["stall"]];
    }
    echo count($data)==0 ? "null" : json_encode($data);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
