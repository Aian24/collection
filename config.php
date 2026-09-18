<?php

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Disable MySQLi strict exception mode so connection errors/query errors don't throw uncaught Fatal Exceptions in PHP 8.1+
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// 1. Environment Detection & Credentials Configuration
$isCli = (php_sapi_name() === 'cli');
$hostHeader = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isLocal = in_array($hostHeader, ['localhost', '127.0.0.1', '::1'])
           || (isset($_SERVER['DOCUMENT_ROOT']) && (strpos($_SERVER['DOCUMENT_ROOT'], 'xampp') !== false || strpos($_SERVER['DOCUMENT_ROOT'], 'wamp') !== false))
           || ($isCli && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

// Primary & Fallback credentials
if ($isLocal) {
    // Localhost XAMPP
    $primaryHost = getenv('DB_HOST') ?: "localhost";
    $primaryUser = getenv('DB_USER') ?: "root";
    $primaryPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
    $primaryName = getenv('DB_NAME') ?: "collection";

    $fallbackHost = "localhost";
    $fallbackUser = "wqxgzpmy_collection";
    $fallbackPass = "R4styL0p3z";
    $fallbackName = "wqxgzpmy_collection";
} else {
    // Production / Live (Bluehost)
    $primaryHost = getenv('DB_HOST') ?: "localhost";
    $primaryUser = getenv('DB_USER') ?: "wqxgzpmy_collection";
    $primaryPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "R4styL0p3z";
    $primaryName = getenv('DB_NAME') ?: "wqxgzpmy_collection";

    $fallbackHost = "localhost";
    $fallbackUser = "root";
    $fallbackPass = "";
    $fallbackName = "collection";
}

$dbHost     = $primaryHost;
$dbUsername = $primaryUser;
$dbPassword = $primaryPass;
$dbName     = $primaryName;

// 2. Establish Database Connection
$conn = null;

try {
    $conn = @new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
} catch (Throwable $e) {
    $conn = null;
}

// If primary connection failed (e.g. credentials mismatch between environments), try fallback once
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

// 3. Handle Connection Failures Gracefully (No Infinite 3s Meta Refresh DDOS Loop!)
if (!$conn || $conn->connect_error) {
    $errorMsg = $conn ? $conn->connect_error : "Unable to connect to database";
    error_log("Database connection error: " . $errorMsg);

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || (isset($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], 'fetch_') !== false || strpos($_SERVER['REQUEST_URI'], 'suggest_') !== false || strpos($_SERVER['REQUEST_URI'], 'get_') !== false || strpos($_SERVER['REQUEST_URI'], 'sync_') !== false || strpos($_SERVER['REQUEST_URI'], 'check_session') !== false || strpos($_SERVER['REQUEST_URI'], 'server_processing') !== false));

    if ($isAjax) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Database server is momentarily busy. Please retry shortly.'
        ]);
        exit();
    } else {
        http_response_code(503);
        header('Retry-After: 15');
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Reconnecting</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #334155; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: white; border-radius: 16px; padding: 32px; max-width: 460px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01); border: 1px solid #e2e8f0; }
        .icon { width: 52px; height: 52px; background: #eff6ff; color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px; }
        h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
        p { font-size: 14px; color: #64748b; line-height: 1.5; margin: 0 0 20px; }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#9881;</div>
        <h1>Database Server Busy</h1>
        <p>The database server is momentarily handling high traffic. Please wait a few moments and click Reload.</p>
        <div style="margin-top: 20px;">
            <button class="btn" onclick="window.location.reload()">Reload Now</button>
        </div>
    </div>
</body>
</html>';
        exit();
    }
}

// 4. Configure Connection Settings
@$conn->set_charset("utf8mb4");
@$conn->query("SET time_zone = '+08:00'");

// 5. Automatic connection cleanup on script end to prevent connection pool exhaustion
register_shutdown_function(function() use (&$conn) {
    if ($conn instanceof mysqli && @$conn->ping()) {
        @$conn->close();
    }
});

?>

