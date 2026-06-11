<?php
/**
 * LC Lopez Collection - Offline Login Handler
 * Allows users to login using cached credentials when offline
 */

session_start();

// Check if this is an offline mode login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['offline_mode']) && $_POST['offline_mode'] == '1') {
    
    // Get cached user data
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $lname = isset($_POST['lname']) ? $_POST['lname'] : '';
    $branch = isset($_POST['branch']) ? $_POST['branch'] : '';
    $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : 'normal_user';
    
    // Validate required fields
    if (empty($username) || empty($branch)) {
        header("Location: index.php");
        exit();
    }
    
    // Set session variables for offline mode
    $_SESSION["username"] = $username;
    $_SESSION["lname"] = $lname;
    $_SESSION["branch"] = $branch;
    $_SESSION["user_type"] = $user_type;
    $_SESSION["offline_mode"] = true; // Flag to indicate offline session
    $_SESSION["id"] = 0; // Placeholder ID for offline mode
    
    // Redirect based on user type
    if ($user_type === "normal_user") {
        header("Location: user.php");
        exit();
    } elseif ($user_type === "admin") {
        header("Location: admin/admin.php");
        exit();
    } elseif ($user_type === "admin_viewer") {
        header("Location: admin/adminacc.php");
        exit();
    } elseif ($user_type === "superuser") {
        header("Location: superuser.php");
        exit();
    } elseif ($user_type === "adminapm") {
        header("Location: admin/collectionapm.php");
        exit();
    } else {
        // Default to user page
        header("Location: user.php");
        exit();
    }
    
} else {
    // Invalid request, redirect to login
    header("Location: index.php");
    exit();
}
?>

