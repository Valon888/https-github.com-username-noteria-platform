<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize conversation history in session
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = array();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Noteria — Asistent AI 24/7</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/fontawesome/all.min.css">
<style>
  :root {
    --ink: #1a1410;
    --parchment: #f5f0e8;
    --gold: #b8962e;
    --gold-light: #d4af5a;
    --gold-dark: #8b6914;
    --seal: #8b1a1a;
    --cream: #faf7f2;
    --white: #ffffff;
    --muted: #7a6e60;
    --border: #e0d8cc;
    --success: #22c55e;
    --error: #ef4444;
    --shadow: 0 8px 32px rgba(26,20,16,0.12);
    --shadow-sm: 0 2px 8px rgba(26,20,16,0.06);
  }

  * { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box;
  }

  html {
    scroll-behavior: smooth;
  }

  body {
    font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, var(--cream) 0%, #ede8dc 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background-attachment: fixed;
  }

  body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
      radial-gradient(ellipse at 20% 50%, rgba(184,150,46,0.04) 0%, transparent 60%),
      radial-gradient(ellipse at 80% 20%, rgba(139,26,26,0.03) 0%, transparent 50%);
    pointer-events: none;
    z-index: -1;
  }

  /* Page wrapper */
  .page {
    width: 100%;
    max-width: 950px;
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  /* Site header strip */
  .site-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 32px;
    background: linear-gradient(135deg, var(--ink) 0%, #2a1f18 100%);
    border-radius: 8px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
  }

  .site-header:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
  }

  .site-logo {
    font-family: 'Cormorant Garamond', serif;
    font-size: 26px;
    font-weight: 700;
    color: var(--gold-light);
    letter-spacing: 0.12em;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .site-logo i {
    font-size: 28px;
    color: var(--gold);
  }

  .site-nav {
    display: flex;
    gap: 32px;
  }

  .site-nav a {
    font-size: 13px;
    color: rgba(255,255,255,0.65);
    text-decoration: none;
    letter-spacing: 0.04em;
    font-weight: 400;
    transition: all 0.2s ease;
    position: relative;
  }

  .site-nav a::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--gold);
    transition: width 0.3s ease;
  }

  .site-nav a:hover {
    color: var(--gold-light);
  }

  .site-nav a:hover::after {
    width: 100%;
  }

  /* Main chat card */
  .chat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    height: 700px;
    transition: all 0.3s ease;
    position: relative;
  }

  .chat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--gold), var(--gold-light), var(--seal), var(--gold));
    background-size: 200% 100%;
    animation: gradientShift 3s ease infinite;
  }

  @keyframes gradientShift {
    0%, 100% { background-position: 0% 0%; }
    50% { background-position: 100% 0%; }
  }

  /* Chat header */
  .chat-header {
    background: linear-gradient(135deg, var(--ink) 0%, #2a1f18 100%);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    border-bottom: 1px solid rgba(184,150,46,0.2);
  }

  .chat-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold-light), var(--seal));
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Cormorant Garamond', serif;
    font-size: 22px;
    color: white;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(184,150,46,0.3);
    animation: floatIn 0.5s ease;
  }

  @keyframes floatIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
  }

  .chat-header-info h3 {
    font-family: 'Cormorant Garamond', serif;
    font-size: 18px;
    font-weight: 600;
    color: white;
    letter-spacing: 0.02em;
    margin-bottom: 2px;
  }

  .chat-header-info p {
    font-size: 12px;
    color: var(--gold-light);
    font-weight: 400;
    letter-spacing: 0.03em;
  }

  .status-indicator {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--success);
  }

  .status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--success);
    box-shadow: 0 0 8px rgba(34,197,94,0.6);
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 8px rgba(34,197,94,0.6); }
    50% { opacity: 0.6; box-shadow: 0 0 12px rgba(34,197,94,0.8); }
  }

  /* Messages area */
  .messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 28px 24px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    scroll-behavior: smooth;
    background: linear-gradient(180deg, var(--white) 0%, #fdfaf5 100%);
  }

  .messages::-webkit-scrollbar {
    width: 6px;
  }

  .messages::-webkit-scrollbar-track {
    background: transparent;
  }

  .messages::-webkit-scrollbar-thumb {
    background: rgba(184,150,46,0.4);
    border-radius: 3px;
    transition: background 0.2s;
  }

  .messages::-webkit-scrollbar-thumb:hover {
    background: rgba(184,150,46,0.6);
  }

  /* Message bubbles */
  .message {
    display: flex;
    gap: 12px;
    max-width: 85%;
    animation: slideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    opacity: 1;
  }

  @keyframes slideIn {
    from {
      opacity: 0;
      transform: translateY(12px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .message.user {
    align-self: flex-end;
    flex-direction: row-reverse;
  }

  .message.ai {
    align-self: flex-start;
  }

  .msg-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    margin-top: 4px;
  }

  .message.ai .msg-avatar {
    background: linear-gradient(135deg, var(--gold-light), var(--gold-dark));
    color: white;
    font-family: 'Cormorant Garamond', serif;
    font-size: 16px;
    box-shadow: 0 2px 8px rgba(184,150,46,0.3);
  }

  .message.user .msg-avatar {
    background: linear-gradient(135deg, var(--ink), #2a1f18);
    color: rgba(255,255,255,0.9);
    font-size: 12px;
    box-shadow: 0 2px 8px rgba(26,20,16,0.2);
  }

  .bubble {
    padding: 14px 18px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.7;
    word-wrap: break-word;
  }

  .message.ai .bubble {
    background: linear-gradient(135deg, var(--parchment), #f3ede0);
    color: var(--ink);
    border-top-left-radius: 6px;
    border: 1px solid rgba(184,150,46,0.2);
    box-shadow: 0 2px 8px rgba(26,20,16,0.04);
  }

  .message.user .bubble {
    background: linear-gradient(135deg, var(--ink), #2a1f18);
    color: white;
    border-top-right-radius: 6px;
    box-shadow: 0 2px 8px rgba(26,20,16,0.15);
  }

  .bubble strong { font-weight: 600; color: var(--gold); }
  .bubble em { font-style: italic; opacity: 0.9; }
  
  .bubble ul, .bubble ol {
    margin: 8px 0 8px 20px;
  }

  .bubble li {
    margin: 6px 0;
  }

  /* Quick replies */
  .quick-replies {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
    padding-left: 48px;
    animation: slideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  .quick-btn {
    padding: 9px 16px;
    border: 1.5px solid var(--gold);
    background: rgba(184,150,46,0.05);
    color: var(--gold);
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;
    letter-spacing: 0.02em;
  }

  .quick-btn:hover {
    background: var(--gold);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(184,150,46,0.3);
  }

  .quick-btn:active {
    transform: translateY(0);
  }

  /* Typing indicator */
  .typing-indicator {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    animation: slideIn 0.3s ease;
  }

  .typing-dots {
    background: linear-gradient(135deg, var(--parchment), #f3ede0);
    border: 1px solid rgba(184,150,46,0.2);
    padding: 14px 18px;
    border-radius: 18px;
    border-top-left-radius: 6px;
    display: flex;
    gap: 6px;
    align-items: center;
    box-shadow: 0 2px 8px rgba(26,20,16,0.04);
  }

  .typing-dots span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--muted);
    animation: bounce 1.4s infinite;
  }

  .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
  .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

  @keyframes bounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 1; }
    30% { transform: translateY(-8px); opacity: 0.8; }
  }

  /* Input area */
  .chat-input-area {
    border-top: 1px solid var(--border);
    padding: 18px 20px;
    display: flex;
    gap: 14px;
    align-items: flex-end;
    background: linear-gradient(180deg, #faf7f2 0%, var(--parchment) 100%);
  }

  .input-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    background: white;
    border: 1.5px solid var(--border);
    border-radius: 26px;
    padding: 12px 18px;
    gap: 10px;
    transition: all 0.2s ease;
  }

  .input-wrapper:focus-within {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(184,150,46,0.1);
  }

  #userInput {
    flex: 1;
    border: none;
    outline: none;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--ink);
    background: transparent;
    resize: none;
    max-height: 100px;
    line-height: 1.6;
  }

  #userInput::placeholder {
    color: rgba(122,110,96,0.6);
    font-weight: 400;
  }

  .send-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--ink), #2a1f18);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(26,20,16,0.2);
  }

  .send-btn:hover {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(184,150,46,0.3);
  }

  .send-btn:active {
    transform: translateY(-1px);
  }

  .send-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none;
  }

  .send-btn i {
    color: white;
    font-size: 16px;
  }

  /* Info strip below */
  .info-strip {
    text-align: center;
    font-size: 12px;
    color: var(--muted);
    letter-spacing: 0.03em;
    padding: 12px;
  }

  .info-strip strong {
    color: var(--gold);
    font-weight: 600;
  }

  .info-strip a {
    color: var(--gold);
    text-decoration: none;
    transition: all 0.2s ease;
  }

  .info-strip a:hover {
    text-decoration: underline;
  }

  /* Appointment card in chat */
  .appointment-card {
    background: linear-gradient(135deg, #fafaf9, #f5f3f0);
    border: 1.5px solid var(--gold);
    border-radius: 12px;
    padding: 18px;
    margin-top: 12px;
    font-size: 13px;
    box-shadow: 0 4px 12px rgba(184,150,46,0.15);
  }

  .appointment-card .appt-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 16px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .appt-row {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--muted);
    margin-bottom: 10px;
    font-size: 13px;
  }

  .appt-row span {
    color: var(--ink);
    font-weight: 600;
  }

  .appt-confirm-btn {
    width: 100%;
    margin-top: 14px;
    padding: 11px;
    background: linear-gradient(135deg, var(--ink), #2a1f18);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    cursor: pointer;
    letter-spacing: 0.03em;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(26,20,16,0.15);
  }

  .appt-confirm-btn:hover {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(184,150,46,0.3);
  }

  .appt-confirm-btn:active {
    transform: translateY(0);
  }

  /* Responsive */
  @media (max-width: 768px) {
    .page { max-width: 100%; padding: 16px; gap: 16px; }
    .chat-card { height: 600px; border-radius: 12px; }
    .menu-trigger { display: block; cursor: pointer; }
    .site-nav { display: flex; }
    .message { max-width: 95%; }
  }

  /* Utilities */
  .text-center { text-align: center; }
  .mt-2 { margin-top: 8px; }
  .mb-2 { margin-bottom: 8px; }
</style>
</head>
<body>

<div class="page">

  <!-- Fancy site header -->
  <div class="site-header">
    <div class="site-logo">
      <i class="fas fa-stamp"></i> N O T E R I A
    </div>
    <nav class="site-nav">
      <a href="#"><i class="fas fa-check-circle"></i> Shërbimet</a>
      <a href="#"><i class="fas fa-tag"></i> Tarifat</a>
      <a href="#"><i class="fas fa-envelope"></i> Kontakti</a>
      <a href="#"><i class="fas fa-calendar-alt"></i> Rezervo</a>
    </nav>
  </div>

  <!-- Chat widget -->
  <div class="chat-card">
    <div class="chat-header">
      <div class="chat-avatar">N</div>
      <div class="chat-header-info">
        <h3>Asistenti i Noterisë</h3>
        <p>Fuqizuar nga AI avancuar · Disponibël 24/7</p>
      </div>
      <div class="status-indicator">
        <span class="status-dot"></span>
        <span>Aktiv</span>
      </div>
    </div>

    <div class="messages" id="messages">
      <!-- Messages injected by JS -->
    </div>

    <div class="chat-input-area">
      <div class="input-wrapper">
        <i class="fas fa-pen-fancy" style="color: var(--gold); opacity: 0.6;"></i>
        <textarea id="userInput" placeholder="Shkruani pyetjen tuaj këtu..." rows="1"></textarea>
      </div>
      <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="Dërgoni mesazhin">
        <i class="fas fa-paper-plane"></i>
      </button>
    </div>
  </div>

  <div class="info-strip">
    © Noteria 2026 · <strong>Asistent AI 24/7</strong> · Të Dhënat Tuaja Janë të Sigurte · <a href="#">Politika e Privatësisë</a>
  </div>

</div>

<script>
let conversationHistory = [];
let isTyping = false;

async function callClaudeAPI(userMessage) {
  try {
    // Convert conversationHistory format for API
    const messages = conversationHistory.map(msg => ({
      role: msg.role === 'assistant' ? 'ai' : 'user',
      content: msg.content
    }));
    
    const response = await fetch('/api/chatbot.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        messages: messages
      })
    });

    const data = await response.json();
    
    if (!response.ok || !data.success) {
      const errorMsg = data.error || `HTTP ${response.status}: ${response.statusText}`;
      throw new Error(errorMsg);
    }

    return data.message;
  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
}

