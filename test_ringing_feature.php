<?php
// Test ringing feature
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Ringing Feature - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            padding: 20px;
        }
        .container {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.37);
            max-width: 600px;
            width: 100%;
        }
        h1 {
            margin-bottom: 10px;
            font-size: 2.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            text-align: center;
        }
        .subtitle {
            text-align: center;
            font-size: 1rem;
            color: #ccc;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255,255,255,0.05);
            border-left: 4px solid #ffeb3b;
            border-radius: 10px;
        }
        .section h2 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: #ffeb3b;
        }
        .controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        button {
            padding: 15px;
            font-size: 0.95rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            color: #fff;
        }
        .btn-play {
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            grid-column: 1 / 2;
        }
        .btn-play:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(67, 160, 71, 0.6);
        }
        .btn-stop {
            background: linear-gradient(135deg, #e53935 0%, #ef5350 100%);
            grid-column: 2 / 3;
        }
        .btn-stop:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(229, 57, 53, 0.6);
        }
        .btn-call {
            background: linear-gradient(135deg, #3949ab 0%, #1e88e5 100%);
            grid-column: 1 / -1;
            padding: 18px;
            font-size: 1.1rem;
        }
        .btn-call:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(33, 150, 243, 0.6);
        }
        .info {
            background: rgba(100,200,255,0.1);
            padding: 15px;
            border-left: 4px solid #64c8ff;
            border-radius: 5px;
            font-size: 0.95rem;
            line-height: 1.8;
        }
        .info ul {
            margin-left: 25px;
            margin-top: 10px;
        }
        .info li {
            margin-bottom: 8px;
        }
        .audio-player {
            margin-top: 20px;
            width: 100%;
        }
        .status {
            padding: 15px;
            background: rgba(255,235,59,0.15);
            border: 1px solid #ffeb3b;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            color: #ffeb3b;
            display: none;
        }
        .status.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Audio element for ringtone -->
    <audio id="ringtone" preload="auto" loop>
        <source src="ringtone-031-437514.mp3" type="audio/mpeg">
        Your browser does not support the audio element.
    </audio>

    <div class="container">
        <h1>🔔 Ringing Feature Test</h1>
        <p class="subtitle">Testa sistemin e ziles për thirrjet video</p>
        
        <div class="section">
            <h2>🎮 Kontrollat</h2>
            <div class="controls">
                <button class="btn-play" onclick="playRingtone()">
                    <i class="fas fa-play"></i> Luaj Zilen
                </button>
                <button class="btn-stop" onclick="stopRingtone()">
                    <i class="fas fa-stop"></i> Ndal Zilen
                </button>
                <button class="btn-call" onclick="simulateIncomingCall()">
                    <i class="fas fa-phone-incoming"></i> Simuloj Thirrje Hyrëse
                </button>
            </div>
            <div class="status" id="status"></div>
        </div>

        <div class="section">
            <h2>📖 Si Funksionon</h2>
            <div class="info">
                <strong>Shënimet e Ringing Feature:</strong>
                <ul>
                    <li><strong>Luaj Zilen:</strong> Fillon zilen e telefonit me 70% volume</li>
                    <li><strong>Ndal Zilen:</strong> Ndalon zilen menjëherë</li>
                    <li><strong>Simuloj Thirrje:</strong> Shfaq modalin e thirrjes me zile (si Viber/WhatsApp)</li>
                    <li><strong>Auto-timeout:</strong> Thirrja nuk pranohet në 30 sekonda</li>
                </ul>
            </div>
        </div>

        <div class="section">
            <h2>🎵 Kontrolli i Audio-it</h2>
            <audio id="ringtone" controls class="audio-player">
                <source src="ringtone-031-437514.mp3" type="audio/mpeg">
            </audio>
        </div>

        <div class="section" style="background: rgba(76,175,80,0.1); border-left-color: #66bb6a;">
            <h2 style="color: #66bb6a;">✅ Karakteristikat</h2>
            <div class="info" style="background: transparent; border-left-color: #66bb6a;">
                <ul>
                    <li>Zile profesionale me opcione të ndryshme</li>
                    <li>Modal me animacione smooth</li>
                    <li>Avatar me pulsing glow effect</li>
                    <li>Buttons Prano/Refuzo me hover effects</li>
                    <li>Compatible me të gjithë browserët</li>
                    <li>Mobile responsive design</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function setStatus(message, duration) {
            var statusEl = document.getElementById('status');
            if (message) {
                statusEl.textContent = message;
                statusEl.classList.add('active');
                if (duration) {
                    setTimeout(function() {
                        statusEl.classList.remove('active');
                    }, duration);
                }
            } else {
                statusEl.classList.remove('active');
            }
        }

        function playRingtone() {
            var audio = document.getElementById('ringtone');
            if (audio) {
                audio.volume = 0.7;
                audio.play().catch(function(error) {
                    console.log("Audio play failed:", error);
                    setStatus('❌ Nuk mund të luaj zilen', 3000);
                });
                setStatus('🔔 Zila po luan...', 0);
            }
        }

        function stopRingtone() {
            var audio = document.getElementById('ringtone');
            if (audio) {
                audio.pause();
                audio.currentTime = 0;
            }
            setStatus('🛑 Zila u ndal', 2000);
        }

        function showIncomingCall(callerName) {
            var html = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); z-index: 99999; display: flex; justify-content: center; align-items: center;" id="callModal" onclick="this.id!=='callModal' || rejectCall()">
                    <div style="background: linear-gradient(135deg, #1a237e 0%, #3949ab 50%, #1565c0 100%); border-radius: 30px; padding: 50px 40px; text-align: center; box-shadow: 0 15px 50px rgba(0, 0, 0, 0.6); max-width: 500px; animation: slideUp 0.5s ease-out;" onclick="event.stopPropagation();">
                        <div style="width: 130px; height: 130px; border-radius: 50%; background: linear-gradient(135deg, #3949ab 0%, #1e88e5 100%); margin: 0 auto 30px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 40px rgba(33, 150, 243, 0.9); animation: pulse 2s infinite;">
                            <i class="fas fa-video" style="font-size: 4.5rem; color: #fff;"></i>
                        </div>
                        <div style="font-size: 2.3rem; font-weight: 700; color: #fff; margin-bottom: 10px; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);">` + (callerName || 'Noter') + `</div>
                        <div style="font-size: 1.2rem; color: #ffeb3b; margin-bottom: 35px; animation: blink 1s infinite;">🔔 Po thërret...</div>
                        <div style="display: flex; gap: 25px; justify-content: center;">
                            <button onclick="acceptCall()" style="width: 90px; height: 90px; border-radius: 50%; border: none; font-size: 2.5rem; background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%); color: #fff; cursor: pointer; box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3); transition: all 0.3s ease;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="rejectCall()" style="width: 90px; height: 90px; border-radius: 50%; border: none; font-size: 2.5rem; background: linear-gradient(135deg, #e53935 0%, #ef5350 100%); color: #fff; cursor: pointer; box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3); transition: all 0.3s ease;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes slideUp {
                        from { transform: translateY(50px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                    @keyframes pulse {
                        0%, 100% { box-shadow: 0 0 40px rgba(33, 150, 243, 0.9); }
                        50% { box-shadow: 0 0 60px rgba(33, 150, 243, 1); }
                    }
                    @keyframes blink {
                        0%, 50%, 100% { opacity: 1; }
                        25%, 75% { opacity: 0.6; }
                    }
                </style>
            `;
            document.body.insertAdjacentHTML('beforeend', html);
            playRingtone();
        }

        function acceptCall() {
            stopRingtone();
            var modal = document.getElementById('callModal');
            if (modal) {
                modal.remove();
            }
            setStatus('✅ Thirrja u pranua!', 3000);
        }

        function rejectCall() {
            stopRingtone();
            var modal = document.getElementById('callModal');
            if (modal) {
                modal.remove();
            }
            setStatus('❌ Thirrja u refuzua!', 3000);
        }

        function simulateIncomingCall() {
            var callerName = prompt('Emri i llamuesit:', 'Noter');
            if (callerName !== null && callerName.trim() !== '') {
                showIncomingCall(callerName);
            }
        }
    </script>
</body>
</html>
