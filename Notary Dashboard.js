// dashboard.js
function pollConnections() {
    fetch('/api/get_connections.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            data.sessions.forEach(session => {
                if (session.status === 'disconnected') {
                    showAlert(session.user_id);
                }
            });
        });
}

function showAlert(user_id) {
    // Show alert, red indicator, and play sound
    document.getElementById('user-' + user_id).classList.add('disconnected');
    new Audio('/sounds/alert.mp3').play();
    alert('Client connection lost – Possible power outage');
}

setInterval(pollConnections, 5000);