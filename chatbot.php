<?php
// chatbot.php - Full UI integration for Noteria SaaS Chatbot
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal Notary Chatbot</title>
    <style>
        #chatbox { max-width: 420px; margin: 40px auto; border: 1px solid #ccc; border-radius: 10px; padding: 16px; background: #f9f9f9; box-shadow: 0 2px 8px #eee; }
        #messages { height: 340px; overflow-y: auto; margin-bottom: 12px; background: #fff; border-radius: 6px; padding: 10px; border: 1px solid #eee; }
        .user-msg { text-align: right; color: #244d96; margin: 8px 0; font-weight: 500; }
        .bot-msg { text-align: left; color: #333; margin: 8px 0; font-weight: 400; }
        .suggestion { color: #b8860b; font-size: 0.97em; margin: 4px 0; cursor: pointer; text-decoration: underline; }
        #chat-form { display: flex; gap: 8px; }
        #chat-input { flex: 1; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 1em; }
        #chat-send { padding: 10px 18px; border-radius: 6px; border: none; background: #244d96; color: #fff; font-weight: 600; cursor: pointer; }
        #chat-send:disabled { background: #aaa; }
    </style>
</head>
<body>
<div id="chatbox">
    <div id="messages"></div>
    <form id="chat-form" autocomplete="off">
        <input type="text" name="message" id="chat-input" placeholder="Ask a legal-notary question..." required autofocus>
        <button type="submit" id="chat-send">Send</button>
    </form>
</div>
<script>
const messages = document.getElementById('messages');
const chatForm = document.getElementById('chat-form');
const chatInput = document.getElementById('chat-input');
const chatSend = document.getElementById('chat-send');

function appendMessage(msg, sender) {
    const div = document.createElement('div');
    div.className = sender === 'user' ? 'user-msg' : 'bot-msg';
    div.innerHTML = msg;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
}

function appendSuggestion(suggestion) {
    const div = document.createElement('div');
    div.className = 'suggestion';
    div.innerHTML = suggestion;
    div.onclick = function() {
        if (suggestion.includes('Book Appointment')) {
            window.location.href = 'reservation.php';
        } else {
            chatInput.value = suggestion.replace(/<[^>]+>/g, '');
            chatInput.focus();
        }
    };
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
}

chatForm.onsubmit = function(e) {
    e.preventDefault();
    const msg = chatInput.value.trim();
    if (!msg) return;
    appendMessage(msg, 'user');
    chatInput.value = '';
    chatSend.disabled = true;
    fetch('chatbot_controller.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'message=' + encodeURIComponent(msg)
    })
    .then(r => r.json())
    .then(data => {
        appendMessage(data.response, 'bot');
        if (data.suggestions) {
            data.suggestions.forEach(appendSuggestion);
        }
        chatSend.disabled = false;
        chatInput.focus();
    })
    .catch(() => {
        appendMessage('Sorry, there was an error. Please try again.', 'bot');
        chatSend.disabled = false;
    });
};
</script>
</body>
</html>
