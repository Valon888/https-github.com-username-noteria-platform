// heartbeat.js
let heartbeatInterval = 5000;
let lastSent = 0;

function sendHeartbeat() {
    const now = Date.now();
    if (now - lastSent < heartbeatInterval) return;
    lastSent = now;

    fetch('/api/heartbeat.php', {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN // Set this from PHP
        },
        body: JSON.stringify({
            user_id: window.USER_ID,
            session_id: window.SESSION_ID,
            appointment_id: window.APPOINTMENT_ID,
            timestamp: Math.floor(now / 1000)
        })
    });
}

setInterval(sendHeartbeat, heartbeatInterval);
window.addEventListener('load', sendHeartbeat);