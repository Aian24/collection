<?php

// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';
session_start();



session_start();
session_unset();
session_destroy();

header('location:index.php');

?>