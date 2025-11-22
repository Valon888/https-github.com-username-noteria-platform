<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Test Verifikimi 3-Minutësh</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 800px; 
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
            font-size: 2.2rem;
        }
        .test-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }
        .btn.success { background: #28a745; }
        .btn.warning { background: #ffc107; color: #212529; }
        .btn.danger { background: #dc3545; }
        .timer-display {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 20px 0;
        }
        .step-list {
            background: #e7f3ff;
            border: 2px solid #b3d9ff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .step-list ol {
            margin: 0;
            padding-left: 20px;
        }
        .step-list li {
            margin: 10px 0;
            font-weight: 500;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid;
        }
        .alert.info {
            background: #cce7ff;
            color: #004085;
            border-color: #007bff;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Verifikimi të Shpejtë - 3 Minuta</h1>

        <div class="timer-display">
            ⏱️ Target: Verifikim brenda 3 minutave
        </div>

        <div class="alert info">
            <strong>📋 Qëllimi i Testit:</strong> Të testojmë nëse administratorët mund të verifikojnë pagesat brenda 3 minutave nga momenti i regjistrimit.
        </div>

        <div class="test-section">
            <h3>🚀 Hapi 1: Regjistro një pagesa teste</h3>
            <p>Filloni duke regjistruar një zyrë noterise të re për të simuluar një pagesa të re.</p>
            <a href="zyrat_register.php" class="btn success" target="_blank">📝 Regjistro Zyrë Teste</a>
        </div>

        <div class="test-section">
            <h3>⚡ Hapi 2: Hap dashboard-in e verifikimit</h3>
            <p>Hapni dashboard-in e verifikimit të shpejtë ku do të shihni pagesat në pritje.</p>
            <a href="payment_verification_admin.php" class="btn" target="_blank">🔍 Dashboard Verifikimi</a>
        </div>

        <div class="test-section">
            <h3>📱 Hapi 3: Monitoroni notifikimet</h3>
            <p>Përdorni quick access panel për të parë notifikimet në kohë reale.</p>
            <a href="payment_quick_access.php" class="btn warning" target="_blank">⚡ Quick Access</a>
        </div>

        <div class="step-list">
            <h4>📝 Procedura e Testimit:</h4>
            <ol>
                <li><strong>Filloni timer-in</strong> - Shënoni kohën kur regjistroni pagesën</li>
                <li><strong>Regjistroni</strong> - Përdorni të dhëna të vërteta teste</li>
                <li><strong>Monitoroni</strong> - Kontrolloni nëse pagesa shfaqet në dashboard</li>
                <li><strong>Verifikoni</strong> - Aprovoni ose refuzoni pagesën</li>
                <li><strong>Matni kohën</strong> - A u krye brenda 3 minutave?</li>
            </ol>
        </div>

        <div class="test-section">
            <h3>🔧 Mjetet Shtesë</h3>
            <p>Mjete të tjera që mund t'ju ndihmojnë gjatë testimit.</p>
            <a href="test_dashboard.php" class="btn" target="_blank">🧪 Test Dashboard</a>
            <a href="payment_notifications_api.php?action=get_stats" class="btn" target="_blank">📊 API Stats</a>
            <a href="setup_payment_tables.php" class="btn" target="_blank">🗄️ Setup Database</a>
        </div>

        <div class="alert success">
            <h4>✅ Kriteret e Suksesit:</h4>
            <ul>
                <li><strong>Koha:</strong> Verifikimi duhet kryer brenda 3 minutave</li>
                <li><strong>Automatizimi:</strong> Dashboard-i duhet të përditësohet automatikisht</li>
                <li><strong>Notifikimet:</strong> Pagesat e reja duhet të shfaqen menjëherë</li>
                <li><strong>Email-i:</strong> Konfirmimi duhet dërguar pas verifikimit</li>
                <li><strong>Auditimi:</strong> Të gjitha veprimet duhet të logohen</li>
            </ul>
        </div>

        <div class="test-section">
            <h3>📊 Të dhëna teste të rekomanduara:</h3>
            <div style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 8px; font-family: monospace;">
                <strong>Emri:</strong> Test Office Kosovo<br>
                <strong>Email:</strong> test@noteria-test.com<br>
                <strong>IBAN:</strong> XK051212012345678906<br>
                <strong>Pagesa:</strong> 50.00€<br>
                <strong>Metoda:</strong> Transfertë Bankare
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <button onclick="startTimer()" class="btn success">▶️ Fillo Testin</button>
            <button onclick="stopTimer()" class="btn danger">⏹️ Ndalo Timer-in</button>
        </div>

        <div id="timer-result" style="text-align: center; margin-top: 20px; font-size: 1.2rem; font-weight: bold;"></div>
    </div>

    <script>
        let startTime = null;
        let timerInterval = null;

        function startTimer() {
            startTime = Date.now();
            document.getElementById('timer-result').innerHTML = 
                '<span style="color: #007bff;">⏱️ Timer filloi! Koha: 00:00</span>';
            
            timerInterval = setInterval(updateTimer, 1000);
            
            // Hapni faqen e regjistrimit automatikisht
            window.open('zyrat_register.php', '_blank');
        }

        function updateTimer() {
            if (!startTime) return;
            
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            
            const timeStr = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            let color = '#007bff';
            let status = 'Në kohë';
            
            if (elapsed > 180) { // 3 minuta
                color = '#dc3545';
                status = 'Vonë!';
            } else if (elapsed > 120) { // 2 minuta
                color = '#ffc107';
                status = 'Afër limitit';
            }
            
            document.getElementById('timer-result').innerHTML = 
                `<span style="color: ${color};">⏱️ Koha: ${timeStr} - ${status}</span>`;
        }

        function stopTimer() {
            if (!startTime) {
                alert('Timer-i nuk është filluar ende!');
                return;
            }
            
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            
            clearInterval(timerInterval);
            
            const timeStr = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            let result = '';
            
            if (elapsed <= 180) {
                result = `<span style="color: #28a745;">✅ SUKSES! Verifikimi u krye në ${timeStr} (nën 3 minuta)</span>`;
            } else {
                result = `<span style="color: #dc3545;">❌ Vonë! Verifikimi zgjati ${timeStr} (mbi 3 minuta)</span>`;
            }
            
            document.getElementById('timer-result').innerHTML = result;
            startTime = null;
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                startTimer();
            }
            if (e.ctrlKey && e.key === 'q') {
                e.preventDefault();
                stopTimer();
            }
        });
    </script>
</body>
</html>