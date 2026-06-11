/**
 * Session Manager
 * Monitors PHP session status and warns users about expiration
 * Helps prevent losing work due to session timeout while offline
 */

const SessionManager = (function() {
    'use strict';

    // Configuration
    const SESSION_CHECK_INTERVAL = 60000; // Check every 1 minute
    const SESSION_WARNING_TIME = 300000; // Warn 5 minutes before expiration
    const SESSION_TIMEOUT = 1800000; // Default PHP session timeout (30 minutes)
    
    let sessionCheckTimer = null;
    let lastActivityTime = Date.now();
    let isWarningShown = false;

    /**
     * Initialize session manager
     */
    function init() {
        // Track user activity
        trackUserActivity();
        
        // Start session monitoring
        startSessionMonitoring();
        
        // Check session on page load
        checkSessionStatus();
        
        console.log('Session Manager initialized');
    }

    /**
     * Track user activity to reset session timer
     */
    function trackUserActivity() {
        const events = ['mousedown', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                lastActivityTime = Date.now();
                isWarningShown = false;
            }, { passive: true });
        });
    }

    /**
     * Start monitoring session status
     */
    function startSessionMonitoring() {
        sessionCheckTimer = setInterval(() => {
            const timeSinceActivity = Date.now() - lastActivityTime;
            const timeUntilExpiry = SESSION_TIMEOUT - timeSinceActivity;
            
            // Show warning if session is about to expire
            if (timeUntilExpiry <= SESSION_WARNING_TIME && !isWarningShown) {
                showSessionWarning(Math.floor(timeUntilExpiry / 60000));
                isWarningShown = true;
            }
            
            // Check if session is still valid on server (if online)
            if (navigator.onLine) {
                checkSessionStatus();
            }
        }, SESSION_CHECK_INTERVAL);
    }

    /**
     * Check session status with server
     */
    async function checkSessionStatus() {
        if (!navigator.onLine) {
            return; // Can't check while offline
        }

        try {
            const response = await fetch('check_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=check_session'
            });

            const data = await response.json();
            
            if (data.status === 'expired') {
                handleSessionExpired();
            } else if (data.status === 'active') {
                // Session is still active
                lastActivityTime = Date.now();
            }
        } catch (error) {
            console.error('Session check failed:', error);
        }
    }

    /**
     * Keep session alive by pinging server
     */
    async function keepAlive() {
        if (!navigator.onLine) {
            showOfflineWarning();
            return false;
        }

        try {
            const response = await fetch('check_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=keep_alive'
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                lastActivityTime = Date.now();
                isWarningShown = false;
                
                if (typeof showModal === 'function') {
                    showModal('Success', 'Session extended successfully!', 'success');
                } else {
                    alert('Session extended successfully!');
                }
                
                return true;
            } else {
                handleSessionExpired();
                return false;
            }
        } catch (error) {
            console.error('Keep alive failed:', error);
            return false;
        }
    }

    /**
     * Show warning when session is about to expire
     */
    function showSessionWarning(minutesLeft) {
        const warningHTML = `
            <div id="session-warning" class="fixed top-20 right-4 bg-yellow-500 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm animate-bounce">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-2xl mr-3 mt-1"></i>
                    <div class="flex-1">
                        <h3 class="font-bold mb-2">Session Expiring Soon!</h3>
                        <p class="text-sm mb-3">Your session will expire in approximately ${minutesLeft} minute(s).</p>
                        <div class="flex gap-2">
                            <button onclick="SessionManager.keepAlive()" class="bg-white text-yellow-700 px-3 py-1 rounded text-sm font-semibold hover:bg-yellow-100">
                                <i class="fas fa-refresh mr-1"></i> Extend Session
                            </button>
                            <button onclick="SessionManager.dismissWarning()" class="bg-yellow-600 text-white px-3 py-1 rounded text-sm hover:bg-yellow-700">
                                Dismiss
                            </button>
                        </div>
                    </div>
                    <button onclick="SessionManager.dismissWarning()" class="text-white hover:text-yellow-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;

        // Remove existing warning if any
        const existingWarning = document.getElementById('session-warning');
        if (existingWarning) {
            existingWarning.remove();
        }

        // Add new warning
        document.body.insertAdjacentHTML('beforeend', warningHTML);

        // Auto-dismiss after 30 seconds if not interacted with
        setTimeout(() => {
            dismissWarning();
        }, 30000);
    }

    /**
     * Show offline warning when trying to extend session
     */
    function showOfflineWarning() {
        if (typeof showModal === 'function') {
            showModal('Offline', 'Cannot extend session while offline. Please connect to internet and try again.', 'warning');
        } else {
            alert('Cannot extend session while offline. Please connect to internet and try again.');
        }
    }

    /**
     * Dismiss session warning
     */
    function dismissWarning() {
        const warning = document.getElementById('session-warning');
        if (warning) {
            warning.remove();
        }
    }

    /**
     * Handle session expiration
     */
    function handleSessionExpired() {
        // Stop monitoring
        if (sessionCheckTimer) {
            clearInterval(sessionCheckTimer);
        }

        // Check if there are offline transactions
        if (typeof OfflineHandler !== 'undefined') {
            OfflineHandler.getUnsyncedCount().then(count => {
                if (count > 0) {
                    showSessionExpiredWithOfflineData(count);
                } else {
                    showSessionExpiredModal();
                }
            }).catch(() => {
                showSessionExpiredModal();
            });
        } else {
            showSessionExpiredModal();
        }
    }

    /**
     * Show session expired modal (no offline data)
     */
    function showSessionExpiredModal() {
        const modalHTML = `
            <div id="session-expired-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg shadow-xl p-6 max-w-md mx-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-clock text-red-600 text-6xl mb-4"></i>
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Session Expired</h2>
                        <p class="text-gray-600">Your session has expired due to inactivity. Please login again to continue.</p>
                    </div>
                    <button onclick="window.location.href='index.php'" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-6 rounded-lg font-semibold">
                        <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                    </button>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    /**
     * Show session expired with offline data warning
     */
    function showSessionExpiredWithOfflineData(count) {
        const modalHTML = `
            <div id="session-expired-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg shadow-xl p-6 max-w-md mx-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-6xl mb-4"></i>
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Session Expired</h2>
                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-4">
                            <p class="font-bold text-yellow-800 mb-2">⚠️ WARNING</p>
                            <p class="text-yellow-700 text-sm">
                                You have <strong>${count} offline transaction(s)</strong> that need to be synced!
                            </p>
                        </div>
                        <p class="text-gray-600 mb-4">
                            Your session has expired. Please login again to sync your offline transactions.
                        </p>
                        <div class="bg-blue-50 border border-blue-200 rounded p-3 mb-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                Your offline transactions are safe and will be synced after you login.
                            </p>
                        </div>
                    </div>
                    <button onclick="window.location.href='index.php'" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-6 rounded-lg font-semibold">
                        <i class="fas fa-sign-in-alt mr-2"></i> Go to Login & Sync
                    </button>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    /**
     * Get time until session expires
     */
    function getTimeUntilExpiry() {
        const timeSinceActivity = Date.now() - lastActivityTime;
        const timeUntilExpiry = SESSION_TIMEOUT - timeSinceActivity;
        return Math.max(0, timeUntilExpiry);
    }

    /**
     * Stop session monitoring (cleanup)
     */
    function stop() {
        if (sessionCheckTimer) {
            clearInterval(sessionCheckTimer);
            sessionCheckTimer = null;
        }
    }

    // Public API
    return {
        init,
        keepAlive,
        dismissWarning,
        checkSessionStatus,
        getTimeUntilExpiry,
        stop
    };
})();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => SessionManager.init());
} else {
    SessionManager.init();
}

