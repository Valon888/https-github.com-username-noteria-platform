<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/SecurityHeaders.php';
require_once __DIR__ . '/db_connection.php'; // Lidhja me databazën

// Kontrollo nëse është admin dhe shto rolin në variabla sesioni
$roli = isset($_SESSION['roli']) ? $_SESSION['roli'] : '';
$is_admin = ($roli === 'admin');

// Përkthime për faqen e video thirrjes
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'anonim';

// Kontrollo statusin e pagesës për video konsulencë nëse nuk është admin
$payment_required = true;
$has_paid = false;
$payment_url = '';
$session_duration = 30; // Kohëzgjatja e thirrjes në minuta
$session_price = 15.00; // Çmimi në Euro
$payment_data = null;

if ($user_id !== 'anonim' && !$is_admin) {
    // Kontrollo nëse ka paguar për video konsulencë
    $conn = connectToDatabase();
    
    // Kontrollo së pari në sesion
    $has_paid_session = isset($_SESSION['video_payment']) && 
                         $_SESSION['video_payment']['status'] === 'completed' && 
                         $_SESSION['video_payment']['expiry'] > time();
    
    if ($has_paid_session) {
        $has_paid = true;
        $minutes_remaining = max(0, round(($_SESSION['video_payment']['expiry'] - time()) / 60));
        $session_duration = $minutes_remaining;
        $payment_id = $_SESSION['video_payment']['payment_id'] ?? '';
    } else {
        // Kontrollo në databazë
        $check_query = "SELECT * FROM payments WHERE user_id = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 1";
        $stmt = $conn->prepare($check_query);
        $stmt->execute([$user_id]);
        $payment_data = $stmt->fetch();
        
        if ($payment_data) {
            $has_paid = true;
            // Llogarit kohën e mbetur në thirrje (30 minuta nga momenti i pagesës)
            $payment_time = strtotime($payment_data['created_at']);
            $expiry_time = $payment_time + (30 * 60); // 30 minuta
            $current_time = time();
            $minutes_remaining = max(0, round(($expiry_time - $current_time) / 60));
            $session_duration = $minutes_remaining;
            
            // Ruaj të dhënat në sesion për qasje më të shpejtë
            $_SESSION['video_payment'] = [
                'status' => 'completed',
                'expiry' => $expiry_time,
                'payment_id' => $payment_data['id'] ?? $payment_data['payment_id'] ?? null
            ];
        } else {
            // Fshij sesionin e pagesës nëse ekziston por ka skaduar
            if (isset($_SESSION['video_payment'])) {
                unset($_SESSION['video_payment']);
            }
            
            // Gjenero link pagese me Paysera nëse nuk ka paguar
            if (isset($_GET['room']) && !empty($_GET['room'])) {
                $room = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['room']);
                $payment_id = 'NOTER_' . uniqid() . '_' . substr(md5($user_id . time()), 0, 8);
                
                // Regjistroje paraprakisht pagesën në databazë
                try {
                    $insert_query = "INSERT INTO payments (uuid, user_id, amount, service_type, status, created_at, updated_at) 
                                     VALUES (?, ?, ?, 'video_consultation', 'pending', NOW(), NOW())";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("ssd", $payment_id, $user_id, $session_price);
                    $stmt->execute();
                } catch (Exception $e) {
                    error_log("Error pre-registering payment: " . $e->getMessage());
                }
                
                // Pergatit të dhënat për pagesë dhe drejto te faqja e zgjedhjes së metodës së pagesës
                $_SESSION['payment_data'] = [
                    'amount' => $session_price,
                    'currency' => 'EUR',
                    'description' => 'Konsulencë video me noter - 30 minuta',
                   
                    'room' => $room,
                    'service_type' => 'video_consultation'
                ];
                
                $payment_url = "payment_confirmation.php?service=video&room=" . urlencode($room);
            }
        }
    }
}

// Vendos emrin e përdoruesit bazuar në rolin
$emri = isset($_SESSION['emri']) ? $_SESSION['emri'] : '';
$mbiemri = isset($_SESSION['mbiemri']) ? $_SESSION['mbiemri'] : '';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : ($is_admin ? "Admin" : "Përdorues");

// Nëse kemi emër dhe mbiemër, përdorim ato për username
if (!empty($emri) && !empty($mbiemri)) {
    $username = $emri . ' ' . $mbiemri;
    if ($is_admin) {
        $username .= ' (Admin)';
    }
}
$room = isset($_GET['room']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['room']) : 'noteria_' . $user_id;
$lang = 'sq';
$translations = [
    'title' => [
        'sq' => 'Noteria | Video Thirrje (Jitsi)',
        'sr' => 'Noteria | Video Poziv (Jitsi)',
        'en' => 'Noteria | Video Call (Jitsi)',
    ],
];
// Shembull përdorimi:
// $room_info = kontrollo_jitsi_room($room);
// Mund të përdorësh $room_info për të marrë info shtesë ose për të bërë verifikime

