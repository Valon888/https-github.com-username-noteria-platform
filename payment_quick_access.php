<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ Payment Quick Access</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 20px; 
            background: #f8f9fa;
        }
        .quick-access-panel {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            padding: 25px;
            max-width: 400px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }
        .quick-access-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff6b6b 0%, #ee5a24 100%);
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.4rem;
            font-weight: 700;
        }
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-mini {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid;
        }
        .stat-mini.pending { border-color: #ffc107; }
        .stat-mini.verified { border-color: #28a745; }
        .stat-number-mini {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label-mini {
            color: #6c757d;
            font-size: 0.8rem;
        }
        .pending .stat-number-mini { color: #ffc107; }
        .verified .stat-number-mini { color: #28a745; }
        
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn-quick {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40,167,69,0.3);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
            color: white;
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108,117,125,0.3);
        }
        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: auto;
        }
        .last-update {
            text-align: center;
            color: #6c757d;
            font-size: 0.8rem;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .new-payment-alert {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <div class="quick-access-panel">
        <h2>⚡ Verifikim i Shpejtë</h2>
        
        <div id="new-payment-alert" class="new-payment-alert" style="display: none;">
            🚨 Pagesa e re u regjistrua!
        </div>
        
        <div class="stats-mini">
            <div class="stat-mini pending">
                <div class="stat-number-mini" id="pending-count">-</div>
                <div class="stat-label-mini">Në pritje</div>
            </div>
            <div class="stat-mini verified">
                <div class="stat-number-mini" id="verified-today">-</div>
                <div class="stat-label-mini">Sot</div>
            </div>
        </div>
        
        <div class="quick-actions">
            <a href="payment_verification_admin.php" class="btn-quick btn-primary" id="verify-btn">
                🔍 Verifiko Pagesat
                <span class="notification-badge" id="notification-badge" style="display: none;">0</span>
            </a>
            
            <a href="zyrat_register.php" class="btn-quick btn-success">
                📝 Regjistrim i Ri
            </a>
            
            <a href="test_dashboard.php" class="btn-quick btn-secondary">
                🧪 Dashboard
            </a>
        </div>
        
        <div class="last-update">
            Përditësuar: <span id="last-update">-</span>
        </div>
    </div>

    <script>
        let lastPendingCount = 0;
        
        function updateStats() {
            fetch('payment_notifications_api.php?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.stats) {
                        const pendingCount = parseInt(data.stats.pending);
                        const todayCount = parseInt(data.stats.today);
                        
                        // Përditëso numrat
                        document.getElementById('pending-count').textContent = pendingCount;
                        document.getElementById('verified-today').textContent = todayCount;
                        
                        // Kontrollo për pagesa të reja
                        if (pendingCount > lastPendingCount && lastPendingCount > 0) {
                            showNewPaymentAlert();
                        }
                        lastPendingCount = pendingCount;
                        
                        // Përditëso badge
                        const badge = document.getElementById('notification-badge');
                        const verifyBtn = document.getElementById('verify-btn');
                        
                        if (pendingCount > 0) {
                            badge.textContent = pendingCount;
                            badge.style.display = 'inline';
                            verifyBtn.classList.add('pulse');
                        } else {
                            badge.style.display = 'none';
                            verifyBtn.classList.remove('pulse');
                        }
                        
                        // Përditëso kohën
                        document.getElementById('last-update').textContent = 
                            new Date().toLocaleTimeString('sq-AL');
                    }
                })
                .catch(error => {
                    console.error('Error updating stats:', error);
                });
        }
        
        function showNewPaymentAlert() {
            const alert = document.getElementById('new-payment-alert');
            alert.style.display = 'block';
            
            // Fshije pas 5 sekondash
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
            
            // Sound notification (opsional)
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Pagesa e Re!', {
                    body: 'Një pagesa e re është regjistruar dhe kërkon verifikim.',
                    icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">💳</text></svg>'
                });
            }
        }
        
        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // Përditëso çdo 15 sekonda
        updateStats(); // Thirr menjëherë
        setInterval(updateStats, 15000);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === '1') {
                e.preventDefault();
                window.open('payment_verification_admin.php', '_blank');
            }
            if (e.ctrlKey && e.key === '2') {
                e.preventDefault();
                window.open('zyrat_register.php', '_blank');
            }
        });
    </script>
</body>
</html>