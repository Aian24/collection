<?php
include '../config.php';
session_start();

// Ensure only admins can access this page
if (!isset($_SESSION["username"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$root_error_log = '../error_log';
$admin_error_log = 'error_log';

// Handle Clear Logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    $log_type = $_POST['log_type'];
    if ($log_type === 'root' && file_exists($root_error_log)) {
        file_put_contents($root_error_log, '');
    } elseif ($log_type === 'admin' && file_exists($admin_error_log)) {
        file_put_contents($admin_error_log, '');
    } elseif ($log_type === 'all') {
        if (file_exists($root_error_log)) file_put_contents($root_error_log, '');
        if (file_exists($admin_error_log)) file_put_contents($admin_error_log, '');
    }
    
    $_SESSION['log_message'] = "Logs cleared successfully!";
    header("Location: view_logs.php");
    exit();
}

// Function to tail a file (get last N lines efficiently)
function tailFile($filepath, $lines = 100) {
    if (!file_exists($filepath)) {
        return "No log file found at this location.";
    }
    
    $handle = fopen($filepath, "r");
    if (!$handle) {
        return "Could not open log file.";
    }

    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = array();
    
    while ($linecounter > 0) {
        $t = " ";
        while ($t != "\n") {
            if (fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true; 
                break; 
            }
            $t = fgetc($handle);
            $pos--;
        }
        $linecounter--;
        if ($beginning) {
            rewind($handle);
        }
        $text[$lines - $linecounter - 1] = fgets($handle);
        if ($beginning) break;
    }
    fclose($handle);
    
    // Reverse array to show chronological order (oldest at top, newest at bottom of the tail)
    // Or keep newest at top. Let's keep newest at top for logs!
    // No, standard tail is chronological. Let's reverse it so newest is at the bottom, 
    // but in UI we can order it how we want. We'll reverse it so it's chronological.
    return array_reverse(array_filter($text));
}

// Get last 200 lines of each log
$root_logs = tailFile($root_error_log, 200);
$admin_logs = tailFile($admin_error_log, 200);

$message = '';
if (isset($_SESSION['log_message'])) {
    $message = $_SESSION['log_message'];
    unset($_SESSION['log_message']);
}

// Helper to colorize log lines
function formatLogLine($line) {
    $line = htmlspecialchars(trim($line));
    if (stripos($line, 'fatal error') !== false || stripos($line, 'parse error') !== false) {
        return "<span class='text-red-400 font-bold'>$line</span>";
    } elseif (stripos($line, 'warning') !== false) {
        return "<span class='text-yellow-400'>$line</span>";
    } elseif (stripos($line, 'notice') !== false) {
        return "<span class='text-blue-300'>$line</span>";
    }
    return $line;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .log-container {
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', Courier, monospace;
            border-radius: 8px;
            padding: 15px;
            height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 0.85rem;
            line-height: 1.4;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
        }
        /* Scrollbar styling for log container */
        .log-container::-webkit-scrollbar { width: 8px; }
        .log-container::-webkit-scrollbar-track { background: #2d2d2d; border-radius: 4px; }
        .log-container::-webkit-scrollbar-thumb { background: #555; border-radius: 4px; }
        .log-container::-webkit-scrollbar-thumb:hover { background: #777; }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><i class="fas fa-terminal text-blue-600 mr-3"></i>System Error Logs</h1>
                <p class="text-gray-500 mt-1">View real-time PHP errors from your Bluehost environment</p>
            </div>
            <div class="flex gap-3">
                <a href="server_health.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Health Monitor
                </a>
                <button onclick="window.location.reload()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-lg shadow-blue-200 font-semibold">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh Logs
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm" role="alert">
                <p><i class="fas fa-check-circle mr-2"></i><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Root Error Log -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
                <div class="bg-gray-50 border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-700"><i class="far fa-folder-open text-yellow-500 mr-2"></i>Main Application Logs</h2>
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-mono bg-gray-200 text-gray-600 px-2 py-1 rounded">/error_log</span>
                        <form method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to clear this log?');">
                            <input type="hidden" name="log_type" value="root">
                            <button type="submit" name="clear_logs" class="text-red-500 hover:text-red-700 text-sm font-semibold transition-colors">
                                <i class="fas fa-trash-alt mr-1"></i> Clear
                            </button>
                        </form>
                    </div>
                </div>
                <div class="p-4">
                    <div class="log-container" id="root-logs">
<?php
if (is_array($root_logs) && !empty($root_logs)) {
    foreach ($root_logs as $line) {
        echo formatLogLine($line) . "\n";
    }
} else {
    echo "<span class='text-gray-500 italic'>" . (is_array($root_logs) ? "Log file is empty." : $root_logs) . "</span>";
}
?>
                    </div>
                    <div class="mt-2 text-right">
                        <button onclick="scrollToBottom('root-logs')" class="text-xs text-blue-500 hover:text-blue-700"><i class="fas fa-arrow-down mr-1"></i>Scroll to Latest</button>
                    </div>
                </div>
            </div>

            <!-- Admin Error Log -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
                <div class="bg-gray-50 border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-700"><i class="fas fa-lock text-gray-500 mr-2"></i>Admin Area Logs</h2>
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-mono bg-gray-200 text-gray-600 px-2 py-1 rounded">admin/error_log</span>
                        <form method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to clear this log?');">
                            <input type="hidden" name="log_type" value="admin">
                            <button type="submit" name="clear_logs" class="text-red-500 hover:text-red-700 text-sm font-semibold transition-colors">
                                <i class="fas fa-trash-alt mr-1"></i> Clear
                            </button>
                        </form>
                    </div>
                </div>
                <div class="p-4">
                    <div class="log-container" id="admin-logs">
<?php
if (is_array($admin_logs) && !empty($admin_logs)) {
    foreach ($admin_logs as $line) {
        echo formatLogLine($line) . "\n";
    }
} else {
    echo "<span class='text-gray-500 italic'>" . (is_array($admin_logs) ? "Log file is empty." : $admin_logs) . "</span>";
}
?>
                    </div>
                    <div class="mt-2 text-right">
                        <button onclick="scrollToBottom('admin-logs')" class="text-xs text-blue-500 hover:text-blue-700"><i class="fas fa-arrow-down mr-1"></i>Scroll to Latest</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 text-center">
            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to clear ALL error logs?');">
                <input type="hidden" name="log_type" value="all">
                <button type="submit" name="clear_logs" class="px-6 py-2 bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 hover:border-red-300 rounded-lg transition-colors font-semibold shadow-sm">
                    <i class="fas fa-dumpster-fire mr-2"></i>Clear All Logs
                </button>
            </form>
        </div>

    </div>

    <script>
        function scrollToBottom(id) {
            const el = document.getElementById(id);
            el.scrollTop = el.scrollHeight;
        }

        // Auto scroll to bottom on load
        window.onload = function() {
            scrollToBottom('root-logs');
            scrollToBottom('admin-logs');
        };
    </script>
</body>
</html>
