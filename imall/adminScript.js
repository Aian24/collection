// Admin chat functionality
// OPTIMIZED: Added visibility-based pausing and increased interval from 5s to 15s
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
    // Don't poll if page is not visible
    if (document.hidden) return;
    
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

// Admin chat polling with visibility-based pause/resume
var _adminChatInterval = null;

function _startAdminChatPolling() {
    if (_adminChatInterval) clearInterval(_adminChatInterval);
    updateAdminChat();
    _adminChatInterval = setInterval(updateAdminChat, 15000); // Every 15s (was 5s)
}

function _stopAdminChatPolling() {
    if (_adminChatInterval) {
        clearInterval(_adminChatInterval);
        _adminChatInterval = null;
    }
}

// Pause/resume based on page visibility
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        _stopAdminChatPolling();
    } else {
        _startAdminChatPolling();
    }
});

// Start polling
_startAdminChatPolling();
