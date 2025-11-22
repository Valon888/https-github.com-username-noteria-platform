<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Quick Setup - Sistemi i Pagesave</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #00c6ff 0%, #0072ff 100%);
        }
        h1 { 
            color: #1e3c72; 
            text-align: center; 
            margin-bottom: 40px;
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        .setup-step {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid #007bff;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .setup-step:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,123,255,0.15);
        }
        .setup-step h3 {
            color: #007bff;
            margin-top: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .step-number {
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }
        .btn {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 8px 5px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }
        .btn.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 4px 15px rgba(40,167,69,0.3);
        }
        .btn.warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255,193,7,0.3);
        }
        .btn.secondary {
            background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
            box-shadow: 0 4px 15px rgba(108,117,125,0.3);
        }
        .status-check {
            background: #e7f3ff;
            border: 2px solid #b3d9ff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            padding: 8px;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }
        .status-item:hover {
            background-color: rgba(0,123,255,0.05);
        }
        .status-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }
        .status-icon.success { background: #28a745; }
        .status-icon.error { background: #dc3545; }
        .status-icon.warning { background: #ffc107; color: #212529; }
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            margin: 20px 0;
            overflow: hidden;
        }
        .progress-fill {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        .info-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .code-snippet {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin: 10px 0;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        .alert.warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Quick Setup - Sistemi i Pagesave Online</h1>

        <?php
        $setup_complete = true;
        $setup_progress = 0;
        $total_steps = 5;
        ?>

        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo ($setup_progress / $total_steps) * 100; ?>%"></div>
        </div>

        <!-- Hapi 1: Kontroll databaze -->
        <div class="setup-step">
            <h3><span class="step-number">1</span> Kontroll Databaze</h3>
            <div class="status-check">
                <?php
                try {
                    require_once 'payment_config.php';
                    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    echo '<div class="status-item"><span class="status-icon success">✓</span> Lidhja me databazën është e suksesshme</div>';
                    $setup_progress++;
                } catch (Exception $e) {
                    echo '<div class="status-item"><span class="status-icon error">✗</span> Gabim në lidhje: ' . $e->getMessage() . '</div>';
                    $setup_complete = false;
                }
                ?>
            </div>
            <a href="payment_config.php" class="btn secondary">📝 Redakto Konfiguracionin</a>
        </div>

        <!-- Hapi 2: Tabela të databazës -->
        <div class="setup-step">
            <h3><span class="step-number">2</span> Krijimi i Tabelave</h3>
            <div class="status-check">
                <?php
                if (isset($pdo)) {
                    $required_tables = ['payment_logs', 'payment_audit_log', 'security_settings'];
                    $missing_tables = [];
                    
                    foreach ($required_tables as $table) {
                        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                        if ($stmt->rowCount() > 0) {
                            echo "<div class='status-item'><span class='status-icon success'>✓</span> Tabela '$table' ekziston</div>";
                        } else {
                            echo "<div class='status-item'><span class='status-icon error'>✗</span> Tabela '$table' mungon</div>";
                            $missing_tables[] = $table;
                            $setup_complete = false;
                        }
                    }
                    
                    if (empty($missing_tables)) {
                        $setup_progress++;
                    }
                } else {
                    echo '<div class="status-item"><span class="status-icon warning">!</span> Nuk mund të kontrollojmë tabelat pa lidhje databaze</div>';
                }
                ?>
            </div>
            <a href="setup_payment_tables.php" class="btn success">🗄️ Krijo Tabelat</a>
            <a href="create_payment_tables.sql" class="btn secondary">📄 Shkarko SQL</a>
        </div>

        <!-- Hapi 3: Direktoriat e ngarkimit -->
        <div class="setup-step">
            <h3><span class="step-number">3</span> Direktoriat e Ngarkimit</h3>
            <div class="status-check">
                <?php
                $upload_dir = 'uploads/payment_proofs/';
                
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                if (is_dir($upload_dir) && is_writable($upload_dir)) {
                    echo '<div class="status-item"><span class="status-icon success">✓</span> Direktoria uploads/ është gati</div>';
                    $setup_progress++;
                } else {
                    echo '<div class="status-item"><span class="status-icon error">✗</span> Direktoria uploads/ nuk mund të krijohet ose nuk ka leje shkrimi</div>';
                    $setup_complete = false;
                }
                
                // Kontrollo file upload limits
                $upload_max = ini_get('upload_max_filesize');
                $post_max = ini_get('post_max_size');
                echo "<div class='status-item'><span class='status-icon success'>ℹ</span> Upload max: $upload_max, Post max: $post_max</div>";
                ?>
            </div>
            <div class="code-snippet">
# Nëse keni probleme me lejet, ekzekutoni:
chmod 755 uploads/
chmod 755 uploads/payment_proofs/
            </div>
        </div>

        <!-- Hapi 4: Sistemi i pagesave -->
        <div class="setup-step">
            <h3><span class="step-number">4</span> Sistemi i Pagesave</h3>
            <div class="status-check">
                <?php
                if (file_exists('PaymentVerificationAdvanced.php')) {
                    try {
                        require_once 'PaymentVerificationAdvanced.php';
                        if (isset($pdo)) {
                            $payment_system = new PaymentVerificationAdvanced($pdo);
                            echo '<div class="status-item"><span class="status-icon success">✓</span> PaymentVerificationAdvanced është i ngarkuar</div>';
                            
                            // Test IBAN validation
                            $test_iban = 'XK051212012345678906';
                            if ($payment_system->validateIBANAdvanced($test_iban)) {
                                echo '<div class="status-item"><span class="status-icon success">✓</span> IBAN validation funksionon</div>';
                                $setup_progress++;
                            } else {
                                echo '<div class="status-item"><span class="status-icon warning">!</span> IBAN validation ka probleme</div>';
                            }
                        } else {
                            echo '<div class="status-item"><span class="status-icon warning">!</span> Nuk mund të testojmë pa lidhje databaze</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="status-item"><span class="status-icon error">✗</span> Gabim në ngarkimin e sistemit: ' . $e->getMessage() . '</div>';
                        $setup_complete = false;
                    }
                } else {
                    echo '<div class="status-item"><span class="status-icon error">✗</span> PaymentVerificationAdvanced.php nuk ekziston</div>';
                    $setup_complete = false;
                }
                ?>
            </div>
            <a href="test_payment_system.php" class="btn success">🧪 Testo Sistemin</a>
        </div>

        <!-- Hapi 5: Email sistemi -->
        <div class="setup-step">
            <h3><span class="step-number">5</span> Email Sistemi</h3>
            <div class="status-check">
                <?php
                if (file_exists('email_config.php')) {
                    require_once 'email_config.php';
                    global $email_config;
                    
                    if ($email_config['smtp_enabled']) {
                        echo '<div class="status-item"><span class="status-icon success">✓</span> SMTP është aktiv</div>';
                        if (!empty($email_config['smtp_username']) && !empty($email_config['smtp_password'])) {
                            echo '<div class="status-item"><span class="status-icon success">✓</span> SMTP kredencialët janë konfiguruar</div>';
                            $setup_progress++;
                        } else {
                            echo '<div class="status-item"><span class="status-icon warning">!</span> SMTP kredencialët mungojnë</div>';
                        }
                    } else {
                        echo '<div class="status-item"><span class="status-icon warning">!</span> SMTP është në modalitetin test (vetëm log)</div>';
                        echo '<div class="status-item"><span class="status-icon success">✓</span> Test mode është aktiv - email nuk do të dërgohen</div>';
                        $setup_progress++; // Test mode është OK për tani
                    }
                } else {
                    echo '<div class="status-item"><span class="status-icon error">✗</span> email_config.php nuk ekziston</div>';
                    $setup_complete = false;
                }
                ?>
            </div>
            <a href="email_config.php" class="btn secondary">📧 Redakto Email Config</a>
        </div>

        <!-- Rezultati final -->
        <div class="info-card">
            <?php if ($setup_complete && $setup_progress >= 4): ?>
                <div class="alert success">
                    <h4>🎉 Setup i Kompletuar!</h4>
                    <p>Sistemi është gati për përdorim. Mund të filloni të regjistroni zyrat e noterisë.</p>
                </div>
            <?php elseif ($setup_progress >= 3): ?>
                <div class="alert warning">
                    <h4>⚠️ Setup Pothuajse i Kompletuar</h4>
                    <p>Sistemi mund të përdoret, por ka disa çështje minore që duhet zgjidhur.</p>
                </div>
            <?php else: ?>
                <div class="alert error">
                    <h4>❌ Setup i Paplotë</h4>
                    <p>Ka probleme që duhet zgjidhur para se sistemi të mund të përdoret.</p>
                </div>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <strong>Progresi:</strong> <?php echo $setup_progress; ?>/<?php echo $total_steps; ?> hapa të kompletuar
            </div>

            <div style="margin-top: 20px;">
                <a href="zyrat_register.php" class="btn <?php echo $setup_complete && $setup_progress >= 4 ? 'success' : 'secondary'; ?>">
                    📝 Shko te Forma e Regjistrimit
                </a>
                <a href="test_dashboard.php" class="btn secondary">🧪 Test Dashboard</a>
                <a href="DOCUMENTATION.md" class="btn secondary">📚 Dokumentacioni</a>
            </div>
        </div>

        <!-- Informacion shtesë -->
        <div class="info-card">
            <h4>🔧 Quick Troubleshooting</h4>
            
            <h5>🗄️ Probleme Databaze:</h5>
            <div class="code-snippet">
# Hapni phpMyAdmin dhe ekzekutoni:
CREATE DATABASE IF NOT EXISTS noteria;
USE noteria;
# Pastaj ekzekutoni create_payment_tables.sql
            </div>

            <h5>📁 Probleme me File Upload:</h5>
            <div class="code-snippet">
# Në php.ini, sigurohuni që:
upload_max_filesize = 10M
post_max_size = 10M
file_uploads = On
            </div>

            <h5>📧 Email Test Mode:</h5>
            <div class="code-snippet">
# Për të aktivizuar email-et e vërtetë:
# Redaktoni email_config.php
$email_config['smtp_enabled'] = true;
# Shtoni Gmail credentials ose SMTP provider tjetër
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px; color: #6c757d;">
            <small>Noteria Platform v1.0.0 - Sistema e Verifikimit të Pagesave Online</small>
        </div>
    </div>
</body>
</html>