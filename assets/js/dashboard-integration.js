// Integrimi i kodit për njoftimet e thirrjeve video në faqen e dashboard-it

// Pasi të ngarkohet faqja, shtojmë butonin për kontrollin e ziles
document.addEventListener('DOMContentLoaded', function() {
    // Shtojmë butonin e kontrollit të ziles në header
    const headerElement = document.querySelector('.dashboard-header') || document.querySelector('header');
    
    if (headerElement) {
        // Krijojmë butonin
        const soundToggleButton = document.createElement('button');
        soundToggleButton.id = 'notification-sound-toggle';
        soundToggleButton.className = 'notification-sound-toggle sound-enabled';
        soundToggleButton.dataset.enabled = 'true';
        soundToggleButton.innerHTML = '<i class="fas fa-volume-up"></i> Zilja e aktivizuar';
        soundToggleButton.onclick = toggleNotificationSound;
        
        // Kontrollojmë nëse ekziston kontejneri i profilit ose krijojmë një të ri
        const userProfileContainer = headerElement.querySelector('.user-profile') || 
                                    headerElement.querySelector('.header-actions');
        
        if (userProfileContainer) {
            userProfileContainer.insertBefore(soundToggleButton, userProfileContainer.firstChild);
        } else {
            // Nëse nuk ekziston ndonjë kontejner, e shtojmë direkt në header
            headerElement.appendChild(soundToggleButton);
        }
    }
    
    // Kontrollojmë nëse statusi i tingullit është i ruajtur në localStorage
    const soundEnabled = localStorage.getItem('notificationSoundEnabled') !== 'false';
    const soundToggle = document.getElementById('notification-sound-toggle');
    
    if (soundToggle) {
        soundToggle.dataset.enabled = soundEnabled ? 'true' : 'false';
        soundToggle.innerHTML = soundEnabled ? 
            '<i class="fas fa-volume-up"></i> Zilja e aktivizuar' : 
            '<i class="fas fa-volume-mute"></i> Zilja e joaktivizuar';
        soundToggle.className = 'notification-sound-toggle ' + (soundEnabled ? 'sound-enabled' : 'sound-disabled');
    }
    
    // Shto ikonën e thirrjeve video në sidebar menu nëse ekziston
    const sidebarMenu = document.querySelector('.dashboard-sidebar ul') || 
                       document.querySelector('.sidebar-menu');
    
    if (sidebarMenu) {
        const videoChatMenuItem = document.createElement('li');
        videoChatMenuItem.className = 'sidebar-item';
        videoChatMenuItem.innerHTML = `
            <a href="video_calls.php" class="sidebar-link">
                <i class="fas fa-video"></i>
                <span>Video Thirrjet</span>
                <span class="calls-badge" id="new-calls-badge" style="display: none;">0</span>
            </a>
        `;
        
        sidebarMenu.appendChild(videoChatMenuItem);
    }
});

// Funksion për të përditësuar badge-in e thirrjeve të reja
function updateCallsBadge(count) {
    const badge = document.getElementById('new-calls-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Modifikojmë funksionin checkForNewCalls për të përditësuar badge-in
const originalCheckForNewCalls = window.checkForNewCalls;
window.checkForNewCalls = function() {
    fetch('check_new_calls.php')
        .then(response => response.json())
        .then(data => {
            if (data.hasNewCalls) {
                // Përditësojmë badge-in
                updateCallsBadge(1);
                
                // Nëse ka thirrje të reja dhe tingulli nuk është duke u luajtur, atëherë luaj zilen
                if (document.getElementById('notification-sound-toggle')?.dataset.enabled === 'true' && !soundPlaying) {
                    playNotificationSound();
                    showCallNotification(data.callerName);
                }
            } else {
                // Nëse nuk ka thirrje të reja, ndalo tingullin nëse është duke u luajtur
                if (soundPlaying) {
                    stopNotificationSound();
                }
            }
        })
        .catch(error => {
            console.error('Gabim gjatë kontrollit për thirrje të reja:', error);
        });
};