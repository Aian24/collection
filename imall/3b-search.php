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

// (B) DO SEARCH
$data = [];
$stmt = $pdo->prepare("SELECT * FROM `tenants` WHERE `contract` LIKE ?");
$stmt->execute(["%".$_POST["search"]."%"]);
while ($r = $stmt->fetch()) { $data[] = [
  "D" => $r["contract"], "company" => $r["company"], "stall" => $r["stall"]
]; }
echo count($data)==0 ? "null" : json_encode($data) ;