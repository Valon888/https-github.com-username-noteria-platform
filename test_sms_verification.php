<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📱 Test SMS Verifikimi - 3 Minuta</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            padding: 30px;
        }
        h1 { 
            color: #2d6cdf; 
            text-align: center; 
            margin-bottom: 30px;
        }
        .test-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #2d6cdf;
        }
        .phone-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2eafc;
            border-radius: 8px;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        .btn {
            background: #2d6cdf;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
        }
        .btn:hover {
            background: #184fa3;
            transform: translateY(-2px);
        }
        .btn.success {
            background: #4caf50;
        }
        .btn.success:hover {
            background: #388e3c;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            display: none;
        }
        .result.success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #4caf50;
        }
        .result.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #f44336;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Test SMS Verifikimi - Sistemi 3-Minutësh</h1>
        
        <div class="test-section">
            <h3>🧪 Test i Dërgimit të SMS-it</h3>
            <p>Testoni sistemin e verifikimit të telefonit me numrin tuaj.</p>
            
            <input type="text" id="phone_number" class="phone-input" 
                   placeholder="+38344123456" value="+383">
            
            <button onclick="sendTestSMS()" class="btn">📱 Dërgo SMS Test</button>
            <button onclick="checkStats()" class="btn success">📊 Shiko Statistikat</button>
            
            <div id="sms_result" class="result"></div>
        </div>
        
        <div class="test-section">
            <h3>✅ Verifikim i Kodit</h3>
            <p>Shkruani kodin që morët përmes SMS-it.</p>
            
            <input type="text" id="verification_code" class="phone-input" 
                   placeholder="123456" maxlength="6">
            
            <button onclick="verifyCode()" class="btn success">✅ Verifiko Kodin</button>
            
            <div id="verify_result" class="result"></div>
        </div>
        
        <div class="test-section">
            <h3>📊 Statistikat e SMS-ve</h3>
            <div id="stats_grid" class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" id="stat_total">-</div>
                    <div class="stat-label">Total SMS</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="stat_verified">-</div>
                    <div class="stat-label">Verifikuar</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="stat_avg_time">-</div>
                    <div class="stat-label">Kohë mesatare</div>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <p><strong>🎯 Target:</strong> Verifikim brenda 3 minutave</p>
            <p><small>Sistemi përdor provider-ë: IPKO, Infobip, Twilio</small></p>
        </div>
    </div>

    <script>
        let currentTransactionId = null;
        
        function sendTestSMS() {
            const phone = document.getElementById('phone_number').value;
            const result = document.getElementById('sms_result');
            
            if (!phone.match(/^\+383\d{8}$/)) {
                showResult('sms_result', 'error', '❌ Numri i telefonit duhet të jetë në formatin +383XXXXXXXX');
                return;
            }
            
            // Gjeneroji transaction ID për test
            currentTransactionId = 'TEST_' + Date.now();
            
            fetch('phone_verification_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'send_test',
                    phone: phone,
                    transaction_id: currentTransactionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult('sms_result', 'success', 
                        '✅ SMS u dërgua me sukses!<br>' +
                        '📱 Provider: ' + (data.provider || 'Test') + '<br>' +
                        '⏰ Koha për verifikim: 3 minuta');
                } else {
                    showResult('sms_result', 'error', '❌ ' + data.error);
                }
            })
            .catch(error => {
                showResult('sms_result', 'error', '❌ Gabim në dërgim: ' + error.message);
            });
        }
        
        function verifyCode() {
            const code = document.getElementById('verification_code').value;
            const phone = document.getElementById('phone_number').value;
            
            if (!currentTransactionId) {
                showResult('verify_result', 'error', '❌ Dërgoni SMS-in fillimisht');
                return;
            }
            
            if (!code.match(/^\d{6}$/)) {
                showResult('verify_result', 'error', '❌ Kodi duhet të jetë 6 shifra');
                return;
            }
            
            fetch('phone_verification_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'verify_test',
                    phone: phone,
                    transaction_id: currentTransactionId,
                    code: code
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult('verify_result', 'success', 
                        '🎉 VERIFIKIM I SUKSESSHËM!<br>' +
                        '⚡ Kohë e verifikimit: ' + (data.verification_time || 'N/A') + '<br>' +
                        '🏆 Target 3-minutësh: ' + (data.within_target ? '✅ Përmbushur' : '❌ Kaluar'));
                } else {
                    showResult('verify_result', 'error', '❌ ' + data.error);
                }
            })
            .catch(error => {
                showResult('verify_result', 'error', '❌ Gabim në verifikim: ' + error.message);
            });
        }
        
        function checkStats() {
            fetch('phone_verification_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_stats'})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stats = data.stats.last_hour;
                    document.getElementById('stat_total').textContent = stats.total_sent || 0;
                    document.getElementById('stat_verified').textContent = stats.total_verified || 0;
                    
                    const avgTime = stats.avg_verification_time_seconds;
                    if (avgTime) {
                        document.getElementById('stat_avg_time').textContent = Math.round(avgTime) + 's';
                    } else {
                        document.getElementById('stat_avg_time').textContent = '-';
                    }
                }
            });
        }
        
        function showResult(elementId, type, message) {
            const element = document.getElementById(elementId);
            element.className = 'result ' + type;
            element.innerHTML = message;
            element.style.display = 'block';
        }
        
        // Auto-load stats when page loads
        window.onload = function() {
            checkStats();
        };
    </script>
</body>
</html>