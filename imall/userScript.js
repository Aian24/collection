// Chat functionality for user pages
// OPTIMIZED: Added visibility-based pausing and increased interval from 5s to 15s
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
    // Don't poll if page is not visible
    if (document.hidden) return;
    
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

// Chat polling with visibility-based pause/resume
var _chatInterval = null;

function _startChatPolling() {
    if (_chatInterval) clearInterval(_chatInterval);
    updateChat();
    _chatInterval = setInterval(updateChat, 15000); // Every 15s (was 5s)
}

function _stopChatPolling() {
    if (_chatInterval) {
        clearInterval(_chatInterval);
        _chatInterval = null;
    }
}

// Pause/resume based on page visibility
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        _stopChatPolling();
    } else {
        _startChatPolling();
    }
});

// Start polling
_startChatPolling();
