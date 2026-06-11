<?php
/**
 * Cache Clearing Utility
 * Can be used to clear OPcache, APCu, and temporary files 
 * when Bluehost background processes cause issues.
 * 
 * IMPORTANT: This script intentionally does NOT use session_start()
 * so it remains accessible even when PHP sessions are locked/stuck.
 */

// NO session_start() here - this must work even when sessions are deadlocked!

$messages = [];

// 1. Clear OPcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        $messages[] = "PHP OPcache successfully reset.";
    } else {
        $messages[] = "PHP OPcache could not be reset (may be disabled).";
    }
} else {
    $messages[] = "OPcache is not installed or enabled.";
}

// 2. Clear APCu Cache
if (function_exists('apcu_clear_cache')) {
    if (apcu_clear_cache()) {
        $messages[] = "APCu cache successfully cleared.";
    } else {
        $messages[] = "APCu cache could not be cleared.";
    }
} else {
    $messages[] = "APCu cache is not installed or enabled.";
}

// 3. Clean up old session files (help free locked sessions)
$session_path = session_save_path();
if (!empty($session_path) && is_dir($session_path)) {
    $cleaned = 0;
    $files = glob($session_path . '/sess_*');
    if ($files) {
        foreach ($files as $file) {
            // Only delete sessions older than 30 minutes
            if (filemtime($file) < time() - 1800) {
                @unlink($file);
                $cleaned++;
            }
        }
    }
    $messages[] = "Cleaned $cleaned old session files.";
} else {
    $messages[] = "Session path not accessible for cleanup.";
}

// 4. Try to invoke cPanel/Bluehost specific cache clearing via URL if applicable
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// 5. Truncate error_log if it's too large (over 10MB)
$error_log = __DIR__ . '/error_log';
if (file_exists($error_log)) {
    $size = filesize($error_log);
    $sizeMB = round($size / 1048576, 2);
    if ($size > 10485760) { // 10MB
        // Keep last 1000 lines, truncate the rest
        $lines = file($error_log);
        $lastLines = array_slice($lines, -1000);
        file_put_contents($error_log, implode('', $lastLines));
        $newSize = round(filesize($error_log) / 1048576, 2);
        $messages[] = "Error log truncated: {$sizeMB}MB → {$newSize}MB (kept last 1000 lines).";
    } else {
        $messages[] = "Error log size: {$sizeMB}MB (OK, no truncation needed).";
    }
} else {
    $messages[] = "No error_log file found.";
}

$messages[] = "Cache clear completed at: " . date('Y-m-d H:i:s T');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear Website Cache</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full text-center">
        <div class="text-green-500 mb-4">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Cache Cleared!</h2>
        <p class="text-gray-600 mb-6">Server-level PHP caches have been flushed to free up background resources.</p>
        
        <div class="bg-gray-50 p-4 rounded text-left text-sm text-gray-700 mb-6">
            <ul class="list-disc pl-5 space-y-1">
                <?php foreach($messages as $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <a href="index.php" class="inline-block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200">
            Return to Login
        </a>
    </div>
</body>
</html>
