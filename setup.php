<?php
/**
 * Noteria Chatbot - Quick Setup Wizard
 */
session_start();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noteria Chatbot - Setup Wizard</title>
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #faf7f2 0%, #ede8dc 100%);
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { max-width: 600px; width: 100%; }
        .wizard-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(26,20,16,0.12);
            border-left: 4px solid #b8962e;
        }
        .wizard-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .wizard-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            color: #1a1410;
            margin-bottom: 8px;
        }
        .wizard-header p {
            color: #7a6e60;
            font-size: 14px;
        }
        .step {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0d8cc;
        }
        .step:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .step-number {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: #b8962e;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .step h3 {
            color: #1a1410;
            font-size: 18px;
            margin-bottom: 12px;
        }
        .step p {
            color: #7a6e60;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #1a1410, #2a1f18);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .button:hover {
            background: linear-gradient(135deg, #b8962e, #8b6914);
            transform: translateY(-2px);
        }
        .code-block {
            background: #1a1410;
            color: #d4af5a;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 12px 0;
            word-break: break-all;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            border-left: 4px solid #eab308;
        }
        .alert-warning {
            background: #fef3c7;
            color: #78350f;
        }
        .links {
            text-align: center;
            margin-top: 30px;
        }
        .links a {
            color: #b8962e;
            text-decoration: none;
            font-weight: 600;
            margin: 0 12px;
            transition: color 0.2s;
        }
        .links a:hover {
            color: #8b6914;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="wizard-card">
        <div class="wizard-header">
            <h1><i class="fas fa-magic"></i> Setup Wizard</h1>
            <p>Këto janë hapat për të konfiguruar Chatbot-in</p>
        </div>

        <div class="alert alert-warning">
            <i class="fas fa-lightbulb"></i> 
            <strong>5 minuta</strong> - Koha e përafërt për të vendosur
        </div>

        <!-- Step 1 -->
        <div class="step">
            <div class="step-number">1</div>
            <h3>Hapet Anthropic Console</h3>
            <p>Shkoni në <strong>https://console.anthropic.com</strong> dhe hyrni në llogarinë tuaj Claude.</p>
            <p style="color: #b8962e;">Nëse nuk keni llogarinë, krijoni njërën falas 👉</p>
            <a href="https://console.anthropic.com" target="_blank" class="button">
                <i class="fas fa-external-link-alt"></i> Shko në Anthropic
            </a>
        </div>

        <!-- Step 2 -->
        <div class="step">
            <div class="step-number">2</div>
            <h3>Krijoni API Key</h3>
            <p>Në Anthropic Console:</p>
            <ol style="color: #7a6e60; margin-left: 20px; line-height: 1.8;">
                <li>Klikoni <strong>"API Keys"</strong> në menu të majtë</li>
                <li>Klikoni butonin <strong>"Create Key"</strong> (ngjyrë blu)</li>
                <li>Kopjoni çelësin (fillon me <code>sk-ant-v1-</code>)</li>
            </ol>
        </div>

        <!-- Step 3 -->
        <div class="step">
            <div class="step-number">3</div>
            <h3>Hapni fajllin .env</h3>
            <p>Hapni këtë fajll në redaktuesin e tekstit:</p>
            <div class="code-block">
                d:\Laragon\www\noteria\.env
            </div>
            <p style="color: #7a6e60; font-size: 12px;">
                <i class="fas fa-info-circle"></i> 
                Nëse nuk e gjeni fajllin, aktivizoni "Shfaq fajllat e fshehur" në Windows Explorer
            </p>
        </div>

        <!-- Step 4 -->
        <div class="step">
            <div class="step-number">4</div>
            <h3>Zëvendësoni API Key-in</h3>
            <p>Gjeni këtë linjë në .env:</p>
            <div class="code-block">
                CLAUDE_API_KEY=sk-ant-v1-YOUR-API-KEY-HERE
            </div>
            <p>Zëvendësojeni me çelësin tuaj të vërtetë:</p>
            <div class="code-block">
                CLAUDE_API_KEY=sk-ant-v1-xxxxxxxxxxxxxxxxxxxxxxxx
            </div>
            <p style="color: #7a6e60; font-size: 12px;">
                <i class="fas fa-lock"></i> 
                Mos e ndani çelësin me askë!
            </p>
        </div>

        <!-- Step 5 -->
        <div class="step">
            <div class="step-number">5</div>
            <h3>Ruani dhe Testoni</h3>
            <p><strong>Ctrl + S</strong> për të ruajtur fajllin .env</p>
            <p>Më pas klikoni më poshtë për të testuar:</p>
            <a href="/noteria/chatbot-test.php" class="button">
                <i class="fas fa-flask"></i> Testoni Chatbot-in
            </a>
        </div>

        <div class="links">
            <a href="/noteria/noteria_chatbot.php">
                <i class="fas fa-robot"></i> Shko te Chatbot
            </a>
            <a href="/noteria/CHATBOT_SETUP.md">
                <i class="fas fa-book"></i> Dokumentacioni Plotë
            </a>
        </div>
    </div>
</div>

<script>
// Check if API is configured
fetch('/noteria/api/chatbot.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ messages: [{ role: 'user', content: 'Hi' }] })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        // API is working, redirect to chatbot
        setTimeout(() => {
            console.log('✅ API is configured! Redirecting to chatbot...');
            // Don't redirect automatically to let user see the setup steps
        }, 1000);
    }
})
.catch(e => console.log('ℹ️ API not ready yet:', e));
</script>

</body>
</html>
