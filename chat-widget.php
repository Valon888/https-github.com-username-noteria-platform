<!-- Live Chat Widget -->
<style>
/* Chat Widget Styles */
.chat-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.chat-toggle {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
    border-radius: 50%;
    border: none;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 8px 24px rgba(0,51,102,0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.chat-toggle::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.chat-toggle:hover::before {
    width: 120px;
    height: 120px;
}

.chat-toggle:hover {
    transform: scale(1.1) translateY(-2px);
    box-shadow: 0 12px 32px rgba(0,51,102,0.4);
}

.chat-toggle.active {
    background: linear-gradient(135deg, var(--rks-gold) 0%, var(--rks-gold-light) 100%);
}

.chat-window {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 350px;
    height: 500px;
    background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,51,102,0.25);
    border: 2px solid rgba(0,51,102,0.1);
    display: none;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.chat-header {
    background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chat-header .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.chat-header .info h5 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.chat-header .info p {
    margin: 0;
    font-size: 12px;
    opacity: 0.8;
}

.chat-close {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s;
}

.chat-close:hover {
    background: rgba(255,255,255,0.2);
}

.chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.message {
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
}

.message.support {
    justify-content: flex-start;
}

.message.user {
    justify-content: flex-end;
}

.message .avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-right: 12px;
    flex-shrink: 0;
}

.message.support .avatar {
    background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.message.user .avatar {
    background: linear-gradient(135deg, var(--rks-gold) 0%, var(--rks-gold-light) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    margin-left: 12px;
    margin-right: 0;
}

.message .content {
    max-width: 250px;
    padding: 12px 16px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.4;
    position: relative;
}

.message.support .content {
    background: white;
    color: #333;
    border: 1px solid rgba(0,51,102,0.1);
    box-shadow: 0 2px 8px rgba(0,51,102,0.08);
}

.message.support .content::after {
    content: '';
    position: absolute;
    left: -8px;
    top: 16px;
    width: 0;
    height: 0;
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
    border-right: 8px solid white;
}

.message.user .content {
    background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(0,51,102,0.2);
}

.message.user .content::after {
    content: '';
    position: absolute;
    right: -8px;
    top: 16px;
    width: 0;
    height: 0;
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
    border-left: 8px solid var(--rks-blue);
}

.message .time {
    font-size: 11px;
    opacity: 0.6;
    margin-top: 4px;
}

.chat-input-area {
    padding: 20px;
    background: white;
    border-top: 1px solid rgba(0,51,102,0.1);
}

.chat-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid rgba(0,51,102,0.1);
    border-radius: 25px;
    outline: none;
    font-size: 14px;
    transition: border-color 0.3s;
    resize: none;
}

.chat-input:focus {
    border-color: var(--rks-blue);
    box-shadow: 0 0 0 3px rgba(0,51,102,0.1);
}

.chat-send {
    position: absolute;
    right: 25px;
    bottom: 25px;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
    border: none;
    border-radius: 50%;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(0,51,102,0.2);
}

.chat-send:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(0,51,102,0.3);
}

.typing-indicator {
    display: none;
    padding: 12px 20px;
    font-size: 14px;
    color: #666;
}

.typing-indicator .dots {
    display: inline-block;
}

.typing-indicator .dots::after {
    content: '';
    animation: typing 1.5s infinite;
}

@keyframes typing {
    0%, 60%, 100% {
        content: '...';
    }
    25% {
        content: '.';
    }
    50% {
        content: '..';
    }
}

/* Notification Badge */
.chat-notification {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 20px;
    height: 20px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    font-size: 11px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    display: none;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .chat-window {
        width: calc(100vw - 40px);
        height: calc(100vh - 120px);
        bottom: 80px;
        right: 20px;
    }

    .chat-toggle {
        bottom: 15px;
        right: 15px;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .chat-window {
        background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
        color: #e2e8f0;
    }

    .chat-messages {
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    }

    .message.support .content {
        background: #2d3748;
        color: #e2e8f0;
        border-color: #4a5568;
    }

    .message.support .content::after {
        border-right-color: #2d3748;
    }

    .chat-input-area {
        background: #1a202c;
        border-top-color: #4a5568;
    }

    .chat-input {
        background: #2d3748;
        border-color: #4a5568;
        color: #e2e8f0;
    }

    .chat-input:focus {
        border-color: var(--rks-gold);
        box-shadow: 0 0 0 3px rgba(207,168,86,0.1);
    }
}
</style>

<div class="chat-widget">
    <div class="chat-notification" id="chatNotification">3</div>
    <button class="chat-toggle" id="chatToggle">
        <i class="fas fa-comments"></i>
    </button>

    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <div class="d-flex align-items-center">
                <div class="avatar">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="info">
                    <h5>Mbështetja e-Noteria</h5>
                    <p>Online • Përgjigje brenda 2 min</p>
                </div>
            </div>
            <button class="chat-close" id="chatClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="message support">
                <div class="avatar">S</div>
                <div>
                    <div class="content">
                        Përshëndetje! 👋 Mirë se vini në e-Noteria. Si mund t'ju ndihmoj sot me shërbimet tona noteriale?
                    </div>
                    <div class="time">tani</div>
                </div>
            </div>
        </div>

        <div class="typing-indicator" id="typingIndicator">
            <span>Mbështetja po shkruan</span>
            <span class="dots"></span>
        </div>

        <div class="chat-input-area">
            <textarea
                class="chat-input"
                id="chatInput"
                placeholder="Shkruani mesazhin tuaj këtu..."
                rows="1"
                maxlength="500"></textarea>
            <button class="chat-send" id="chatSend">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
// Live Chat Functionality
document.addEventListener('DOMContentLoaded', function() {
    const chatToggle = document.getElementById('chatToggle');
    const chatWindow = document.getElementById('chatWindow');
    const chatClose = document.getElementById('chatClose');
    const chatInput = document.getElementById('chatInput');
    const chatSend = document.getElementById('chatSend');
    const chatMessages = document.getElementById('chatMessages');
    const typingIndicator = document.getElementById('typingIndicator');
    const chatNotification = document.getElementById('chatNotification');

    let messageCount = 0;
    let isTyping = false;

    // Toggle chat window
    chatToggle.addEventListener('click', function() {
        const isVisible = chatWindow.style.display === 'flex';

        if (isVisible) {
            chatWindow.style.display = 'none';
            chatToggle.classList.remove('active');
        } else {
            chatWindow.style.display = 'flex';
            chatToggle.classList.add('active');
            chatNotification.style.display = 'none';
            chatInput.focus();

            // Scroll to bottom
            setTimeout(() => {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }, 100);
        }
    });

    // Close chat
    chatClose.addEventListener('click', function() {
        chatWindow.style.display = 'none';
        chatToggle.classList.remove('active');
    });

    // Send message
    function sendMessage() {
        const message = chatInput.value.trim();
        if (!message) return;

        // Add user message
        addMessage(message, 'user');
        chatInput.value = '';

        // Show typing indicator
        showTyping();

        // Simulate response after delay
        setTimeout(() => {
            hideTyping();
            generateResponse(message);
        }, 1500 + Math.random() * 2000);
    }

    // Send on Enter
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Send on button click
    chatSend.addEventListener('click', sendMessage);

    // Auto-resize textarea
    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });

    // Add message to chat
    function addMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;

        const avatar = type === 'user' ? 'U' : 'S';
        const time = new Date().toLocaleTimeString('sq-AL', {
            hour: '2-digit',
            minute: '2-digit'
        });

        messageDiv.innerHTML = `
            <div class="avatar">${avatar}</div>
            <div>
                <div class="content">${text}</div>
                <div class="time">${time}</div>
            </div>
        `;

        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        messageCount++;
    }

    // Show typing indicator
    function showTyping() {
        typingIndicator.style.display = 'block';
        isTyping = true;
    }

    // Hide typing indicator
    function hideTyping() {
        typingIndicator.style.display = 'none';
        isTyping = false;
    }

    // Intelligent AI Response Generator
    function generateResponse(userMessage) {
        const normalizedMessage = normalizeText(userMessage);
        const intent = detectIntent(normalizedMessage);
        const response = getContextualResponse(intent, normalizedMessage);
        addMessage(response, 'support');
    }

    // Normalize text for better processing
    function normalizeText(text) {
        return text.toLowerCase()
            .replace(/ç/g, 'c')
            .replace(/ë/g, 'e')
            .replace(/[?!.,;:\'"]/g, '')
            .trim();
    }

    // Detect user intent using keyword analysis
    function detectIntent(message) {
        const keywords = {
            'registration_notary': ['regjistrim', 'noter', 'noter-i', 'abonime', 'zyre noteriale', 'si regjistroj'],
            'registration_user': ['përdorues', 'llogarit', 'themel llogari', 'regjistrohuni', 'kycu'],
            'pricing': ['çmim', 'cost', 'koston', 'pagan', 'aboniment', '150', 'euro'],
            'verification': ['verifik', 'dokument', 'kode verifikimi', 'i vlefshëm'],
            'status': ['status', 'aplikim', 'referenc', 'kontrollo'],
            'help': ['ndihm', 'pyetje', 'si', 'mund', 'problem', 'nuk funksionoj'],
            'contact': ['kontakt', 'telefon', 'email', 'adres', 'zyra'],
            'features': ['shërbim', 'çfar përfshin', 'akses', 'cloud', 'online']
        };

        for (const [intent, words] of Object.entries(keywords)) {
            if (words.some(word => message.includes(word))) {
                return intent;
            }
        }
        return 'general';
    }

    // Get contextual response based on detected intent
    function getContextualResponse(intent, message) {
        const responseBank = {
            'registration_notary': [
                'Për të regjistruar zyrën tuaj noteriale në e-Noteria:\n✓ Klikoni "Kyçuni" → "Regjistrohu si Noter"\n✓ Plotësoni të dhënat e zyrës\n✓ Ngarkoni dokumentet (licenca, certifikate)\n✓ Paguani abonimin 150€/muaj\n\nPas verifikimit, do të keni akses të plotë në platform.',
                'Regjistimi si zyrë noteriale është shumë i thjeshtë! Ju duhen:\n📄 Licenca e noterit (kopje e noterizuar)\n📋 Certifikatë e regjistrimit të biznesit\n🪪 ID personale\n🏢 Prova e adresës s\'zyrës\n📊 Certifikatë tatimore\n\nTë gjitha në PDF, jo më të mëdha se 5MB.'
            ],
            'registration_user': [
                'Si përdorues i thjeshtë, regjistrimi është FALAS! ✨\n1. Klikoni "Kyçuni" → "Regjistrohu si Përdorues"\n2. Plotësoni të dhënat tuaja personale\n3. Verifikoni email-in\n4. Krijoni fjalëkalimin\n\nGata! Tani mund të rezervoni termine dhe kontrolloni aplikime.',
                'Nuk keni nevojë të paguani për regjistrimin si përdorues! 🎉\nMerrni akses në:\n✓ Rezervim të termeve online\n✓ Ndjekje të statusit të aplikimeve\n✓ Verifikim dokumentesh\n✓ Mbështetje 24/7\n\nRejastrohu tani - është 100% falas!'
            ],
            'pricing': [
                'Abonimi SaaS për zyrat noteriale:\n💶 150€ për muaj\n\nPërfshin:\n✓ Akses cloud-based 24/7\n✓ Sistemin e rezervimeve online\n✓ Verifikimin elektronik\n✓ Raporte detaljuara\n✓ Siguri maksimum të të dhënave\n✓ Mbështetje teknike\n\nPërdoruesit e rregullt → Regjistrimi FALAS',
                'Transparenca në çmime! 💰\n📌 Zyrat noteriale: 150€/muaj\n📌 Përdoruesit individualë: FALAS\nNuk ka këpushe të fshehura! Të gjitha shërbimet janë të përfshira në abonimin bazë.'
            ],
            'verification': [
                'Për verifikimin e dokumenteve:\n1. Shkoni në faqen "Verifikimi"\n2. Shkruani kodin e verifikimit\n3. Klikoni "Verifiko Dokumentin"\n4. Merrni rezultatin menjëherë\n\nSistemi tregon: ✓ I vlefshëm | ⚠️ I skaduar | ❌ I falsifikuar',
                'Verifikimi online në 10 sekonda! ⚡\nJa si funksionon:\n→ Shkruati kodin e dokumentit\n→ Sistemi kontrollon bazën tonë\n→ Merrni rezultatin real-time\n\nSiguri 100% - i mbështetur nga Ministria e Drejtësisë'
            ],
            'status': [
                'Për të kontrolluar statusin e aplikimit tuaj:\n1. Shkoni në "Statusi"\n2. Shkruani numrin e referencës (nga emaili)\n3. Klikoni "Kontrollo"\n4. Shihni përditësimet e gjalla\n\nStatust e mundshme: 🔄 Në Proces | ✅ Aprovuar | ❌ Refuzuar',
                'Nxiti statusin e aplikimit tuaj anytime, anywhere! 📱\nMerrni njoftim të menjëhershëm kur:\n✓ Aplikimi hyn në sistem\n✓ Fillon verifikimi\n✓ Zbatohet miratimi\n✓ Është gata për marrje\n\nNjoftime SMS + Email'
            ],
            'help': [
                'Jemi këtu për t\'ju ndihmuar 24/7! 🎯\n\nMënyrat për të marrë ndihmë:\n📞 Telefon: 038 200 100\n📧 Email: support@e-noteria.rks-gov.net\n💬 Chat Live: Këtu (përgjigje brenda 2 min)\n🏢 Në person: Rruga "Agim Ramadani" nr. 1\n\nJa pyetjen tënde, do t\'ju përgjigjem menjëherë!',
                'Mbështetja jonë është super e shpejtë! ⚡\n👨‍💻 Tim teknik: Disponueshëm 24/7\n⚙️ Koha mesatare e përgjigjes: 2 minuta\n✅ Rezolucioni shpejtë: 95% probleme zgjidhen në ditën e parë\n\nSi mund t\'ju ndihmoj?'
            ],
            'contact': [
                'Na kontaktoni përmes:\n\n📞 TELEFON: 038 200 100 (24/7)\n📧 EMAIL: support@e-noteria.rks-gov.net\n💬 CHAT: Këtu (Përgjigje brenda 2 min)\n📍 ADRESA: Rruga "Agim Ramadani" nr. 1, Prishtinë 10000\n\nOrat e punës: Hënë-Premte 08:00-20:00 | Sabat-Diel: Përgjigje në 2 orë',
                'Të na gjeni lehtë! 📍\n\n💼 Zyra qendrore Prishtinë\n🌐 Disponueshëm online 24/7\n🚀 Përgjigje e shpejtë garantuar\n\nCili kanal është më i mirë për ju?'
            ],
            'features': [
                'Shërbimet e e-Noteria përfshijnë:\n\n✅ Notarizim elektronik\n✅ Verifikimi i dokumenteve\n✅ Rezervim online termesh\n✅ Ndjekje real-time statusit\n✅ Backup sigur të të dhënave\n✅ Raporte detaljuara\n✅ Mbështetje 24/7\n✅ Integrim me sisteme të tjera\n\nSecilin shërbim mund ta gjeni në menynë e sipërme.',
                'Platformën tonë përdorin 300+ zyra noteriale në Kosovë! 🚀\n\nPerformancat:\n⚡ Sistemi përballon 1M+ përdorues në ditë\n🔒 Siguri klasit enterprise\n☁️ Cloud-based - asnjë instalim\n📈 Rritje me zyrat tuaja\n\nGjithçka në një platformë të vetme!'
            ],
            'general': [
                'Përshëndetje! 👋 Unë jam asistenti AI i e-Noteria. Mund të t\'përgjigjem për:\n✓ Regjistrimin si Noter ose Përdorues\n✓ Çmimet dhe abonimi\n✓ Verifikimin e dokumenteve\n✓ Statusin e aplikimeve\n✓ Shërbimet tona\n✓ Kontaktim\n\nPyetja juaj?',
                'Si mund t\'ju ndihmoj sot? 🤖\n\nDo të preferonit të dini:\n1️⃣ Si të regjistrohem?\n2️⃣ Çfarë koston?\n3️⃣ Si verifiko dokumentet?\n4️⃣ Si kontrollo statusin?\n5️⃣ Shumica nuk di!\n\nThjesht shkruani, unë kuptoj! 😊'
            ]
        };

        const responses = responseBank[intent] || responseBank.general;
        return responses[Math.floor(Math.random() * responses.length)];
    }

    // Show notification after some time (simulate new messages)
    setTimeout(() => {
        if (chatWindow.style.display !== 'flex') {
            chatNotification.style.display = 'flex';
        }
    }, 10000);

    // Close chat when clicking outside
    document.addEventListener('click', function(e) {
        if (!chatWidget.contains(e.target) && chatWindow.style.display === 'flex') {
            chatWindow.style.display = 'none';
            chatToggle.classList.remove('active');
        }
    });
});
</script>