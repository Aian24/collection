<?php

$dbHost     = "localhost";  
$dbUsername = "wqxgzpmy_imall";       
$dbPassword = "R4styL0p3z";          
$dbName     = "wqxgzpmy_imall";  
 
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName); 
 
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
} 

?>

