<?php

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

$dbHost     = "localhost";  
$dbUsername = "root";       
$dbPassword = "";          
$dbName     = "collection";  
 
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName); 
 
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
} 

// Force MySQL connection to use Manila timezone (PHP is already set above)
$conn->query("SET time_zone = '+08:00'");

?>