function addMessage(role, content, quickReplies = null, appointmentData = null) {
  const messagesEl = document.getElementById('messages');
  
  const msgDiv = document.createElement('div');
  msgDiv.className = `message ${role}`;
  
  const avatarDiv = document.createElement('div');
  avatarDiv.className = 'msg-avatar';
  avatarDiv.textContent = role === 'ai' ? 'N' : 'Ju';
  
  const bubbleDiv = document.createElement('div');
  bubbleDiv.className = 'bubble';
  bubbleDiv.innerHTML = parseContent(content);
  
  if (role === 'ai') {
    msgDiv.appendChild(avatarDiv);
    msgDiv.appendChild(bubbleDiv);
  } else {
    msgDiv.appendChild(bubbleDiv);
    msgDiv.appendChild(avatarDiv);
  }
  
  messagesEl.appendChild(msgDiv);
  
  if (appointmentData) {
    const card = createAppointmentCard(appointmentData);
    messagesEl.appendChild(card);
  }
  
  if (quickReplies && quickReplies.length > 0) {
    const qrDiv = document.createElement('div');
    qrDiv.className = 'quick-replies';
    quickReplies.forEach(qr => {
      const btn = document.createElement('button');
      btn.className = 'quick-btn';
      btn.innerHTML = qr;
      btn.onclick = () => {
        qrDiv.remove();
        handleUserMessage(qr.replace(/<[^>]*>/g, ''));
      };
      qrDiv.appendChild(btn);
    });
    messagesEl.appendChild(qrDiv);
  }
  
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function parseContent(text) {
  return text
    .replace(/\n/g, '<br>')
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/__(.*?)__/g, '<em>$1</em>');
}

