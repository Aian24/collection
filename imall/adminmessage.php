<!-- Add this code to admin.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Admin Chat</title>
</head>
<body>
    <div id="chat-container">
        <div id="admin-chat-messages"></div>
        <input type="text" id="admin-message-input" placeholder="Type your reply... "  onkeydown="handleKeyDown(event)">
        <button onclick="sendAdminMessage()">Send</button>
    </div>

    <script src="adminScript.js"></script>
</body>
</html>
