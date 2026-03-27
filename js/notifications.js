// js/notifications.js

document.addEventListener('DOMContentLoaded', function() {
    // Krijo container për njoftime nëse nuk ekziston
    if (!document.getElementById('notification-container')) {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        `;
        document.body.appendChild(container);
    }

    // Start SSE connection
    const evtSource = new EventSource('sse_notifications.php');

    evtSource.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            if (data.error) return;
            
            showNotification(data.title, data.message, data.type);
        } catch (e) {
            console.error('Error parsing notification:', e);
        }
    };

    evtSource.onerror = function(err) {
        console.log("EventSource failed:", err);
        // Lidhja do të provojë të rilidhet automatikisht nga browseri
    };
});

function showNotification(title, message, type = 'info') {
    const container = document.getElementById('notification-container');
    
    const notif = document.createElement('div');
    
    // Ngjyrat sipas tipit
    let bgColors = {
        'success': 'linear-gradient(135deg, #10B981, #059669)',
        'error': 'linear-gradient(135deg, #EF4444, #DC2626)',
        'warning': 'linear-gradient(135deg, #F59E0B, #D97706)',
        'info': 'linear-gradient(135deg, #3B82F6, #2563EB)'
    };
    
    let icons = {
        'success': '✓',
        'error': '✕',
        'warning': '⚠',
        'info': 'ℹ'
    };

    const bg = bgColors[type] || bgColors['info'];
    const icon = icons[type] || icons['info'];

    notif.style.cssText = `
        background: ${bg};
        color: white;
        padding: 16px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: flex-start;
        gap: 12px;
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-family: 'Montserrat', sans-serif;
        overflow: hidden;
        position: relative;
    `;

    notif.innerHTML = `
        <div style="font-size: 1.2rem; font-weight: bold;">${icon}</div>
        <div>
            <div style="font-weight: 700; margin-bottom: 4px;">${title}</div>
            <div style="font-size: 0.9rem; opacity: 0.95; line-height: 1.4;">${message}</div>
        </div>
        <button onclick="this.parentElement.remove()" style="
            background: none; 
            border: none; 
            color: white; 
            opacity: 0.7; 
            cursor: pointer; 
            font-size: 1.2rem;
            padding: 0;
            margin-left: auto;
        ">×</button>
    `;

    container.appendChild(notif);

    // Animate in
    requestAnimationFrame(() => {
        notif.style.transform = 'translateX(0)';
    });

    // Auto remove after 5 seconds
    setTimeout(() => {
        notif.style.transform = 'translateX(120%)';
        setTimeout(() => notif.remove(), 300);
    }, 6000);
    
    // Play sound
    try {
        const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
        audio.volume = 0.5;
        audio.play().catch(e => console.log('Audio play failed (user interaction needed)'));
    } catch (e) {}
}
