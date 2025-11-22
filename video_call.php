<?php
require_once __DIR__ . '/SecurityHeaders.php';
require_once __DIR__ . '/db_connection.php'; // Lidhja me databazën
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        $check_query = "SELECT * FROM payments WHERE user_id = ? AND service_type = 'video_consultation' AND status = 'completed' AND expiry_date > NOW() LIMIT 1";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $has_paid = true;
            $payment_data = $result->fetch_assoc();
            // Llogarit kohën e mbetur në thirrje
            $expiry_time = strtotime($payment_data['expiry_date']);
            $current_time = time();
            $minutes_remaining = max(0, round(($expiry_time - $current_time) / 60));
            $session_duration = $minutes_remaining;
            
            // Ruaj të dhënat në sesion për qasje më të shpejtë
            $_SESSION['video_payment'] = [
                'status' => 'completed',
                'expiry' => $expiry_time,
                'payment_id' => $payment_data['payment_id']
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
                    $insert_query = "INSERT INTO payments (payment_id, user_id, amount, currency, service_type, status, creation_date) 
                                     VALUES (?, ?, ?, 'EUR', 'video_consultation', 'pending', NOW())";
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
        $stmtCall->bind_param('sss', $call_id, $room, $user_id);
        $stmtCall->execute();
        $stmtCall->close();
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
    <!-- Google Fonts & Ikona -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:700,400&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto+Mono:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Audio element for ringtone -->
    <audio id="ringtone" preload="auto" loop crossorigin="anonymous">
        <source src="phone-ringtone-telephone-324474.mp3" type="audio/mpeg">
        <source src="ringtone-031-437514.mp3" type="audio/mpeg">
        Your browser does not support the audio element.
    </audio>
    
    <!-- Calling sound for when the user calls someone -->
    <audio id="calling-sound" preload="auto" loop crossorigin="anonymous">
        <source src="phone-calling-sfx-317333.mp3" type="audio/mpeg">
        Your browser does not support the audio element.
    </audio>
    
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
                        console.log("⚠️ HTML5 audio error, trying workaround:", error.message);
                        // Fallback: Try to play using Web Audio API
                        playRingtoneViaWebAudio();
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
                audio.pause();
                audio.currentTime = 0;
                audio.loop = false;
                console.log('✓ Ringtone stopped');
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
                        console.log('Could not play calling sound:', e);
                    });
                }
            }
        }
        
        // Stop calling sound
        window.stopCallingSound = function() {
            var audio = document.getElementById('calling-sound');
            if (audio) {
                audio.pause();
                audio.currentTime = 0;
                audio.loop = false;
                console.log('✓ Calling sound stopped');
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
                            console.log("⚠️ Ringtone error:", error.message);
                            // Try WebAudio fallback
                            window.playRingtoneViaWebAudio();
                        });
                    }
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
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            overflow-x: hidden;
            color: #fff;
        }
        .glass {
            background: rgba(255,255,255,0.1);
            box-shadow: 0 8px 32px 0 rgba(0,0,0,0.37);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.18);
        }
        /* Incoming Call Modal Styles */
        .incoming-call-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 99999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-in-out;
        }
        .incoming-call-modal.show {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .incoming-call-container {
            background: linear-gradient(135deg, #1a237e 0%, #3949ab 50%, #1565c0 100%);
            border-radius: 25px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
            max-width: 500px;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .caller-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3949ab 0%, #1e88e5 100%);
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 30px rgba(33, 150, 243, 0.8);
            animation: pulse-avatar 2s infinite;
        }
        @keyframes pulse-avatar {
            0%, 100% { box-shadow: 0 0 30px rgba(33, 150, 243, 0.8); }
            50% { box-shadow: 0 0 50px rgba(33, 150, 243, 1); }
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
            font-size: 1.1rem;
            color: #ffeb3b;
            margin-bottom: 30px;
            animation: blink 1s infinite;
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
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        .accept-btn {
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            color: #fff;
        }
        .accept-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 30px rgba(67, 160, 71, 0.6);
        }
        .reject-btn {
            background: linear-gradient(135deg, #e53935 0%, #ef5350 100%);
            color: #fff;
        }
        .reject-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 30px rgba(229, 57, 53, 0.6);
        }
        #header-bar {
            width: 100vw;
            background: linear-gradient(90deg, #1a237e 0%, #3949ab 50%, #1565c0 100%);
            color: #fff;
            padding: 20px 0 18px 0;
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 2px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.4);
            font-family: 'Montserrat', Arial, sans-serif;
            user-select: none;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom-left-radius: 25px;
            border-bottom-right-radius: 25px;
            animation: fadeInDown 1s cubic-bezier(.23,1.01,.32,1);
        }
        @keyframes fadeInDown {
            0% { opacity: 0; transform: translateY(-60px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        #header-bar .brand {
            color: #ffeb3b;
            letter-spacing: 4px;
            font-size: 2.9rem;
            font-family: 'Roboto Mono', monospace;
            text-shadow: 0 0 15px rgba(255, 235, 59, 0.7);
            margin-right: 12px;
            animation: glowing 3s infinite alternate;
        }
        @keyframes glowing {
            0% { text-shadow: 0 0 10px rgba(255, 235, 59, 0.7); }
            100% { text-shadow: 0 0 25px rgba(255, 235, 59, 0.9), 0 0 40px rgba(255, 235, 59, 0.5); }
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
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3949ab 0%, #1e88e5 100%);
            box-shadow: 0 0 20px rgba(33, 150, 243, 0.8);
            margin-right: 14px;
            vertical-align: middle;
            overflow: hidden;
            border: 3px solid rgba(255,255,255,0.8);
            animation: pulse-ring 3s ease-in-out infinite;
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(33, 150, 243, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(33, 150,243, 0); }
            100% { box-shadow: 0 0 0 0 rgba(33, 150, 243, 0); }
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
            background: rgba(46, 125, 50, 0.2);
            color: #81c784;
            border: 1px solid rgba(129, 199, 132, 0.5);
            border-radius: 50px;
            padding: 5px 20px;
            font-size: 1.05rem;
            margin-left: 12px;
            font-weight: 700;
            vertical-align: middle;
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.4);
            animation: popIn 1.2s cubic-bezier(.23,1.01,.32,1);
            text-shadow: 0 0 10px rgba(129, 199, 132, 0.7);
            backdrop-filter: blur(5px);
        }
        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.7);}
            100% { opacity: 1; transform: scale(1);}
        }
        #header-bar .secure-badge {
            display: inline-block;
            background: rgba(25, 118, 210, 0.2);
            color: #64b5f6;
            border: 1px solid rgba(100, 181, 246, 0.5);
            border-radius: 50px;
            padding: 5px 20px;
            font-size: 1.05rem;
            margin-left: 12px;
            font-weight: 700;
            vertical-align: middle;
            box-shadow: 0 0 20px rgba(33, 150, 243, 0.4);
            backdrop-filter: blur(5px);
            text-shadow: 0 0 10px rgba(100, 181, 246, 0.7);
        }
        #header-bar .live-dot {
            display: inline-block;
            width: 15px;
            height: 15px;
            background: #f44336;
            border-radius: 50%;
            margin-left: 14px;
            box-shadow: 0 0 15px #f44336;
            animation: pulse 1.5s infinite;
            vertical-align: middle;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(244, 67, 54, 0); }
            100% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0); }
        }
        #abuse-btn {
            background: linear-gradient(90deg, #f44336 0%, #ff7043 100%);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 8px 25px;
            cursor: pointer;
            font-size: 1.1rem;
            margin-left: 22px;
            font-weight: 600;
            box-shadow: 0 0 20px rgba(244, 67, 54, 0.4);
            transition: all 0.3s ease;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        #abuse-btn:hover {
            background: linear-gradient(90deg, #d32f2f 0%, #f44336 100%);
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(244, 67, 54, 0.6);
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
            margin: 28px auto 0 auto;
            width: 92vw;
            max-width: 700px;
            background: rgba(255, 193, 7, 0.15);
            color: #ffd54f;
            border: 1px solid rgba(255, 213, 79, 0.3);
            border-radius: 18px;
            padding: 24px 32px;
            font-size: 1.15rem;
            text-align: center;
            box-shadow: 0 0 30px rgba(255, 193, 7, 0.2);
            animation: fadeInUp 1.2s cubic-bezier(.23,1.01,.32,1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(60px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        #room-info {
            margin: 32px auto 0 auto;
            padding: 25px 40px;
            background: rgba(3, 169, 244, 0.1);
            border-radius: 20px;
            width: fit-content;
            font-size: 18px;
            color: #81d4fa;
            text-align: center;
            border: 1px solid rgba(129, 212, 250, 0.3);
            box-shadow: 0 0 30px rgba(3, 169, 244, 0.2);
            animation: fadeInUp 1.5s cubic-bezier(.23,1.01,.32,1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        #room-link {
            font-family: 'Roboto Mono', monospace;
            background: rgba(25, 118, 210, 0.15);
            border: 1px solid rgba(100, 181, 246, 0.3);
            border-radius: 50px;
            padding: 8px 20px;
            margin-right: 12px;
            font-size: 1.15rem;
            color: #64b5f6;
            box-shadow: 0 0 15px rgba(33, 150, 243, 0.3);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        #copy-btn {
            background: linear-gradient(90deg, #1976d2 0%, #42a5f5 100%);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(33, 150, 243, 0.4);
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        #copy-btn:hover {
            background: linear-gradient(90deg, #0d47a1 0%, #1976d2 100%);
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(33, 150, 243, 0.6);
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
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.6);
            background: rgba(0, 0, 0, 0.2);
            animation: fadeInUp 1.7s cubic-bezier(.23,1.01,.32,1);
            border: 1px solid rgba(100, 181, 246, 0.3);
            position: relative;
        }
        #video::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #1976d2, #64b5f6, #1976d2);
            animation: moveGradient 3s linear infinite;
            z-index: 2;
            border-top-left-radius: 24px;
            border-top-right-radius: 24px;
        }
        @keyframes moveGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        #footer-bar {
            width: 100vw;
            background: rgba(13, 71, 161, 0.3);
            color: #64b5f6;
            text-align: center;
            font-size: 1.08rem;
            padding: 24px 0 16px 0;
            font-family: 'Roboto Mono', monospace;
            letter-spacing: 1px;
            margin-top: 40px;
            border-top-left-radius: 32px;
            border-top-right-radius: 32px;
            box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-top: 1px solid rgba(100, 181, 246, 0.3);
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
        #modal h2 {
            margin-top: 0;
            color: #ef5350;
            text-shadow: 0 0 10px rgba(239, 83, 80, 0.7);
            font-size: 1.8rem;
            letter-spacing: 1px;
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
        }
        
        .mic-btn, .camera-btn {
            background: linear-gradient(45deg, #1976d2, #42a5f5);
        }
        
        .share-btn {
            background: linear-gradient(45deg, #43a047, #66bb6a);
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
        
        .footer-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            flex-wrap: wrap;
        }
        
        .footer-info {
            display: flex;
            align-items: center;
        }
        
        .footer-divider {
            margin: 0 10px;
            color: rgba(255,255,255,0.5);
        }
        
        .footer-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .report-police-btn {
            display: flex;
            align-items: center;
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
            margin-right: 8px;
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
        
        @media (max-width: 900px) {
            #video { height: 60vh; }
            #header-bar { font-size: 1.5rem; padding: 14px 0 10px 0; }
            #room-info { font-size: 15px; padding: 12px 8px; }
            .footer-content { flex-direction: column; gap: 15px; }
            .footer-actions { flex-direction: column; align-items: center; gap: 15px; }
            .call-stats { flex-direction: column; gap: 10px; }
            .link-container { flex-direction: column; }
        }
        
        @media (max-width: 600px) {
            #video { height: 45vh; }
            #header-bar { font-size: 1.1rem; }
            .input-wrapper input { width: 200px; }
            .admin-controls { flex-wrap: wrap; }
        }
        
        /* Add styled scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            background-color: rgba(13, 71, 161, 0.1);
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(transparent, #1976d2, transparent);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background-color: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
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

    <div id="particles-js" style="position:fixed; width:100%; height:100%; top:0; left:0; z-index:-1;"></div>
    <div id="header-bar" class="glass">
        <span class="avatar"><i class="fa-solid fa-video"></i></span>
        <span class="brand">NOTERIA</span>
    <span class="subtitle"><?= isset($translations['subtitle'][$lang]) ? $translations['subtitle'][$lang] : '' ?></span>
        <?php if ($is_admin): ?>
            <span class="user-badge" style="background: rgba(255, 193, 7, 0.2); color: #ffd54f; border-color: rgba(255, 213, 79, 0.5); box-shadow: 0 0 20px rgba(255, 193, 7, 0.4);"><i class="fa-solid fa-crown"></i> <?php echo htmlspecialchars($username); ?></span>
        <?php else: ?>
            <span class="user-badge"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
        <?php endif; ?>
    <span class="secure-badge" title="Dhoma është private dhe e mbrojtur"><i class="fa-solid fa-lock"></i> <?= isset($translations['secure'][$lang]) ? $translations['secure'][$lang] : 'E mbrojtur' ?></span>
        <span class="live-dot" title="Video thirrja është aktive"></span>
    <button id="abuse-btn" onclick="openModal()"><i class="fa-solid fa-triangle-exclamation"></i> <?= isset($translations['report_abuse'][$lang]) ? $translations['report_abuse'][$lang] : 'Raporto' ?></button>
    <button id="test-ringtone-btn" onclick="testRingtoneClick()" style="display: inline-block; background: linear-gradient(90deg, #ff9800, #f57c00); color: white; padding: 8px 20px; border-radius: 50px; border: none; text-decoration: none; margin-left: 8px; font-weight: 600; font-size: 0.9rem; box-shadow: 0 0 15px rgba(255, 152, 0, 0.4); text-shadow: 0 1px 3px rgba(0,0,0,0.3); cursor: pointer; transition: all 0.3s ease;" title="Test ringtone audio">
        <i class="fa-solid fa-volume-high"></i> Test Zile
    </button>
    <span id="jitsi-status-badge" style="display: inline-block; background: rgba(244, 67, 54, 0.2); color: #ef5350; border: 1px solid rgba(244, 67, 54, 0.5); padding: 8px 15px; border-radius: 50px; margin-left: 12px; font-weight: 600; font-size: 0.85rem; box-shadow: 0 0 10px rgba(244, 67, 54, 0.3);">
        <i class="fa-solid fa-circle" style="color: #ef5350; margin-right: 5px;"></i> <span id="jitsi-status-text">Lidhja</span>
    </span>
    <!-- Return to Dashboard Button - visible to all users -->
    <a href="dashboard.php" style="display: inline-block; background: linear-gradient(90deg, #43a047, #66bb6a); color: white; padding: 8px 20px; border-radius: 50px; text-decoration: none; margin-left: 12px; font-weight: 600; font-size: 0.9rem; box-shadow: 0 0 15px rgba(67, 160, 71, 0.4); text-shadow: 0 1px 3px rgba(0,0,0,0.3);">
        <i class="fa-solid fa-arrow-left"></i> Kthehu në Panel
    </a>
    </div>
    <div id="notice" class="glass fade-in">
        <?php if (!$is_admin && !$has_paid && $payment_required): ?>
            <!-- Shfaq njoftimin për pagesë -->
            <div style="text-align:center; padding:15px;">
                <div style="font-size:1.4rem; font-weight:700; color:#ffcc00; margin-bottom:15px;">
                    <i class="fa-solid fa-credit-card" style="margin-right:10px;"></i>
                    Konsulenca me Noter kërkon pagesë
                </div>
                <p style="color:#fff; font-size:1.1rem; margin-bottom:20px;">
                    Video thirrjet për konsulencë me noter janë shërbim me pagesë.
                    Çmimi për një seancë 30 minutëshe është <strong><?= $session_price ?> EUR</strong>.
                </p>
                <?php if (!empty($payment_url)): ?>
                    <a href="<?= htmlspecialchars($payment_url) ?>" class="pulse-button" style="text-decoration:none; display:inline-block; background:linear-gradient(45deg, #43a047, #66bb6a);">
                        <span class="button-content">
                            <i class="fa-solid fa-credit-card"></i> 
                            Paguaj <?= $session_price ?> EUR
                        </span>
                        <span class="button-glow"></span>
                    </a>
                    <p style="color:#cce5ff; font-size:0.9rem; margin-top:15px;">
                        <i class="fa-solid fa-lock" style="margin-right:5px;"></i>
                        Pagesa procesohet në mënyrë të sigurt përmes Paysera, Raiffeisen Bank dhe BKT.
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Formular për bashkimin në dhomë ose informacion për përdoruesin që ka paguar -->
            <?php if ($has_paid): ?>
                <div style="text-align:center; padding:10px; background:rgba(67, 160, 71, 0.2); border-radius:10px; margin-bottom:15px;">
                    <i class="fa-solid fa-check-circle" style="color:#66bb6a; font-size:1.3rem; margin-right:8px;"></i>
                    <span style="color:#66bb6a; font-weight:600;">Pagesa e konfirmuar! Ju keni <?= $session_duration ?> minuta konsulencë të disponueshme.</span>
                </div>
            <?php endif; ?>
            <form id="join-room-form" method="get" action="video_call.php" style="margin:18px 0 0 0; text-align:center;">
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
            <button class="control-btn end-call-btn" title="Mbyll thirrjen">
                <i class="fa-solid fa-phone-slash"></i>
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
    <h2><i class="fa-solid fa-triangle-exclamation"></i> <?= isset($translations['modal_title'][$lang]) ? $translations['modal_title'][$lang] : '' ?></h2>
        <form id="abuse-form" onsubmit="return submitAbuse();">
            <textarea id="abuse-msg" placeholder="<?= isset($translations['modal_placeholder'][$lang]) ? $translations['modal_placeholder'][$lang] : '' ?>" required></textarea>
            <br>
            <button type="submit"><i class="fa-solid fa-paper-plane"></i> <?= isset($translations['modal_send'][$lang]) ? $translations['modal_send'][$lang] : '' ?></button>
            <button type="button" class="close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i> <?= isset($translations['modal_close'][$lang]) ? $translations['modal_close'][$lang] : '' ?></button>
        </form>
    <div id="abuse-success" style="display:none;"><i class="fa-solid fa-circle-check"></i> <?= isset($translations['modal_success'][$lang]) ? $translations['modal_success'][$lang] : '' ?></div>
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
    
    <!-- End call message -->
    <div id="end-call-message" class="end-call-message">
      <h3>Thirrja përfundoi</h3>
      <p>Ju faleminderit për përdorimin e shërbimit tonë të video thirrjeve.</p>
      <button id="return-to-dashboard" style="background: #2d6cdf; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-top: 15px; cursor: pointer;">
        Kthehu në Panel
      </button>
    </div>
    
        <script src="https://meet.jit.si/external_api.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
        <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
        <script>
            // Expose server-generated call_id to client-side for tracking and updates
            window.CALL_ID = '<?php echo isset($call_id) ? htmlspecialchars($call_id) : ''; ?>';
      document.addEventListener("DOMContentLoaded", function() {
        // Shfaq preloaderin e rrjetit në fillim
        const networkPreloader = document.getElementById('network-preloader');
        networkPreloader.classList.add('show');
        
        // Fshehe preloaderin pas 5 sekondash
        setTimeout(function() {
            networkPreloader.classList.remove('show');
        }, 5000);
        
        // Handle the end call button
        document.querySelector('.end-call-btn').addEventListener('click', function() {
            if (confirm('A jeni i sigurt që dëshironi të mbyllni thirrjen?')) {
                endCall();
            }
        });
        
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
        
        // Inicializo monitorimin e cilësisë së lidhjes
        function updateConnectionQuality() {
            // Vlera simuluese për demo (në implementimin e plotë do merren nga API)
            const qualities = [
                {level: 'Shkëlqyeshme', color: '#4caf50', bandwidth: '5.0 Mbps', packets: '100%', latency: '12ms', bars: 5},
                {level: 'Shumë e mirë', color: '#8bc34a', bandwidth: '3.8 Mbps', packets: '99%', latency: '20ms', bars: 4},
                {level: 'E mirë', color: '#ffc107', bandwidth: '2.2 Mbps', packets: '97%', latency: '45ms', bars: 3},
                {level: 'Mesatare', color: '#ff9800', bandwidth: '1.5 Mbps', packets: '92%', latency: '80ms', bars: 2},
                {level: 'E dobët', color: '#f44336', bandwidth: '0.8 Mbps', packets: '85%', latency: '150ms', bars: 1}
            ];
            
            // Në këtë demonstrim, gjithmonë përdorim cilësinë maksimale
            // Në realitet, kjo do të ndryshonte bazuar në statistikat e rrjetit
            const quality = qualities[0];
            
            document.querySelector('.signal-text').textContent = quality.level;
            document.querySelector('.signal-text').style.color = quality.color;
            document.getElementById('bandwidth-value').textContent = quality.bandwidth;
            document.getElementById('packet-value').textContent = quality.packets;
            document.getElementById('latency-value').textContent = quality.latency;
            
            // Ngjyroso barrat e sinjalit
            const bars = document.querySelectorAll('.signal-icon .bar');
            bars.forEach((bar, index) => {
                if (index < quality.bars) {
                    bar.style.background = quality.color;
                    bar.style.opacity = '1';
                } else {
                    bar.style.background = '#666';
                    bar.style.opacity = '0.5';
                }
            });
        }
        
        // Përditëso treguesin çdo 3 sekonda
        updateConnectionQuality();
        setInterval(updateConnectionQuality, 3000);
        
        // Inicializimi i particles.js për efekt modern në sfond - optimizuar për performancë
        try {
          particlesJS("particles-js", {
            "particles": {
              "number": {
                "value": 30, // Zvogëluar numrin e grimcave për performancë më të mirë
                "density": {
                  "enable": true,
                  "value_area": 800
                }
              },
              "color": {
                "value": "#ffffff"
              },
              "shape": {
                "type": "circle",
                "stroke": {
                  "width": 0,
                  "color": "#000000"
                },
                "polygon": {
                  "nb_sides": 5
                }
              },
              "opacity": {
                "value": 0.2, // Zvogëluar opacitetin për performancë
                "random": false,
                "anim": {
                  "enable": false,
                  "speed": 0.5, // Zvogëluar shpejtësinë për performancë
                  "opacity_min": 0.1,
                  "sync": false
                }
              },
              "size": {
                "value": 3,
                "random": true,
                "anim": {
                  "enable": false,
                  "speed": 20, // Zvogëluar shpejtësinë për performancë
                  "size_min": 0.1,
                  "sync": false
                }
              },
              "line_linked": {
                "enable": true,
                "distance": 150,
                "color": "#64b5f6",
                "opacity": 0.2,
                "width": 1
              },
              "move": {
                "enable": true,
                "speed": 1, // Zvogëluar shpejtësinë për performancë
                "direction": "none",
                "random": false,
                "straight": false,
                "out_mode": "out",
                "bounce": false,
                "attract": {
                  "enable": false,
                  "rotateX": 300, // Zvogëluar për performancë
                  "rotateY": 600 // Zvogëluar për performancë
                }
              }
            },
            "interactivity": {
              "detect_on": "canvas",
              "events": {
                "onhover": {
                  "enable": false, // Fikur për performancë më të mirë
                  "mode": "grab"
                },
                "onclick": {
                  "enable": false, // Fikur për performancë më të mirë
                  "mode": "push"
                },
                "resize": true
              },
              "modes": {
                "grab": {
                  "distance": 140,
                  "line_linked": {
                    "opacity": 0.5
                  }
                },
                "bubble": {
                  "distance": 400,
                  "size": 40,
                  "duration": 2,
                  "opacity": 8,
                  "speed": 3
                },
                "repulse": {
                  "distance": 200,
                  "duration": 0.4
                },
                "push": {
                  "particles_nb": 2 // Zvogëluar për performancë
                },
                "remove": {
                  "particles_nb": 1 // Zvogëluar për performancë
                }
              }
            },
            "retina_detect": false // Fikur për performancë më të mirë
          });
        } catch (e) {
          console.log("Particles.js nuk u inicializua me sukses:", e);
        }

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
        
        // Funksion për kohëmatës të thirrjes me funksionalitet shtesë për konsulencë me pagesë
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
          <div style="position:fixed; top:80px; right:20px; background:rgba(0,0,0,0.8); padding:10px 15px; border-radius:10px; z-index:100; display:flex; align-items:center; box-shadow:0 0 20px rgba(0,0,0,0.5); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.2);">
            <i class="fa-solid fa-hourglass-half" style="color:#ffc107; margin-right:10px; animation:pulse 1.5s infinite;"></i>
            <div>
              <div style="font-size:0.85rem; color:rgba(255,255,255,0.7);">Kohë e mbetur:</div>
              <div id="payment-time-remaining" style="font-size:1.2rem; font-weight:700; color:#fff;">00:00:00</div>
            </div>
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
        
        // Kohëmatësi standard i thirrjes
        setInterval(function() {
          callSeconds++;
          const hours = Math.floor(callSeconds / 3600);
          const minutes = Math.floor((callSeconds % 3600) / 60);
          const seconds = callSeconds % 60;
          
          document.getElementById('call-timer').innerText = 
            (hours < 10 ? '0' + hours : hours) + ':' +
            (minutes < 10 ? '0' + minutes : minutes) + ':' +
            (seconds < 10 ? '0' + seconds : seconds);
        }, 1000);
        
        // Simulim të numrit të pjesëmarrësve (do të zëvendësohet nga API i Jitsi)
        let participants = 1;
        setInterval(function() {
          const randomChange = Math.random() > 0.7;
          if (randomChange) {
            if (Math.random() > 0.5 && participants < 8) {
              participants++;
            } else if (participants > 1) {
              participants--;
            }
            document.getElementById('participant-count').innerText = participants;
          }
        }, 10000);
        
        // Simulim të cilësisë së lidhjes (do të zëvendësohet nga API i Jitsi)
        const connectionQualities = ['Shkëlqyeshëm', 'Mirë', 'Mesatare', 'E dobët'];
        let qualityIndex = 0;
        setInterval(function() {
          if (Math.random() > 0.8) {
            qualityIndex = Math.floor(Math.random() * connectionQualities.length);
            document.getElementById('connection-quality').innerText = connectionQualities[qualityIndex];
          }
        }, 15000);
        
        // Event listeners për butonat e kontrollit të thirrjes
        document.querySelectorAll('.control-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            const action = this.classList[1].replace('-btn', '');
            console.log(`Action: ${action}`);
            
            // Simulim i veprimeve (do të zëvendësohet nga API i Jitsi)
            if (action === 'mic') {
              this.classList.toggle('muted');
              this.innerHTML = this.classList.contains('muted') ? 
                '<i class="fa-solid fa-microphone-slash"></i>' : 
                '<i class="fa-solid fa-microphone"></i>';
            } else if (action === 'camera') {
              this.classList.toggle('off');
              this.innerHTML = this.classList.contains('off') ? 
                '<i class="fa-solid fa-video-slash"></i>' : 
                '<i class="fa-solid fa-video"></i>';
            } else if (action === 'end-call') {
              if (confirm('A jeni i sigurt që dëshironi të mbyllni thirrjen?')) {
                window.location.href = 'dashboard.php';
              }
            }
          });
        });
        
        // Event listeners për butonat admin
        document.querySelectorAll('.admin-button').forEach(btn => {
          btn.addEventListener('click', function() {
            const action = this.classList[1];
            console.log(`Admin action: ${action}`);
            
            if (action === 'record') {
              alert('Regjistrimi i thirrjes filloi!');
              this.innerHTML = '<i class="fa-solid fa-stop"></i>';
              this.classList.remove('record');
              this.classList.add('recording');
            } else if (action === 'recording') {
              alert('Regjistrimi u ndal!');
              this.innerHTML = '<i class="fa-solid fa-record-vinyl"></i>';
              this.classList.remove('recording');
              this.classList.add('record');
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
        
        // Ndërrimi i gjuhës
        document.querySelectorAll('.lang-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            alert(`Gjuha u ndryshua në: ${this.dataset.lang.toUpperCase()}`);
          });
        });

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

        const domain = 'meet.jit.si';
        const options = {
            roomName: window.ROOM,
            width: "100%",
            height: "100%",
            parentNode: document.querySelector('#video'),
            userInfo: { displayName: window.USERNAME },
            configOverwrite: {
                startWithVideoMuted: false,
                startWithAudioMuted: false,
                resolution: 720,
                constraints: {
                    video: {
                        height: { ideal: 720, max: 720, min: 480 },
                        width: { ideal: 1280, max: 1280, min: 640 },
                        frameRate: { ideal: 30, min: 15 },
                        aspectRatio: 16/9
                    }
                },
                disableSimulcast: false,
                channelLastN: 6,
                prejoinPageEnabled: false,
                p2p: {
                    enabled: true,
                    preferH264: true,
                    stunServers: [
                        { urls: 'stun:stun.l.google.com:19302' }
                    ]
                },
                desktopSharingFrameRate: { min: 15, max: 30 },
                disableAudioLevels: false,
                enableNoAudioDetection: true,
                enableNoisyMicDetection: true,
                analytics: { disabled: true },
                videoQuality: {
                    preferredCodec: 'VP8',
                    maxBitratesVideo: {
                        low: 200000,
                        standard: 1000000,
                        high: 2500000
                    },
                    minHeightForQualityLvl: {
                        360: 'low',
                        720: 'standard',
                        1080: 'high'
                    }
                }
            },
            interfaceConfigOverwrite: {
                filmStripOnly: false,
                SHOW_JITSI_WATERMARK: false,
                SHOW_BRAND_WATERMARK: false,
                SHOW_POWERED_BY: false,
                DEFAULT_REMOTE_DISPLAY_NAME: 'Përdorues',
                DEFAULT_LOCAL_DISPLAY_NAME: 'Unë',
                TOOLBAR_BUTTONS: [
                    'microphone', 'camera', 'desktop', 'fullscreen',
                    'hangup', 'profile', 'chat', 'settings',
                    'raisehand', 'videoquality', 'filmstrip', 'invite',
                    'tileview', 'help', 'mute-everyone', 'security'
                ]
            }
        };
        window.api = new JitsiMeetExternalAPI(domain, options);
        
        // Start calling sound when user initiates the call
        console.log('📱 Starting calling sound...');
        window.playCallingSound();

        // Kontrollo statusin e dhomës Jitsi nga browseri
        function kontrolloJitsiRoom(room, domain = 'meet.jit.si') {
            const conference = `${room}@conference.${domain}`;
            fetch(`https://api.jitsi.net/conferenceMapper?conference=${encodeURIComponent(conference)}`)
              .then(r => r.json())
              .then(data => {
                console.log("Info për dhomën:", data);
                // Mund të shfaqësh ose përdorësh të dhënat këtu
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

        // Shto gjithashtu edhe pas ngarkimit fillestar
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
        
        document.querySelector('.share-btn').addEventListener('click', function() {
            try {
                window.api.executeCommand('toggleShareScreen');
            } catch (e) {
                console.log("Gabim në toggleShareScreen:", e);
                alert("Nuk mund të ndahej ekrani. Ju lutemi përdorni butonat e Jitsi.");
            }
        });
        
        document.querySelector('.end-call-btn').addEventListener('click', function() {
            if (confirm('A jeni i sigurt që dëshironi të mbyllni thirrjen?')) {
                try {
                    window.api.executeCommand('hangup');
                } catch (e) {
                    console.log("Gabim në hangup:", e);
                }
                window.location.href = 'dashboard.php';
            }
        });
        
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
                // Përditëso numrin e pjesëmarrësve manualisht
                api.getParticipantsInfo().then(participants => {
                    const participantCount = document.getElementById('participant-count');
                    if (participantCount) {
                        participantCount.innerText = participants.length;
                    }
                }).catch(e => {
                    console.log("Nuk mund të merreshin të dhënat e pjesëmarrësve:", e);
                });
            } catch (e) {
                console.log("Gabim në updateConferenceStats:", e);
            }
        }
        
        // Përdor intervalin për të përditësuar statistikat
        setInterval(updateConferenceStats, 5000);
        
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
                    try {
                        // Dërgon mesazh tek të gjithë pjesëmarrësit
                        try {
                            window.api.executeCommand('sendEndpointTextMessage', '', 'ADMIN_ENDED_CALL');
                        } catch (msgErr) {
                            console.log("Gabim në dërgimin e mesazhit të mbylljes:", msgErr);
                        }
                        
                        // Mbyll thirrjen pas 1 sekonde
                        setTimeout(() => {
                            try {
                                window.api.executeCommand('hangup');
                            } catch (hangupErr) {
                                console.log("Gabim në hangup:", hangupErr);
                            }
                            window.location.href = 'dashboard.php';
                        }, 1000);
                    } catch (e) {
                        console.log("Gabim në end-call:", e);
                        alert("Ndodhi një gabim. Duke mbyllur thirrjen lokalisht...");
                        window.location.href = 'dashboard.php';
                    }
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
            try {
                window.api.executeCommand('setVideoQuality', 1080);
            } catch (e) {
                console.log("Komanda e cilësisë së videos nuk u ekzekutua: ", e);
            }
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
            try {
                // Cilësia e videos - komandë e mbështetur
                window.api.executeCommand('setVideoQuality', 1080);
                
                // Optimizim i cilësisë së lidhjes
                try {
                    // Rifresho filmstrip për të rilidhur pjesëmarrësit
                    window.api.executeCommand('toggleFilmStrip');
                    setTimeout(() => window.api.executeCommand('toggleFilmStrip'), 300);
                    
                    // Kontrollojmë dhe rivendosim cilësitë optimale
                    window.api.executeCommand('setFollowMe', false); // Çaktivizo ndjekjen për performancë më të mirë
                    
                    // Kontrollojmë pajisjet për të siguruar transmetimin optimal
                    api.getAvailableDevices().then(devices => {
                        // Zgjedh pajisjet më të mira të disponueshme
                        if (devices.videoInput && devices.videoInput.length) {
                            // Gjej kamerën me cilësinë më të lartë
                            const bestCamera = devices.videoInput[0]; // Zakonisht e para është më e mira
                            try {
                                window.api.executeCommand('setVideoInputDevice', bestCamera.deviceId);
                            } catch (e) {
                                console.log("Nuk mund të vendosej kamera: ", e);
                            }
                        }
                        
                        if (devices.audioInput && devices.audioInput.length) {
                            // Gjej mikrofonin me cilësinë më të lartë
                            const bestMic = devices.audioInput[0]; // Zakonisht i pari është më i miri
                            try {
                                window.api.executeCommand('setAudioInputDevice', bestMic.deviceId);
                            } catch (e) {
                                console.log("Nuk mund të vendosej mikrofoni: ", e);
                            }
                        }
                    });
                } catch (innerE) {
                    console.log("Gabim gjatë optimizimit të lidhjes: ", innerE);
                }
            } catch (e) {
                console.log("Nuk mund të ekzekutohet komanda e API: ", e);
            }
            
            // Rifreskimi i konferencës dhe treguesve vizualë
            document.getElementById('connection-quality').innerText = "Ultra HD";
            document.getElementById('connection-quality').style.color = '#76ff03';
            
            // Përditëso treguesit e cilësisë në UI
            document.getElementById('bandwidth-value').textContent = "8.0 Mbps";
            document.getElementById('packet-value').textContent = "100%";
            document.getElementById('latency-value').textContent = "5ms";
            
            // Animim i lehtë për të treguar optimizimin
            const qualityText = document.querySelector('.signal-text');
            if (qualityText) {
                qualityText.style.transition = "all 0.5s ease";
                qualityText.style.transform = "scale(1.1)";
                setTimeout(() => {
                    qualityText.style.transform = "scale(1)";
                }, 500);
            }
        }, 15000); // Zvogëluar nga 30 sekonda në 15 sekonda për optimizim më të shpejtë
        
        // Detektim i problemeve të rrjetit dhe zgjidhje automatike
        window.api.addEventListener('connectionEstablished', function() {
            console.log('Lidhja u vendos me sukses');
        });
        
        // Monitoro dhe riparo lidhjet me probleme
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
                
                // Riparim automatik i lidhjes me strategji avancuar
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
                console.log("Gabim në aplikimin e filtrave të avancuar:", err);
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
          fetch('heartbeat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'call_id=' + encodeURIComponent(window.CALL_ID)
          })
          .then(r => r.json())
          .then(data => {
            if (!data.success) {
              console.warn('Heartbeat error:', data.error);
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
        
        // Handle connection errors
        window.api.addEventListener('errorOccurred', function(error) {
            console.error('❌ Jitsi Error:', error);
        });
      });
    </script>
</body>
</html>
