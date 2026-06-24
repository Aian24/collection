<?php
include '../config.php';
session_start();

// Ensure only admins can access this page
if (!isset($_SESSION["username"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

session_write_close(); // Release session lock

// Functions to gather health data
function getDirectorySize($dir) {
    $size = 0;
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $size += getDirectorySize($path);
                } else {
                    $size += filesize($path);
                }
            }
        }
    }
    return $size;
}

function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// 1. Database Connections
$db_status = [];
$result = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
if ($row = $result->fetch_assoc()) $db_status['connected'] = $row['Value'];

$result = $conn->query("SHOW VARIABLES LIKE 'max_connections'");
if ($row = $result->fetch_assoc()) $db_status['max'] = $row['Value'];

$result = $conn->query("SHOW STATUS LIKE 'Threads_running'");
if ($row = $result->fetch_assoc()) $db_status['running'] = $row['Value'];

// 2. PHP Environment Settings
$php_env = [
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'session_gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
    'session_save_path' => ini_get('session.save_path') ?: sys_get_temp_dir()
];

// 3. Active Sessions (Count files in session dir)
$session_count = 0;
$session_dir = $php_env['session_save_path'];
if (is_dir($session_dir) && is_readable($session_dir)) {
    $files = glob($session_dir . '/sess_*');
    $session_count = $files !== false ? count($files) : 'Unknown';
} else {
    $session_count = 'Directory not readable';
}

// 4. Memory & Load
$memory_usage = memory_get_usage(true);
$load_avg = function_exists('sys_getloadavg') ? sys_getloadavg() : ['N/A', 'N/A', 'N/A'];

// 5. Error Logs Size
$root_error_log = '../error_log';
$admin_error_log = 'error_log';
$root_log_size = file_exists($root_error_log) ? filesize($root_error_log) : 0;
$admin_log_size = file_exists($admin_error_log) ? filesize($admin_error_log) : 0;

// 6. Running PHP Processes (If shell_exec is allowed)
$php_processes = 'N/A (Disabled)';
if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(', ', ini_get('disable_functions'))))) {
    $output = @shell_exec("ps aux | grep php | grep -v grep | wc -l 2>&1");
    if ($output !== null) {
        $php_processes = trim($output);
    }
}