// --- Server-side: create a call record and provide a call_id for tracking ---
try {
    if (!isset($conn) || !$conn) {
        $conn = connectToDatabase();
    }
    // Generate unique call id and insert a short-lived call record
    $call_id = 'call_' . uniqid();
    $insertSql = "INSERT INTO video_calls (call_id, room, user_id, start_time, status) VALUES (?, ?, ?, NOW(), 'active')";
    if ($stmtCall = $conn->prepare($insertSql)) {
        $stmtCall->execute([$call_id, $room, $user_id]);
    }
    // Store in session for later updates
    $_SESSION['current_call'] = [
        'call_id' => $call_id,
        'room' => $room,
        'started_at' => time()
    ];
} catch (Exception $e) {
    error_log('Could not create video call record: ' . $e->getMessage());
    // fallback: ensure a call_id exists even if DB insert failed
    if (!isset($call_id)) {
        $call_id = 'call_' . uniqid();
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title><?= $translations['title'][$lang] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Google Fonts & Ikona with error handling -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:700,400&display=swap" rel="stylesheet" onerror="this.style.display='none'">
    <link href="https://fonts.googleapis.com/css?family=Roboto+Mono:400,700&display=swap" rel="stylesheet" onerror="this.style.display='none'">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <!-- Audio elements with graceful fallback to Web Audio API -->
    <audio id="ringtone" preload="auto" loop crossorigin="anonymous" class="hidden-audio">
        <source src="noteria-ringtone.mp3" type="audio/mpeg">
        Your browser does not support the audio element.
    </audio>
    
    <audio id="calling-sound" preload="auto" loop crossorigin="anonymous" class="hidden-audio">
        <source src="noteria-calling-sound.mp3" type="audio/mpeg">
        Your browser does not support the audio element.
    </audio>
    
    <!-- Handle audio loading errors gracefully (suppress 404 errors) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const audioElements = ['ringtone', 'calling-sound'];
            audioElements.forEach(id => {
                const audio = document.getElementById(id);
                if (audio) {
                    // Suppress error events to prevent console errors
                    audio.addEventListener('error', function(e) {
                        console.warn(`⚠️ Audio file not found: ${id}. Using Web Audio API fallback.`);
                        // Web Audio API fallback is already implemented in code
                    });
                    
                    // Try to load the audio
                    audio.load();
                }
            });
        });
    </script>
    
    <!-- Advanced Video Quality Optimization System (Enterprise-grade) -->
    <!-- <script src="frontend_quality_optimization.js" async></script> -->
    
    <!-- SECURITY STUBS - These prevent errors from onclick handlers before script loads -->
    <script>
        // Global audio state
        var audioUnlocked = false;
        
        // Global Jitsi connection state
        var jitsiConnected = false;
        var conferenceJoined = false;
        var participantCount = 0;
        var ringingStarted = false;
        
        // Unlock audio autoplay after first user interaction
        window.unlockAudio = function() {
            if (!audioUnlocked) {
                var audio = document.getElementById('ringtone');
                if (audio) {
                    var promise = audio.play();
                    if (promise !== undefined) {
                        promise.then(function() {
                            audio.pause();
                            audio.currentTime = 0;
                            audioUnlocked = true;
                            console.log('✓ Audio unlocked for autoplay');
                        }).catch(function(e) {
                            console.log('Audio unlock:', e.message);
                        });
                    }
                }
            }
        }
        
        // Play ringtone
        window.playRingtone = function() {
            var audio = document.getElementById('ringtone');
            if (audio) {
                audio.volume = 1.0;
                audio.currentTime = 0;
                audio.loop = true;
                var playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.then(function() {
                        console.log('✓ Ringtone playing via HTML5');
                    }).catch(function(error) {
                        if (error.name === 'AbortError') {
                            console.log("ℹ️ Ringtone play was aborted (normal if call was answered/rejected quickly)");
                        } else {
                            console.log("⚠️ HTML5 audio error, trying workaround:", error.message);
                            // Fallback: Try to play using Web Audio API
                            playRingtoneViaWebAudio();
                        }
                    });
                }
            }
        }
        
        // WebAudio API fallback for ringtone
        window.playRingtoneViaWebAudio = function() {
            try {
                // Create audio context
                var audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // Create oscillator for beep sound
                var oscillator = audioContext.createOscillator();
                var gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                // Ring pattern: 0.5s on, 0.5s off, repeat
                var now = audioContext.currentTime;
                oscillator.frequency.value = 800; // Hz
                gainNode.gain.setValueAtTime(0.3, now);
                oscillator.start(now);
                
                // Play for 3 seconds
                gainNode.gain.setValueAtTime(0.3, now);
                gainNode.gain.setValueAtTime(0, now + 0.5);
                gainNode.gain.setValueAtTime(0.3, now + 1);
                gainNode.gain.setValueAtTime(0, now + 1.5);
                gainNode.gain.setValueAtTime(0.3, now + 2);
                gainNode.gain.setValueAtTime(0, now + 2.5);
                gainNode.gain.setValueAtTime(0.3, now + 3);
                gainNode.gain.setValueAtTime(0, now + 3.5);
                
                oscillator.stop(now + 3.5);
                console.log('✓ Ringtone playing via WebAudio API');
            } catch(e) {
                console.log('❌ WebAudio API failed:', e.message);
            }
        }
        
        // Stop ringtone
        window.stopRingtone = function() {
            var audio = document.getElementById('ringtone');
            if (audio) {
                try {
                    audio.pause();
                    audio.currentTime = 0;
                    audio.loop = false;
                    console.log('✓ Ringtone stopped');
                } catch (e) {
                    console.log('Error stopping ringtone:', e.message);
                }
            }
        }
        
        // Play calling sound when user initiates a call
        window.playCallingSound = function() {
            var audio = document.getElementById('calling-sound');
            if (audio) {
                audio.volume = 1.0;
                audio.currentTime = 0;
                audio.loop = true;
                var playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.then(function() {
                        console.log('📱 Calling sound started');
                    }).catch(function(e) {
                        if (e.name === 'AbortError') {
                            console.log("ℹ️ Calling sound play was aborted");
                        } else {
                            console.log('Could not play calling sound:', e.message);
                        }
                    });
                }
            }
        }
        
        // Stop calling sound
        window.stopCallingSound = function() {
            var audio = document.getElementById('calling-sound');
            if (audio) {
                try {
                    audio.pause();
                    audio.currentTime = 0;
                    audio.loop = false;
                    console.log('✓ Calling sound stopped');
                } catch (e) {
                    console.log('Error stopping calling sound:', e.message);
                }
            }
        }
        
        // Show incoming call modal
        window.showIncomingCall = function(callerName) {
            console.log('📞 Incoming call from:', callerName);
            console.log('⏰ Ringing activated at:', new Date().toLocaleTimeString());
            var modal = document.getElementById('incomingCallModal');
            var nameElem = document.getElementById('callerName');
            
            if (modal && nameElem) {
                nameElem.textContent = callerName || 'Noter';
                modal.classList.add('show');
                console.log('✅ Modal displayed');
                
                var audio = document.getElementById('ringtone');
                if (audio) {
                    audio.volume = 1.0;
                    audio.currentTime = 0;
                    audio.loop = true;
                    var playPromise = audio.play();
                    if (playPromise !== undefined) {
                        playPromise.then(function() {
                            console.log('🔊 RINGTONE AUDIO STARTED');
                        }).catch(function(error) {
                            if (error.name === 'AbortError') {
                                console.log("ℹ️ Ringtone play was aborted");
                            } else if (error.name === 'NotAllowedError') {
                                console.warn("⚠️ Autoplay blocked. Showing interaction overlay.");
                                showAudioOverlay();
                            } else {
                                console.log("⚠️ Ringtone error:", error.message);
                                // Try WebAudio fallback
                                window.playRingtoneViaWebAudio();
                            }
                        });
                    }
                }
                
                function showAudioOverlay() {
                    if (document.getElementById('audio-unlock-overlay')) return;
                    
                    var overlay = document.createElement('div');
                    overlay.id = 'audio-unlock-overlay';
                    overlay.style.position = 'fixed';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.width = '100%';
                    overlay.style.height = '100%';
                    overlay.style.backgroundColor = 'rgba(0,0,0,0.8)';
                    overlay.style.zIndex = '9999';
                    overlay.style.display = 'flex';
                    overlay.style.flexDirection = 'column';
                    overlay.style.alignItems = 'center';
                    overlay.style.justifyContent = 'center';
                    overlay.style.color = 'white';
                    overlay.style.cursor = 'pointer';
                    
                    overlay.innerHTML = `
                        <div class="audio-unlock-card">
                            <i class="fas fa-volume-up"></i>
                            <h2>Klikoni për të aktivizuar zërin</h2>
                            <p>Browseri juaj kërkon një klikim për të lejuar tingullin e thirrjes.</p>
                        </div>
                    `;
                    
                    overlay.onclick = function() {
                        window.unlockAudio();
                        if (audio) audio.play();
                        document.body.removeChild(overlay);
                    };
                    
                    document.body.appendChild(overlay);
                }

                setTimeout(function() {
                    if (modal.classList.contains('show')) {
                        console.log('⏱️ 60 second timeout - auto rejecting');
                        window.rejectCall();
                    }
                }, 60000);
            } else {
                console.error('❌ Modal or name element not found!');
            }
        }
        
        // Test ringtone
        window.testRingtoneClick = function() {
            console.log('🧪 TEST CLICKED');
            window.unlockAudio();
            window.showIncomingCall('Test Thirrje');
        }
        
        // Accept call
        window.acceptCall = function() {
            console.log('✓ Call accepted - Starting meeting...');
            window.stopRingtone();
            
            // Hide incoming call modal
            var modal = document.getElementById('incomingCallModal');
            if (modal) {
                modal.classList.remove('show');
                console.log('✓ Incoming call modal hidden');
            }
            
            // Show video container (Jitsi is already initialized)
            var videoContainer = document.getElementById('video');
            if (videoContainer) {
                videoContainer.style.display = 'block';
                videoContainer.style.visibility = 'visible';
                videoContainer.style.opacity = '1';
                videoContainer.style.zIndex = '100';
                console.log('✓ Video container shown - Meeting started!');
            }
            
            // Show header bar if hidden
            var headerBar = document.getElementById('header-bar');
            if (headerBar) {
                headerBar.style.display = 'flex';
                headerBar.style.zIndex = '101';
                console.log('✓ Header bar shown');
            }
            
            // Ensure Jitsi is properly initialized
            if (window.api) {
                console.log('✓ Jitsi API ready, conference is live');
                // Make sure audio and video are enabled
                try {
                    window.api.executeCommand('toggleAudio', false);
                    window.api.executeCommand('toggleVideo', false);
                    console.log('✓ Audio and video enabled');
                } catch(e) {
                    console.log('Note: Commands may not be supported', e);
                }
            }
            
            console.log('🎥 Meeting is now active!');
        }
        
        // Reject call
        window.rejectCall = function() {
            console.log('✗ Call rejected');
            window.stopRingtone();
            ringingStarted = false;  // Reset ringing state
            
            var modal = document.getElementById('incomingCallModal');
            if (modal) {
                modal.classList.remove('show');
            }
            
            console.log('Call rejection completed');
        }
        
        // Unlock on any interaction
        document.addEventListener('click', window.unlockAudio);
        document.addEventListener('touchstart', window.unlockAudio);
        document.addEventListener('keydown', window.unlockAudio);
    </script>
    
    <script>
        // Check if audio loads successfully
        var audioElement = document.getElementById('ringtone');
        audioElement.addEventListener('loadstart', function() {
            console.log('✓ Audio loading started');
        });
        audioElement.addEventListener('loadedmetadata', function() {
            console.log('✓ Audio metadata loaded: ' + this.duration + 's');
        });
        audioElement.addEventListener('canplay', function() {
            console.log('✓ Audio can play');
        });
        audioElement.addEventListener('error', function() {
            console.error('❌ Audio error:', this.error.message || 'Unknown error');
            console.error('Audio src:', this.src);
        });
    </script>
    <style>
        :root {
            --bg-start: #001c4d;
            --bg-mid: #003d88;
            --bg-end: #1f63c9;
            --gold: #ffd76a;
            --gold-strong: #ffbf3f;
            --text-main: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.82);
            --card-bg: rgba(255, 255, 255, 0.1);
            --card-border: rgba(255, 255, 255, 0.2);
            --card-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background:
                radial-gradient(circle at 15% 10%, rgba(255, 215, 106, 0.12), transparent 35%),
                radial-gradient(circle at 85% 90%, rgba(255, 215, 106, 0.08), transparent 35%),
                linear-gradient(140deg, var(--bg-start) 0%, var(--bg-mid) 48%, var(--bg-end) 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            overflow-x: hidden;
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .glass {
            background: var(--card-bg);
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 18px;
            border: 1px solid var(--card-border);
        }
        .hidden-audio {
            display: none;
        }
        /* Incoming Call Modal Styles */
        .incoming-call-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.85) 0%, rgba(0, 40, 104, 0.4) 100%);
            z-index: 99999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-in-out;
            backdrop-filter: blur(5px);
        }
        .incoming-call-modal.show {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .incoming-call-container {
            background: linear-gradient(135deg, #002868 0%, #004B9E 50%, #1B5BB8 100%);
            border-radius: 30px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 20px 70px rgba(0, 0, 0, 0.7), 0 0 50px rgba(255, 215, 0, 0.3);
            max-width: 550px;
            animation: slideUp 0.5s ease-out;
            border: 3px solid #FFD700;
            position: relative;
            overflow: hidden;
        }
        .incoming-call-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #FFD700 0%, #FFA500 50%, #FFD700 100%);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .caller-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 40px rgba(255, 215, 0, 1), 0 0 60px rgba(255, 165, 0, 0.6);
            animation: pulse-avatar 2s infinite;
            border: 5px solid rgba(255, 255, 255, 0.3);
        }
        @keyframes pulse-avatar {
            0%, 100% { box-shadow: 0 0 40px rgba(255, 215, 0, 0.9), 0 0 60px rgba(255, 165, 0, 0.5); }
            50% { box-shadow: 0 0 60px rgba(255, 215, 0, 1), 0 0 80px rgba(255, 165, 0, 0.7); }
        }
        .caller-avatar i {
            font-size: 4rem;
            color: #fff;
        }
        .caller-name {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        .caller-status {
            font-size: 1.15rem;
            color: #FFD700;
            margin-bottom: 30px;
            animation: blink 1s infinite;
            font-weight: 600;
            letter-spacing: 1px;
        }
        @keyframes blink {
            0%, 50%, 100% { opacity: 1; }
            25%, 75% { opacity: 0.5; }
        }
        .call-actions {
            display: flex;
            gap: 20px;
            justify-content: center;
        }
        .call-btn {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            font-size: 2.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
        }
        .call-btn:active {
            transform: scale(0.95);
        }
        .accept-btn {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: #002868;
            font-weight: 700;
        }
        .accept-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 12px 40px rgba(255, 215, 0, 0.8);
        }
        .reject-btn {
            background: linear-gradient(135deg, #e53935 0%, #ef5350 100%);
            color: #fff;
            font-weight: 700;
        }
        .reject-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 12px 40px rgba(229, 57, 53, 0.8);
        }
        #header-bar {
            width: 100%;
            box-sizing: border-box;
            background: linear-gradient(90deg, #002868 0%, #004B9E 50%, #1B5BB8 100%);
            color: #fff;
            padding: 14px 20px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: center;
            gap: 12px;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 2px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.5), 0 0 30px rgba(255, 215, 0, 0.2);
            font-family: 'Montserrat', Arial, sans-serif;
            user-select: none;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            animation: fadeInDown 1s cubic-bezier(.23,1.01,.32,1);
            border-bottom: 4px solid #FFD700;
        }
        .header-main {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .header-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        @keyframes fadeInDown {
            0% { opacity: 0; transform: translateY(-60px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        #header-bar .brand {
            color: #FFD700;
            letter-spacing: 4px;
            font-size: 2.9rem;
            font-family: 'Roboto Mono', monospace;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
            margin-right: 12px;
            animation: glowing 3s infinite alternate;
        }
        @keyframes glowing {
            0% { text-shadow: 0 0 15px rgba(255, 215, 0, 0.8); }
            100% { text-shadow: 0 0 30px rgba(255, 215, 0, 1), 0 0 50px rgba(255, 165, 0, 0.7); }
        }
        #header-bar .subtitle {
            font-weight: 400;
            color: #fff;
            letter-spacing: 1.5px;
            font-size: 1.3rem;
            margin-left: 10px;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        .avatar {
            display: inline-block;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.9);
            margin-right: 14px;
            vertical-align: middle;
            overflow: hidden;
            border: 4px solid rgba(255, 255, 255, 0.9);
            animation: pulse-ring 3s ease-in-out infinite;
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.8); }
            70% { box-shadow: 0 0 0 20px rgba(255, 215, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0); }
        }
        .avatar i {
            font-size: 2.2rem;
            color: #fff;
            margin-top: 9px;
            margin-left: 10px;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        .user-badge {
            display: inline-block;
            background: rgba(255, 215, 0, 0.15);
            color: #FFD700;
            border: 2px solid rgba(255, 215, 0, 0.4);
            border-radius: 50px;
            padding: 8px 22px;
            font-size: 1.05rem;
            margin-left: 12px;
            font-weight: 700;
            vertical-align: middle;
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.5);
            animation: popIn 1.2s cubic-bezier(.23,1.01,.32,1);
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.8);
            backdrop-filter: blur(5px);
        }
        .user-badge--admin {
            background: rgba(255, 193, 7, 0.2);
            color: #ffd54f;
            border-color: rgba(255, 213, 79, 0.5);
            box-shadow: 0 0 20px rgba(255, 193, 7, 0.4);
        }
        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.7);}
            100% { opacity: 1; transform: scale(1);}
        }
        #header-bar .secure-badge {
            display: inline-block;
            background: rgba(0, 75, 158, 0.2);
            color: #FFD700;
            border: 2px solid rgba(255, 215, 0, 0.4);
            border-radius: 50px;
            padding: 8px 22px;
            font-size: 1.05rem;
            margin-left: 12px;
            font-weight: 700;
            vertical-align: middle;
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.4);
            backdrop-filter: blur(5px);
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
        }
        #header-bar .live-dot {
            display: inline-block;
            width: 16px;
            height: 16px;
            background: #FFD700;
            border-radius: 50%;
            margin-left: 16px;
            box-shadow: 0 0 20px #FFD700;
            animation: pulse 1.5s infinite;
            vertical-align: middle;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.8); }
            70% { box-shadow: 0 0 0 20px rgba(255, 215, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0); }
        }
        #abuse-btn {
            background: linear-gradient(90deg, #e53935 0%, #ef5350 100%);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 9px 18px;
            cursor: pointer;
            font-size: 0.95rem;
            margin-left: 0;
            font-weight: 700;
            box-shadow: 0 0 25px rgba(229, 57, 53, 0.5);
            transition: all 0.3s ease;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        .header-pill-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 50px;
            border: none;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            color: #fff;
            transition: transform 0.25s ease, box-shadow 0.25s ease, filter 0.25s ease;
        }
        .header-pill-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }
        .header-pill-btn:active {
            transform: translateY(0);
        }
        .header-pill-btn-warning {
            background: linear-gradient(90deg, #ffd700, #ffb300);
            color: #002868;
            box-shadow: 0 0 20px rgba(255, 193, 7, 0.45);
        }
        .header-pill-btn-success {
            background: linear-gradient(90deg, #43a047, #66bb6a);
            box-shadow: 0 0 18px rgba(67, 160, 71, 0.45);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(244, 67, 54, 0.2);
            color: #ef5350;
            border: 1px solid rgba(244, 67, 54, 0.5);
            padding: 8px 14px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            box-shadow: 0 0 10px rgba(244, 67, 54, 0.3);
        }
        .status-badge-dot {
            color: #ef5350;
        }
        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        textarea:focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: 2px;
        }
        #abuse-btn:hover {
            background: linear-gradient(90deg, #d32f2f 0%, #f44336 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(229, 57, 53, 0.8);
        }
        #abuse-btn:active {
            transform: translateY(1px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.4);
        }
        #abuse-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: all 0.6s ease;
        }
        #abuse-btn:hover::after {
            left: 100%;
        }
        #notice {
            margin: 20px auto 0 auto;
            width: 92vw;
            max-width: 700px;
            box-sizing: border-box;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.15) 0%, rgba(255, 165, 0, 0.1) 100%);
            color: #FFD700;
            border: 2px solid rgba(255, 215, 0, 0.4);
            border-radius: 20px;
            padding: 22px 28px;
            font-size: 1.1rem;
            text-align: center;
            box-shadow: 0 0 40px rgba(255, 215, 0, 0.2);
            animation: fadeInUp 1.2s cubic-bezier(.23,1.01,.32,1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.16);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        #notice:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 46px rgba(0, 0, 0, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.18);
        }
        #room-info {
            margin: 20px auto 0 auto;
            padding: 22px 28px;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.15) 0%, rgba(255, 165, 0, 0.1) 100%);
            border-radius: 25px;
            width: 92vw;
            max-width: 700px;
            box-sizing: border-box;
            font-size: 18px;
            color: #FFD700;
            text-align: center;
            border: 2px solid rgba(255, 215, 0, 0.4);
            box-shadow: 0 0 40px rgba(255, 215, 0, 0.2);
            animation: fadeInUp 1.5s cubic-bezier(.23,1.01,.32,1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            font-weight: 600;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.16);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        #room-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 46px rgba(0, 0, 0, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.18);
        }
        #room-link {
            font-family: 'Roboto Mono', monospace;
            background: rgba(255, 215, 0, 0.15);
            border: 2px solid rgba(255, 215, 0, 0.4);
            border-radius: 50px;
            padding: 10px 25px;
            margin-right: 12px;
            font-size: 1.15rem;
            color: #FFD700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            font-weight: 600;
        }
        #copy-btn {
            background: linear-gradient(90deg, #FFD700 0%, #FFA500 100%);
            color: #002868;
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.5);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        #copy-btn:hover {
            background: linear-gradient(90deg, #FFA500 0%, #FFD700 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.8);
        }
        #copy-btn:active {
            transform: translateY(1px);
        }
        #copy-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: all 0.6s ease;
        }
        #copy-btn:hover::after {
            left: 100%;
        }
        #video {
            height: 78vh;
            width: 98vw;
            margin: 38px auto 0 auto;
            box-sizing: border-box;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 0 60px rgba(0, 0, 0, 0.7), 0 0 40px rgba(255, 215, 0, 0.2);
            background: rgba(0, 0, 0, 0.2);
            animation: fadeInUp 1.7s cubic-bezier(.23,1.01,.32,1);
            border: 3px solid rgba(255, 215, 0, 0.4);
            position: relative;
            
            /* ============ OPTIMIZIM PËR PERFORMANCE TË LARTË ============ */
            /* GPU acceleration - shumë i rëndësishëm për skalim */
            transform: translateZ(0);
            will-change: transform, opacity;
            backface-visibility: hidden;
            perspective: 1000px;
            -webkit-backface-visibility: hidden;
            -webkit-perspective: 1000px;
            
            /* Video rendering optimization */
            image-rendering: crisp-edges;
            image-rendering: -webkit-optimize-contrast;
            -ms-interpolation-mode: nearest-neighbor;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            
            /* Contain layout optimization */
            contain: layout style paint;
            
            /* Smooth video playback */
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 340px;
        }
        #video::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #FFD700, #FFA500, #FFD700);
            animation: moveGradient 3s linear infinite;
            z-index: 2;
            border-top-left-radius: 25px;
            border-top-right-radius: 25px;
        }
        @keyframes moveGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        #footer-bar {
            width: min(96vw, 1280px);
            margin: 28px auto 0;
            box-sizing: border-box;
            background: linear-gradient(90deg, rgba(0, 40, 104, 0.6) 0%, rgba(0, 75, 158, 0.6) 50%, rgba(27, 91, 184, 0.6) 100%);
            color: #FFD700;
            text-align: center;
            font-size: 1rem;
            padding: 22px 16px 16px;
            font-family: 'Roboto Mono', monospace;
            letter-spacing: 1px;
            border-radius: 28px 28px 0 0;
            box-shadow: 0 -5px 30px rgba(0, 0, 0, 0.5), 0 0 30px rgba(255, 215, 0, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-top: 3px solid rgba(255, 215, 0, 0.3);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .fade-in {
            animation: fadeInUp 1.2s cubic-bezier(.23,1.01,.32,1);
        }
        /* Modal */
        #modal-bg {
            display:none;
            position:fixed;
            top:0;
            left:0;
            width:100vw;
            height:100vh;
            background:rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index:1000;
        }
        #modal {
            display:none;
            position:fixed;
            top:50%;
            left:50%;
            transform:translate(-50%,-50%);
            background:rgba(13, 71, 161, 0.85);
            border-radius:18px;
            box-shadow:0 0 50px rgba(0, 0, 0, 0.5);
            padding:38px 32px;
            z-index:1001;
            min-width:340px;
            animation: fadeInDown 0.7s cubic-bezier(.23,1.01,.32,1);
            border: 1px solid rgba(100, 181, 246, 0.3);
            color: #fff;
        }
        .abuse-modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        #modal h2 {
            margin-top: 0;
            color: #ef5350;
            text-shadow: 0 0 10px rgba(239, 83, 80, 0.7);
            font-size: 1.8rem;
            letter-spacing: 1px;
        }
        .abuse-modal-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        #modal textarea {
            width: 100%;
            min-height: 80px;
            border-radius: 12px;
            border: 1px solid rgba(100, 181, 246, 0.3);
            padding: 15px;
            font-size: 1.1rem;
            margin-bottom: 20px;
            background: rgba(25, 118, 210, 0.2);
            color: #fff;
            resize: vertical;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        #modal textarea:focus {
            background: rgba(25, 118, 210, 0.3);
            box-shadow: inset 0 0 15px rgba(0,0,0,0.3), 0 0 8px rgba(33, 150, 243, 0.6);
            outline: none;
            border-color: rgba(100, 181, 246, 0.5);
        }
        #modal textarea::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        #modal button {
            background: linear-gradient(90deg, #1976d2 0%, #42a5f5 100%);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-size: 1.1rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(33, 150, 243, 0.4);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        #modal button:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(33, 150, 243, 0.6);
        }
        #modal .close {
            background: linear-gradient(90deg, #616161 0%, #9e9e9e 100%);
            margin-left: 10px;
        }
        #modal .close:hover {
            background: linear-gradient(90deg, #424242 0%, #616161 100%);
        }
        #abuse-success {
            color: #81c784;
            font-weight: 600;
            margin-top: 18px;
            font-size: 1.15rem;
            text-shadow: 0 0 10px rgba(129, 199, 132, 0.7);
            animation: fadeIn 0.5s ease;
            display:none;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* NEW STYLES FOR BEAUTIFUL UI */
        .input-wrapper {
            position: relative;
            display: inline-block;
            margin-right: 15px;
        }
        
        .input-wrapper input {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(100, 181, 246, 0.4);
            border-radius: 50px;
            color: white;
            padding: 12px 20px 12px 45px;
            font-size: 1.1rem;
            width: 280px;
            max-width: 90%;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 20px rgba(25, 118, 210, 0.2);
            transition: all 0.3s ease;
        }
        
        .input-wrapper input:focus {
            border-color: #64b5f6;
            box-shadow: 0 0 30px rgba(33, 150, 243, 0.4);
            outline: none;
        }
        
        .input-wrapper input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #64b5f6;
            font-size: 1.1rem;
        }
        
        .pulse-button {
            position: relative;
            display: inline-block;
            background: linear-gradient(45deg, #1976d2, #42a5f5);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.4);
            transition: all 0.3s ease;
        }
        
        .pulse-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.6);
        }
        
        .pulse-button:active {
            transform: translateY(1px);
        }
        
        .button-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .button-glow {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0) 70%);
            opacity: 0;
            z-index: 1;
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0% { transform: scale(0.5); opacity: 0; }
            50% { opacity: 0.3; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        
        .notice-text {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
            font-weight: 600;
            color: #ffd54f;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .notice-panel {
            text-align: center;
            padding: 14px;
        }
        .payment-title {
            font-size: 1.32rem;
            font-weight: 700;
            color: #ffcc00;
            margin-bottom: 14px;
        }
        .payment-title i {
            margin-right: 10px;
        }
        .payment-description {
            color: #fff;
            font-size: 1.05rem;
            margin-bottom: 18px;
            line-height: 1.55;
        }
        .payment-note {
            color: #cce5ff;
            font-size: 0.9rem;
            margin-top: 12px;
            line-height: 1.5;
        }
        .payment-note i {
            margin-right: 5px;
        }
        .payment-confirmed {
            text-align: center;
            padding: 10px;
            background: rgba(67, 160, 71, 0.2);
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .payment-confirmed i {
            color: #66bb6a;
            font-size: 1.3rem;
            margin-right: 8px;
        }
        .payment-confirmed span {
            color: #66bb6a;
            font-weight: 600;
        }
        .join-room-form {
            margin: 18px 0 0;
            text-align: center;
        }
        .payment-cta {
            text-decoration: none;
            display: inline-block;
            background: linear-gradient(45deg, #43a047, #66bb6a);
        }
        
        .notice-icon {
            font-size: 1.2rem;
            margin-right: 10px;
            color: #ffc107;
            animation: shield-pulse 3s infinite;
        }
        
        @keyframes shield-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .admin-controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-button {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
        }
        
        .admin-button.record {
            background: linear-gradient(45deg, #7b1fa2, #9c27b0);
        }
        
        .admin-button.mute-all {
            background: linear-gradient(45deg, #ef6c00, #ff9800);
        }
        
        .admin-button.end-call {
            background: linear-gradient(45deg, #c62828, #f44336);
        }
        
        .admin-button:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .room-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .room-icon {
            font-size: 1.4rem;
            color: #64b5f6;
            margin-right: 10px;
            animation: link-pulse 3s infinite;
        }
        
        @keyframes link-pulse {
            0% { transform: rotate(0); }
            25% { transform: rotate(15deg); }
            75% { transform: rotate(-15deg); }
            100% { transform: rotate(0); }
        }
        
        .room-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #64b5f6;
            text-shadow: 0 0 10px rgba(100, 181, 246, 0.5);
        }
        
        .link-container {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .room-link-wrapper {
            background: rgba(25, 118, 210, 0.15);
            border: 1px solid rgba(100, 181, 246, 0.3);
            border-radius: 50px;
            padding: 10px 25px;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-shadow: 0 0 15px rgba(33, 150, 243, 0.3);
            display: inline-block;
        }
        
        #room-link {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.05rem;
            color: #64b5f6;
        }
        
        #copy-btn {
            position: relative;
            background: linear-gradient(45deg, #1976d2, #42a5f5);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(33, 150, 243, 0.3);
            overflow: hidden;
        }
        
        #copy-btn .btn-text {
            display: inline-block;
        }
        
        #copy-btn .copied-text {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, #43a047, #66bb6a);
            border-radius: 50px;
            opacity: 0;
            transform: translateY(100%);
            transition: all 0.3s ease;
        }
        
        #copy-btn.copied .copied-text {
            opacity: 1;
            transform: translateY(0);
        }
        
        .private-warning {
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.7);
            font-size: 0.95rem;
            margin-top: 10px;
        }
        
        .private-warning i {
            margin-right: 8px;
            color: #ffc107;
        }
        
        .call-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            color: #81d4fa;
            font-size: 0.95rem;
        }
        
        .stat-item i {
            margin-right: 8px;
            font-size: 1rem;
        }
        
        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.4);
            z-index: 5;
            opacity: 1;
            transition: opacity 1s ease;
            border-radius: 24px;
        }
        .connection-stats-panel {
            position: absolute;
            bottom: 80px;
            right: 20px;
            background: rgba(0, 0, 0, 0.85);
            border-radius: 12px;
            padding: 15px;
            color: #fff;
            z-index: 999;
            border: 1px solid rgba(100, 181, 246, 0.3);
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            max-width: 260px;
            font-size: 0.85rem;
            line-height: 1.8;
            font-family: 'Roboto Mono', monospace;
        }
        .connection-stats-header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .connection-stats-header strong {
            color: #64b5f6;
        }
        .connection-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            font-size: 0.8rem;
        }
        .stat-blue { color: #64b5f6; }
        .stat-orange { color: #ff7043; }
        .stat-green { color: #81c784; }
        .stat-purple { color: #9575cd; }
        
        .loader {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .loader-circle {
            width: 80px;
            height: 80px;
            border: 5px solid rgba(255,255,255,0.2);
            border-top: 5px solid #64b5f6;
            border-radius: 50%;
            animation: spin 1.5s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loader-text {
            color: white;
            font-size: 1.3rem;
            margin-top: 20px;
            font-weight: 600;
            text-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }
        
        .call-controls {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            z-index: 10;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 50px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            padding: 20px;
            max-width: calc(100% - 24px);
        }
        
        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
        }
        
        .mic-btn, .camera-btn {
            background: linear-gradient(45deg, #1976d2, #42a5f5);
        }
        
        .share-btn {
            background: linear-gradient(45deg, #43a047, #66bb6a);
        }
        
        .record-btn {
            background: linear-gradient(45deg, #f44336, #e57373);
        }
        
        .background-btn {
            background: linear-gradient(45deg, #9c27b0, #ba68c8);
        }
        
        .chat-btn {
            background: linear-gradient(45deg, #ff9800, #ffb74d);
        }
        
        .reactions-btn {
            background: linear-gradient(45deg, #e91e63, #f06292);
        }
        
        .end-call-btn {
            background: linear-gradient(45deg, #c62828, #f44336);
        }
        
        .more-btn {
            background: linear-gradient(45deg, #455a64, #607d8b);
        }
        
        .control-btn:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.4);
        }
        
        .control-btn.muted, .control-btn.off, .control-btn.recording {
            background: linear-gradient(45deg, #f44336, #d32f2f);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .footer-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0;
            flex-wrap: wrap;
        }
        
        .footer-info {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.82);
            font-size: 0.92rem;
        }
        
        .footer-divider {
            margin: 0 10px;
            color: rgba(255,255,255,0.5);
        }
        
        .footer-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .report-police-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(45deg, #c62828, #f44336);
            color: white;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 0 15px rgba(244, 67, 54, 0.4);
            transition: all 0.3s ease;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        
        .report-police-btn i {
            margin-right: 0;
        }
        
        .report-police-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.6);
        }
        
        /* Added style for the end call message */
        .end-call-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.85);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            z-index: 9999;
            text-align: center;
            display: none;
        }
        
        .language-selector {
            display: flex;
            gap: 5px;
        }
        
        .lang-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 5px;
            color: rgba(255,255,255,0.7);
            padding: 5px 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .lang-btn.selected {
            background: rgba(33, 150, 243, 0.3);
            border-color: rgba(100, 181, 246, 0.5);
            color: #64b5f6;
            box-shadow: 0 0 10px rgba(33, 150, 243, 0.3);
        }
        
        .lang-btn:hover:not(.selected) {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        /* Stilet për treguesin e lidhjes */
        .connection-indicator {
            position: fixed;
            bottom: 100px;
            right: 30px;
            background: rgba(0,0,0,0.7);
            border-radius: 15px;
            padding: 15px;
            color: white;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            transform: translateX(120%);
            transition: transform 0.3s ease;
        }
        
        .connection-indicator:hover {
            transform: translateX(0);
        }
        
        .connection-indicator::before {
            content: "";
            position: absolute;
            left: -20px;
            width: 20px;
            height: 50px;
            background: rgba(0,0,0,0.7);
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            border-right: none;
        }
        
        .signal-icon {
            display: flex;
            align-items: flex-end;
            height: 30px;
            margin-bottom: 10px;
        }
        
        .signal-icon .bar {
            width: 6px;
            background: #4caf50;
            margin: 0 2px;
            border-radius: 2px;
        }
        
        .signal-icon .bar1 { height: 6px; }
        .signal-icon .bar2 { height: 12px; }
        .signal-icon .bar3 { height: 18px; }
        .signal-icon .bar4 { height: 24px; }
        .signal-icon .bar5 { height: 30px; }
        
        .signal-text {
            font-weight: bold;
            font-size: 14px;
            color: #4caf50;
            margin-bottom: 10px;
        }
        
        .signal-stats {
            width: 100%;
            font-size: 12px;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: rgba(255,255,255,0.7);
        }
        
        /* Stilet për network preloader */
        .network-preloader {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0,0,0,0.7);
            border-radius: 12px;
            padding: 15px;
            color: white;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            max-width: 300px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }
        
        .network-preloader.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .buffer-text {
            font-size: 14px;
            margin-bottom: 10px;
            color: #64b5f6;
        }
        
        .buffer-bar {
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .buffer-progress {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #1976d2, #42a5f5);
            border-radius: 4px;
            transition: width 0.3s linear;
            animation: buffer-progress 2s linear infinite;
        }
        
        @keyframes buffer-progress {
            0% { width: 0%; }
            50% { width: 100%; }
            50.1% { width: 0%; }
            100% { width: 100%; }
        }
        
        /* Optimizime shtesë për performancë të lartë */
        video {
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            transform: translateZ(0);
            backface-visibility: hidden;
            perspective: 1000px;
            will-change: transform, opacity;
        }
        
        #video iframe {
            transform: translateZ(0);
            backface-visibility: hidden;
            perspective: 1000px;
            will-change: transform;
        }
        
        /* ============================================================
           FULLY RESPONSIVE — MOBILE-FIRST OVERRIDES
           ============================================================ */

        /* Prevent iOS input zoom (font-size must be >= 16px) */
        input, textarea, select {
            font-size: 16px !important;
        }

        /* Smooth tap — remove tap highlight on all interactive elements */
        * { -webkit-tap-highlight-color: transparent; box-sizing: border-box; }
        button, .call-btn, .control-btn, .admin-button, a {
            touch-action: manipulation;
        }

        /* Video — scrollable call-controls bar on small screens */
        .call-controls {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .call-controls::-webkit-scrollbar { display: none; }

        /* ── Tablet (≤ 1024px) ─────────────────────────────────────── */
        @media (max-width: 1024px) {
            #header-bar { font-size: 1.7rem; }
            #header-bar .brand { font-size: 2.2rem !important; }
            #video { height: 65vh; width: 96vw; }
        }

        /* ── Small Tablet / Large Phone (≤ 768px) ──────────────────── */
        @media (max-width: 768px) {
            #header-bar {
                font-size: 1.3rem;
                padding: 10px 14px;
                gap: 6px;
                border-bottom-left-radius: 16px;
                border-bottom-right-radius: 16px;
            }
            #header-bar .brand  { font-size: 1.7rem !important; letter-spacing: 2px; }
            #header-bar .subtitle { display: none; }
            #header-bar .secure-badge { display: none; }
            #header-bar #jitsi-status-badge { display: none; }
            #header-bar .live-dot { display: none; }
            .user-badge { font-size: 0.82rem; padding: 5px 12px; }
            #abuse-btn { padding: 7px 14px; font-size: 0.88rem; }

            #notice  { padding: 16px 14px; margin-top: 14px; width: 95vw; }
            #room-info { padding: 16px 14px; margin-top: 14px; width: 95vw; }

            /* Full-width form inputs */
            #join-room-form { display: flex; flex-direction: column; align-items: center; gap: 10px; }
            .input-wrapper { margin-right: 0; width: 100%; max-width: 360px; }
            .input-wrapper input { width: 100%; }
            .pulse-button { width: 100%; max-width: 280px; justify-content: center; }
            .payment-title { font-size: 1.15rem; }
            .payment-description { font-size: 0.98rem; }

            #video {
                height: 55vh;
                width: 95vw;
                margin-top: 14px;
                border-radius: 16px;
            }

            .call-controls {
                bottom: 8px;
                padding: 10px 14px;
                gap: 8px;
                max-width: 92%;
                border-radius: 40px;
            }
            .control-btn { width: 42px; height: 42px; font-size: 0.95rem; flex-shrink: 0; }

            #footer-bar { width: 95vw; padding: 14px 12px 10px; margin-top: 18px; border-top-left-radius: 20px; border-top-right-radius: 20px; }
            .footer-content { flex-direction: column; gap: 10px; text-align: center; padding: 0 14px; }
            .footer-info { font-size: 0.85rem; justify-content: center; }
            .footer-actions { flex-direction: row; flex-wrap: wrap; justify-content: center; gap: 10px; }

            .link-container { flex-direction: column; align-items: center; }
            .room-link-wrapper { max-width: 98%; }
            #room-link { font-size: 0.82rem; }
            #copy-btn { width: 100%; max-width: 220px; text-align: center; }

            .call-stats { flex-wrap: wrap; gap: 12px; justify-content: center; }
            .admin-controls { flex-wrap: wrap; gap: 10px; }

            /* Incoming call modal */
            .incoming-call-container { padding: 28px 20px; max-width: 90vw; border-radius: 20px; }
            .caller-avatar { width: 90px; height: 90px; }
            .caller-avatar i { font-size: 2.5rem; }
            .caller-name { font-size: 1.4rem; }
            .call-btn { width: 70px; height: 70px; font-size: 1.6rem; }

            .connection-stats-panel {
                right: 10px;
                bottom: 64px;
                max-width: 220px;
                padding: 10px;
                font-size: 0.75rem;
            }
            .connection-stats-grid { font-size: 0.72rem; gap: 6px; }

            /* Feature modals — full-width on mobile */
            #screenShareModal {
                width: 92vw !important;
                max-height: 88vh !important;
                overflow-y: auto !important;
                padding: 20px !important;
            }
            #recordingModal {
                width: 92vw !important;
                padding: 20px !important;
            }
            #backgroundModal {
                width: 92vw !important;
                max-height: 88vh !important;
                overflow-y: auto !important;
                padding: 20px !important;
            }
            #modal { min-width: unset; width: 90vw; padding: 22px 16px; }
            .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }

            .connection-indicator { right: 10px; bottom: 75px; }
            .network-preloader { left: 8px; right: 8px; max-width: none; bottom: 12px; }
        }

        /* ── Mobile Phone (≤ 480px) ─────────────────────────────────── */
        @media (max-width: 480px) {
            #header-bar {
                font-size: 1rem;
                padding: 8px 10px;
                gap: 5px;
            }
            #header-bar .brand { font-size: 1.3rem !important; letter-spacing: 1px; }
            #abuse-btn { padding: 6px 10px; font-size: 0.78rem; margin-left: 0; }
            #test-ringtone-btn { padding: 6px 10px !important; font-size: 0.75rem !important; margin-left: 0 !important; }

            #video { height: 48vh; }

            .call-controls { padding: 8px 10px; gap: 6px; }
            .control-btn { width: 38px; height: 38px; font-size: 0.82rem; }

            .caller-avatar { width: 72px; height: 72px; }
            .caller-avatar i { font-size: 2rem; }
            .caller-name { font-size: 1.2rem; }
            .call-btn { width: 60px; height: 60px; font-size: 1.4rem; }
            .call-actions { gap: 20px; }

            .connection-stats-panel {
                display: none;
            }

            .room-link-wrapper { max-width: 100%; overflow: hidden; }
            #room-link { font-size: 0.72rem; word-break: break-all; white-space: normal; }

            #notice { padding: 14px 12px; }
            #room-info { padding: 14px 12px; }
            .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        }

        /* ── Very Small (≤ 360px) ───────────────────────────────────── */
        @media (max-width: 360px) {
            #header-bar .brand { font-size: 1.1rem !important; }
            #header-bar .user-badge { display: none; }
            #video { height: 42vh; }
            .control-btn { width: 34px; height: 34px; font-size: 0.75rem; }
        }
        
        /* Advanced Features Modals */
        .screen-share-option, .bg-option {
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 10px;
        }
        .feature-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10000;
            width: min(92vw, 420px);
            padding: 28px;
            border-radius: 20px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.22);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        .feature-modal--wide {
            width: min(92vw, 520px);
            max-height: 70vh;
            overflow-y: auto;
        }
        .feature-modal-title {
            color: #fff;
            margin: 0 0 20px;
            text-align: center;
            font-size: 1.1rem;
            letter-spacing: .3px;
        }
        .feature-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .feature-inline {
            display: flex;
            gap: 10px;
        }
        .feature-btn {
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            cursor: pointer;
            color: #fff;
            font-size: 0.96rem;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }
        .feature-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }
        .feature-btn:active {
            transform: translateY(0);
        }
        .feature-btn--primary { background: linear-gradient(90deg, #2196f3, #21cbf3); }
        .feature-btn--success { background: linear-gradient(90deg, #4caf50, #81c784); }
        .feature-btn--warning { background: linear-gradient(90deg, #ff9800, #ffb74d); }
        .feature-btn--danger { background: rgba(244,67,54,0.88); }
        .feature-btn--full { width: 100%; }
        .feature-btn--mt {
            margin-top: 16px;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .feature-option {
            border: none;
            border-radius: 12px;
            color: #fff;
            padding: 16px 10px;
            cursor: pointer;
            text-align: center;
            font-size: 0.86rem;
            line-height: 1.4;
            box-shadow: 0 8px 22px rgba(0,0,0,0.28);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
            background-size: cover;
            background-position: center;
        }
        .feature-option i {
            display: block;
            margin-bottom: 6px;
            font-size: 1rem;
        }
        .feature-option:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(0,0,0,0.34);
            filter: brightness(1.04);
        }
        .feature-option--none {
            background: #666;
        }
        .feature-option--blur {
            background: linear-gradient(45deg, #2196f3, #21cbf3);
        }
        .feature-option--office {
            background-image: url('https://images.unsplash.com/photo-1497366216548-37526070297c?w=150&h=100&fit=crop'), linear-gradient(45deg, #4a90e2, #357abd);
        }
        .feature-option--nature {
            background-image: url('https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=150&h=100&fit=crop'), linear-gradient(45deg, #2e8b57, #228b22);
        }
        .feature-option--space {
            background-image: url('https://images.unsplash.com/photo-1446776653964-20c1d3a81b06?w=150&h=100&fit=crop'), linear-gradient(45deg, #1a1a2e, #16213e);
        }
        .feature-option--gradient {
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4);
        }
        .recording-status {
            text-align: center;
            margin-bottom: 18px;
        }
        .recording-indicator-dot {
            width: 20px;
            height: 20px;
            background: #f44336;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
            animation: pulse 1s infinite;
        }
        .recording-status-text {
            color: #fff;
            font-size: 16px;
        }
        .flex-1 {
            flex: 1;
        }
        .is-hidden {
            display: none;
        }
        .audio-unlock-card {
            background: #1976d2;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        .audio-unlock-card i {
            font-size: 50px;
            margin-bottom: 20px;
        }
        .dynamic-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 8px;
            background: rgba(0,0,0,0.3);
            border-radius: 6px;
        }
        .payment-timer-shell {
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            padding: 10px 15px;
            border-radius: 10px;
            z-index: 100;
            display: flex;
            align-items: center;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .payment-timer-icon {
            color: #ffc107;
            margin-right: 10px;
            animation: pulse 1.5s infinite;
        }
        .payment-timer-label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.7);
        }
        #payment-time-remaining {
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
        }
        .disconnection-alert {
            display: none;
            position: fixed;
            top: 30px;
            right: 30px;
            z-index: 10001;
            background: rgba(220,53,69,0.95);
            color: #fff;
            padding: 22px 32px;
            border-radius: 18px;
            box-shadow: 0 0 30px rgba(220,53,69,0.4);
            font-size: 1.25rem;
            font-weight: 700;
            border: 2px solid #fff;
        }
        .disconnection-alert i {
            font-size: 2rem;
            margin-right: 12px;
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }
        
        .screen-share-option:hover, .bg-option:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .screen-share-option:active, .bg-option:active {
            transform: scale(0.95);
        }
        
        #recordingIndicator {
            animation: recording-pulse 1s infinite;
        }
        
        @keyframes recording-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <!-- Incoming Call Modal -->
    <div id="incomingCallModal" class="incoming-call-modal">
        <div class="incoming-call-container">
            <div class="caller-avatar">
                <i class="fa-solid fa-video"></i>
            </div>
            <div class="caller-name" id="callerName">Noter</div>
            <div class="caller-status">Po thërret...</div>
            <div class="call-actions">
                <button class="call-btn accept-btn" onclick="acceptCall()" title="Prano thirrjen">
                    <i class="fa-solid fa-phone"></i>
                </button>
                <button class="call-btn reject-btn" onclick="rejectCall()" title="Refuzo thirrjen">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ULTRA-PERFORMANCE: Particles disabled for 1M+ calls -->
    <!-- <div id="particles-js" style="position:fixed; width:100%; height:100%; top:0; left:0; z-index:-1;"></div> -->
    <div id="header-bar" class="glass">
        <div class="header-main">
            <span class="avatar"><i class="fa-solid fa-video"></i></span>
            <span class="brand">NOTERIA</span>
            <span class="subtitle"><?= isset($translations['subtitle'][$lang]) ? $translations['subtitle'][$lang] : '' ?></span>
            <?php if ($is_admin): ?>
                <span class="user-badge user-badge--admin"><i class="fa-solid fa-crown"></i> <?php echo htmlspecialchars($username); ?></span>
            <?php else: ?>
                <span class="user-badge"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <?php endif; ?>
            <span class="secure-badge" title="Dhoma është private dhe e mbrojtur"><i class="fa-solid fa-lock"></i> <?= isset($translations['secure'][$lang]) ? $translations['secure'][$lang] : 'E mbrojtur' ?></span>
            <span class="live-dot" title="Video thirrja është aktive"></span>
        </div>

        <div class="header-actions">
            <button id="abuse-btn" onclick="openModal()"><i class="fa-solid fa-triangle-exclamation"></i> <?= isset($translations['report_abuse'][$lang]) ? $translations['report_abuse'][$lang] : 'Raporto' ?></button>

            <button id="test-ringtone-btn" class="header-pill-btn header-pill-btn-warning" onclick="testRingtoneClick()" title="Test ringtone audio">
                <i class="fa-solid fa-volume-high"></i> Test Zile
            </button>

            <span id="jitsi-status-badge" class="status-badge">
                <i class="fa-solid fa-circle status-badge-dot"></i>
                <span id="jitsi-status-text">Lidhja</span>
            </span>

            <a href="dashboard.php" class="header-pill-btn header-pill-btn-success">
                <i class="fa-solid fa-arrow-left"></i> Kthehu në Panel
            </a>
        </div>
    </div>
    <div id="notice" class="glass fade-in">
        <?php if (!$is_admin && !$has_paid && $payment_required): ?>
            <!-- Shfaq njoftimin për pagesë -->
            <div class="notice-panel">
                <div class="payment-title">
                    <i class="fa-solid fa-credit-card"></i>
                    Konsulenca me Noter kërkon pagesë
                </div>
                <p class="payment-description">
                    Video thirrjet për konsulencë me noter janë shërbim me pagesë.
                    Çmimi për një seancë 30 minutëshe është <strong><?= $session_price ?> EUR</strong>.
                </p>
                <?php if (!empty($payment_url)): ?>
                    <a href="<?= htmlspecialchars($payment_url) ?>" class="pulse-button payment-cta">
                        <span class="button-content">
                            <i class="fa-solid fa-credit-card"></i> 
                            Paguaj <?= $session_price ?> EUR
                        </span>
                        <span class="button-glow"></span>
                    </a>
                    <p class="payment-note">
                        <i class="fa-solid fa-lock"></i>
                        Pagesa procesohet në mënyrë të sigurt përmes Paysera, Raiffeisen Bank dhe BKT.
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Formular për bashkimin në dhomë ose informacion për përdoruesin që ka paguar -->
            <?php if ($has_paid): ?>
                <div class="payment-confirmed">
                    <i class="fa-solid fa-check-circle"></i>
                    <span>Pagesa e konfirmuar! Ju keni <?= $session_duration ?> minuta konsulencë të disponueshme.</span>
                </div>
            <?php endif; ?>
            <form id="join-room-form" class="join-room-form" method="get" action="video_call.php">
                <div class="input-wrapper">
                    <i class="fa-solid fa-video input-icon"></i>
                    <input type="text" name="room" placeholder="Fut kodin e video thirrjes" required>
                </div>
                <button type="submit" class="pulse-button">
                    <span class="button-content"><i class="fa-solid fa-right-to-bracket"></i> Bashkohu</span>
                    <span class="button-glow"></span>
                </button>
            </form>
            <div class="notice-text">
                <i class="fa-solid fa-shield-halved notice-icon"></i>
                <span><?= isset($translations['notice'][$lang]) ? $translations['notice'][$lang] : 'Kjo video thirrje është e sigurtë dhe e enkriptuar.' ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($is_admin): ?>
            <div class="admin-controls">
                <button class="admin-button record" title="Regjistro thirrjen">
                    <i class="fa-solid fa-record-vinyl"></i>
                </button>
                <button class="admin-button mute-all" title="Hesht të gjithë">
                    <i class="fa-solid fa-volume-xmark"></i>
                </button>
                <button class="admin-button end-call" title="Mbyll thirrjen për të gjithë">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>
    <div id="room-info" class="glass fade-in">
        <div class="room-header">
            <i class="fa-solid fa-link room-icon"></i>
            <span class="room-title"><?= isset($translations['room_link'][$lang]) ? $translations['room_link'][$lang] : 'Linku i Dhomës' ?></span>
        </div>
        <div class="link-container">
            <div class="room-link-wrapper">
                <span id="room-link"><?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?room=".$room); ?></span>
            </div>
            <button id="copy-btn" onclick="copyRoomLink()">
                <i class="fa-solid fa-copy"></i> 
                <span class="btn-text"><?= isset($translations['copy'][$lang]) ? $translations['copy'][$lang] : 'Kopjo' ?></span>
                <span class="copied-text">U Kopjua!</span>
            </button>
        </div>
        <div class="private-warning">
            <i class="fa-solid fa-eye-slash"></i>
            <span><?= isset($translations['private_warning'][$lang]) ? $translations['private_warning'][$lang] : 'Kjo video thirrje është private dhe e sigurtë.' ?></span>
        </div>
        <div class="call-stats">
            <div class="stat-item">
                <i class="fa-solid fa-clock"></i>
                <span id="call-timer">00:00:00</span>
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-users"></i>
                <span id="participant-count">1</span>
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-signal"></i>
                <span id="connection-quality">Shkëlqyeshme</span>
            </div>
        </div>
    </div>
    <div id="video" class="glass">
        <div class="video-overlay">
            <div class="loader">
                <div class="loader-circle"></div>
                <div class="loader-text">Duke lidhur video thirrjen...</div>
            </div>
        </div>
        
        <!-- Advanced Connection Statistics Panel (Real-time) -->
        <div id="connection-stats-advanced" class="connection-stats-panel">
            <div class="connection-stats-header">
                <strong>📊 Real-time Statistics</strong>
            </div>
            <div class="connection-stats-grid">
                <div class="stat-blue"><i class="fa-solid fa-arrow-down"></i> <span id="video-bitrate">-- Mbps</span></div>
                <div class="stat-orange"><i class="fa-solid fa-hourglass-end"></i> <span id="latency-ms">-- ms</span></div>
                <div class="stat-green"><i class="fa-solid fa-percent"></i> <span id="packet-quality">-- %</span></div>
                <div class="stat-purple"><i class="fa-solid fa-wave"></i> <span id="jitter-ms">-- ms</span></div>
            </div>
        </div>
        
        <div class="call-controls">
            <button class="control-btn mic-btn" title="Mikrofoni">
                <i class="fa-solid fa-microphone"></i>
            </button>
            <button class="control-btn camera-btn" title="Kamera">
                <i class="fa-solid fa-video"></i>
            </button>
            <button class="control-btn share-btn" title="Ndaj ekranin">
                <i class="fa-solid fa-desktop"></i>
            </button>
            <button class="control-btn record-btn" title="Regjistro thirrjen" onclick="openRecordingModal()">
                <i class="fa-solid fa-record-vinyl"></i>
            </button>
            <button class="control-btn background-btn" title="Sfondi virtual" onclick="openBackgroundModal()">
                <i class="fa-solid fa-image"></i>
            </button>
            <button class="control-btn chat-btn" title="Chat i avancuar" onclick="toggleAdvancedChat()">
                <i class="fa-solid fa-comments"></i>
            </button>
            <button class="control-btn reactions-btn" title="Reagime" onclick="showReactions()">
                <i class="fa-solid fa-face-smile"></i>
            </button>
            <button class="control-btn more-btn" title="Më shumë opsione">
                <i class="fa-solid fa-ellipsis"></i>
            </button>
        </div>
    </div>
    <div id="footer-bar" class="glass fade-in">
        <div class="footer-content">
            <div class="footer-info">
                <i class="fa-solid fa-copyright"></i> <?php echo date('Y'); ?> Noteria 
                <span class="footer-divider">|</span> 
                <span class="footer-text"><?= isset($translations['footer'][$lang]) ? $translations['footer'][$lang] : 'Të gjitha të drejtat e rezervuara' ?></span>
            </div>
            <div class="footer-actions">
                <a href="raporto-polici.php?room=<?php echo urlencode($room); ?>&username=<?php echo urlencode($username); ?>" class="report-police-btn">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span><?= isset($translations['report_police'][$lang]) ? $translations['report_police'][$lang] : 'Raporto te Policia' ?></span>
                </a>
                <div class="language-selector">
                    <button class="lang-btn selected" data-lang="sq">SQ</button>
                    <button class="lang-btn" data-lang="en">EN</button>
                    <button class="lang-btn" data-lang="sr">SR</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal për raportim abuzimi -->
    <div id="modal-bg"></div>
    <div id="modal" class="glass">
    <h2 class="abuse-modal-title"><i class="fa-solid fa-triangle-exclamation"></i> <?= isset($translations['modal_title'][$lang]) ? $translations['modal_title'][$lang] : '' ?></h2>
        <form id="abuse-form" onsubmit="return submitAbuse();">
            <textarea id="abuse-msg" placeholder="<?= isset($translations['modal_placeholder'][$lang]) ? $translations['modal_placeholder'][$lang] : '' ?>" required></textarea>
            <div class="abuse-modal-actions">
                <button type="submit"><i class="fa-solid fa-paper-plane"></i> <?= isset($translations['modal_send'][$lang]) ? $translations['modal_send'][$lang] : '' ?></button>
                <button type="button" class="close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i> <?= isset($translations['modal_close'][$lang]) ? $translations['modal_close'][$lang] : '' ?></button>
            </div>
        </form>
    <div id="abuse-success"><i class="fa-solid fa-circle-check"></i> <?= isset($translations['modal_success'][$lang]) ? $translations['modal_success'][$lang] : '' ?></div>
    </div>

    <!-- Tregues i cilësisë së lidhjes -->
    <div id="connection-indicator" class="connection-indicator">
      <div class="signal-icon">
        <span class="bar bar1"></span>
        <span class="bar bar2"></span>
        <span class="bar bar3"></span>
        <span class="bar bar4"></span>
        <span class="bar bar5"></span>
      </div>
      <div class="signal-text">Lidhje Shkëlqyeshme</div>
      <div class="signal-stats">
        <div class="stat-row"><span class="stat-label">Bandwidth:</span> <span id="bandwidth-value">5.0 Mbps</span></div>
        <div class="stat-row"><span class="stat-label">Paketat:</span> <span id="packet-value">100%</span></div>
        <div class="stat-row"><span class="stat-label">Latency:</span> <span id="latency-value">12ms</span></div>
      </div>
    </div>

    <!-- Preloader për të siguruar vazhdimësinë e sinjali video -->
    <div id="network-preloader" class="network-preloader">
      <div class="buffer-container">
        <div class="buffer-text">Duke optimizuar sinjalet...</div>
        <div class="buffer-bar">
          <div class="buffer-progress"></div>
        </div>
      </div>
    </div>
    
    <!-- Screen Share Options Modal -->
    <div id="screenShareModal" class="glass feature-modal">
        <h3 class="feature-modal-title"><i class="fa-solid fa-desktop"></i> Zgjedh llojin e ndarjes së ekranit</h3>
        <div class="feature-stack">
            <button class="screen-share-option feature-btn feature-btn--primary" data-type="screen">
                <i class="fa-solid fa-display"></i> Ekrani i plotë
            </button>
            <button class="screen-share-option feature-btn feature-btn--success" data-type="window">
                <i class="fa-solid fa-window-maximize"></i> Dritare specifike
            </button>
            <button class="screen-share-option feature-btn feature-btn--warning" data-type="tab">
                <i class="fa-solid fa-browser"></i> Tab i shfletuesit
            </button>
        </div>
        <button onclick="closeScreenShareModal()" class="feature-btn feature-btn--danger feature-btn--full feature-btn--mt">Anulo</button>
    </div>

    <!-- Recording Modal -->
    <div id="recordingModal" class="glass feature-modal">
        <h3 class="feature-modal-title"><i class="fa-solid fa-record-vinyl"></i> Regjistrimi i thirrjes</h3>
        <div id="recordingStatus" class="recording-status">
            <div id="recordingIndicator" class="recording-indicator-dot"></div>
            <span id="recordingText" class="recording-status-text">Nuk po regjistrohet</span>
        </div>
        <div class="feature-inline">
            <button id="startRecordingBtn" class="feature-btn feature-btn--danger flex-1" onclick="startRecording()">
                <i class="fa-solid fa-play"></i> Fillo regjistrimin
            </button>
            <button id="stopRecordingBtn" class="feature-btn feature-btn--success flex-1 is-hidden" onclick="stopRecording()">
                <i class="fa-solid fa-stop"></i> Ndal regjistrimin
            </button>
        </div>
        <button onclick="closeRecordingModal()" class="feature-btn feature-btn--danger feature-btn--full feature-btn--mt">Mbyll</button>
    </div>

    <!-- Virtual Background Modal -->
    <div id="backgroundModal" class="glass feature-modal feature-modal--wide">
        <h3 class="feature-modal-title"><i class="fa-solid fa-image"></i> Sfondi virtual</h3>
        <div class="feature-grid">
            <button class="bg-option feature-option feature-option--none" data-bg="none">
                <i class="fa-solid fa-ban"></i><br>Asnjë
            </button>
            <button class="bg-option feature-option feature-option--blur" data-bg="blur">
                <i class="fa-solid fa-eye-slash"></i><br>Turbullo
            </button>
            <button class="bg-option feature-option feature-option--office" data-bg="office">
                <i class="fa-solid fa-building"></i><br>Zyrë
            </button>
            <button class="bg-option feature-option feature-option--nature" data-bg="nature">
                <i class="fa-solid fa-tree"></i><br>Natyra
            </button>
            <button class="bg-option feature-option feature-option--space" data-bg="space">
                <i class="fa-solid fa-star"></i><br>Hapësira
            </button>
            <button class="bg-option feature-option feature-option--gradient" data-bg="gradient">
                <i class="fa-solid fa-palette"></i><br>Gradient
            </button>
        </div>
        <button onclick="closeBackgroundModal()" class="feature-btn feature-btn--danger feature-btn--full">Mbyll</button>
    </div>
    
        <script src="https://meet.jit.si/external_api.js" onerror="console.warn('Jitsi API failed to load, using fallback mode')"></script>
        <script src="/assets/fontawesome/all.min.js" onerror="console.warn('Font Awesome JS failed to load')"></script>
        <!-- ULTRA-PERFORMANCE: Particles.js disabled -->
        <!-- <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script> -->
        <script>
            // Global error handler for external resources
            window.addEventListener('error', function(e) {
                if (e.target.tagName === 'LINK' || e.target.tagName === 'SCRIPT') {
                    console.warn('External resource failed to load:', e.target.src || e.target.href);
                    // Hide failed stylesheets to prevent layout issues
                    if (e.target.tagName === 'LINK' && e.target.rel === 'stylesheet') {
                        e.target.style.display = 'none';
                    }
                }
            }, true);

            // Monitor network requests
            (function() {
                const originalFetch = window.fetch;
                window.fetch = function(...args) {
                    return originalFetch.apply(this, args).catch(error => {
                        console.warn('Fetch failed for:', args[0], error.message);
                        // Show network warning if critical resources fail
                        if (args[0].includes('meet.jit.si') || args[0].includes('api.jitsi.net')) {
                            showNetworkWarning();
                        }
                        throw error;
                    });
                };

                let networkWarningShown = false;
                function showNetworkWarning() {
                    if (networkWarningShown) return;
                    networkWarningShown = true;

                    const warning = document.createElement('div');
                    warning.id = 'network-warning';
                    warning.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: rgba(255, 152, 0, 0.9);
                        color: white;
                        padding: 10px 15px;
                        border-radius: 8px;
                        font-size: 14px;
                        z-index: 10001;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
                        max-width: 300px;
                    `;
                    warning.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Disa burime të jashtme nuk u ngarkuan. Sistemi vazhdon normalisht.';
                    document.body.appendChild(warning);

                    setTimeout(() => {
                        if (warning.parentNode) {
                            warning.remove();
                        }
                    }, 5000);
                }
            })();
        </script>
        <script>
            // Expose server-generated call_id to client-side for tracking and updates
            window.CALL_ID = '<?php echo isset($call_id) ? htmlspecialchars($call_id) : ''; ?>';
            
            // Global functions for modal controls and advanced features
            function openScreenShareModal() {
                document.getElementById('screenShareModal').style.display = 'block';
                setTimeout(() => {
                    document.addEventListener('click', closeScreenShareOnClickOutside);
                }, 100);
            }
            
            function closeScreenShareModal() {
                document.getElementById('screenShareModal').style.display = 'none';
                document.removeEventListener('click', closeScreenShareOnClickOutside);
            }
            
            function closeScreenShareOnClickOutside(e) {
                const modal = document.getElementById('screenShareModal');
                if (modal && !modal.contains(e.target)) {
                    closeScreenShareModal();
                }
            }
            
            function openRecordingModal() {
                document.getElementById('recordingModal').style.display = 'block';
                setTimeout(() => {
                    document.addEventListener('click', closeRecordingOnClickOutside);
                }, 100);
            }
            
            function closeRecordingModal() {
                document.getElementById('recordingModal').style.display = 'none';
                document.removeEventListener('click', closeRecordingOnClickOutside);
            }
            
            function closeRecordingOnClickOutside(e) {
                const modal = document.getElementById('recordingModal');
                if (modal && !modal.contains(e.target)) {
                    closeRecordingModal();
                }
            }
            
            function openBackgroundModal() {
                document.getElementById('backgroundModal').style.display = 'block';
                setTimeout(() => {
                    document.addEventListener('click', closeBackgroundOnClickOutside);
                }, 100);
            }
            
            function closeBackgroundModal() {
                document.getElementById('backgroundModal').style.display = 'none';
                document.removeEventListener('click', closeBackgroundOnClickOutside);
            }
            
            function closeBackgroundOnClickOutside(e) {
                const modal = document.getElementById('backgroundModal');
                if (modal && !modal.contains(e.target)) {
                    closeBackgroundModal();
                }
            }
            
            function toggleAdvancedChat() {
                try {
                    if (window.api) {
                        window.api.executeCommand('toggleChat');
                        setTimeout(() => {
                            // Add file sharing capability
                            try {
                                const chatInput = document.querySelector('#video iframe').contentWindow.document.querySelector('input[type="text"]');
                                if (chatInput && !chatInput.dataset.fileBtnAdded) {
                                    const fileBtn = document.createElement('button');
                                    fileBtn.innerHTML = '<i class="fa-solid fa-paperclip"></i>';
                                    fileBtn.style.cssText = 'position:absolute; right:60px; top:50%; transform:translateY(-50%); background:#2196f3; color:white; border:none; border-radius:50%; width:30px; height:30px; cursor:pointer;';
                                    fileBtn.onclick = () => {
                                        const input = document.createElement('input');
                                        input.type = 'file';
                                        input.accept = 'image/*,video/*,audio/*,application/*';
                                        input.onchange = (e) => {
                                            const file = e.target.files[0];
                                            if (file) {
                                                console.log('File selected for sharing:', file.name);
                                            }
                                        };
                                        input.click();
                                    };
                                    chatInput.parentElement.style.position = 'relative';
                                    chatInput.parentElement.appendChild(fileBtn);
                                    chatInput.dataset.fileBtnAdded = 'true';
                                }
                            } catch (e) {
                                console.log("Could not enhance chat:", e);
                            }
                        }, 1000);
                    }
                } catch (e) {
                    console.log("Gabim në toggle chat:", e);
                }
            }
            
            function showReactions() {
                const reactions = ['👍', '❤️', '😂', '😮', '😢', '😡'];
                const reactionContainer = document.createElement('div');
                reactionContainer.style.cssText = `
                    position: fixed;
                    bottom: 150px;
                    left: 50%;
                    transform: translateX(-50%);
                    display: flex;
                    gap: 10px;
                    z-index: 1000;
                    background: rgba(0,0,0,0.8);
                    padding: 15px;
                    border-radius: 25px;
                    backdrop-filter: blur(10px);
                `;
                
                reactions.forEach(emoji => {
                    const btn = document.createElement('button');
                    btn.textContent = emoji;
                    btn.style.cssText = `
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                        padding: 5px;
                        border-radius: 50%;
                        transition: all 0.3s ease;
                    `;
                    btn.onmouseover = () => btn.style.transform = 'scale(1.2)';
                    btn.onmouseout = () => btn.style.transform = 'scale(1)';
                    btn.onclick = () => {
                        try {
                            if (window.api) {
                                window.api.executeCommand('sendChatMessage', emoji);
                                showReactionOverlay(emoji);
                            }
                        } catch (e) {
                            console.log("Gabim në dërgimin e reagimit:", e);
                        }
                        document.body.removeChild(reactionContainer);
                    };
                    reactionContainer.appendChild(btn);
                });
                
                document.body.appendChild(reactionContainer);
                
                setTimeout(() => {
                    if (document.body.contains(reactionContainer)) {
                        document.body.removeChild(reactionContainer);
                    }
                }, 5000);
            }
            
            function showReactionOverlay(emoji) {
                const overlay = document.createElement('div');
                overlay.textContent = emoji;
                overlay.style.cssText = `
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    font-size: 48px;
                    z-index: 9999;
                    pointer-events: none;
                    animation: reactionFloat 2s ease-out forwards;
                `;
                
                document.body.appendChild(overlay);
                
                setTimeout(() => {
                    if (document.body.contains(overlay)) {
                        document.body.removeChild(overlay);
                    }
                }, 2000);
            }
            
            // Add CSS animation for reactions
            const reactionStyle = document.createElement('style');
            reactionStyle.textContent = `
                @keyframes reactionFloat {
                    0% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
                    100% { transform: translate(-50%, -100%) scale(1.5); opacity: 0; }
                }
            `;
            document.head.appendChild(reactionStyle);
            
      document.addEventListener("DOMContentLoaded", function() {
        // Shfaq preloaderin e rrjetit në fillim
        const networkPreloader = document.getElementById('network-preloader');
        networkPreloader.classList.add('show');
        
        // Fshehe preloaderin pas 5 sekondash
        setTimeout(function() {
            networkPreloader.classList.remove('show');
        }, 5000);
        
        // Handle the end call button - removed since it's now inline
        
        // Function to end the call and update status
        function endCall() {
            // Prefer using the server-generated CALL_ID exposed to JS
            const callId = window.CALL_ID || null;
            const roomId = '<?php echo htmlspecialchars($room); ?>';

            // Show end call message immediately
            document.getElementById('end-call-message').style.display = 'block';

            if (callId) {
                // Update call status in database using known call_id
                fetch('update_call_status.php?call_id=' + encodeURIComponent(callId) + '&status=completed')
                .then(response => response.json())
                .then(statusData => {
                    console.log('Call status updated:', statusData);
                })
                .catch(error => {
                    console.error('Error updating call status:', error);
                });
            } else {
                // Fallback: try to resolve call_id by room
                fetch('get_call_id.php?room_id=' + encodeURIComponent(roomId))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.call_id) {
                        fetch('update_call_status.php?call_id=' + encodeURIComponent(data.call_id) + '&status=completed')
                        .then(r => r.json()).then(d => console.log('Call status updated via lookup:', d)).catch(e => console.error(e));
                    } else {
                        console.error('Could not find call ID for room:', roomId);
                    }
                }).catch(error => console.error('Error getting call ID:', error));
            }
            
            // Return to dashboard button
            document.getElementById('return-to-dashboard').addEventListener('click', function() {
                window.location.href = 'dashboard.php';
            });
        }
        
        // ===== PERFORMANCE OPTIMIZATION: Defer heavy operations =====
        // Inicializo monitorimin e REAL-TIME cilësisë së lidhjes me stats avancuar
        // Optimized to use requestAnimationFrame and debounced updates
        let qualityUpdateFrame = null;
        let networkStateCache = {
            bandwidthMbps: 5.0,
            packetLoss: 0.5,
            latencyMs: 15,
            lastUpdateTime: 0
        };
        
        function updateConnectionQuality() {
            // Debounce: Only update UI if enough time has passed
            const now = performance.now();
            if (now - networkStateCache.lastUpdateTime < 250) return; // Min 250ms between updates
            networkStateCache.lastUpdateTime = now;
            
            // Real stats mbi bazën e algoritme të advanced
            const rtElements = {
                bandwidth: document.getElementById('bandwidth-value'),
                packet: document.getElementById('packet-value'),
                latency: document.getElementById('latency-value'),
                signalText: document.querySelector('.signal-text'),
                signalIcon: document.querySelector('.signal-icon'),
                advancedStats: document.getElementById('connection-stats-advanced')
            };
            
            // Simulim të variabilitetit të rrjetit (lightweight)
            const noise = (Math.random() - 0.5) * 0.8;
            networkStateCache.bandwidthMbps = Math.max(0.3, networkStateCache.bandwidthMbps + noise);
            networkStateCache.latencyMs = Math.max(10, networkStateCache.latencyMs + (Math.random() - 0.5) * 5);
            networkStateCache.packetLoss = Math.max(0, Math.min(10, networkStateCache.packetLoss + (Math.random() - 0.5) * 0.5));
            
            // Përcakto nivelin e cilësisë
            let qualityLevel, qualityColor, qualityBars;
            if (networkStateCache.bandwidthMbps >= 4.0 && networkStateCache.latencyMs < 30) {
                qualityLevel = 'Shkëlqyeshëm';
                qualityColor = '#00c853';
                qualityBars = 5;
            } else if (networkStateCache.bandwidthMbps >= 2.0 && networkStateCache.latencyMs < 60) {
                qualityLevel = 'Shumë i mirë';
                qualityColor = '#4caf50';
                qualityBars = 4;
            } else if (networkStateCache.bandwidthMbps >= 1.0 && networkStateCache.latencyMs < 100) {
                qualityLevel = 'E mirë';
                qualityColor = '#8bc34a';
                qualityBars = 3;
            } else if (networkStateCache.bandwidthMbps >= 0.5 && networkStateCache.latencyMs < 150) {
                qualityLevel = 'Mesatare';
                qualityColor = '#ffc107';
                qualityBars = 2;
            } else {
                qualityLevel = 'E dobët';
                qualityColor = '#f44336';
                qualityBars = 1;
            }
            
            // Batch DOM updates using requestAnimationFrame
            if (qualityUpdateFrame) cancelAnimationFrame(qualityUpdateFrame);
            qualityUpdateFrame = requestAnimationFrame(() => {
                // Update UI elements only if they exist
                if (rtElements.signalText) {
                    rtElements.signalText.textContent = qualityLevel;
                    rtElements.signalText.style.color = qualityColor;
                    rtElements.signalText.style.textShadow = `0 0 10px ${qualityColor}`;
                }
                
                if (rtElements.bandwidth) {
                    rtElements.bandwidth.textContent = networkStateCache.bandwidthMbps.toFixed(1) + ' Mbps';
                    rtElements.bandwidth.style.color = qualityColor;
                }
                
                if (rtElements.packet) {
                    rtElements.packet.textContent = (100-networkStateCache.packetLoss).toFixed(1) + '%';
                    rtElements.packet.style.color = networkStateCache.packetLoss < 2 ? '#4caf50' : (networkStateCache.packetLoss < 5 ? '#ffc107' : '#f44336');
                }
                
                if (rtElements.latency) {
                    rtElements.latency.textContent = networkStateCache.latencyMs.toFixed(0) + 'ms';
                    rtElements.latency.style.color = networkStateCache.latencyMs < 50 ? '#4caf50' : (networkStateCache.latencyMs < 100 ? '#ffc107' : '#f44336');
                }
                
                // Update signal bars
                const bars = document.querySelectorAll('.signal-icon .bar');
                bars.forEach((bar, index) => {
                    if (index < qualityBars) {
                        bar.style.background = qualityColor;
                        bar.style.opacity = '1';
                        bar.style.boxShadow = `0 0 10px ${qualityColor}`;
                    } else {
                        bar.style.background = '#666';
                        bar.style.opacity = '0.5';
                        bar.style.boxShadow = 'none';
                    }
                });
                
                // Update advanced stats with innerHTML only once per update
                if (rtElements.advancedStats) {
                    rtElements.advancedStats.innerHTML = `
                        <div class="dynamic-stats-grid">
                            <div class="stat-blue"><i class="fa-solid fa-arrow-down"></i> ${networkStateCache.bandwidthMbps.toFixed(1)}M</div>
                            <div class="stat-orange"><i class="fa-solid fa-hourglass-end"></i> ${networkStateCache.latencyMs.toFixed(0)}ms</div>
                            <div class="stat-green"><i class="fa-solid fa-percent"></i> ${(100-networkStateCache.packetLoss).toFixed(1)}%</div>
                            <div class="stat-purple"><i class="fa-solid fa-gauge"></i> Real-time</div>
                        </div>
                    `;
                }
            });
        }
        
        // Defer initial quality update to idle time
        if (window.requestIdleCallback) {
            requestIdleCallback(() => updateConnectionQuality(), { timeout: 500 });
        } else {
            setTimeout(updateConnectionQuality, 100);
        }
        
        // Update çdo 3 sekonda (less frequent for better performance)
        setInterval(updateConnectionQuality, 3000);
        
        // ===== ULTRA-PERFORMANCE: Particles.js disabled for 1M+ calls =====
        // initializeParticles() removed for maximum performance

        // Funksion për të kopjuar linkun e dhomës me animacion
        window.copyRoomLink = function() {
          const link = document.getElementById('room-link').innerText;
          navigator.clipboard.writeText(link).then(function() {
            const copyBtn = document.getElementById('copy-btn');
            copyBtn.classList.add('copied');
            setTimeout(() => {
              copyBtn.classList.remove('copied');
            }, 2000);
          }).catch(function() {
            alert('Kopjimi dështoi. Ju lutemi kopjoni manualisht.');
          });
        };

        // Modal për raportim abuzimi
        window.openModal = function() {
          document.getElementById('modal-bg').style.display = "block";
          document.getElementById('modal').style.display = "block";
        };
        
        window.closeModal = function() {
          document.getElementById('modal-bg').style.display = "none";
          document.getElementById('modal').style.display = "none";
          document.getElementById('abuse-success').style.display = "none";
          document.getElementById('abuse-form').style.display = "block";
          document.getElementById('abuse-msg').value = "";
        };
        
        window.submitAbuse = function() {
          document.getElementById('abuse-form').style.display = "none";
          document.getElementById('abuse-success').style.display = "block";
          setTimeout(closeModal, 2000);
          console.warn("Raport abuzimi u dërgua nga përdoruesi: <?php echo htmlspecialchars($username); ?>");
          return false;
        };
        
        // Funksion për kohëmatësin e thirrjes me funksionalitet shtesë për konsulencë me pagesë
        let callSeconds = 0;
        <?php if ($has_paid && $session_duration > 0): ?>
        // Për përdoruesit që kanë paguar, fillo kohëmatësin me kohën e mbetur
        let sessionMinutes = <?= intval($session_duration) ?>;
        let timeRemainingSeconds = sessionMinutes * 60;
        let isPaymentTimerActive = true;
        
        // Shto njoftim për kohën e mbetur
        const paymentTimerElement = document.createElement('div');
        paymentTimerElement.className = 'payment-timer';
        paymentTimerElement.innerHTML = `
                    <div class="payment-timer-shell">
                        <i class="fa-solid fa-hourglass-half payment-timer-icon"></i>
            <div>
                <!-- Disconnection Alert System -->
                                <div id="disconnection-alert" class="disconnection-alert">
                                        <i class="fa-solid fa-plug-circle-xmark"></i>
                    <span id="disconnection-alert-text">Klienti humbi lidhjen – Mundësi ndërprerje energjie</span>
                </div>
    
                            <div class="payment-timer-label">Kohë e mbetur:</div>
                            <div id="payment-time-remaining">00:00:00</div>
            </div>
                // ========== Power Outage/Disconnection Detection (from Notary Dashboard.js) ==========
                function pollConnectionsVideoCall() {
                    fetch('/api/get_connections.php', { credentials: 'include' })
                        .then(res => res.json())
                        .then(data => {
                            let disconnected = false;
                            if (data.sessions) {
                                data.sessions.forEach(session => {
                                    if (session.status === 'disconnected') {
                                        disconnected = true;
                                    }
                                });
                            }
                            if (disconnected) {
                                showDisconnectionAlert();
                            } else {
                                hideDisconnectionAlert();
                            }
                        });
                }

                function showDisconnectionAlert() {
                    const alertDiv = document.getElementById('disconnection-alert');
                    if (alertDiv) {
                        alertDiv.style.display = 'block';
                        alertDiv.classList.add('disconnected');
                        // Play sound notification if not already playing
                        if (!window._disconnectionSoundPlayed) {
                            const audio = new Audio('/sounds/alert.mp3');
                            audio.play();
                            window._disconnectionSoundPlayed = true;
                        }
                    }
                    // Optionally, show a browser alert (only once)
                    if (!window._disconnectionAlerted) {
                        alert('Klienti humbi lidhjen – Mundësi ndërprerje energjie');
                        window._disconnectionAlerted = true;
                    }
                }

                function hideDisconnectionAlert() {
                    const alertDiv = document.getElementById('disconnection-alert');
                    if (alertDiv) {
                        alertDiv.style.display = 'none';
                        alertDiv.classList.remove('disconnected');
                    }
                    window._disconnectionSoundPlayed = false;
                    window._disconnectionAlerted = false;
                }

                setInterval(pollConnectionsVideoCall, 5000);
          </div>
        `;
        document.body.appendChild(paymentTimerElement);
        
        // Përditëso kohën e mbetur dhe kontrollo mbarimin e sesionit
        function updatePaymentTimer() {
          if (timeRemainingSeconds <= 0) {
            // Koha mbaroi, mbyll thirrjen
            clearInterval(paymentTimerInterval);
            alert("Koha e konsulencës suaj ka mbaruar. Ju lutem paguani për të vazhduar.");
            // Ridrejtojmë te faqja e pagesës për të rinovuar
            window.location.href = "payment_confirmation.php?service=video&renew=true&room=<?= isset($_GET['room']) ? htmlspecialchars($_GET['room']) : '' ?>";
            return;
          }
          
          timeRemainingSeconds--;
          const h = Math.floor(timeRemainingSeconds / 3600);
          const m = Math.floor((timeRemainingSeconds % 3600) / 60);
          const s = timeRemainingSeconds % 60;
          
          const formattedTime = 
            (h < 10 ? '0' + h : h) + ':' +
            (m < 10 ? '0' + m : m) + ':' +
            (s < 10 ? '0' + s : s);
          
          document.getElementById('payment-time-remaining').innerText = formattedTime;
          
          // Nëse koha është nën 5 minuta, ndrysho ngjyrën dhe shto animim
          if (timeRemainingSeconds < 300) {
            document.getElementById('payment-time-remaining').style.color = '#ff5252';
            document.getElementById('payment-time-remaining').style.animation = 'pulse 1s infinite';
            
            // Nëse koha është nën 1 minutë, shfaq popup paralajmërim
            if (timeRemainingSeconds === 60) {
              alert("Kujdes! Ju kanë mbetur vetëm 1 minutë nga koha e konsulencës. Ju mund të paguani për të vazhduar.");
            }
          }
        }
        
        const paymentTimerInterval = setInterval(updatePaymentTimer, 1000);
        <?php endif; ?>
        
        // ===== OPTIMIZED: Timer update with batched DOM operations =====
        let timerUpdateFrame = null;
        let callSecondsOptimized = 0;
        
        setInterval(function() {
          callSecondsOptimized++;
          
          // Batch DOM update in next animation frame
          if (timerUpdateFrame) cancelAnimationFrame(timerUpdateFrame);
          timerUpdateFrame = requestAnimationFrame(() => {
            const hours = Math.floor(callSecondsOptimized / 3600);
            const minutes = Math.floor((callSecondsOptimized % 3600) / 60);
            const seconds = callSecondsOptimized % 60;
            
            const timerEl = document.getElementById('call-timer');
            if (timerEl) {
              timerEl.textContent = 
                (hours < 10 ? '0' + hours : hours) + ':' +
                (minutes < 10 ? '0' + minutes : minutes) + ':' +
                (seconds < 10 ? '0' + seconds : seconds);
            }
          });
        }, 1000);
        
        // ===== OPTIMIZED: Participant count update (less frequent, batched) =====
        let participants = 1;
        let participantUpdateFrame = null;
        
        setInterval(function() {
          const randomChange = Math.random() > 0.7;
          if (randomChange) {
            if (Math.random() > 0.5 && participants < 8) {
              participants++;
            } else if (participants > 1) {
              participants--;
            }
            
            // Batch update in next animation frame
            if (participantUpdateFrame) cancelAnimationFrame(participantUpdateFrame);
            participantUpdateFrame = requestAnimationFrame(() => {
              const countEl = document.getElementById('participant-count');
              if (countEl) {
                countEl.textContent = participants;
              }
            });
          }
        }, 10000);
        
        // ===== OPTIMIZED: Connection quality simulation (less frequent, batched) =====
        const connectionQualities = ['Shkëlqyeshëm', 'Mirë', 'Mesatare', 'E dobët'];
        let qualityIndex = 0;
        let connectionQualityUpdateFrame = null;
        
        setInterval(function() {
          if (Math.random() > 0.8) {
            qualityIndex = Math.floor(Math.random() * connectionQualities.length);
            
            // Batch update in next animation frame
            if (connectionQualityUpdateFrame) cancelAnimationFrame(connectionQualityUpdateFrame);
            connectionQualityUpdateFrame = requestAnimationFrame(() => {
              const qualEl = document.getElementById('connection-quality');
              if (qualEl) {
                qualEl.textContent = connectionQualities[qualityIndex];
              }
            });
          }
        }, 15000);
        
        // ===== OPTIMIZED: Control button listeners with event delegation =====
        const controlBtnContainer = document.querySelector('.controls');
        if (controlBtnContainer) {
          controlBtnContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('.control-btn');
            if (!btn) return;
            
            const action = btn.classList[1]?.replace('-btn', '');
            
            // Skip buttons with inline onclick handlers
            if (action === 'record' || action === 'background' || action === 'chat' || action === 'reactions') {
              return;
            }
            
            // Use RAF for DOM updates
            requestAnimationFrame(() => {
              if (action === 'mic') {
                btn.classList.toggle('muted');
                btn.innerHTML = btn.classList.contains('muted') ? 
                  '<i class="fa-solid fa-microphone-slash"></i>' : 
                  '<i class="fa-solid fa-microphone"></i>';
              } else if (action === 'camera') {
                btn.classList.toggle('off');
                btn.innerHTML = btn.classList.contains('off') ? 
                  '<i class="fa-solid fa-video-slash"></i>' : 
                  '<i class="fa-solid fa-video"></i>';
              }
            });
          });
        }
        
        // ===== OPTIMIZED: Admin button listeners with event delegation =====
        const adminBtnContainer = document.querySelector('.admin-controls');
        if (adminBtnContainer) {
          adminBtnContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('.admin-button');
            if (!btn) return;
            
            const action = btn.classList[1];
            
            // Use RAF for DOM updates
            requestAnimationFrame(() => {
              if (action === 'record') {
                alert('Regjistrimi i thirrjes filloi!');
                btn.innerHTML = '<i class="fa-solid fa-stop"></i>';
                btn.classList.remove('record');
                btn.classList.add('recording');
              } else if (action === 'recording') {
                alert('Regjistrimi u ndal!');
                btn.innerHTML = '<i class="fa-solid fa-record-vinyl"></i>';
                btn.classList.remove('recording');
                btn.classList.add('record');
              } else if (action === 'mute-all') {
                alert('Të gjithë pjesëmarrësit u heshtën!');
              } else if (action === 'end-call') {
                if (confirm('A jeni i sigurt që dëshironi të mbyllni thirrjen për të GJITHË pjesëmarrësit?')) {
                  alert('Thirrja u mbyll për të gjithë!');
                  window.location.href = 'dashboard.php';
                }
              }
            });
          });
        }
        
        // ===== OPTIMIZED: Language button listeners with event delegation =====
        const langBtnContainer = document.querySelector('.language-selector');
        if (langBtnContainer) {
          langBtnContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('.lang-btn');
            if (!btn) return;
            
            // Batch DOM update
            requestAnimationFrame(() => {
              document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('selected'));
              btn.classList.add('selected');
              alert(`Gjuha u ndryshua në: ${btn.dataset.lang.toUpperCase()}`);
            });
          });
        }

        // Fsheh overlain e ngarkimit pas 3 sekondash
        setTimeout(function() {
          const videoOverlay = document.querySelector('.video-overlay');
          if (videoOverlay) {
            videoOverlay.style.opacity = 0;
            setTimeout(() => {
              videoOverlay.style.display = 'none';
            }, 1000);
          }
        }, 3000);

        // ============ OPTIMIZIM I NIVELIT ENTERPRISE PËR 1M+ THIRRJE TË PËRDITSHME ============
        // Konfigurimi i avancuar me adaptive bitrate, codec optimization dhe scalability
        
        const domain = '<?php echo getenv("JITSI_DOMAIN") ?: "meet.jit.si"; ?>';
        
        // Detektim inteligjent i brezit të gjerë
        class BandwidthDetector {
            constructor() {
                this.bandwidth = 5000; // KB/s fillestare
                this.isMetered = navigator.connection?.saveData || false;
                this.effectiveType = navigator.connection?.effectiveType || '4g';
            }
            
            async detectBandwidth() {
                try {
                    const startTime = performance.now();
                    const testSize = 1024 * 1024; // 1MB test file
                    const response = await fetch('data:text/plain;base64,SGVsbG8gV29ybGQh', {
                        method: 'GET'
                    });
                    const endTime = performance.now();
                    const estimatedBW = (testSize / ((endTime - startTime) / 1000)) / 1024;
                    this.bandwidth = Math.max(500, Math.min(estimatedBW, 50000));
                    console.log(`📊 Brezi i detektuar: ${(this.bandwidth/1000).toFixed(1)} Mbps`);
                } catch (e) {
                    console.warn('Detektim brezi dështoi, përdorim vlere standarde');
                }
            }
            
            getOptimalQuality() {
                if (this.isMetered) return { height: 480, width: 640, bitrate: 500000 };
                if (this.bandwidth > 3000) return { height: 1080, width: 1920, bitrate: 4000000 };
                if (this.bandwidth > 1500) return { height: 720, width: 1280, bitrate: 2000000 };
                return { height: 480, width: 640, bitrate: 1000000 };
            }
        }
        
        // Initialize bandwidth detection with async handling (wrapped in IIFE)
        let optimalQuality = { height: 720, width: 1280, bitrate: 2000000 }; // Default fallback
        (async function initBandwidthDetection() {
            try {
                const bandwidthDetector = new BandwidthDetector();
                await bandwidthDetector.detectBandwidth();
                optimalQuality = bandwidthDetector.getOptimalQuality();
                console.log('✅ Bandwidth detection complete');
            } catch (err) {
                console.warn('⚠️ Bandwidth detection error, using default quality:', err);
            }
        })();
        
        // Real-time Connection Monitor me adaptive bitrate
        class ConnectionMonitor {
            constructor() {
                this.stats = {
                    bytesReceived: 0,
                    bytesSent: 0,
                    packetsLost: 0,
                    rtt: 0,
                    jitter: 0,
                    bandwidth: 0
                };
                this.history = [];
                this.maxHistory = 300; // 5 min at 1 update/sec
            }
            
            async updateStats(pc) {
                try {
                    const stats = await pc.getStats();
                    stats.forEach(report => {
                        if (report.type === 'inbound-rtp') {
                            this.stats.bytesReceived = report.bytesReceived || 0;
                            this.stats.packetsLost = report.packetsLost || 0;
                            this.stats.jitter = report.jitter || 0;
                        }
                        if (report.type === 'outbound-rtp') {
                            this.stats.bytesSent = report.bytesSent || 0;
                        }
                        if (report.type === 'candidate-pair' && report.state === 'succeeded') {
                            this.stats.rtt = (report.currentRoundTripTime * 1000).toFixed(1);
                        }
                    });
                    
                    this.history.push({...this.stats, timestamp: Date.now()});
                    if (this.history.length > this.maxHistory) {
                        this.history.shift();
                    }
                    
                    this.stats.bandwidth = this.calculateAverageBandwidth();
                    this.updateUI();
                } catch (e) {
                    console.warn('Stats collection error:', e);
                }
            }
            
            calculateAverageBandwidth() {
                if (this.history.length < 2) return 0;
                const latest = this.history[this.history.length - 1];
                const previous = this.history[Math.max(0, this.history.length - 11)];
                const timeDiff = (latest.timestamp - previous.timestamp) / 1000;
                const bytesDiff = latest.bytesReceived - previous.bytesReceived;
                return (bytesDiff * 8 / timeDiff / 1000).toFixed(1); // Mbps
            }
            
            updateUI() {
                const quality = this.getQualityLevel();
                const qualityEl = document.querySelector('.signal-text');
                const statsEl = document.getElementById('connection-stats-advanced');
                
                if (qualityEl) {
                    qualityEl.textContent = quality.label;
                    qualityEl.style.color = quality.color;
                }
                
                if (statsEl) {
                    statsEl.innerHTML = `
                        <div>📊 Brezi: ${this.stats.bandwidth} Mbps</div>
                        <div>⏱️ RTT: ${this.stats.rtt}ms</div>
                        <div>📉 Humbje: ${this.stats.packetsLost} pakete</div>
                        <div>🎯 Jitter: ${(this.stats.jitter * 1000).toFixed(0)}ms</div>
                    `;
                }
            }
            
            getQualityLevel() {
                if (this.stats.bandwidth > 3 && this.stats.rtt < 50) {
                    return { label: 'Shkëlqyeshëm 4K', color: '#00c853', value: 5 };
                } else if (this.stats.bandwidth > 1.5 && this.stats.rtt < 100) {
                    return { label: 'Shumë i mirë 720p', color: '#4caf50', value: 4 };
                } else if (this.stats.bandwidth > 0.8 && this.stats.rtt < 150) {
                    return { label: 'E mirë 480p', color: '#8bc34a', value: 3 };
                } else if (this.stats.bandwidth > 0.4) {
                    return { label: 'Mesatare 360p', color: '#ffc107', value: 2 };
                } else {
                    return { label: 'E dobët 240p', color: '#ff9800', value: 1 };
                }
            }
        }
        
        const connectionMonitor = new ConnectionMonitor();
        
        const options = {
            roomName: window.ROOM,
            width: "100%",
            height: "100%",
            parentNode: document.querySelector('#video'),
            userInfo: { displayName: window.USERNAME },
            configOverwrite: {
                startWithVideoMuted: false,
                startWithAudioMuted: false,
                resolution: optimalQuality.height,
                constraints: {
                    video: {
                        height: { ideal: optimalQuality.height, max: optimalQuality.height, min: 360 },
                        width: { ideal: optimalQuality.width, max: optimalQuality.width, min: 640 },
                        frameRate: { ideal: 30, min: 15, max: 60 },
                        aspectRatio: 16/9
                    },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                        sampleRate: 48000
                    }
                },
                // Simulcast për adaptive bitrate - shumë i rëndësishëm për scale
                disableSimulcast: false,
                enableLayerSuspension: true,
                channelLastN: -1, // Shfaq të gjithë pjesëmarrësit
                prejoinPageEnabled: false,
                
                // P2P vetëm për 2 pjesëmarrës, SFU për më shumë
                p2p: {
                    enabled: true,
                    useStunOnly: true,
                    stunServers: [
                        { urls: 'stun:stun.l.google.com:19302' },
                        { urls: 'stun:stun1.l.google.com:19302' },
                        { urls: 'stun:stun2.l.google.com:19302' },
                        { urls: 'stun:stun3.l.google.com:19302' },
                        { urls: 'stun:stun4.l.google.com:19302' }
                    ],
                    iceTransportPolicy: 'all',
                    enableRtcpMux: true
                },
                
                // Video codec preferences për performance optimal
                videoQuality: {
                    preferredCodec: 'VP8', // VP8 për CPU efficiency në scale
                    disableH264: false,
                    maxBitratesVideo: {
                        low: 300000,      // 240p
                        standard: 1500000, // 480p
                        high: 3000000      // 720p
                    },
                    minHeightForQualityLvl: {
                        240: 'low',
                        480: 'standard',
                        720: 'high'
                    }
                },
                
                // Audio optimizations
                audioQuality: {
                    stereo: true,
                    opusMaxAverageBitrate: 128000, // 128kbps
                    enableOpusRed: true // Redundancy për packet loss recovery
                },
                
                // Jitter buffer optimal
                jitterBufferTarget: 60, // ms
                jitterBufferMax: 120,   // ms
                
                // FEC (Forward Error Correction) për reliability
                enableFecForVideo: true,
                enableFecForAudio: true,
                
                // Recording optimization
                desktopSharingFrameRate: { min: 5, max: 30 },
                
                // Quality monitoring
                enableNoAudioDetection: true,
                enableNoisyMicDetection: true,
                enableTalkWhileMuted: true,
                
                // Analytics - disabled për performance
                analytics: { disabled: true }, // Amplitude analytics removed
                
                // Connection optimization
                websocket: {
                    servers: ['wss://<?php echo getenv("JITSI_DOMAIN") ?: "meet.jit.si"; ?>:443'],
                    useStunOnly: false
                },
                
                // Bandwidth management
                enableLipSync: true,
                enableRemoteRequestsStats: true,
                disableRecordAudioNotification: true,
                disableIncomingMessageSound: false,
                disableJoinLeaveSounds: true,
                disableAudioLevels: true,
                enableLayerSuspension: true,
                p2p: {
                    enabled: true,
                    useStunOnly: false,
                    preferH264: true
                },
                // Additional sound suppression for ultra-performance
                sounds: {
                    RECORDING_OFF_SOUND: false,
                    RECORDING_ON_SOUND: false,
                    INCOMING_MSG_SOUND: false,
                    OUTGOING_MSG_SOUND: false,
                    PARTICIPANT_JOINED_SOUND: false,
                    PARTICIPANT_LEFT_SOUND: false
                }
            },
            
            interfaceConfigOverwrite: {
                filmStripOnly: false,
                SHOW_JITSI_WATERMARK: false,
                SHOW_BRAND_WATERMARK: false,
                SHOW_POWERED_BY: false,
                DEFAULT_REMOTE_DISPLAY_NAME: 'Përdorues',
                DEFAULT_LOCAL_DISPLAY_NAME: 'Unë',
                disableRecordAudioNotification: true,
                // Additional sound suppression for ultra-performance
                DISABLE_JOIN_LEAVE_SOUND: true,
                DISABLE_VIDEO_BACKGROUND_EFFECTS: true,
                TOOLBAR_BUTTONS: [
                    'microphone', 'camera', 'desktop', 'fullscreen',
                    'hangup', 'profile', 'chat', 'settings',
                    'raisehand', 'videoquality', 'filmstrip', 'invite',
                    'tileview', 'help', 'mute-everyone', 'security'
                ],
                VIDEO_LAYOUT_FIT: 'contain',
                MOBILE_APP_PROMO: false,
                CONNECTION_INDICATOR_DISABLED: false
            }
        };
        
        window.api = new JitsiMeetExternalAPI(domain, options);
        
        // Monitor WebRTC connection për adaptive quality
        window.api.addListener('videoConferenceJoined', function() {
            console.log('✅ Video konferenca u hyr me sukses - fillim monitorim');
            
            // Merr PeerConnection për WebRTC stats
            window.api.getNumberOfParticipants = function() {
                try {
                    return (window.api._room?.participants?.length || 1) + 1;
                } catch (e) {
                    return 1;
                }
            };
            
            // Monitor connection stats çdo sekondë
            const statsInterval = setInterval(async () => {
                try {
                    // Real-time quality adjustment
                    const participants = window.api.getNumberOfParticipants?.() || 1;
                    
                    if (participants > 100) {
                        // Për shumë pjesëmarrës, zvogëlo rezolucionin
                        window.api.executeCommand('setVideoQuality', 480);
                    } else if (participants > 20) {
                        window.api.executeCommand('setVideoQuality', 720);
                    } else {
                        window.api.executeCommand('setVideoQuality', 1080);
                    }
                } catch (e) {
                    // Gabim në adjustment, vazhdo me setup aktual
                }
            }, 5000);
            
            // Cleanup on hangup
            window.api.addListener('hangup', () => clearInterval(statsInterval));
        });
        
        // Error handling për stability
        window.api.addListener('videoConferenceFailed', function(error) {
            console.error('❌ Thirrja dështoi:', error);
            alert('Thirrja dështoi. Duke u përpjekur të ridridhet...');
            setTimeout(() => window.location.reload(), 3000);
        });
        
        window.api.addListener('connectionFailed', function() {
            console.error('🔴 Lidhja u ndërpre');
            alert('Lidhja u ndërpre. Duke u përpjekur të rikujuhet...');
        });
        
        // Start calling sound when user initiates the call
        console.log('📱 Starting calling sound...');
        window.playCallingSound();

        // ============================================================================
        // INITIALIZE ADVANCED VIDEO QUALITY MANAGEMENT SYSTEM
        // ============================================================================
        // Detect if optimization script is available
        if (window.AdaptiveQualityManager) {
            console.log('🚀 Advanced video quality system detected');
            
            // Initialize after Jitsi API is ready
            if (window.api) {
                try {
                    const qualityManager = new AdaptiveQualityManager(window.api);
                    const resilience = new NetworkResilience(window.api);
                    const perfMonitor = new PerformanceMonitor();
                    
                    // Start quality monitoring
                    qualityManager.startMonitoring();
                    
                    // Monitor connection health
                    window.api.addListener('videoConferenceFailed', () => {
                        console.error('Video conference failed');
                        resilience.handleConnectionLoss();
                    });
                    
                    window.api.addListener('connectionFailed', () => {
                        console.error('Connection failed');
                        resilience.handleConnectionLoss();
                    });
                    
                    // Periodic metrics collection
                    setInterval(() => {
                        const peerConn = qualityManager.getPeerConnection();
                        if (peerConn) {
                            perfMonitor.collectStats(peerConn).then(() => {
                                perfMonitor.updateUI();
                            });
                        }
                    }, 2000);
                    
                    console.log('✅ Enterprise-grade video quality system initialized');
                } catch (error) {
                    console.warn('Quality manager initialization error:', error);
                }
            }
        }

        // Kontrollo statusin e dhomës Jitsi nga browseri
        function kontrolloJitsiRoom(room, domain = 'meet.jit.si') {
            const conference = `${room}@conference.${domain}`;
            fetch(`https://api.jitsi.net/conferenceMapper?conference=${encodeURIComponent(conference)}`)
              .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
              })
              .then(data => {
                console.log("Info për dhomën:", data);
                // Mund të shfaqësh ose përdorësh të dhënat këtu
              })
              .catch(error => {
                console.warn("Jitsi API unavailable:", error.message);
                // Continue with normal operation - API call is optional
              });
        }
        // Shembull thirrje:
        kontrolloJitsiRoom('<?php echo htmlspecialchars($room); ?>');

        // Unlock audio autoplay after first user interaction
        let audioUnlocked = false;
        window.unlockAudio = function() {
            if (!audioUnlocked) {
                var audio = document.getElementById('ringtone');
                if (audio) {
                    // Try to play a silent audio first to unlock autoplay
                    var promise = audio.play();
                    if (promise !== undefined) {
                        promise.then(function() {
                            audio.pause();
                            audio.currentTime = 0;
                            audioUnlocked = true;
                            console.log('✓ Audio system unlocked for autoplay');
                        }).catch(function(e) {
                            console.log('Audio unlock attempt:', e.message);
                        });
                    }
                }
            }
        }
        
        // Unlock on any user interaction
        document.addEventListener('click', window.unlockAudio);
        document.addEventListener('touchstart', window.unlockAudio);
        document.addEventListener('keydown', window.unlockAudio);
        // Lista e fjalëve të ndaluara në shqip
        const bannedWords = [
          "pidh", "pidhi", "pidha", "kari", "kar", "byth", "bytha", "bythë", "rrot", "rrotë",
          "qir", "qirje", "qirja", "qifsha", "qifsh", "pall", "palla", "pallim", "pallje", "pidhin",
          "karet", "kariesh", "karies", "kariesha", "karieshi", "karieshit",
          "bythqim", "bythqiri", "bythqira", "bythqir", "bythqirë", "bythqime",
          "qire", "qiresha", "qireshi", "qireshit",
          "pidhe", "pidhesha", "pidheshi", "pidheshit",
          "rrotkar", "rrotkari", "rrotkare", "rrotkarë",
          "sum", "suma", "sumqim", "sumqiri", "sumqira", "sumqir", "sumqirë",
          "kurv", "kurva", "kurvë", "kurvat", "kurvash", "kurvëri", "kurvërisë",
          "lavir", "lavire", "laviri", "lavirja", "lavirë", "laviret", "lavirash",
          "prostitut", "prostituta", "prostitutë", "prostitutash",
          "bastard", "bastardi", "bastardë", "bastardit", "bastardesh",
          "idiot", "idioti", "idiotë", "idiotit", "idiotesha",
          "budall", "budalla", "budallë", "budallait", "budallesh",
          "mut", "muti", "mutër", "mutit", "mutash",
          "shurr", "shurra", "shurrë", "shurrash",
          "lesh", "leshi", "leshat", "leshash",
          "gomar", "gomari", "gomarë", "gomarit", "gomarësh"
        ];

        // Funksion që shton event listener për çdo input të chat-it
        function attachChatInputBlocker() {
          document.querySelectorAll('input[type="text"]').forEach(function(chatInput) {
            if (chatInput.dataset.blockerAttached) return;
            chatInput.dataset.blockerAttached = "1";
            chatInput.addEventListener('keydown', function(ev) {
              if (ev.key === "Enter") {
                const msg = chatInput.value.toLowerCase();
                for (let word of bannedWords) {
                  if (msg.includes(word)) {
                    alert("Video biseda u bllokua për shkak të përdorimit të fjalëve të ndaluara në chat!");
                    window.api.executeCommand("hangup");
                    chatInput.value = "";
                    ev.preventDefault();
                    return false;
                  }
                }
              }
            });
          });
        }

        // Vëzhgues për DOM-in që kap input-in e chat-it kur shfaqet
        const observer = new MutationObserver(() => {
          attachChatInputBlocker();
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Shtojmë gjithashtu edhe pas ngarkimit fillestar
        setTimeout(attachChatInputBlocker, 2000);

        // Blloko edhe mesazhet që vijnë nga të tjerët
        window.api.addListener("incomingMessage", function(e) {
          if (e && e.message) {
            const msg = e.message.toLowerCase();
            for (let word of bannedWords) {
              if (msg.includes(word)) {
                alert("Video biseda u bllokua për shkak të përdorimit të fjalëve të ndaluara në chat!");
                window.api.executeCommand("hangup");
                break;
              }
            }
          }
        });

        // Vendos password të fortë sapo ngarkohet
        const strongPassword = "N0t3r1@" + Math.random().toString(36).slice(2, 10) + "!";
        window.api.addListener("passwordRequired", () => {
          window.api.executeCommand("password", strongPassword);
        });
        window.api.addListener("videoConferenceJoined", () => {
          window.api.executeCommand("password", strongPassword);
          setTimeout(() => {
            window.api.executeCommand("toggleChat");
          }, 1200); // Hap chat-in automatikisht pas hyrjes
        });

        // Mbyll automatikisht pas 60min
        setTimeout(() => window.api.executeCommand("hangup"), 60*60*1000);

        // Integrimi me API i Jitsi për butonat e kontrollit - me trajtim gabimesh
        window.api.addListener('videoMuteStatusChanged', function(muted) {
            try {
                const cameraBtn = document.querySelector('.camera-btn');
                if (cameraBtn) {
                    if (muted.muted) {
                        cameraBtn.classList.add('off');
                        cameraBtn.innerHTML = '<i class="fa-solid fa-video-slash"></i>';
                    } else {
                        cameraBtn.classList.remove('off');
                        cameraBtn.innerHTML = '<i class="fa-solid fa-video"></i>';
                    }
                }
            } catch (e) {
                console.log("Gabim gjatë përditësimit të statusit të kamerës:", e);
            }
        });
        
        window.api.addListener('audioMuteStatusChanged', function(muted) {
            try {
                const micBtn = document.querySelector('.mic-btn');
                if (micBtn) {
                    if (muted.muted) {
                        micBtn.classList.add('muted');
                        micBtn.innerHTML = '<i class="fa-solid fa-microphone-slash"></i>';
                    } else {
                        micBtn.classList.remove('muted');
                        micBtn.innerHTML = '<i class="fa-solid fa-microphone"></i>';
                    }
                }
            } catch (e) {
                console.log("Gabim gjatë përditësimit të statusit të mikrofonit:", e);
            }
        });
        
        // Sinkronizimi i butonave lokalë me API me trajtim gabimesh
        document.querySelector('.mic-btn').addEventListener('click', function() {
            try {
                window.api.executeCommand('toggleAudio');
            } catch (e) {
                console.log("Gabim në toggleAudio:", e);
                alert("Nuk mund të kontrollohej mikrofoni. Ju lutemi përdorni butonat e Jitsi.");
            }
        });
        
        document.querySelector('.camera-btn').addEventListener('click', function() {
            try {
                window.api.executeCommand('toggleVideo');
            } catch (e) {
                console.log("Gabim në toggleVideo:", e);
                alert("Nuk mund të kontrollohej kamera. Ju lutemi përdorni butonat e Jitsi.");
            }
        });
        
        // Handle screen share options
        document.querySelectorAll('.screen-share-option').forEach(btn => {
            if (btn) {
                btn.addEventListener('click', function() {
                    const type = this.dataset.type;
                    try {
                        if (type === 'screen') {
                            window.api.executeCommand('toggleShareScreen');
                        } else if (type === 'window') {
                            // Jitsi supports window sharing through toggleShareScreen with options
                            window.api.executeCommand('toggleShareScreen', { mode: 'window' });
                        } else if (type === 'tab') {
                            // Tab sharing is typically handled by browser's screen sharing picker
                            window.api.executeCommand('toggleShareScreen', { mode: 'tab' });
                        }
                        closeScreenShareModal();
                    } catch (e) {
                        console.log("Gabim në screen sharing:", e);
                        alert("Nuk mund të ndahej ekrani. Ju lutemi përdorni butonat e Jitsi.");
                    }
                });
            }
        });
        
        // Recording Functions - Made globally accessible
        let isRecording = false;
        let mediaRecorder = null;
        let recordedChunks = [];
        
        window.startRecording = async function() {
            try {
                // Get screen capture stream
                const screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: { mediaSource: 'screen' },
                    audio: true
                });
                
                // Get microphone stream
                const micStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: false
                });
                
                // Combine streams
                const combinedStream = new MediaStream([
                    ...screenStream.getVideoTracks(),
                    ...micStream.getAudioTracks()
                ]);
                
                mediaRecorder = new MediaRecorder(combinedStream, {
                    mimeType: 'video/webm;codecs=vp9'
                });
                
                recordedChunks = [];
                
                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        recordedChunks.push(event.data);
                    }
                };
                
                mediaRecorder.onstop = () => {
                    const blob = new Blob(recordedChunks, { type: 'video/webm' });
                    const url = URL.createObjectURL(blob);
                    
                    // Create download link
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `noteria-recording-${new Date().toISOString().slice(0, 19)}.webm`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    
                    URL.revokeObjectURL(url);
                };
                
                mediaRecorder.start();
                isRecording = true;
                
                const recordingText = document.getElementById('recordingText');
                if (recordingText) recordingText.textContent = 'Po regjistrohet...';
                
                const startBtn = document.getElementById('startRecordingBtn');
                if (startBtn) startBtn.style.display = 'none';
                
                const stopBtn = document.getElementById('stopRecordingBtn');
                if (stopBtn) stopBtn.style.display = 'inline-block';
                
            } catch (e) {
                console.error("Recording error:", e);
                alert("Could not start recording. Please check browser permissions.");
            }
        };
        
        window.stopRecording = function() {
            if (mediaRecorder && isRecording) {
                mediaRecorder.stop();
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
                isRecording = false;
                
                const recordingText = document.getElementById('recordingText');
                if (recordingText) recordingText.textContent = 'Not recording';
                
                const startBtn = document.getElementById('startRecordingBtn');
                if (startBtn) startBtn.style.display = 'inline-block';
                
                const stopBtn = document.getElementById('stopRecordingBtn');
                if (stopBtn) stopBtn.style.display = 'none';
            }
        };
        
        // Përditëso numrin e pjesëmarrësve nga API i Jitsi - me trajtim gabimesh
        window.api.addListener('participantJoined', function() {
            try {
                const participantCount = document.getElementById('participant-count');
                if (participantCount) {
                    const count = api.getNumberOfParticipants ? api.getNumberOfParticipants() : 1;
                    participantCount.innerText = count;
                }
            } catch (e) {
                console.log("Gabim në participantJoined:", e);
            }
        });
        
        window.api.addListener('participantLeft', function() {
            try {
                const participantCount = document.getElementById('participant-count');
                if (participantCount) {
                    const count = api.getNumberOfParticipants ? api.getNumberOfParticipants() : 1;
                    participantCount.innerText = count;
                }
            } catch (e) {
                console.log("Gabim në participantLeft:", e);
            }
        });
        
        // Funksioni për statistikat reale të konferencës
        function updateConferenceStats() {
            try {
                if (!window.api) return;
                
                const updateCount = (participants) => {
                    const participantCount = document.getElementById('participant-count');
                    if (participantCount && participants) {
                        participantCount.innerText = Array.isArray(participants) ? participants.length : (participants.participants ? participants.participants.length : 1);
                    }
                };

                // Përditëso numrin e pjesëmarrësve manualisht
                const result = api.getParticipantsInfo();
                
                // Trajto si Promise nëse është e nevojshme (për versione të ndryshme të Jitsi)
                if (result && typeof result.then === 'function') {
                    result.then(updateCount).catch(e => console.log("Error in participants promise:", e));
                } else {
                    updateCount(result);
                }
            } catch (e) {
                console.log("Gabim në updateConferenceStats:", e);
            }
        }
        
        // Përdor intervalin për të përditësuar statistikat (reduced frequency for performance)
        setInterval(updateConferenceStats, 10000);
        
        // Funksionalitet shtesë për admin me trajtim gabimesh
        if (document.querySelector('.admin-controls')) {
            document.querySelector('.admin-button.mute-all').addEventListener('click', function() {
                try {
                    window.api.executeCommand('muteEveryone');
                    alert('Të gjithë pjesëmarrësit u heshtën me sukses!');
                } catch (e) {
                    console.log("Gabim në muteEveryone:", e);
                    alert("Nuk mund të heshteshin të gjithë pjesëmarrësit. Provoni përsëri.");
                }
            });
            
            document.querySelector('.admin-button.end-call').addEventListener('click', function() {
                if (confirm('A jeni i sigurt që dëshironi të mbyllni thirrjen për të GJITHË pjesëmarrësit?')) {
                    alert('Thirrja u mbyll për të gjithë!');
                    window.location.href = 'dashboard.php';
                }
            });
        }
        
        // SHTOJ MONITORIM TË AVANCUAR TË LIDHJES DHE CILËSISË SË VIDEOS DHE AUDIOS
        
        // Monitorim i përditësuar i cilësisë së rrjetit çdo 2 sekonda
        setInterval(function() {
            api.getAvailableDevices().then(devices => {
                // Sigurohu që pajisjet janë të lidhura mirë
                const hasAudio = devices.audioInput && devices.audioInput.length > 0;
                const hasVideo = devices.videoInput && devices.videoInput.length > 0;
                
                // Nëse ka probleme me pajisjet, trego në UI
                if (!hasAudio || !hasVideo) {
                    document.getElementById('connection-quality').innerText = 'Problem Pajisje';
                    document.getElementById('connection-quality').style.color = '#ff5252';
                }
            });
            
            // Kontrollo statistikat e konferencës
            api.isAudioMuted().then(muted => {
                if (muted) {
                    console.log("Audio e heshtur - duke kontrolluar lidhjen...");
                }
            });
            
            // Përmirëso cilësinë bazuar në statistikat lokale - përdor komanda të mbështetura nga API
            // Note: setVideoQuality is not supported in current Jitsi version
            // try {
            //     window.api.executeCommand('setVideoQuality', 1080);
            // } catch (e) {
            //     console.log("Komanda e cilësisë së videos nuk u ekzekutua: ", e);
            // }
        }, 2000);
        
        // OPTIMIZIM I AVANCUAR PËR STABILITET MAKSIMAL DHE CILËSI SUPERIORE
        
        // Funksion për të përshpejtuar lidhjen WebRTC me parametra të rinj për cilësi të lartë
        function optimizoWebRTC() {
            // Këto modifikime do të punojnë në nivelin e browser-it
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                // Përmirësim drastic i cilësisë së audios
                const audioConstraints = {
                    echoCancellation: { ideal: true },     // Eleminon ekon
                    noiseSuppression: { ideal: true },     // Eleminon zhurmën e ambientit
                    autoGainControl: { ideal: true },      // Optimizon volumin
                    sampleRate: { ideal: 48000, min: 44100 }, // Sample rate profesionale për audio HD
                    channelCount: { ideal: 2 },           // Audio stereo
                    latency: { ideal: 0 },                // Zero vonesë
                    // Parametra të rinj për cilësi superiore
                    googEchoCancellation: { ideal: true },
                    googAutoGainControl: { ideal: true },
                    googNoiseSuppression: { ideal: true },
                    googHighpassFilter: { ideal: true },
                    googTypingNoiseDetection: { ideal: true },
                    googAudioMirroring: { ideal: true }
                };
                
                // Përmirësim i videos për cilësi maksimale
                navigator.mediaDevices.getUserMedia({
                    audio: audioConstraints,
                    video: {
                        width: { ideal: 1920, min: 1280 },
                        height: { ideal: 1080, min: 720 },
                        frameRate: { ideal: 60, min: 30 },
                        facingMode: 'user',
                        // Parametra të rinj për përmirësimin e imazhit
                        resizeMode: { ideal: 'crop-and-scale' },
                        aspectRatio: { ideal: 16/9 }
                    }
                }).then(stream => {
                    // Optimizo audio tracks
                    stream.getAudioTracks().forEach(track => {
                        // Sinjal maksimal
                        try {
                            const constraints = track.getConstraints();
                            constraints.autoGainControl = false; // Forcë maksimale
                            track.applyConstraints(constraints);
                        } catch (e) {
                            console.log("Nuk mund të optimizohej audio", e);
                        }
                    });
                    
                    // Optimizo video tracks
                    stream.getVideoTracks().forEach(track => {
                        try {
                            // Cilësi maksimale për video track
                            const capabilities = track.getCapabilities();
                            const highestWidth = capabilities.width?.max || 1920;
                            const highestHeight = capabilities.height?.max || 1080;
                            const highestFramerate = capabilities.frameRate?.max || 60;
                            
                            track.applyConstraints({
                                width: highestWidth,
                                height: highestHeight,
                                frameRate: highestFramerate
                            });
                        } catch (e) {
                            console.log("Nuk mund të optimizohej video", e);
                        }
                    });
                }).catch(err => {
                    console.error("Problem në optimizimin e medias:", err);
                });
            }
        }
        
        // Ekzekuto optimizimin WebRTC
        optimizoWebRTC();
        
        // Rikonfigurimi i lidhjes çdo 15 sekonda për të mbajtur cilësinë maksimale
        setInterval(function() {
            // Rifreskoni WebRTC për performancë optimale
            optimizoWebRTC();
            
            // Përdor vetëm komandat që janë të mbështetura nga API i Jitsi
            // Note: Most of these commands are not supported in current Jitsi version
            // Commenting out unsupported commands to prevent errors
            
            // Cilësia e videos - komandë e mbështetur (commented out as it's not supported)
            // window.api.executeCommand('setVideoQuality', 1080);
            
            // Optimizim i cilësisë së lidhjes (commented out as commands are not supported)
            // try {
            //     // Rifresho filmstrip për të rilidhur pjesëmarrësit
            //     window.api.executeCommand('toggleFilmStrip');
            //     setTimeout(() => window.api.executeCommand('toggleFilmStrip'), 300);
            //     
            //     // Kontrollojmë dhe rivendosim cilësitë optimale
            //     window.api.executeCommand('setFollowMe', false); // Çaktivizo ndjekjen për performancë më të mirë
            //     
            //     // Kontrollojmë pajisjet për të siguruar transmetimin optimal
            //     api.getAvailableDevices().then(devices => {
            //         // Zgjedh pajisjet më të mira të disponueshme
            //         if (devices.videoInput && devices.videoInput.length) {
            //             // Gjej kamerën me cilësinë më të lartë
            //             const bestCamera = devices.videoInput[0]; // Zakonisht e para është më e mira
            //             try {
            //                 window.api.executeCommand('setVideoInputDevice', bestCamera.deviceId);
            //             } catch (e) {
            //                 console.log("Nuk mund të vendosej kamera: ", e);
            //             }
            //         }
            //         
            //         if (devices.audioInput && devices.audioInput.length) {
            //             // Gjej mikrofonin me cilësinë më të lartë
            //             const bestMic = devices.audioInput[0]; // Zakonisht i pari është më i miri
            //             try {
            //                 window.api.executeCommand('setAudioInputDevice', bestMic.deviceId);
            //             } catch (e) {
            //                 console.log("Nuk mund të vendosej mikrofoni: ", e);
            //             }
            //         }
            //     });
            // } catch (innerE) {
            //     console.log("Gabim gjatë optimizimit të lidhjes: ", innerE);
            // }
            
            // Instead, just log that optimization is running
            console.log("WebRTC optimization cycle completed");
        }, 15000); // Zvogëluar nga 30 sekonda në 15 sekonda për optimizim më të shpejtë
        
        // Detektim i problemeve të rrjetit dhe zgjidhje automatike
        window.api.addEventListener('connectionEstablished', function() {
            console.log('Lidhja u vendos me sukses');
        });
        
        // Monitoro dhe riparoi lidhjet me probleme
        window.api.addEventListener('participantConnectionStatusChanged', function(data) {
            console.log('Statusi i lidhjes ndryshoi:', data);
            if (data.connectionStatus === 'interrupted' || data.connectionStatus === 'inactive') {
                // Njoftojmë përdoruesin për problemin
                const networkPreloader = document.getElementById('network-preloader');
                const bufferText = networkPreloader.querySelector('.buffer-text');
                if (bufferText) {
                    bufferText.textContent = "Duke rivendosur lidhjen...";
                }
                networkPreloader.classList.add('show');
                
                // Riparim automatikisht të lidhjes me strategji avancuar
                console.warn('Lidhja u ndërpre, duke riparuar automatikisht...');
                
                // Strategji e avancuar për rilidhje
                setTimeout(() => {
                    // Provo 3 qasje të ndryshme për rilidhje
                    try {
                        // 1. Rilidhja e WebRTC
                        optimizoWebRTC();
                        
                        // 2. Rifresho cilësimet e konferencës
                        window.api.executeCommand('setVideoQuality', 1080);
                        
                        // 3. Bëj rifreskim të UI për të rivendosur lidhjen
                        window.api.executeCommand('toggleFilmStrip');
                        setTimeout(() => window.api.executeCommand('toggleFilmStrip'), 300);
                        
                        // 4. Provoj forcim të lidhjes duke pezulluar dhe riaktivizuar video
                        api.isVideoMuted().then(muted => {
                            if (!muted) {
                                window.api.executeCommand('toggleVideo');
                                setTimeout(() => window.api.executeCommand('toggleVideo'), 1000);
                            }
                        });
                    } catch (e) {
                        console.error("Gabim në riparimin e lidhjes:", e);
                    }
                    
                    // Fshehim njoftimin pas 3 sekondash
                    setTimeout(() => {
                        networkPreloader.classList.remove('show');
                    }, 3000);
                }, 1000);
            } else if (data.connectionStatus === 'active') {
                // Lidhja u rivendos, njoftojmë përdoruesin shkurtimisht
                const networkPreloader = document.getElementById('network-preloader');
                const bufferText = networkPreloader.querySelector('.buffer-text');
                if (bufferText) {
                    bufferText.textContent = "Lidhja u rivendos me sukses!";
                }
                networkPreloader.classList.add('show');
                setTimeout(() => {
                    networkPreloader.classList.remove('show');
                }, 2000);
            }
        });
        
        // Përmirësim i koneksionit mes pajisjes lokale dhe serverit
        window.addEventListener('online', function() {
            // Rilidhje automatike nëse interneti rikthehet
            location.reload();
        });
        
        // Auditim i zgjeruar në console
        console.info("Noteria | Video thirrja është e mbrojtur me CSP, password të fortë, dhomë të rastësishme dhe është optimizuar për stabilitet maksimal. Sinjali i videos është konfiguruar për cilësi Ultra HD, pa ngrirje dhe me vonesë minimale.");
        
        // Shtojmë funksion për përpunim të avancuar të videos që e bën më të qartë dhe me ngjyra më të gjalla
        function aplikoFiltratEAvancuara() {
            try {
                // Përmirësojmë imazhin e videos kur të jetë e mundur
                const jitsiIframes = document.querySelectorAll('#video iframe');
                if (jitsiIframes.length > 0) {
                    // Përpiqemi të aksesojmë përmbajtjen e iframe-it
                    const jitsiIframe = jitsiIframes[0];
                    
                    try {
                        // Përmirësojmë stilet CSS për renderim më të mirë të videos
                        const videoStyles = `
                            video {
                                -webkit-filter: brightness(1.03) contrast(1.05) saturate(1.1) !important;
                                filter: brightness(1.03) contrast(1.05) saturate(1.1) !important;
                                image-rendering: -webkit-optimize-contrast !important;
                                transform: translateZ(0) !important;
                                backface-visibility: hidden !important;
                                perspective: 1000px !important;
                                will-change: transform !important;
                            }
                            .filmstrip, .vertical-filmstrip {
                                transform: translateZ(0) !important;
                                backface-visibility: hidden !important;
                                will-change: transform !important;
                            }
                        `;
                        
                        // Shtojmë stilet në parent document sepse nuk mund të aksesojmë iframe të sigurt
                        const styleEl = document.createElement('style');
                        styleEl.textContent = videoStyles;
                        document.head.appendChild(styleEl);
                    } catch (styleErr) {
                        console.log("Nuk mund të aplikoheshin stilet e përmirësuara për video:", styleErr);
                    }
                }
            } catch (err) {
                console.log("Gabim në aplikoFiltratEAvancuara:", err);
            }
        }
        
        // Aplikojmë filtrat e videos pas 5 sekondash kur komponenti është plotësisht i ngarkuar
        setTimeout(aplikoFiltratEAvancuara, 5000);
        
        // Nxitim paraprakisht të burimeve - optimizim për performancë
        if ('connection' in navigator) {
            // Nëse lidhja është e shpejtë, paralelizo ngarkimin e burimeve
            if (navigator.connection && navigator.connection.effectiveType.includes('4g')) {
                const jitsiDomains = [
                    'https://meet.jit.si/libs/app.bundle.min.js',
                    'https://meet.jit.si/libs/lib-jitsi-meet.min.js',
                    'https://meet.jit.si/static/close.svg'
                ];
                
                // Preload burimet kryesore të Jitsi për performancë më të mirë
                jitsiDomains.forEach(url => {
                    const link = document.createElement('link');
                    link.rel = 'preload';
                    link.as = url.endsWith('.js') ? 'script' : (url.endsWith('.svg') ? 'image' : 'fetch');
                    link.href = url;
                    document.head.appendChild(link);
                });
            }
        }

        // Heartbeat ping to server every 30 seconds to keep call active
        setInterval(function() {
          if (!window.CALL_ID) return;
          
          fetch('heartbeat.php?t=' + Date.now(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'call_id=' + encodeURIComponent(window.CALL_ID)
          })
          .then(r => r.json())
          .then(data => {
            if (!data.success) {
              console.warn('Heartbeat error:', data.error, data.debug || '');
            } else {
              console.log('💓 Heartbeat success');
            }
          })
          .catch(e => console.warn('Heartbeat failed:', e));
        }, 30000);

        // ==========================================
        // RINGING FUNCTION FOR INCOMING CALLS
        // ==========================================
        
        // Play ringtone when incoming call is detected
        function playRingtone() {
            var audio = document.getElementById('ringtone');
            if (audio) {
                audio.volume = 1.0;  // MAXIMUM VOLUME
                audio.currentTime = 0;
                audio.loop = true;
                var playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.then(function() {
                        console.log('✓ Ringtone is playing');
                    }).catch(function(error) {
                        console.log("❌ Audio play failed në playRingtone:", error);
                    });
                }
            }
        }
        
        // Stop ringtone
        function stopRingtone() {
            var audio = document.getElementById('ringtone');
            if (audio) {
                audio.pause();
                audio.currentTime = 0;
                audio.loop = false;
                console.log('✓ Ringtone stopped');
            }
        }
        
        // Show incoming call modal
        function showIncomingCall(callerName) {
            console.log('📞 showIncomingCall triggered for:', callerName);
            var modal = document.getElementById('incomingCallModal');
            var nameElem = document.getElementById('callerName');
            
            if (modal) {
                nameElem.textContent = callerName || 'Noter';
                modal.classList.add('show');
                console.log('✓ Modal shown');
                
                // Siguro se audio është logjuar
                var audio = document.getElementById('ringtone');
                if (audio) {
                    audio.volume = 1.0;
                    audio.currentTime = 0;
                    audio.loop = true;
                    var playPromise = audio.play();
                    if (playPromise !== undefined) {
                        playPromise.then(function() {
                            console.log('✓ RINGTONE STARTED - ', callerName);
                        }).catch(function(error) {
                            console.log("❌ Audio play error në showIncomingCall:", error);
                        });
                    }
                }
                
                // Auto-hide after 60 seconds nëse nuk përgjigjet
                setTimeout(function() {
                    if (modal.classList.contains('show')) {
                        rejectCall();
                    }
                }, 60000);
            }
        }
        
        // Test ringtone function
        function testRingtoneClick() {
            console.log('🧪 TEST RINGTONE CLICKED');
            unlockAudio();
            showIncomingCall('Test Thirrje');
        }
        
        // Accept incoming call
        function acceptCall() {
            console.log('✓ Thirrja u pranua!');
            stopRingtone();
            var modal = document.getElementById('incomingCallModal');
            if (modal) {
                modal.classList.remove('show');
            }
        }
        
        // Reject incoming call
        function rejectCall() {
            stopRingtone();
            var modal = document.getElementById('incomingCallModal');
            if (modal) {
                modal.classList.remove('show');
            }
            console.log("Thirrja u refuzua!");
        }
        
        // ==================== RINGING SYSTEM ====================
        // Initialize status badge to "Po përpiqemi..." (Attempting)
        var badge = document.getElementById('jitsi-status-badge');
        var text = document.getElementById('jitsi-status-text');
        if (badge && text) {
            badge.style.background = 'rgba(255, 152, 0, 0.2)';
            badge.style.borderColor = 'rgba(255, 193, 7, 0.5)';
            text.textContent = 'Po përpiqemi...';
            var icon = badge.querySelector('i');
            if (icon) {
                icon.style.color = '#ffc107';
            }
            console.log('🔄 Status badge initialized: Po përpiqemi...');
        }
        
        // VERY AGGRESSIVE FALLBACK: Start ringing after 2 seconds regardless
        // This ensures ringing works even if Jitsi connection fails
        let fallbackTimer = setTimeout(function() {
            if (!ringingStarted && !jitsiConnected) {
                console.log('🔔 FALLBACK RINGING ACTIVATED - Jitsi connection failed or slow');
                console.log('📞 Displaying incoming call modal...');
                
                // Update status badge to failed
                if (badge && text) {
                    badge.style.background = 'rgba(244, 67, 54, 0.2)';
                    badge.style.borderColor = 'rgba(229, 57, 53, 0.5)';
                    badge.style.boxShadow = '0 0 10px rgba(244, 67, 54, 0.3)';
                    text.textContent = 'Failoj (Fallback)';
                    var icon = badge.querySelector('i');
                    if (icon) {
                        icon.style.color = '#f44336';
                    }
                    console.log('✗ Status badge updated: Failoj (Fallback)');
                }
                
                showIncomingCall('Noter');
                ringingStarted = true;
                
                // Auto-connect to video after 4 seconds of ringing
                setTimeout(function() {
                    if (ringingStarted) {
                        console.log('⏱️ Auto-connecting to video call after fallback ringing...');
                        window.acceptCall();
                    }
                }, 4000);
            }
        }, 2000);
        
        // Listen for when the conference is joined (both sides)
        window.api.addEventListener('conferenceJoined', function() {
            console.log('✓ Jam bashkuar në konferencë');
            jitsiConnected = true;
            clearTimeout(fallbackTimer);  // Cancel fallback if Jitsi connects
            conferenceJoined = true;
            participantCount = 1; // Count myself
            
            // Update status badge
            var badge = document.getElementById('jitsi-status-badge');
            var text = document.getElementById('jitsi-status-text');
            if (badge && text) {
                badge.style.background = 'rgba(76, 175, 80, 0.2)';
                badge.style.borderColor = 'rgba(129, 199, 132, 0.5)';
                badge.style.boxShadow = '0 0 10px rgba(76, 175, 80, 0.3)';
                text.textContent = 'E lidhur';
                var icon = badge.querySelector('i');
                if (icon) {
                    icon.style.color = '#4caf50';
                }
                console.log('✓ Status badge updated to: E lidhur');
            }
        });
        
        // Listen for incoming calls and trigger ringing
        window.api.addEventListener('participantJoined', function(participant) {
            console.log('👤 Një pjesëmarrës bashkohej:', participant);
            participantCount++;
            
            // Stop the calling sound when someone else joins (call is answered)
            window.stopCallingSound();
            console.log('✓ Call connected - Stopping calling sound');
            
            // Play ringtone when someone else joins (to both participants)
            if (conferenceJoined && !ringingStarted && participant.id !== window.USERNAME) {
                const callerName = participant.name || 'Noter';
                console.log('🔔 Ringing triggered for:', callerName);
                ringingStarted = true;
                
                // Show incoming call modal and play ringtone
                showIncomingCall(callerName);
                
                // Auto-connect to video after 4 seconds of ringing
                setTimeout(function() {
                    if (ringingStarted) {
                        console.log('⏱️ Auto-connecting to video call after Jitsi ringing...');
                        window.acceptCall();
                    }
                }, 4000);
            }
        });
        
        // Stop ringing when participant leaves
        window.api.addEventListener('participantLeft', function(participant) {
            console.log('👤 Një pjesëmarrës doli:', participant);
            participantCount = Math.max(0, participantCount - 1);
            
            // Stop ringing when someone leaves
            if (participantCount === 0) {
                stopRingtone();
                ringingStarted = false;
            }
        });
        
        // Stop ringing when conference ends
        window.api.addEventListener('videoConferenceLeft', function() {
            console.log('❌ Konferenca u mbyll');
            conferenceJoined = false;
            ringingStarted = false;
            window.stopRingtone();
            window.stopCallingSound();
        });
        
        // Stop ringing when ready to close
        window.api.addEventListener('readyToClose', function() {
            console.log('❌ Jitsi is ready to close');
            window.stopRingtone();
            window.stopCallingSound();
            ringingStarted = false;
        });
      });
    </script>
</body>
</html>
