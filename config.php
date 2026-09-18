<?php

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Disable MySQLi strict exception mode so connection errors/query errors don't throw uncaught Fatal Exceptions in PHP 8.1+
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$dbHost     = "localhost";  
$dbUsername = "root";       
$dbPassword = "";          
$dbName     = "collection";  

// Implement connection with retry mechanism for peak loads / temporary connection spikes
$conn = null;
$maxRetries = 3;
$retryDelayMs = 150; // 150ms backoff

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $conn = @new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
        if ($conn && !$conn->connect_error) {
            break;
        }
    } catch (Throwable $e) {
        $conn = null;
    }
    
    if ($attempt < $maxRetries) {
        usleep($retryDelayMs * 1000);
    }
}

if (!$conn || $conn->connect_error) {
    // Check if the request is an AJAX or JSON request
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || (isset($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], 'fetch_') !== false || strpos($_SERVER['REQUEST_URI'], 'suggest_') !== false || strpos($_SERVER['REQUEST_URI'], 'get_') !== false || strpos($_SERVER['REQUEST_URI'], 'sync_') !== false || strpos($_SERVER['REQUEST_URI'], 'check_session') !== false));

    if ($isAjax) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Database server is temporarily busy. Please try again shortly.'
        ]);
        exit();
    } else {
        http_response_code(503);
        header('Retry-After: 3');
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="3">
    <title>Connecting to Server</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #334155; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: white; border-radius: 16px; padding: 32px; max-width: 440px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01); border: 1px solid #e2e8f0; }
        .icon { width: 48px; height: 48px; background: #eff6ff; color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 20px; font-weight: bold; }
        h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
        p { font-size: 14px; color: #64748b; line-height: 1.5; margin: 0 0 20px; }
        .spinner { display: inline-block; width: 18px; height: 18px; border: 2px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; margin-right: 8px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; cursor: pointer; border: none; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#9881;</div>
        <h1>Server Reconnecting...</h1>
        <p>The database server is momentarily busy under high traffic. We are automatically reconnecting your session.</p>
        <div><span class="spinner"></span> <span style="font-size: 13px; color: #64748b;">Retrying in 3 seconds...</span></div>
        <div style="margin-top: 20px;">
            <button class="btn" onclick="window.location.reload()">Reload Now</button>
        </div>
    </div>
</body>
</html>';
        exit();
    }
}

// Force MySQL connection to use Manila timezone (PHP is already set above)
@$conn->query("SET time_zone = '+08:00'");

?>

