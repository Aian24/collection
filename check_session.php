<?php
/**
 * Session Status Checker
 * Checks if user session is still active and can extend it
 */

header('Content-Type: application/json');
session_start();

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'check_session') {
        // Read session data then release the lock immediately
        $username = $_SESSION["username"] ?? null;
        $branch = $_SESSION["branch"] ?? 'Unknown';
        session_write_close(); // Release session lock - critical for AJAX polling

        // Check if user is logged in
        if (!empty($username)) {
            echo json_encode([
                'status' => 'active',
                'username' => $username,
                'branch' => $branch,
                'timestamp' => time()
            ]);
        } else {
            // Session expired or not logged in
            http_response_code(401);
            echo json_encode([
                'status' => 'expired',
                'message' => 'Session has expired. Please login again.',
                'timestamp' => time()
            ]);
        }
    } 
    elseif ($action == 'keep_alive') {
        // Keep session alive by regenerating it
        if (isset($_SESSION["username"]) && !empty($_SESSION["username"])) {
            // Regenerate session ID to extend session lifetime
            session_regenerate_id(true);
            
            // Update session with current timestamp
            $_SESSION['last_activity'] = time();
            $username = $_SESSION["username"];
            
            // Write and release session lock
            session_write_close();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Session extended successfully.',
                'username' => $username,
                'timestamp' => time()
            ]);
        } else {
            session_write_close();
            http_response_code(401);
            echo json_encode([
                'status' => 'expired',
                'message' => 'Session has expired. Please login again.',
                'timestamp' => time()
            ]);
        }
    }
    else {
        session_write_close();
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action.'
        ]);
    }
} else {
    session_write_close();
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Please use POST.'
    ]);
}
?>

