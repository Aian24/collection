// Add this code to adminScript.js
function sendAdminMessage() {
    var adminMessageInput = document.getElementById('admin-message-input');
    var adminMessage = adminMessageInput.value.trim();

    if (adminMessage !== '') {
        // Send message to server (PHP)
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'sendAdminMessage.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                // Update chat messages on success
                updateAdminChat();
                adminMessageInput.value = '';
            }
        };
        xhr.send('adminMessage=' + encodeURIComponent(adminMessage));
    }
}

function updateAdminChat() {
    // Fetch and display admin chat messages from server (PHP)
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'getMessages.php', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var adminChatMessages = document.getElementById('admin-chat-messages');
            adminChatMessages.innerHTML = xhr.responseText;
            adminChatMessages.scrollTop = adminChatMessages.scrollHeight; // Scroll to bottom
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
        sendAdminMessage();
    }
}

// Fetch and display admin chat messages on page load
updateAdminChat();
setInterval(updateAdminChat, 5000); // Update admin chat every 5 seconds (adjust as needed)
