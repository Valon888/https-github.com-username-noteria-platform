<?php
/**
 * Noteria Chatbot - Configuration Test
 * Quick diagnostics page
 */

session_start();
$api_key = getenv('CLAUDE_API_KEY') ?: (defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : null);
$has_key = !empty($api_key) && $api_key !== 'sk-ant-v1-YOUR-API-KEY-HERE';
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noteria Chatbot - Configuration Test</title>
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #faf7f2 0%, #ede8dc 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 {
            color: #1a1410;
            font-size: 28px;
            margin-bottom: 10px;
            font-family: 'Cormorant Garamond', serif;
        }
        .header p {
            color: #7a6e60;
            font-size: 14px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(26,20,16,0.12);
            border-left: 4px solid #b8962e;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 8px;
            background: #f9f7f4;
        }
        .status-icon {
            font-size: 24px;
            min-width: 30px;
            text-align: center;
        }
        .status-ok { color: #22c55e; }
        .status-warning { color: #eab308; }
        .status-error { color: #ef4444; }
        .status-label {
            flex: 1;
        }
        .status-label .title {
            font-weight: 600;
            color: #1a1410;
            margin-bottom: 4px;
        }
        .status-label .desc {
            font-size: 12px;
            color: #7a6e60;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
        }
        .alert-info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        .alert-success {
            background: #dcfce7;
            border-left: 4px solid #22c55e;
            color: #166534;
        }
        .alert-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        .code-block {
            background: #1a1410;
            color: #d4af5a;
            padding: 12px;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 10px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #1a1410, #2a1f18);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .btn:hover {
            background: linear-gradient(135deg, #b8962e, #8b6914);
            transform: translateY(-2px);
        }
        .btn-primary { display: block; text-align: center; margin-top: 20px; }
        .key-preview {
            font-family: 'JetBrains Mono', monospace;
            word-break: break-all;
            font-size: 12px;
            background: #f0ede8;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-cogs"></i> Noteria Chatbot - Configuration Test</h1>
        <p>Diagnostics & Setup Verification</p>
    </div>

    <!-- Status All sections -->
    
    <?php if ($has_key): ?>
        <div class="alert alert-success">
            <div><i class="fas fa-check-circle"></i></div>
            <div><strong>Perfect!</strong> API Key is configured and the chatbot is ready to use.</div>
        </div>
    <?php else: ?>
        <div class="alert alert-error">
            <div><i class="fas fa-times-circle"></i></div>
            <div>
                <strong>API Key is not configured.</strong><br>
                <a href="/noteria/setup.php" style="color: #991b1b; font-weight: 600; text-decoration: underline;">👉 Click here for step-by-step setup →</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Configuration Status -->
    <div class="card">
        <h2 style="color: #1a1410; margin-bottom: 20px; font-family: 'Cormorant Garamond', serif;">
            <i class="fas fa-list-check"></i> Configuration Status
        </h2>

        <div class="status-item">
            <div class="status-icon status-<?php echo $has_key ? 'ok' : 'error'; ?>">
                <i class="fas fa-<?php echo $has_key ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            </div>
            <div class="status-label">
                <div class="title">API Key</div>
                <div class="desc">
                    <?php 
                    if ($has_key) {
                        echo '✅ API key is configured';
                    } else {
                        echo '❌ API key is not set (using placeholder)';
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="status-item">
            <div class="status-icon status-<?php echo extension_loaded('curl') ? 'ok' : 'error'; ?>">
                <i class="fas fa-<?php echo extension_loaded('curl') ? 'check-circle' : 'times-circle'; ?>"></i>
            </div>
            <div class="status-label">
                <div class="title">PHP cURL Extension</div>
                <div class="desc"><?php echo extension_loaded('curl') ? '✅ Enabled' : '❌ Not enabled'; ?></div>
            </div>
        </div>

        <div class="status-item">
            <div class="status-icon status-<?php echo extension_loaded('json') ? 'ok' : 'error'; ?>">
                <i class="fas fa-<?php echo extension_loaded('json') ? 'check-circle' : 'times-circle'; ?>"></i>
            </div>
            <div class="status-label">
                <div class="title">PHP JSON Extension</div>
                <div class="desc"><?php echo extension_loaded('json') ? '✅ Enabled' : '❌ Not enabled'; ?></div>
            </div>
        </div>

        <div class="status-item">
            <div class="status-icon status-ok">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="status-label">
                <div class="title">PHP Version</div>
                <div class="desc"><?php echo phpversion(); ?></div>
            </div>
        </div>

        <div class="status-item">
            <div class="status-icon status-<?php echo is_file('../api/chatbot.php') ? 'ok' : 'error'; ?>">
                <i class="fas fa-<?php echo is_file('../api/chatbot.php') ? 'check-circle' : 'times-circle'; ?>"></i>
            </div>
            <div class="status-label">
                <div class="title">API Backend File</div>
                <div class="desc"><?php echo is_file('../api/chatbot.php') ? '✅ Found at /api/chatbot.php' : '❌ Not found'; ?></div>
            </div>
        </div>
    </div>

    <!-- Setup Instructions -->
    <div class="card" id="setup">
        <h2 style="color: #1a1410; margin-bottom: 20px; font-family: 'Cormorant Garamond', serif;">
            <i class="fas fa-tools"></i> Setup Instructions
        </h2>

        <div class="alert alert-info">
            <div><i class="fas fa-info-circle"></i></div>
            <div>
                <strong>Follow these steps to configure your Claude API key:</strong>
            </div>
        </div>

        <h3 style="color: #1a1410; margin: 20px 0 10px 0; font-size: 16px;">Step 1: Get API Key</h3>
        <p style="color: #7a6e60; margin-bottom: 10px;">
            Go to <a href="https://console.anthropic.com/" target="_blank" style="color: #b8962e;">Anthropic Console</a> and create an API key.
        </p>

        <h3 style="color: #1a1410; margin: 20px 0 10px 0; font-size: 16px;">Step 2: Set Environment Variable</h3>
        <p style="color: #7a6e60; margin-bottom: 10px;">
            On Windows, set the environment variable:
        </p>
        <div class="code-block">
CLAUDE_API_KEY=sk-ant-v1-YOUR-KEY-HERE
        </div>
        <p style="color: #7a6e60; margin-top: 10px; font-size: 12px;">
            <strong>How to set:</strong> Start Menu → Environment Variables → Add CLAUDE_API_KEY
        </p>

        <h3 style="color: #1a1410; margin: 20px 0 10px 0; font-size: 16px;">Step 3: Restart Services</h3>
        <p style="color: #7a6e60; margin-bottom: 10px;">
            After setting the environment variable, restart Apache/PHP in Laragon.
        </p>

        <h3 style="color: #1a1410; margin: 20px 0 10px 0; font-size: 16px;">Step 4: Test Chatbot</h3>
        <p style="color: #7a6e60; margin-bottom: 10px;">
            Click the button below to test the chatbot:
        </p>
        <a href="/noteria_chatbot.php" class="btn btn-primary">
            <i class="fas fa-robot"></i> Open Chatbot
        </a>
    </div>

    <!-- Quick Test -->
    <div class="card">
        <h2 style="color: #1a1410; margin-bottom: 20px; font-family: 'Cormorant Garamond', serif;">
            <i class="fas fa-bolt"></i> Quick Test
        </h2>

        <p style="color: #7a6e60; margin-bottom: 15px;">
            Send a test message to Claude API:
        </p>

        <form id="testForm" style="display: flex; gap: 10px;">
            <input type="text" id="testMessage" placeholder="Type a test message..." 
                   style="flex: 1; padding: 10px; border: 1px solid #e0d8cc; border-radius: 6px;">
            <button type="submit" class="btn" style="margin-top: 0;">
                <i class="fas fa-paper-plane"></i> Test
            </button>
        </form>

        <div id="testResult" style="margin-top: 20px; display: none;"></div>
    </div>

    <!-- Documentation -->
    <div class="card">
        <h2 style="color: #1a1410; margin-bottom: 20px; font-family: 'Cormorant Garamond', serif;">
            <i class="fas fa-book"></i> Documentation
        </h2>
        <p style="color: #7a6e60; margin-bottom: 10px;">
            For complete setup and debugging instructions, see:
        </p>
        <ul style="color: #7a6e60; margin-left: 20px; margin-bottom: 15px;">
            <li><a href="CHATBOT_SETUP.md" style="color: #b8962e;">CHATBOT_SETUP.md</a> - Full configuration guide</li>
            <li><a href="CHATBOT_IMPROVEMENTS.md" style="color: #b8962e;">CHATBOT_IMPROVEMENTS.md</a> - Features overview</li>
        </ul>
    </div>

</div>

<script>
document.getElementById('testForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const message = document.getElementById('testMessage').value.trim();
    if (!message) return;

    const resultDiv = document.getElementById('testResult');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Testing...</div>';

    try {
        const response = await fetch('/api/chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                messages: [{ role: 'user', content: message }]
            })
        });

        const data = await response.json();

        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><strong>Success!</strong><br>${data.message}</div>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-times-circle"></i>
                    <div><strong>Error:</strong> ${data.error || 'Unknown error'}</div>
                </div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <div><strong>Connection Error:</strong> ${error.message}</div>
            </div>
        `;
    }
});
</script>

</body>
</html>
