<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Sistemi i Pagesave - Noteria</title>
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
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
        }
        h1 { 
            color: #333; 
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
        .test-section h3 {
            color: #007bff;
            margin-top: 0;
            font-size: 1.4rem;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        .code-block {
            background: #f4f4f4;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn.secondary {
            background: #6c757d;
        }
        .btn.secondary:hover {
            background: #545b62;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f2f2f2;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Dashboard - Sistemi i Pagesave</h1>

        <?php
        // Test për lidhjen me databazën
        echo '<div class="test-section">';
        echo '<h3>🗄️ Test Databaze</h3>';
        
        try {
            require_once 'payment_config.php';
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo '<p>✅ Lidhja me databazën <span class="status success">SUCCESS</span></p>';
            
            // Kontrollo tabelat
            $tables = ['payment_logs', 'payment_audit_log', 'security_settings'];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "<p>✅ Tabela '$table' <span class='status success'>EXISTS</span></p>";
                } else {
                    echo "<p>❌ Tabela '$table' <span class='status error'>MISSING</span></p>";
                }
            }
            
        } catch (Exception $e) {
            echo '<p>❌ Gabim në databazë: <span class="status error">' . $e->getMessage() . '</span></p>';
        }
        echo '</div>';

        // Test për sistemin e pagesave
        echo '<div class="test-section">';
        echo '<h3>💳 Test Sistemi i Pagesave</h3>';
        
        try {
            require_once 'PaymentVerificationAdvanced.php';
            $payment_system = new PaymentVerificationAdvanced($pdo);
            echo '<p>✅ PaymentVerificationAdvanced <span class="status success">LOADED</span></p>';
            
            // Test IBAN validation
            $test_iban = 'XK051212012345678906';
            if ($payment_system->validateIBANAdvanced($test_iban)) {
                echo "<p>✅ IBAN Validation për '$test_iban' <span class='status success'>VALID</span></p>";
            } else {
                echo "<p>❌ IBAN Validation për '$test_iban' <span class='status error'>INVALID</span></p>";
            }
            
        } catch (Exception $e) {
            echo '<p>❌ Gabim në sistemin e pagesave: <span class="status error">' . $e->getMessage() . '</span></p>';
        }
        echo '</div>';

        // Test për email sistem
        echo '<div class="test-section">';
        echo '<h3>📧 Test Email Sistemi</h3>';
        
        try {
            require_once 'email_config.php';
            testEmailConfiguration();
            echo '<p>✅ Email Configuration <span class="status success">LOADED</span></p>';
        } catch (Exception $e) {
            echo '<p>❌ Gabim në email sistem: <span class="status error">' . $e->getMessage() . '</span></p>';
        }
        echo '</div>';

        // Shfaq të dhënat e fundit nga payment_logs
        echo '<div class="test-section">';
        echo '<h3>📊 Të dhënat e Fundit - Payment Logs</h3>';
        
        try {
            $stmt = $pdo->query("SELECT * FROM payment_logs ORDER BY created_at DESC LIMIT 5");
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($logs) > 0) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Email</th><th>Emri Zyrës</th><th>Transaction ID</th><th>Metoda</th><th>Statusi</th><th>Data</th></tr>';
                foreach ($logs as $log) {
                    $status_class = $log['verification_status'] === 'verified' ? 'success' : 'warning';
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($log['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($log['office_email']) . '</td>';
                    echo '<td>' . htmlspecialchars($log['office_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($log['transaction_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($log['payment_method']) . '</td>';
                    echo '<td><span class="status ' . $status_class . '">' . htmlspecialchars($log['verification_status']) . '</span></td>';
                    echo '<td>' . htmlspecialchars($log['created_at']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>ℹ️ Nuk ka të dhëna në payment_logs <span class="status warning">EMPTY</span></p>';
            }
            
        } catch (Exception $e) {
            echo '<p>❌ Gabim në leximin e të dhënave: <span class="status error">' . $e->getMessage() . '</span></p>';
        }
        echo '</div>';

        // Test file upload limits
        echo '<div class="test-section">';
        echo '<h3>📁 Test File Upload Configuration</h3>';
        
        $upload_max = ini_get('upload_max_filesize');
        $post_max = ini_get('post_max_size');
        $memory_limit = ini_get('memory_limit');
        
        echo "<p>📤 Upload Max Filesize: <strong>$upload_max</strong></p>";
        echo "<p>📮 Post Max Size: <strong>$post_max</strong></p>";
        echo "<p>🧠 Memory Limit: <strong>$memory_limit</strong></p>";
        
        if (function_exists('curl_version')) {
            echo '<p>✅ cURL <span class="status success">AVAILABLE</span></p>';
        } else {
            echo '<p>❌ cURL <span class="status error">NOT AVAILABLE</span></p>';
        }
        echo '</div>';
        ?>

        <div class="info-box">
            <h4>🔗 Quick Links</h4>
            <a href="zyrat_register.php" class="btn">📝 Regjistro Zyrë</a>
            <a href="test_payment_system.php" class="btn secondary">🧪 Test Payment System</a>
            <a href="setup_payment_tables.php" class="btn secondary">🗄️ Setup Database</a>
        </div>

        <div class="info-box">
            <h4>ℹ️ Informacion</h4>
            <p><strong>Versioni:</strong> 1.0.0</p>
            <p><strong>Data e Testimit:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
            <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
        </div>
    </div>
</body>
</html>