<!-- Add this code to message.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Message to Admin</title>
</head>
<body>
    <div id="chat-container">
        <div id="chat-messages"></div>
        <input type="text" id="message-input" placeholder="Type your message..." onkeydown="handleKeyDown(event)">
        <button onclick="sendMessage()">Send</button>
    </div>

    <script src="userScript.js"></script>
</body>
</html>
