<?php
$conn = new mysqli('localhost', 'wqxgzpmy_imall', 'R4styL0p3z', 'wqxgzpmy_imall');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$result = $conn->query('SELECT * FROM messages ORDER BY timestamp DESC LIMIT 10');

$messages = array();

while ($row = $result->fetch_assoc()) {
    $sender = $row['sender'];
    $message = $row['message'];
    $class = ($sender == 'Admin') ? 'admin-message' : 'user-message';

    // Store the messages in an array
    $messages[] = '<div class="' . $class . '"><strong>' . htmlspecialchars($sender) . ':</strong> ' . htmlspecialchars($message) . '</div>';
}

// Reverse the array to display messages from bottom to top
$messages = array_reverse($messages);

// Display the messages
foreach ($messages as $message) {
    echo $message;
}

$conn->close();
?>
