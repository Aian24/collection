<?php

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// 1. Environment Detection
$isCli = (php_sapi_name() === 'cli');
$hostHeader = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isLocal = in_array($hostHeader, ['localhost', '127.0.0.1', '::1'])
           || (isset($_SERVER['DOCUMENT_ROOT']) && (strpos($_SERVER['DOCUMENT_ROOT'], 'xampp') !== false || strpos($_SERVER['DOCUMENT_ROOT'], 'wamp') !== false))
           || ($isCli && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

if ($isLocal) {
    $primaryHost = "localhost";
    $primaryUser = "root";
    $primaryPass = "";
    $primaryName = "imall";

    $fallbackHost = "localhost";
    $fallbackUser = "wqxgzpmy_imall";
    $fallbackPass = "R4styL0p3z";
    $fallbackName = "wqxgzpmy_imall";
} else {
    $primaryHost = "localhost";
    $primaryUser = "wqxgzpmy_imall";
    $primaryPass = "R4styL0p3z";
    $primaryName = "wqxgzpmy_imall";

    $fallbackHost = "localhost";
    $fallbackUser = "root";
    $fallbackPass = "";
    $fallbackName = "imall";
}

$dbHost     = $primaryHost;
$dbUsername = $primaryUser;
$dbPassword = $primaryPass;
$dbName     = $primaryName;

$conn = null;
try {
    $conn = @new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
} catch (Throwable $e) {
    $conn = null;
}

if (!$conn || $conn->connect_error) {
    try {
        $connFallback = @new mysqli($fallbackHost, $fallbackUser, $fallbackPass, $fallbackName);
        if ($connFallback && !$connFallback->connect_error) {
            $conn = $connFallback;
            $dbHost = $fallbackHost;
            $dbUsername = $fallbackUser;
            $dbPassword = $fallbackPass;
            $dbName = $fallbackName;
        }
    } catch (Throwable $e) {
        // Fallback failed
    }
}

if (!$conn || $conn->connect_error) {
    error_log("iMall Database Connection failed: " . ($conn ? $conn->connect_error : "Connection could not be established"));
}

if ($conn && !$conn->connect_error) {
    @$conn->set_charset("utf8mb4");
    @$conn->query("SET time_zone = '+08:00'");

    register_shutdown_function(function() use (&$conn) {
        if ($conn instanceof mysqli && @$conn->ping()) {
            @$conn->close();
        }
    });
}

?>

