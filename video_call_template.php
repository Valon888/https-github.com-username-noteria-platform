<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Thirrje - Noteria</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        body {
            background-color: #f0f2f5;
            overflow: hidden;
        }
        .video-container {
            position: relative;
            height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
        }
        .remote-video-container {
            flex: 1;
            background-color: #000;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }
        .local-video-container {
            position: absolute;
            width: 25%;
            max-width: 250px;
            min-width: 160px;
            height: auto;
            right: 20px;
            bottom: 90px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 10;
        }
        .controls-container {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 10px;
            z-index: 100;
        }
        .control-button {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin: 0 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
            background-color: rgba(255,255,255,0.9);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .control-button:hover {
            transform: scale(1.1);
        }
        .control-button.end-call {
            background-color: #ff4d4f;
            color: white;
        }
        .control-button.muted, .control-button.video-off {
            background-color: #f0f0f0;
            color: #777;
        }
        #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        #localVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1); /* Pasqyrë për video lokale */
        }
        .status-container {
            position: absolute;
            top: 20px;
            left: 20px;
            background-color: rgba(0,0,0,0.5);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
        }
        .timer-container {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: rgba(0,0,0,0.5);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
        }
        .participant-name {
            position: absolute;
            bottom: 60px;
            left: 20px;
            color: white;
            font-size: 18px;
            font-weight: bold;
            text-shadow: 0 1px 2px rgba(0,0,0,0.8);
        }
        .chat-container {
            position: absolute;
            width: 300px;
            height: 400px;
            right: 20px;
            top: 20px;
            background-color: rgba(255,255,255,0.9);
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            z-index: 20;
            display: none; /* Fillimisht i fshehur */
        }
        .chat-header {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .chat-input {
            padding: 10px;
            border-top: 1px solid #ddd;
            display: flex;
        }
        .chat-input input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
        }
        .chat-input button {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        .chat-message {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 4px;
            max-width: 80%;
        }
        .chat-message.received {
            background-color: #f1f1f1;
            align-self: flex-start;
        }
        .chat-message.sent {
            background-color: #e1f5fe;
            align-self: flex-end;
            margin-left: auto;
        }
        .chat-message .sender {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 3px;
        }
        .chat-message .time {
            font-size: 10px;
            color: #888;
            text-align: right;
            margin-top: 2px;
        }
        .hidden {
            display: none;
        }
        .reconnecting-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
            z-index: 200;
            display: none;
        }
        .reconnecting-spinner {
            border: 4px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 4px solid white;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .tooltip-container {
            position: absolute;
            background-color: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            display: none;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="video-container">
            <div class="remote-video-container">
                <video id="remoteVideo" autoplay playsinline></video>
                <div class="participant-name" id="remoteParticipantName">Duke lidhur...</div>
                <div class="status-container" id="connectionStatus">Duke krijuar lidhjen...</div>
                <div class="timer-container" id="callTimer">00:00:00</div>
            </div>
            
            <div class="local-video-container">
                <video id="localVideo" autoplay playsinline muted></video>
            </div>
            
            <div class="controls-container">
                <div class="control-button" id="toggleMicBtn" title="Çaktivizo/Aktivizo audion">
                    <i class="fas fa-microphone"></i>
                </div>
                <div class="control-button" id="toggleVideoBtn" title="Çaktivizo/Aktivizo kamerën">
                    <i class="fas fa-video"></i>
                </div>
                <div class="control-button" id="toggleScreenShareBtn" title="Ndaj ekranin">
                    <i class="fas fa-desktop"></i>
                </div>
                <div class="control-button" id="toggleChatBtn" title="Hap/Mbyll chatin">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="control-button end-call" id="endCallBtn" title="Përfundo thirrjen">
                    <i class="fas fa-phone-slash"></i>
                </div>
            </div>
            
            <div class="chat-container" id="chatContainer">
                <div class="chat-header">
                    <span>Chat</span>
                    <button class="btn btn-sm" id="closeChatBtn"><i class="fas fa-times"></i></button>
                </div>
                <div class="chat-messages" id="chatMessages"></div>
                <div class="chat-input">
                    <input type="text" id="chatInput" placeholder="Shkruaj një mesazh...">
                    <button id="sendChatBtn"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
            
            <div class="reconnecting-overlay" id="reconnectingOverlay">
                <div class="reconnecting-spinner"></div>
                <p>Duke u rilidhur...</p>
            </div>
        </div>
    </div>
    
    <div class="tooltip-container" id="tooltip"></div>
    
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/noteria-webrtc.js"></script>
    <script>
        // Të dhënat e thirrjes
        const callData = {
            roomId: '<?php echo htmlspecialchars($callData['call_id']); ?>',
            userId: <?php echo $_SESSION['user_id']; ?>,
            username: '<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User ' . $_SESSION['user_id']; ?>',
            callerId: <?php echo $callData['caller_id']; ?>,
            recipientId: <?php echo $callData['recipient_id']; ?>,
            callerName: '<?php echo htmlspecialchars($callData['caller_name']); ?>',
            recipientName: '<?php echo htmlspecialchars($callData['recipient_name']); ?>'
        };
        
        // Elementet e UI
        const remoteVideo = document.getElementById('remoteVideo');
        const localVideo = document.getElementById('localVideo');
        const toggleMicBtn = document.getElementById('toggleMicBtn');
        const toggleVideoBtn = document.getElementById('toggleVideoBtn');
        const toggleScreenShareBtn = document.getElementById('toggleScreenShareBtn');
        const toggleChatBtn = document.getElementById('toggleChatBtn');
        const endCallBtn = document.getElementById('endCallBtn');
        const connectionStatus = document.getElementById('connectionStatus');
        const callTimer = document.getElementById('callTimer');
        const remoteParticipantName = document.getElementById('remoteParticipantName');
        const chatContainer = document.getElementById('chatContainer');
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const sendChatBtn = document.getElementById('sendChatBtn');
        const closeChatBtn = document.getElementById('closeChatBtn');
        const reconnectingOverlay = document.getElementById('reconnectingOverlay');
        const tooltip = document.getElementById('tooltip');
        
        // Variablat për timer
        let callStartTime;
        let callTimerInterval;
        let callDuration = 0;
        
        // Inicializimi i WebRTC
        const webrtc = new NoteriaWebRTC({
            roomId: callData.roomId,
            userId: callData.userId,
            username: callData.username,
            localVideoElement: localVideo,
            remoteVideoElement: remoteVideo,
            signalingUrl: 'video_call_signaling.php',
            onConnectionStateChange: handleConnectionStateChange,
            onRemoteStreamAdded: handleRemoteStreamAdded,
            onRemoteStreamRemoved: handleRemoteStreamRemoved,
            onLocalStreamAdded: handleLocalStreamAdded,
            onMessage: handleMessage
        });
        
        // Funksionet e event handler
        function handleConnectionStateChange(state) {
            console.log('Gjendja e lidhjes ndryshoi:', state);
            
            if (state.isConnected) {
                connectionStatus.textContent = 'Lidhja aktive';
                reconnectingOverlay.style.display = 'none';
                
                // Nëse timeri nuk është nisur, nise
                if (!callStartTime) {
                    startCallTimer();
                }
            } else if (state.iceState === 'checking' || state.connState === 'connecting') {
                connectionStatus.textContent = 'Duke u lidhur...';
            } else if (state.iceState === 'disconnected' || state.connState === 'disconnected') {
                connectionStatus.textContent = 'U shkëput, duke u rilidhur...';
                reconnectingOverlay.style.display = 'flex';
            } else if (state.iceState === 'failed' || state.connState === 'failed') {
                connectionStatus.textContent = 'Lidhja dështoi';
                reconnectingOverlay.style.display = 'flex';
            } else if (state.iceState === 'closed' || state.connState === 'closed') {
                connectionStatus.textContent = 'Lidhja u mbyll';
            }
        }
        
        function handleRemoteStreamAdded(stream) {
            console.log('Remote stream u shtua');
            
            // Vendos emrin e pjesëmarrësit
            const isCallerRemote = callData.userId !== callData.callerId;
            remoteParticipantName.textContent = isCallerRemote ? callData.callerName : callData.recipientName;
            
            // Shfaq një njoftim që lidhja është krijuar
            showToast('Lidhja u krijua me sukses', 'success');
        }
        
        function handleRemoteStreamRemoved() {
            console.log('Remote stream u hoq');
        }
        
        function handleLocalStreamAdded(stream) {
            console.log('Local stream u shtua');
        }
        
        function handleMessage(message) {
            console.log('Mesazh i marrë:', message);
            
            if (message.type === 'chat') {
                // Shto mesazhin në chat
                addChatMessage(
                    message.data.username,
                    message.data.message,
                    message.data.timestamp,
                    false
                );
            } else if (message.type === 'leave') {
                // Përdoruesi tjetër u largua
                showToast(`${message.data.username} u largua nga thirrja`, 'warning');
                endCall();
            }
        }
        
        // Funksionet ndihmëse
        function startCallTimer() {
            callStartTime = Date.now() - callDuration * 1000;
            
            callTimerInterval = setInterval(() => {
                const elapsedSeconds = Math.floor((Date.now() - callStartTime) / 1000);
                callDuration = elapsedSeconds;
                
                const hours = Math.floor(elapsedSeconds / 3600).toString().padStart(2, '0');
                const minutes = Math.floor((elapsedSeconds % 3600) / 60).toString().padStart(2, '0');
                const seconds = (elapsedSeconds % 60).toString().padStart(2, '0');
                
                callTimer.textContent = `${hours}:${minutes}:${seconds}`;
            }, 1000);
        }
        
        function stopCallTimer() {
            if (callTimerInterval) {
                clearInterval(callTimerInterval);
                callTimerInterval = null;
            }
        }
        
        function toggleMicrophone() {
            const result = webrtc.toggleAudio();
            
            if (result) {
                if (webrtc.isAudioMuted) {
                    toggleMicBtn.innerHTML = '<i class="fas fa-microphone-slash"></i>';
                    toggleMicBtn.classList.add('muted');
                    showTooltip(toggleMicBtn, 'Audio e çaktivizuar');
                } else {
                    toggleMicBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                    toggleMicBtn.classList.remove('muted');
                    showTooltip(toggleMicBtn, 'Audio e aktivizuar');
                }
            }
        }
        
        function toggleVideo() {
            const result = webrtc.toggleVideo();
            
            if (result) {
                if (webrtc.isVideoOff) {
                    toggleVideoBtn.innerHTML = '<i class="fas fa-video-slash"></i>';
                    toggleVideoBtn.classList.add('video-off');
                    showTooltip(toggleVideoBtn, 'Kamera e çaktivizuar');
                } else {
                    toggleVideoBtn.innerHTML = '<i class="fas fa-video"></i>';
                    toggleVideoBtn.classList.remove('video-off');
                    showTooltip(toggleVideoBtn, 'Kamera e aktivizuar');
                }
            }
        }
        
        function toggleScreenShare() {
            if (webrtc.isScreenSharing) {
                webrtc.stopScreenShare();
                toggleScreenShareBtn.innerHTML = '<i class="fas fa-desktop"></i>';
                showTooltip(toggleScreenShareBtn, 'Ndarja e ekranit përfundoi');
            } else {
                webrtc.startScreenShare().then(success => {
                    if (success) {
                        toggleScreenShareBtn.innerHTML = '<i class="fas fa-stop-circle"></i>';
                        showTooltip(toggleScreenShareBtn, 'Ndarja e ekranit aktive');
                    }
                });
            }
        }
        
        function toggleChat() {
            if (chatContainer.style.display === 'none' || !chatContainer.style.display) {
                chatContainer.style.display = 'flex';
                showTooltip(toggleChatBtn, 'Chat i hapur');
            } else {
                chatContainer.style.display = 'none';
                showTooltip(toggleChatBtn, 'Chat i mbyllur');
            }
        }
        
        function sendChatMessage() {
            const message = chatInput.value.trim();
            
            if (message) {
                webrtc.sendChatMessage(message).then(success => {
                    if (success) {
                        // Shto mesazhin në chat
                        addChatMessage(
                            callData.username,
                            message,
                            Math.floor(Date.now() / 1000),
                            true
                        );
                        
                        // Pastro fushën e hyrjes
                        chatInput.value = '';
                    }
                });
            }
        }
        
        function addChatMessage(sender, message, timestamp, isSent) {
            const messageElement = document.createElement('div');
            messageElement.className = `chat-message ${isSent ? 'sent' : 'received'}`;
            
            const senderElement = document.createElement('div');
            senderElement.className = 'sender';
            senderElement.textContent = sender;
            
            const contentElement = document.createElement('div');
            contentElement.className = 'content';
            contentElement.textContent = message;
            
            const timeElement = document.createElement('div');
            timeElement.className = 'time';
            
            const date = new Date(timestamp * 1000);
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            timeElement.textContent = `${hours}:${minutes}`;
            
            messageElement.appendChild(senderElement);
            messageElement.appendChild(contentElement);
            messageElement.appendChild(timeElement);
            
            chatMessages.appendChild(messageElement);
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        function endCall() {
            // Përditëso kohëzgjatjen e thirrjes në databazë
            fetch('update_call_duration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    call_id: callData.roomId,
                    duration: callDuration
                }),
            })
            .then(response => response.json())
            .then(data => {
                console.log('Thirrja u mbyll me sukses:', data);
            })
            .catch(error => {
                console.error('Gabim në mbylljen e thirrjes:', error);
            });
            
            // Ndalo timerin
            stopCallTimer();
            
            // Mbyll lidhjen
            webrtc.close();
            
            // Kthehu në faqen e mëparshme
            window.location.href = 'dashboard.php';
        }
        
        function showTooltip(element, text) {
            const rect = element.getBoundingClientRect();
            tooltip.textContent = text;
            tooltip.style.display = 'block';
            tooltip.style.top = `${rect.bottom + 10}px`;
            tooltip.style.left = `${rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
            
            setTimeout(() => {
                tooltip.style.display = 'none';
            }, 2000);
        }
        
        function showToast(message, type = 'info') {
            // Implementim i thjeshtë i njoftimit
            console.log(`[${type}] ${message}`);
            
            // Këtu mund të implementoni një njoftim të plotë
            // toast({ message, type });
        }
        
        // Event listeners
        toggleMicBtn.addEventListener('click', toggleMicrophone);
        toggleVideoBtn.addEventListener('click', toggleVideo);
        toggleScreenShareBtn.addEventListener('click', toggleScreenShare);
        toggleChatBtn.addEventListener('click', toggleChat);
        endCallBtn.addEventListener('click', endCall);
        closeChatBtn.addEventListener('click', toggleChat);
        sendChatBtn.addEventListener('click', sendChatMessage);
        
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
        
        // Nisja e lidhjes kur faqja është gati
        document.addEventListener('DOMContentLoaded', () => {
            // Inicializimi i WebRTC
            webrtc.init().then(success => {
                if (!success) {
                    showToast('Nuk mund të aksesohet kamera ose mikrofoni', 'error');
                }
            });
            
            // Trajtimi i evenimentit beforeunload
            window.addEventListener('beforeunload', (e) => {
                // Mbyll lidhjen para se të largohet nga faqja
                webrtc.close();
            });
            
            // Trajtimi i evenimentit për udhëzime përdorimi (opsionale)
            // showCallInstructions();
        });
    </script>
</body>
</html>