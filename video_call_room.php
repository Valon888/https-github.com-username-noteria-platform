<?php
require_once __DIR__ . '/SecurityHeaders.php';
$room = $_GET['room'] ?? '';
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Video Thirrje + Chat + Screen Share</title>
    <style>
        video { width: 45%; margin: 1%; border: 2px solid #333; border-radius: 8px; }
        #status { margin: 10px 0; font-weight: bold; }
        #chat { width: 90%; margin: 10px auto; border: 1px solid #aaa; padding: 10px; border-radius: 8px; }
        #messages { height: 120px; overflow-y: auto; background: #f8f8f8; margin-bottom: 5px; }
        #chat input { width: 80%; }
        #chat button { width: 18%; }
        #controls { margin: 10px 0; }
    </style>
</head>
<body>
    <h2>Video Thirrje + Chat + Screen Share</h2>
    <div id="status">Duke pritur përdoruesin tjetër...</div>
    <div id="controls">
        <button id="muteBtn">Mute</button>
        <button id="screenBtn">Ndaj ekranin</button>
        <button id="endBtn">End Call</button>
    </div>
    <video id="localVideo" autoplay muted playsinline></video>
    <video id="remoteVideo" autoplay playsinline></video>
    <div id="chat">
        <div id="messages"></div>
        <input id="msgInput" type="text" placeholder="Shkruaj mesazhin...">
        <button id="sendBtn">Dërgo</button>
    </div>
    <script>
    const room = "<?php echo htmlspecialchars($room); ?>";
    const token = "<?php echo htmlspecialchars($token); ?>";
    const ws = new WebSocket("ws://localhost:3001");
    let pc = null;
    let isInitiator = false;
    let started = false;
    let localStream = null;
    let dataChannel = null;
    let screenStream = null;

    ws.onopen = () => {
        ws.send(JSON.stringify({ token, room, data: { type: "join" } }));
        logStatus("U lidh me signaling server...");
    };

    ws.onmessage = async (event) => {
        const msg = JSON.parse(event.data);
        if (!msg.data) return;

        if (msg.data.type === "join") {
            if (!started) {
                isInitiator = true;
                await start();
            }
        } else if (msg.data.type === "offer") {
            if (!started) await start();
            await pc.setRemoteDescription(new RTCSessionDescription(msg.data.offer));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            sendSignal({ type: "answer", answer });
            logStatus("U pranua oferta, u dërgua përgjigja.");
        } else if (msg.data.type === "answer") {
            await pc.setRemoteDescription(new RTCSessionDescription(msg.data.answer));
            logStatus("U pranua përgjigja, lidhja është gati!");
        } else if (msg.data.type === "candidate") {
            if (msg.data.candidate) {
                try {
                    await pc.addIceCandidate(new RTCIceCandidate(msg.data.candidate));
                } catch (e) {
                    console.error('Gabim në ICE candidate:', e);
                }
            }
        }
    };

    function sendSignal(data) {
        ws.send(JSON.stringify({ token, room, data }));
    }

    function logStatus(text) {
        document.getElementById('status').innerText = text;
    }

    async function start() {
        started = true;
        pc = new RTCPeerConnection();
        pc.onicecandidate = (event) => {
            if (event.candidate) sendSignal({ type: "candidate", candidate: event.candidate });
        };
        pc.ontrack = (event) => {
            document.getElementById('remoteVideo').srcObject = event.streams[0];
            logStatus("Lidhja u realizua! Mund të flisni.");
        };

        // DataChannel për chat
        if (isInitiator) {
            dataChannel = pc.createDataChannel("chat");
            setupDataChannel();
        } else {
            pc.ondatachannel = (event) => {
                dataChannel = event.channel;
                setupDataChannel();
            };
        }

        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        document.getElementById('localVideo').srcObject = localStream;
        localStream.getTracks().forEach(track => pc.addTrack(track, localStream));

        if (isInitiator) {
            logStatus("Duke krijuar ofertë...");
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            sendSignal({ type: "offer", offer });
        } else {
            logStatus("Duke pritur ofertë nga përdoruesi tjetër...");
        }
    }

    // CHAT
    function setupDataChannel() {
        dataChannel.onopen = () => logStatus("Chat i hapur!");
        dataChannel.onmessage = (event) => addMessage("Tjetri: " + event.data, "left");
    }
    document.getElementById('sendBtn').onclick = sendMsg;
    document.getElementById('msgInput').onkeydown = function(e) { if (e.key === "Enter") sendMsg(); };
    function sendMsg() {
        const val = document.getElementById('msgInput').value;
        if (val && dataChannel && dataChannel.readyState === "open") {
            dataChannel.send(val);
            addMessage("Ti: " + val, "right");
            document.getElementById('msgInput').value = "";
        }
    }
    function addMessage(msg, align) {
        const div = document.createElement('div');
        div.style.textAlign = align;
        div.textContent = msg;
        document.getElementById('messages').appendChild(div);
        document.getElementById('messages').scrollTop = 9999;
    }

    // Mute/Unmute
    document.getElementById('muteBtn').onclick = function() {
        if (!localStream) return;
        const audioTrack = localStream.getAudioTracks()[0];
        if (!audioTrack) return;
        audioTrack.enabled = !audioTrack.enabled;
        this.innerText = audioTrack.enabled ? "Mute" : "Unmute";
    };

    // End Call
    document.getElementById('endBtn').onclick = function() {
        if (pc) pc.close();
        if (localStream) localStream.getTracks().forEach(track => track.stop());
        if (screenStream) screenStream.getTracks().forEach(track => track.stop());
        ws.close();
        logStatus("Thirrja u mbyll.");
        setTimeout(() => window.close(), 1000);
    };

    // Screen Sharing
    document.getElementById('screenBtn').onclick = async function() {
        if (!pc) return;
        try {
            screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
            const screenTrack = screenStream.getVideoTracks()[0];
            const sender = pc.getSenders().find(s => s.track.kind === "video");
            if (sender) sender.replaceTrack(screenTrack);
            screenTrack.onended = async () => {
                // Kthehu në kamerë kur ndalet sharing
                const camTrack = localStream.getVideoTracks()[0];
                if (sender) sender.replaceTrack(camTrack);
            };
        } catch (e) {
            alert("Nuk u lejua ndarja e ekranit!");
        }
    };

    // Kur mbyllet dritarja, mbyll gjithçka
    window.onbeforeunload = function() {
        if (pc) pc.close();
        if (localStream) localStream.getTracks().forEach(track => track.stop());
        if (screenStream) screenStream.getTracks().forEach(track => track.stop());
        ws.close();
    };
    </script>
</body>
</html>