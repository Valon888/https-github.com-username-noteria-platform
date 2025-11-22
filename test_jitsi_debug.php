<?php
session_start();
$room = isset($_GET['room']) ? htmlspecialchars($_GET['room']) : 'test-ringing-room';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jitsi Debug - Ringing Test</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .debug-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .jitsi-frame {
            width: 100%;
            height: 400px;
            border-radius: 10px;
            border: 2px solid #ddd;
            margin-bottom: 20px;
        }
        
        .debug-log {
            background: #1e1e1e;
            color: #0f0;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .debug-log-entry {
            margin: 5px 0;
            padding: 5px;
            border-left: 3px solid #0f0;
            padding-left: 10px;
        }
        
        .debug-log-entry.error {
            color: #f00;
            border-left-color: #f00;
        }
        
        .debug-log-entry.warning {
            color: #ff0;
            border-left-color: #ff0;
        }
        
        .debug-log-entry.success {
            color: #0f0;
            border-left-color: #0f0;
        }
        
        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            background: #667eea;
            color: white;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        button:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        button.danger {
            background: #e74c3c;
        }
        
        button.danger:hover {
            background: #c0392b;
        }
        
        .stats {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            font-weight: 600;
            color: #333;
        }
        
        .stat-value {
            color: #667eea;
            font-weight: 700;
        }
        
        audio {
            display: block;
            margin-top: 20px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1>🔔 Jitsi Ringing Debug Console</h1>
        
        <div id="jitsiContainer" class="jitsi-frame"></div>
        
        <div class="controls">
            <button onclick="playTestRingtone()">🔊 Play Ringtone</button>
            <button onclick="stopTestRingtone()">⏹️ Stop Ringtone</button>
            <button onclick="testIncomingCall()">📞 Simulate Incoming Call</button>
            <button onclick="clearDebugLog()" class="danger">🗑️ Clear Log</button>
        </div>
        
        <div class="debug-log" id="debugLog"></div>
        
        <div class="stats">
            <div class="stat-item">
                <span class="stat-label">Room:</span>
                <span class="stat-value"><?php echo $room; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Domain:</span>
                <span class="stat-value">meet.jit.si</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">API Status:</span>
                <span class="stat-value" id="apiStatus">Initializing...</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Participants:</span>
                <span class="stat-value" id="participantCount">0</span>
            </div>
        </div>
        
        <audio id="ringtone" preload="auto">
            <source src="ringtone-031-437514.mp3" type="audio/mpeg">
        </audio>
    </div>

    <script src="https://meet.jit.si/external_api.js"></script>
    <script>
        let api = null;
        let participantCount = 0;
        let ringtoneAudio = document.getElementById('ringtone');
        
        // ==================== DEBUG LOGGING ====================
        function addDebugLog(message, type = 'info') {
            const log = document.getElementById('debugLog');
            const entry = document.createElement('div');
            entry.className = `debug-log-entry ${type}`;
            const timestamp = new Date().toLocaleTimeString();
            entry.textContent = `[${timestamp}] ${message}`;
            log.appendChild(entry);
            log.scrollTop = log.scrollHeight;
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
        
        function clearDebugLog() {
            document.getElementById('debugLog').innerHTML = '';
            addDebugLog('Debug log cleared', 'info');
        }
        
        // ==================== RINGTONE FUNCTIONS ====================
        function playTestRingtone() {
            addDebugLog('Playing ringtone...', 'success');
            ringtoneAudio.volume = 0.7;
            ringtoneAudio.loop = true;
            ringtoneAudio.play().catch(err => {
                addDebugLog(`Ringtone play error: ${err.message}`, 'error');
            });
        }
        
        function stopTestRingtone() {
            addDebugLog('Stopping ringtone...', 'warning');
            ringtoneAudio.pause();
            ringtoneAudio.currentTime = 0;
        }
        
        // ==================== INCOMING CALL SIMULATION ====================
        function testIncomingCall() {
            addDebugLog('Simulating incoming call from "Testi Përdorues"...', 'success');
            playTestRingtone();
            
            setTimeout(() => {
                addDebugLog('Incoming call modal would display here', 'info');
            }, 500);
            
            // Auto-stop after 5 seconds for testing
            setTimeout(() => {
                addDebugLog('Auto-stopping test ringtone...', 'info');
                stopTestRingtone();
            }, 5000);
        }
        
        // ==================== JITSI INITIALIZATION ====================
        window.addEventListener('load', function() {
            addDebugLog('Page loaded, initializing Jitsi...', 'info');
            
            const domain = 'meet.jit.si';
            const room = '<?php echo $room; ?>';
            
            const options = {
                roomName: room,
                width: '100%',
                height: '100%',
                parentNode: document.getElementById('jitsiContainer'),
                configOverrides: {
                    startWithVideoMuted: false,
                    startWithAudioMuted: false,
                    disableRemoteLyrics: true
                },
                interfaceConfigOverrides: {
                    DEFAULT_BACKGROUND: '#0a0e27',
                    TOOLBAR_BUTTONS: [
                        'microphone', 'camera', 'closedcaptions', 'desktop',
                        'fullscreen', 'fodeviceselection', 'hangup', 'profile',
                        'chat', 'settings', 'raisehand', 'videoquality',
                        'filmstrip', 'invite', 'help', 'mute-everyone'
                    ]
                }
            };
            
            try {
                api = new JitsiMeetExternalAPI(domain, options);
                addDebugLog('✓ JitsiMeetExternalAPI initialized successfully', 'success');
                document.getElementById('apiStatus').textContent = 'Connected';
            } catch (error) {
                addDebugLog(`✗ Failed to initialize Jitsi: ${error.message}`, 'error');
                document.getElementById('apiStatus').textContent = 'Error';
                return;
            }
            
            // ==================== JITSI EVENT LISTENERS ====================
            
            api.addEventListener('participantJoined', function(participant) {
                participantCount++;
                document.getElementById('participantCount').textContent = participantCount;
                addDebugLog(`👤 Participant joined: ${participant.name || 'Anonymous'}`, 'success');
                
                // Trigger ringtone when someone joins
                addDebugLog(`🔔 Playing ringtone for incoming call from ${participant.name || 'Noter'}`, 'success');
                playTestRingtone();
            });
            
            api.addEventListener('participantLeft', function(participant) {
                participantCount = Math.max(0, participantCount - 1);
                document.getElementById('participantCount').textContent = participantCount;
                addDebugLog(`👤 Participant left: ${participant.name || 'Anonymous'}`, 'warning');
                
                // Stop ringtone when participant leaves
                addDebugLog('🔔 Stopping ringtone - participant left', 'warning');
                stopTestRingtone();
            });
            
            api.addEventListener('videoConferenceLeft', function() {
                addDebugLog('Conference ended - cleaning up...', 'warning');
                stopTestRingtone();
                document.getElementById('apiStatus').textContent = 'Disconnected';
            });
            
            api.addEventListener('readyToClose', function() {
                addDebugLog('Ready to close Jitsi conference', 'info');
                stopTestRingtone();
            });
            
            api.addEventListener('errorOccurred', function(error) {
                addDebugLog(`❌ Jitsi Error: ${error.message || JSON.stringify(error)}`, 'error');
            });
            
            // Network connection monitoring
            api.addEventListener('connectionFailed', function() {
                addDebugLog('❌ WebSocket connection failed!', 'error');
                document.getElementById('apiStatus').textContent = 'Connection Failed';
            });
            
            api.addEventListener('connectionEstablished', function() {
                addDebugLog('✓ WebSocket connection established', 'success');
                document.getElementById('apiStatus').textContent = 'Connected';
            });
        });
        
        // Log Jitsi external API errors
        window.addEventListener('error', function(event) {
            if (event.message.includes('Jitsi') || event.message.includes('jitsi')) {
                addDebugLog(`Window Error: ${event.message}`, 'error');
            }
        });
    </script>
</body>
</html>
