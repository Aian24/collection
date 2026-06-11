<?php
ob_start(); // Start output buffering
include '../config.php';
include '../nav.php';
session_start();

// Handle delete request
if ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    // Get user ID from request
    $userId = $_GET['id'] ?? "";

    // Delete user record from the database
    $deleteQuery = "DELETE FROM users WHERE id='$userId'";
    $deleteResult = mysqli_query($conn, $deleteQuery);

    // Check if deletion was successful
    if ($deleteResult) {
        // Send success response
        http_response_code(200);
    } else {
        // Send error response
        http_response_code(500);
        echo "Error: " . mysqli_error($conn);
    }
}
?>