// 7. Find long-running queries
$long_queries = [];
$result = $conn->query("SHOW FULL PROCESSLIST");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['Command'] !== 'Sleep' && $row['Time'] > 2) {
            $long_queries[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Health Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header { padding: 15px 20px; border-bottom: 1px solid #f0f0f0; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 20px; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #2c3e50; line-height: 1.2; }
        .stat-label { font-size: 0.875rem; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 5px; }
        .status-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
        .status-good { background-color: #2ecc71; box-shadow: 0 0 8px rgba(46, 204, 113, 0.6); }
        .status-warn { background-color: #f1c40f; box-shadow: 0 0 8px rgba(241, 196, 15, 0.6); }
        .status-danger { background-color: #e74c3c; box-shadow: 0 0 8px rgba(231, 76, 60, 0.6); }
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th, .table-custom td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .table-custom th { color: #7f8c8d; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; }
    </style>
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</head>
<body>
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><i class="fas fa-server text-blue-600 mr-3"></i>Server Health Monitor</h1>
                <p class="text-gray-500 mt-1">Real-time diagnostics for background processes and resource usage</p>
            </div>
            <div class="flex gap-3">
                <a href="admin.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Admin
                </a>
                <button onclick="window.location.reload()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-lg shadow-blue-200">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh Now
                </button>
            </div>
        </div>

        <!-- KPI Row 1 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- DB Connections -->
            <div class="card border-t-4 border-blue-500">
                <div class="card-body">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="stat-value"><?php echo $db_status['connected']; ?> <span class="text-lg text-gray-400 font-normal">/ <?php echo $db_status['max']; ?></span></div>
                            <div class="stat-label">Active DB Connections</div>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg text-blue-500"><i class="fas fa-database text-xl"></i></div>
                    </div>
                    <?php 
                        $conn_percent = ($db_status['connected'] / $db_status['max']) * 100;
                        $conn_color = $conn_percent > 80 ? 'bg-red-500' : ($conn_percent > 50 ? 'bg-yellow-500' : 'bg-blue-500');
                    ?>
                    <div class="w-full bg-gray-200 h-1.5 rounded-full mt-4">
                        <div class="<?php echo $conn_color; ?> h-1.5 rounded-full" style="width: <?php echo min(100, $conn_percent); ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Active Sessions -->
            <div class="card border-t-4 border-purple-500">
                <div class="card-body">
                    <div class="flex justify-between items-start">
                        <div>
                            <?php if (is_numeric($session_count)): ?>
                                <div class="stat-value"><?php echo $session_count; ?></div>
                            <?php else: ?>
                                <div class="text-sm font-bold text-red-500 mt-2 mb-1"><?php echo $session_count; ?></div>
                            <?php endif; ?>
                            <div class="stat-label">Active Sessions</div>
                        </div>
                        <div class="p-3 bg-purple-50 rounded-lg text-purple-500"><i class="fas fa-users text-xl"></i></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-4"><i class="fas fa-info-circle mr-1"></i>Saved in: <?php echo basename($php_env['session_save_path']); ?></div>
                </div>
            </div>

            <!-- Background Processes -->
            <div class="card border-t-4 border-green-500">
                <div class="card-body">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="stat-value"><?php echo $php_processes; ?></div>
                            <div class="stat-label">Active PHP Processes</div>
                        </div>
                        <div class="p-3 bg-green-50 rounded-lg text-green-500"><i class="fas fa-microchip text-xl"></i></div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <form method="POST" class="w-full">
                            <button type="submit" name="kill_processes" class="w-full text-xs py-1 px-2 border border-green-200 text-green-700 rounded hover:bg-green-50 transition-colors" onclick="return confirm('Are you sure? This will terminate ALL running PHP scripts immediately.');">
                                <i class="fas fa-skull-crossbones mr-1"></i> Kill Processes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Error Logs -->
            <?php 
                $total_log_mb = ($root_log_size + $admin_log_size) / (1024 * 1024);
                $log_border = $total_log_mb > 50 ? 'border-red-500' : 'border-yellow-500';
                $log_icon_bg = $total_log_mb > 50 ? 'bg-red-50 text-red-500' : 'bg-yellow-50 text-yellow-500';
            ?>
            <div class="card border-t-4 <?php echo $log_border; ?>">
                <div class="card-body">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="stat-value"><?php echo round($total_log_mb, 1); ?> <span class="text-lg text-gray-400 font-normal">MB</span></div>
                            <div class="stat-label">Error Log Size</div>
                        </div>
                        <div class="p-3 <?php echo $log_icon_bg; ?> rounded-lg"><i class="fas fa-file-alt text-xl"></i></div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <form method="POST" class="w-full">
                            <button type="submit" name="clear_logs" class="w-full text-xs py-1 px-2 border border-red-200 text-red-600 rounded hover:bg-red-50 transition-colors">
                                <i class="fas fa-trash-alt mr-1"></i> Clear Logs
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        // Handle Clear Logs action
        if (isset($_POST['clear_logs'])) {
            $cleared = 0;
            if (file_exists($root_error_log)) { file_put_contents($root_error_log, ''); $cleared++; }
            if (file_exists($admin_error_log)) { file_put_contents($admin_error_log, ''); $cleared++; }
            echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><i class="fas fa-check-circle mr-2"></i>Successfully cleared '.$cleared.' error log files!</p>
                  </div>';
            // Refresh variables
            $root_log_size = 0; $admin_log_size = 0;
        }

        // Handle Kill Processes action
        if (isset($_POST['kill_processes'])) {
            if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(', ', ini_get('disable_functions'))))) {
                $user = get_current_user();
                // We must run the kill command in the background with a delay (sleep 2).
                // Otherwise, it instantly kills THIS script before it can send the webpage, causing a 503 Service Unavailable error.
                $kill_cmd = "nohup sh -c 'sleep 2; pkill -u $user -f php; killall -9 -u $user lsphp; killall -9 -u $user php-cgi; killall -9 -u $user php' > /dev/null 2>&1 &";
                @shell_exec($kill_cmd);
                
                echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <p><i class="fas fa-skull-crossbones mr-2"></i>Sent kill signals to all background PHP processes. (Taking effect in 2 seconds)</p>
                      </div>';
            } else {
                echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                        <p><i class="fas fa-exclamation-triangle mr-2"></i>Cannot kill processes: shell_exec is disabled on this server.</p>
                      </div>';
            }
        }
        ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- PHP Environment Settings -->
            <div class="card">
                <div class="card-header bg-gray-50">
                    <i class="fas fa-cogs text-gray-500"></i> PHP Configuration Guardrails
                </div>
                <div class="p-0">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Current Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-medium">Execution Time Limit</td>
                                <td><?php echo $php_env['max_execution_time']; ?> seconds</td>
                                <td>
                                    <?php if ($php_env['max_execution_time'] <= 120 && $php_env['max_execution_time'] > 0): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">OPTIMIZED</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">TOO HIGH</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium">Memory Limit</td>
                                <td><?php echo $php_env['memory_limit']; ?></td>
                                <td>
                                    <?php if (intval($php_env['memory_limit']) <= 256): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">OPTIMIZED</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">TOO HIGH</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium">Current Memory Usage</td>
                                <td><?php echo formatSize($memory_usage); ?></td>
                                <td>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">NORMAL</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium">Server Load Avg</td>
                                <td><?php echo is_array($load_avg) ? implode(', ', $load_avg) : $load_avg; ?></td>
                                <td>
                                    <?php if(is_array($load_avg) && $load_avg[0] < 2): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">HEALTHY</span>
                                    <?php elseif(is_array($load_avg) && $load_avg[0] < 5): ?>
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-bold">ELEVATED</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">HIGH LOAD</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Long Running Queries -->
            <div class="card">
                <div class="card-header bg-gray-50 flex justify-between">
                    <div><i class="fas fa-search-dollar text-gray-500 mr-2"></i> DB Queries (>$2s)</div>
                    <span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded">Threads Running: <?php echo $db_status['running']; ?></span>
                </div>
                <div class="p-0 max-h-64 overflow-y-auto">
                    <?php if (empty($long_queries)): ?>
                        <div class="p-8 text-center text-gray-400">
                            <i class="fas fa-check-circle text-4xl mb-3 text-green-400 opacity-50"></i>
                            <p>No stuck or long-running database queries.</p>
                            <p class="text-xs mt-1">Database is performing optimally.</p>
                        </div>
                    <?php else: ?>
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Time</th>
                                    <th>State</th>
                                    <th>Info</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($long_queries as $q): ?>
                                    <tr class="bg-red-50">
                                        <td><?php echo $q['Id']; ?></td>
                                        <td class="font-bold text-red-600"><?php echo $q['Time']; ?>s</td>
                                        <td><span class="text-xs truncate max-w-[100px] block"><?php echo htmlspecialchars($q['State']); ?></span></td>
                                        <td class="text-xs font-mono"><div class="truncate max-w-[150px]" title="<?php echo htmlspecialchars($q['Info']); ?>"><?php echo htmlspecialchars($q['Info'] ?: 'NULL'); ?></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="text-center text-gray-400 text-sm mt-8">
            <p>This page automatically refreshes every 30 seconds.</p>
            <p class="mt-1">Collection POS &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>
</body>
</html>
