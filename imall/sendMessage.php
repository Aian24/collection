<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = $_POST['message'];


    // Insert the user message into the database
    $conn = new mysqli('localhost', 'wqxgzpmy_imall', 'R4styL0p3z', 'wqxgzpmy_imall');

    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    $userSender = 'User'; // Customize the sender name
    $stmt = $conn->prepare('INSERT INTO messages (sender, message) VALUES (?, ?)');
    $stmt->bind_param('ss', $userSender, $message);
    $stmt->execute();
    $stmt->close();

    $conn->close();
}
?>
