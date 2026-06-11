<?php
/**
 * Emergency Reset Script
 * =====================
 * Use this when the website is completely stuck/infinite loading.
 * 
 * Access: https://lclopezresources.com/collection/emergency_reset.php?key=LCL0p3z_Em3rg3ncy
 * 
 * This script:
 * 1. Does NOT use session_start() (avoids session lock deadlock)
 * 2. Does NOT connect to the database (avoids DB connection pool exhaustion)
 * 3. Clears OPcache to release compiled PHP scripts
 * 4. Destroys ALL session files to break deadlocks
 * 5. Truncates bloated error_log files
 * 6. Reports server resource status
 * 
 * IMPORTANT: After running this, all users will need to login again.
 */

// Simple authentication key - change this to something unique!
$AUTH_KEY = 'LCL0p3z_Em3rg3ncy';

// Verify auth key
if (!isset($_GET['key']) || $_GET['key'] !== $AUTH_KEY) {
    http_response_code(403);
    header('Content-Type: text/plain');
    die("Access denied. Use: ?key=YOUR_KEY");
}

// Force no caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$results = [];
$start_time = microtime(true);

// === 1. Clear OPcache ===
if (function_exists('opcache_reset')) {
    $results[] = opcache_reset() 
        ? '✅ OPcache cleared successfully' 
        : '⚠️ OPcache reset returned false';
} else {
    $results[] = 'ℹ️ OPcache not available';
}

// === 2. Clear APCu cache ===
if (function_exists('apcu_clear_cache')) {
    $results[] = apcu_clear_cache()
        ? '✅ APCu cache cleared'
        : '⚠️ APCu clear failed';
} else {
    $results[] = 'ℹ️ APCu not available';
}

// === 3. Destroy ALL session files ===
$session_paths = [
    session_save_path(),
    '/var/cpanel/php/sessions/ea-php83',
    '/tmp',
];

$total_sessions_cleared = 0;
foreach ($session_paths as $path) {
    if (!empty($path) && is_dir($path) && is_readable($path)) {
        $files = @glob($path . '/sess_*');
        if ($files) {
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $total_sessions_cleared++;
                }
            }
        }
    }
}
$results[] = "✅ Destroyed $total_sessions_cleared session files (all users will need to re-login)";

// === 4. Truncate error_log files ===
$log_files = [
    __DIR__ . '/error_log',
    __DIR__ . '/admin/error_log',
];

foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        $sizeMB = round(filesize($log_file) / 1048576, 2);
        if ($sizeMB > 5) {
            // Keep only last 500 lines
            $lines = @file($log_file);
            if ($lines) {
                $kept = array_slice($lines, -500);
                @file_put_contents($log_file, implode('', $kept));
                $newSize = round(filesize($log_file) / 1048576, 2);
                $results[] = "✅ Truncated $log_file: {$sizeMB}MB → {$newSize}MB";
            }
        } else {
            $results[] = "ℹ️ $log_file: {$sizeMB}MB (OK)";
        }
    }
}

// === 5. Server Status ===
$results[] = '--- Server Status ---';
$results[] = 'PHP Version: ' . phpversion();
$results[] = 'Memory Limit: ' . ini_get('memory_limit');
$results[] = 'Max Execution Time: ' . ini_get('max_execution_time') . 's';
$results[] = 'Session Save Path: ' . session_save_path();
$results[] = 'Server Time: ' . date('Y-m-d H:i:s T');

if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    $results[] = sprintf('Server Load: %.2f / %.2f / %.2f (1/5/15 min)', $load[0], $load[1], $load[2]);
    if ($load[0] > 4) {
        $results[] = '🔴 HIGH SERVER LOAD - This may be causing the infinite loading!';
    }
}

// Disk space
$free = @disk_free_space('/');
$total = @disk_total_space('/');
if ($free !== false && $total !== false) {
    $freeGB = round($free / 1073741824, 2);
    $totalGB = round($total / 1073741824, 2);
    $usedPercent = round(($total - $free) / $total * 100, 1);
    $results[] = "Disk: {$freeGB}GB free of {$totalGB}GB ({$usedPercent}% used)";
    if ($usedPercent > 90) {
        $results[] = '🔴 DISK NEARLY FULL - This can cause crashes!';
    }
}

$elapsed = round((microtime(true) - $start_time) * 1000, 2);
$results[] = "Script completed in {$elapsed}ms";

// Output
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Reset</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 32px; max-width: 600px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        h1 { font-size: 24px; color: #f59e0b; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .subtitle { color: #94a3b8; font-size: 14px; margin-bottom: 24px; }
        .result { padding: 8px 12px; border-radius: 8px; font-size: 13px; font-family: 'Consolas', 'Monaco', monospace; margin-bottom: 4px; }
        .result:nth-child(odd) { background: rgba(255,255,255,0.03); }
        .separator { border-top: 1px solid #334155; margin: 12px 0; }
        .actions { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { padding: 12px 24px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-block; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-warning { background: #f59e0b; color: #1e293b; }
        .btn-warning:hover { background: #d97706; transform: translateY(-1px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-1px); }
        .warning-box { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 10px; padding: 16px; margin-bottom: 20px; font-size: 13px; color: #fbbf24; }
    </style>
</head>
<body>
    <div class="card">
        <h1>⚡ Emergency Reset Complete</h1>
        <p class="subtitle">All caches cleared and stuck sessions destroyed.</p>
        
        <div class="warning-box">
            ⚠️ All users have been logged out. Everyone will need to sign in again.
        </div>
        
        <div class="results">
            <?php foreach ($results as $r): ?>
                <?php if (strpos($r, '---') === 0): ?>
                    <div class="separator"></div>
                    <div class="result" style="color: #60a5fa; font-weight: bold;"><?php echo htmlspecialchars($r); ?></div>
                <?php else: ?>
                    <div class="result"><?php echo htmlspecialchars($r); ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <div class="actions">
            <a href="index.php" class="btn btn-primary">🔑 Go to Login</a>
            <a href="clearcache.php" class="btn btn-warning">🧹 Clear Cache Again</a>
            <a href="emergency_reset.php?key=<?php echo urlencode($AUTH_KEY); ?>" class="btn btn-danger">🔄 Run Again</a>
        </div>
    </div>
</body>
</html>
