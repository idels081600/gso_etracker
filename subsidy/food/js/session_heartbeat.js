/**
 * Session Heartbeat Manager
 * 
 * Keeps the user session alive during active use by sending
 * periodic heartbeat requests to the server.
 * 
 * Usage: Include this file in your HTML header, then initialize with:
 * <script src="session_heartbeat.js"></script>
//  * <script>
//  *   // Start heartbeat with 5 minute interval
//  *   SessionHeartbeat.init({
//  *     interval: 5 * 60 * 1000,  // 5 minutes
//  *     apiUrl: './api_heartbeat.php',
//  *     warningThreshold: 5 * 60 * 1000 // Warn when 5 minutes remain
//  *   });
//  * </script>
 */

const SessionHeartbeat = (function() {
    let config = {
        interval: 5 * 60 * 1000,        // Default: 5 minutes
        apiUrl: './api_heartbeat.php',
        warningThreshold: 5 * 60 * 1000, // Warn when 5 minutes remain
        enableLogging: true,
        showWarning: true,
        onSessionExpired: null,
        onSessionWarning: null,
        onHeartbeatSuccess: null,
        onHeartbeatError: null
    };

    let heartbeatTimer = null;
    let warningTimer = null;
    let isInitialized = false;
    let heartbeatCount = 0;
    let lastWarningTime = 0;
    let warningDebounceMs = 30000; // Only show warning once every 30 seconds

    /**
     * Initialize the heartbeat manager
     */
    function init(customConfig = {}) {
        // Merge custom config
        config = { ...config, ...customConfig };
        
        if (isInitialized) {
            console.warn('SessionHeartbeat already initialized');
            return;
        }

        isInitialized = true;
        log('SessionHeartbeat initialized', config);

        // Start heartbeat interval
        startHeartbeat();

        // Listen for user activity
        attachActivityListeners();

        // Handle page visibility changes
        handleVisibilityChange();
    }

    /**
     * Start the heartbeat interval
     */
    function startHeartbeat() {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
        }

        heartbeatTimer = setInterval(() => {
            sendHeartbeat();
        }, config.interval);

        // Send initial heartbeat
        sendHeartbeat();
    }

    /**
     * Send heartbeat request to server
     */
    function sendHeartbeat() {
        fetch(config.apiUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.status === 401) {
                // Session expired
                handleSessionExpired();
                return null;
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                heartbeatCount++;
                log(`Heartbeat #${heartbeatCount} successful`, data);
                
                // Update session remaining time display if element exists
                updateSessionDisplay(data.session_remaining);
                
                // Check if session_remaining data exists and is valid
                if (data.session_remaining !== null && 
                    data.session_remaining !== undefined && 
                    typeof data.session_remaining === 'number') {
                    
                    // Check if session time is running low (with debounce to avoid spam)
                    const now = Date.now();
                    if (data.session_remaining <= config.warningThreshold && 
                        config.showWarning && 
                        (now - lastWarningTime) > warningDebounceMs) {
                        lastWarningTime = now;
                        showSessionWarning(data.session_remaining);
                    }
                } else {
                    log('Warning: session_remaining is missing or invalid', {
                        session_remaining: data.session_remaining,
                        type: typeof data.session_remaining
                    });
                }

                if (config.onHeartbeatSuccess) {
                    config.onHeartbeatSuccess(data);
                }
            } else {
                handleHeartbeatError('Invalid response');
            }
        })
        .catch(error => {
            handleHeartbeatError(error.message);
        });
    }

    /**
     * Handle session expiration
     */
    function handleSessionExpired() {
        log('Session expired!', 'error');
        clearInterval(heartbeatTimer);
        
        if (config.onSessionExpired) {
            config.onSessionExpired();
        } else {
            // Default behavior: redirect to login
            showNotification('Your session has expired. Please login again.', 'error');
            setTimeout(() => {
                window.location.href = '../../login_v2.php';
            }, 2000);
        }
    }

    /**
     * Handle heartbeat error
     */
    function handleHeartbeatError(message) {
        log(`Heartbeat error: ${message}`, 'warn');
        
        if (config.onHeartbeatError) {
            config.onHeartbeatError(message);
        }
    }

    /**
     * Show session warning
     */
    function showSessionWarning(timeRemaining) {
        const minutes = Math.floor(timeRemaining / 60000);
        const message = `Your session will expire in ${minutes} minute(s). Keep working or your session will close.`;
        
        log('Session warning', message);
        
        if (config.onSessionWarning) {
            config.onSessionWarning(timeRemaining, message);
        } else {
            showNotification(message, 'warning');
        }
    }

    /**
     * Update session display element
     */
    function updateSessionDisplay(timeRemaining) {
        const displayElement = document.getElementById('session-time-remaining');
        if (displayElement) {
            const minutes = Math.floor(timeRemaining / 60000);
            const seconds = Math.floor((timeRemaining % 60000) / 1000);
            displayElement.textContent = `${minutes}m ${seconds}s`;
            displayElement.setAttribute('data-time', timeRemaining);
        }
    }

    /**
     * Attach activity listeners to reset heartbeat
     */
    function attachActivityListeners() {
        const events = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, debounce(() => {
                log(`User activity detected: ${event}`);
                // Activity detected, heartbeat will continue
            }, 1000), true);
        });
    }

    /**
     * Handle page visibility changes
     */
    function handleVisibilityChange() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                log('Page hidden - reducing heartbeat frequency');
                // Optionally reduce heartbeat frequency when page is not visible
                if (heartbeatTimer) {
                    clearInterval(heartbeatTimer);
                }
            } else {
                log('Page visible - resuming heartbeat');
                // Resume heartbeat when page becomes visible
                startHeartbeat();
            }
        });
    }

    /**
     * Show notification to user
     */
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `session-notification session-notification-${type}`;
        notification.innerHTML = `
            <div style="padding: 15px; border-radius: 4px; margin: 10px;">
                ${message}
            </div>
        `;

        // Add styles if not already present
        if (!document.getElementById('session-heartbeat-styles')) {
            const style = document.createElement('style');
            style.id = 'session-heartbeat-styles';
            style.textContent = `
                .session-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    min-width: 300px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                    border-radius: 4px;
                    animation: slideIn 0.3s ease-out;
                }
                .session-notification-info {
                    background-color: #d1ecf1;
                    border: 1px solid #bee5eb;
                    color: #0c5460;
                }
                .session-notification-warning {
                    background-color: #fff3cd;
                    border: 1px solid #ffeaa7;
                    color: #856404;
                }
                .session-notification-error {
                    background-color: #f8d7da;
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                }
                @keyframes slideIn {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'slideIn 0.3s ease-out reverse';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }

    /**
     * Debounce helper function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Logging helper
     */
    function log(message, data) {
        if (config.enableLogging) {
            const timestamp = new Date().toLocaleTimeString();
            console.log(`[SessionHeartbeat ${timestamp}] ${message}`, data || '');
        }
    }

    /**
     * Destroy heartbeat
     */
    function destroy() {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
        }
        if (warningTimer) {
            clearInterval(warningTimer);
        }
        isInitialized = false;
        log('SessionHeartbeat destroyed');
    }

    /**
     * Get statistics
     */
    function getStats() {
        return {
            isInitialized,
            heartbeatCount,
            config
        };
    }

    // Public API
    return {
        init,
        startHeartbeat,
        sendHeartbeat,
        destroy,
        getStats,
        showNotification
    };
})();

// Auto-initialize if data attribute is present on script tag
document.addEventListener('DOMContentLoaded', () => {
    const scripts = document.querySelectorAll('script[data-session-heartbeat]');
    scripts.forEach(script => {
        SessionHeartbeat.init();
    });
});
