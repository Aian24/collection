<?php
// Include your database connection file
ob_start(); // Start output buffering
include 'config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id']) && isset($_POST['online_status'])) {
    $user_id = $_POST['user_id'];
    $online_status = $_POST['online_status'];

    // Update user's online status
    $conn->query("UPDATE `users` SET `online_status` = $online_status WHERE `id` = $user_id");

    echo "User status updated successfully.";
} else {
    echo "Invalid request.";
}

// Fetch online status of users
$onlineStatusQuery = "SELECT id, online_status FROM users";
$onlineStatusResult = mysqli_query($conn, $onlineStatusQuery);
$onlineStatusData = mysqli_fetch_all($onlineStatusResult, MYSQLI_ASSOC);

// Return the online status as JSON
echo json_encode($onlineStatusData);
?>
