// Video call notification system
const notificationSound = new Audio('/noteria/assets/sounds/call-notification.mp3');
let soundPlaying = false;
let callCheckInterval;

// Funksioni për të kontrolluar nëse ka thirrje të reja
function checkForNewCalls() {
  fetch('check_new_calls.php')
    .then(response => response.json())
    .then(data => {
      if (data.hasNewCalls) {
        // Nëse ka thirrje të reja dhe tingulli nuk është duke u luajtur, atëherë luaj zilen
        if (!soundPlaying) {
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
}

// Funksioni për të luajtur tingullin e ziles
function playNotificationSound() {
  soundPlaying = true;
  notificationSound.loop = true;
  notificationSound.play().catch(error => {
    console.error('Gabim gjatë luajtjes së tingullit:', error);
  });
  
  // Trillo për 10 sekonda dhe pastaj ndalo
  setTimeout(() => {
    if (soundPlaying) {
      stopNotificationSound();
      // Pas 30 sekondave kontrollo përsëri
      setTimeout(() => {
        checkForNewCalls();
      }, 30000);
    }
  }, 10000);
}

// Funksioni për të ndaluar tingullin e ziles
function stopNotificationSound() {
  soundPlaying = false;
  notificationSound.pause();
  notificationSound.currentTime = 0;
}

// Funksioni për të shfaqur njoftimin për thirrje
function showCallNotification(callerName) {
  // Krijojmë elementin e njoftimit
  const notification = document.createElement('div');
  notification.className = 'call-notification';
  notification.innerHTML = `
    <div class="call-notification-header">
      <i class="fas fa-phone-alt"></i> Thirrje e re
      <button class="close-notification">&times;</button>
    </div>
    <div class="call-notification-body">
      <p>${callerName || 'Një klient'} po ju thërret!</p>
      <div class="call-actions">
        <button class="answer-call">Përgjigju</button>
        <button class="decline-call">Refuzo</button>
      </div>
    </div>
  `;
  
  // Shtojmë stilet
  const style = document.createElement('style');
  style.textContent = `
    .call-notification {
      position: fixed;
      top: 20px;
      right: 20px;
      width: 300px;
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      z-index: 1000;
      animation: slideIn 0.3s forwards;
      overflow: hidden;
    }
    
    .call-notification-header {
      background-color: #1a56db;
      color: white;
      padding: 12px 15px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-weight: bold;
    }
    
    .call-notification-header i {
      animation: ring 1s infinite;
    }
    
    .close-notification {
      background: none;
      border: none;
      color: white;
      font-size: 20px;
      cursor: pointer;
    }
    
    .call-notification-body {
      padding: 15px;
    }
    
    .call-actions {
      display: flex;
      justify-content: space-between;
      margin-top: 15px;
    }
    
    .answer-call, .decline-call {
      padding: 8px 15px;
      border: none;
      border-radius: 5px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .answer-call {
      background-color: #059669;
      color: white;
    }
    
    .decline-call {
      background-color: #ef4444;
      color: white;
    }
    
    .answer-call:hover, .decline-call:hover {
      transform: scale(1.05);
    }
    
    @keyframes ring {
      0% { transform: rotate(-15deg); }
      50% { transform: rotate(15deg); }
      100% { transform: rotate(-15deg); }
    }
    
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
  `;
  document.head.appendChild(style);
  document.body.appendChild(notification);
  
  // Shtojmë event listeners
  notification.querySelector('.close-notification').addEventListener('click', () => {
    notification.remove();
    stopNotificationSound();
  });
  
  notification.querySelector('.answer-call').addEventListener('click', () => {
    // Hap faqen e thirrjes
    window.open('video_call.php?room=noteria_' + notificationData.userId, '_blank');
    notification.remove();
    stopNotificationSound();
  });
  
  notification.querySelector('.decline-call').addEventListener('click', () => {
    // Refuzo thirrjen duke njoftuar serverin
    fetch('decline_call.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ userId: notificationData.userId }),
    }).then(() => {
      notification.remove();
      stopNotificationSound();
    });
  });
  
  // Fshij njoftimin pas 30 sekondave nëse nuk ka përgjigje
  setTimeout(() => {
    if (document.body.contains(notification)) {
      notification.remove();
    }
  }, 30000);
}

// Fillo kontrollin për thirrje të reja çdo 15 sekonda kur faqja është e hapur
document.addEventListener('DOMContentLoaded', () => {
  // Kontrollo menjëherë në fillim
  checkForNewCalls();
  
  // Vendos intervalin për kontrolle të rregullta
  callCheckInterval = setInterval(checkForNewCalls, 15000);
  
  // Pastro intervalin kur faqja mbyllet
  window.addEventListener('beforeunload', () => {
    clearInterval(callCheckInterval);
  });
});

// Shto funksionalitetin për të lejuar noterin të aktivizojë/çaktivizojë tingullin
function toggleNotificationSound() {
  const soundToggle = document.getElementById('notification-sound-toggle');
  if (soundToggle.dataset.enabled === 'true') {
    soundToggle.dataset.enabled = 'false';
    soundToggle.innerHTML = '<i class="fas fa-volume-mute"></i> Zilja e joaktivizuar';
    soundToggle.classList.remove('sound-enabled');
    soundToggle.classList.add('sound-disabled');
    localStorage.setItem('notificationSoundEnabled', 'false');
  } else {
    soundToggle.dataset.enabled = 'true';
    soundToggle.innerHTML = '<i class="fas fa-volume-up"></i> Zilja e aktivizuar';
    soundToggle.classList.remove('sound-disabled');
    soundToggle.classList.add('sound-enabled');
    localStorage.setItem('notificationSoundEnabled', 'true');
    
    // Testo tingullin një herë për të konfirmuar që funksionon
    const testSound = new Audio('/noteria/assets/sounds/call-notification.mp3');
    testSound.play().then(() => {
      setTimeout(() => {
        testSound.pause();
        testSound.currentTime = 0;
      }, 1500);
    }).catch(error => {
      console.error('Gabim gjatë testimit të tingullit:', error);
    });
  }
}

// Kontrollo statusin e tingullit nga localStorage kur faqja hapet
document.addEventListener('DOMContentLoaded', () => {
  const soundEnabled = localStorage.getItem('notificationSoundEnabled') !== 'false';
  const soundToggle = document.getElementById('notification-sound-toggle');
  
  if (soundToggle) {
    soundToggle.dataset.enabled = soundEnabled ? 'true' : 'false';
    soundToggle.innerHTML = soundEnabled ? 
      '<i class="fas fa-volume-up"></i> Zilja e aktivizuar' : 
      '<i class="fas fa-volume-mute"></i> Zilja e joaktivizuar';
    soundToggle.classList.add(soundEnabled ? 'sound-enabled' : 'sound-disabled');
  }
});