// Add this code to userScript.js
function sendMessage() {
    var messageInput = document.getElementById('message-input');
    var message = messageInput.value.trim();

    if (message !== '') {
        // Send message to server (PHP)
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'sendMessage.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                // Update chat messages on success
                updateChat();
                messageInput.value = '';
            }
        };
        xhr.send('message=' + encodeURIComponent(message));
    }
}

function updateChat() {
    // Fetch and display chat messages from server (PHP)
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'getAdminMessages.php', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var chatMessages = document.getElementById('chat-messages');
            chatMessages.innerHTML = xhr.responseText;
            chatMessages.scrollTop = chatMessages.scrollHeight; // Scroll to bottom
        }
    };
    xhr.send();
}


function handleKeyDown(event) {
    // Check if the pressed key is Enter (key code 13)
    if (event.keyCode === 13) {
        // Prevent the default behavior of the Enter key (e.g., adding a newline)
        event.preventDefault();
        
        // Trigger the sendMessage function
        sendMessage();
    }
}

// Fetch and display chat messages on page load
updateChat();
setInterval(updateChat, 5000); // Update chat every 5 seconds (adjust as needed)