function createAppointmentCard(data) {
  const div = document.createElement('div');
  div.style.paddingLeft = '48px';
  div.innerHTML = `
    <div class="appointment-card">
      <div class="appt-title"><i class="fas fa-calendar-check"></i> Konfirmim Rezervimi</div>
      <div class="appt-row"><i class="fas fa-user" style="color: var(--gold); width: 20px; text-align: center;"></i> Emri: <span>${escapeHtml(data.name)}</span></div>
      <div class="appt-row"><i class="fas fa-briefcase" style="color: var(--gold); width: 20px; text-align: center;"></i> Shërbimi: <span>${escapeHtml(data.service)}</span></div>
      <div class="appt-row"><i class="fas fa-calendar-alt" style="color: var(--gold); width: 20px; text-align: center;"></i> Data: <span>${escapeHtml(data.date)}</span></div>
      <div class="appt-row"><i class="fas fa-clock" style="color: var(--gold); width: 20px; text-align: center;"></i> Ora: <span>08:00–17:00 (do të konfirmohet)</span></div>
      <button class="appt-confirm-btn" onclick="confirmAppointment(this)">
        <i class="fas fa-check-circle"></i> Konfirmo Rezervimin
      </button>
    </div>
  `;
  return div;
}

function confirmAppointment(btn) {
  btn.innerHTML = '<i class="fas fa-check-circle"></i> ✓ Rezervimi u dërgua!';
  btn.style.background = 'linear-gradient(135deg, #22c55e, #16a34a)';
  btn.disabled = true;
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function showTyping() {
  const messagesEl = document.getElementById('messages');
  const typingDiv = document.createElement('div');
  typingDiv.className = 'typing-indicator';
  typingDiv.id = 'typing';
  
  const avatarDiv = document.createElement('div');
  avatarDiv.className = 'msg-avatar';
  avatarDiv.style.background = 'linear-gradient(135deg, var(--gold-light), var(--gold-dark))';
  avatarDiv.style.color = 'white';
  avatarDiv.style.fontFamily = "'Cormorant Garamond', serif";
  avatarDiv.style.fontSize = '16px';
  avatarDiv.textContent = 'N';
  
  const dotsDiv = document.createElement('div');
  dotsDiv.className = 'typing-dots';
  dotsDiv.innerHTML = '<span></span><span></span><span></span>';
  
  typingDiv.appendChild(avatarDiv);
  typingDiv.appendChild(dotsDiv);
  messagesEl.appendChild(typingDiv);
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function hideTyping() {
  const typing = document.getElementById('typing');
  if (typing) typing.remove();
}

function getQuickReplies(text) {
  const lower = text.toLowerCase();
  if (lower.includes('shërbim') || lower.includes('ndihmoj') || lower.includes('mirë')) {
    return [
      '<i class="fas fa-scroll"></i> Kontrata Shitjeje',
      '<i class="fas fa-feather"></i> Prokura',
      '<i class="fas fa-book"></i> Testament',
      '<i class="fas fa-stamp"></i> Vërtetim'
    ];
  }
  if (lower.includes('dokument') || lower.includes('nevojit')) {
    return [
      '<i class="fas fa-calendar-alt"></i> Rezervo Takim',
      '<i class="fas fa-coins"></i> Çmimet',
      '<i class="fas fa-phone"></i> Kontaktoni'
    ];
  }
  return [];
}

async function handleUserMessage(text) {
  if (isTyping) return;
  if (!text.trim()) return;
  
  isTyping = true;
  document.getElementById('sendBtn').disabled = true;
  
  addMessage('user', text);
  showTyping();
  
  conversationHistory.push({ role: 'user', content: text });
  
  try {
    const response = await callClaudeAPI(text);
    conversationHistory.push({ role: 'assistant', content: response });
    
    hideTyping();
    
    const cleanText = response.replace(/REZERVIM_KONFIRMUAR:[^\n]*/gi, '').trim();
    const quickReplies = getQuickReplies(cleanText);
    
    addMessage('ai', cleanText, quickReplies);
  } catch (error) {
    hideTyping();
    
    let errorMessage = '❌ Ndodhi një gabim në komunikim.';
    
    // Check for specific API key issue
    if (error.message.includes('API key not configured')) {
      errorMessage = '⚙️ Sistemi nuk është konfiguruar. Kontaktoni administratorin.';
    } 
    // Check for network errors
    else if (error.message.includes('fetch') || !navigator.onLine) {
      errorMessage = '🌐 Ndodhi një problem me lidhjen. Kontrolloni internetin tuaj.';
    }
    // Check for timeout
    else if (error.message.includes('timeout')) {
      errorMessage = '⏱️ Kërkesa zgjati shumë gjatë. Ju lutemi provoni përsëri.';
    }
    
    errorMessage += '\n\n📞 Ju lutemi kontaktoni: **+383 44 000 000**';
    
    addMessage('ai', errorMessage);
    
    // Log error for debugging
    console.error('Chatbot Error:', error);
  }
  
  isTyping = false;
  document.getElementById('sendBtn').disabled = false;
}

function sendMessage() {
  const input = document.getElementById('userInput');
  const text = input.value.trim();
  if (!text || isTyping) return;
  
  input.value = '';
  input.style.height = 'auto';
  handleUserMessage(text);
}

// Auto-resize textarea
document.getElementById('userInput').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});

// Enter to send, Shift+Enter for newline
document.getElementById('userInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
});

// Initial greeting
window.addEventListener('load', () => {
  setTimeout(() => {
    addMessage('ai', 
      'Mirësevini në **Noteria** 👋\n\nUnë jam **Asistenti i Noterisë**, i disponibël **24 orë në ditë** për t\'ju ndihmuar.\n\nMë mund të përgjigjem për:\n• Informacionin mbi shërbimet noteriale\n• Rezervimin e takimeve në zyrën tonë\n• Dokumentet e nevojshme\n• Çmimet dhe tarifat\n\n**Si mund t\'ju ndihmoj sot?**',
      ['<i class="fas fa-info-circle"></i> Shërbimet', '<i class="fas fa-calendar-alt"></i> Rezervo Takim', '<i class="fas fa-file-alt"></i> Dokumentet', '<i class="fas fa-coins"></i> Çmimet']
    );
  }, 600);
});
</script>
</body>
</html>