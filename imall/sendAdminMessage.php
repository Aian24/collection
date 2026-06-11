<!-- Add this code to sendAdminMessage.php -->
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminMessage = $_POST['adminMessage'];
    
    // Simulate admin response (you can replace this with actual admin response logic)
    $adminResponse = "Admin: Thank you for your message!";
    // Send admin response back to the client
    echo $adminResponse;

    // Insert the admin message into the database
    $conn = new mysqli('localhost', 'wqxgzpmy_imall', 'R4styL0p3z', 'wqxgzpmy_imall');

    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    $adminSender = 'Admin';
    $stmt = $conn->prepare('INSERT INTO messages (sender, message) VALUES (?, ?)');
    $stmt->bind_param('ss', $adminSender, $adminMessage);
    $stmt->execute();
    $stmt->close();

    $conn->close();
}
?>
