<?php
// subscription_settings.php - Faqja e konfigurimit për abonimet e noterëve
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Kontrollo autorizimin (vetëm administratorët mund ta aksesojnë këtë faqe)
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Procesi i përditësimit të konfigurimeve
$updateMessage = '';
$updateStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validimi i të dhënave
        $subscriptionPrice = filter_input(INPUT_POST, 'subscription_price', FILTER_VALIDATE_FLOAT);
        $paymentDay = filter_input(INPUT_POST, 'payment_day', FILTER_VALIDATE_INT);
        $subscriptionFrequency = filter_input(INPUT_POST, 'subscription_frequency', FILTER_SANITIZE_STRING);
        $emailNotification = isset($_POST['email_notification']) ? 1 : 0;
        $graceperiod = filter_input(INPUT_POST, 'grace_period', FILTER_VALIDATE_INT);
        
        // Validimi i të dhënave
        if ($subscriptionPrice === false || $subscriptionPrice <= 0) {
            throw new Exception("Çmimi i abonimit duhet të jetë një numër pozitiv.");
        }
        
        if ($paymentDay === false || $paymentDay < 1 || $paymentDay > 28) {
            throw new Exception("Dita e pagesës duhet të jetë një numër ndërmjet 1 dhe 28.");
        }
        
        if ($graceperiod === false || $graceperiod < 0) {
            throw new Exception("Periudha e faljes duhet të jetë një numër jo-negativ.");
        }
        
        // Kontrollo nëse konfigurimet ekzistojnë tashmë
        $checkStmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
        $exists = $checkStmt->fetchColumn() > 0;
        
        if ($exists) {
            // Përditëso konfigurimet ekzistuese
            $sql = "UPDATE system_settings SET 
                    subscription_price = :subscription_price,
                    payment_day = :payment_day,
                    subscription_frequency = :subscription_frequency,
                    email_notification = :email_notification,
                    grace_period = :grace_period,
                    updated_at = NOW()";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'subscription_price' => $subscriptionPrice,
                'payment_day' => $paymentDay,
                'subscription_frequency' => $subscriptionFrequency,
                'email_notification' => $emailNotification,
                'grace_period' => $graceperiod
            ]);
        } else {
            // Krijo konfigurime të reja
            $sql = "INSERT INTO system_settings 
                    (subscription_price, payment_day, subscription_frequency, 
                     email_notification, grace_period, created_at, updated_at)
                    VALUES 
                    (:subscription_price, :payment_day, :subscription_frequency, 
                     :email_notification, :grace_period, NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'subscription_price' => $subscriptionPrice,
                'payment_day' => $paymentDay,
                'subscription_frequency' => $subscriptionFrequency,
                'email_notification' => $emailNotification,
                'grace_period' => $graceperiod
            ]);
        }
        
        $updateMessage = "Konfigurimet e abonimit u përditësuan me sukses!";
        $updateStatus = 'success';
        
    } catch (PDOException $e) {
        $updateMessage = "Gabim në bazën e të dhënave: " . $e->getMessage();
        $updateStatus = 'error';
    } catch (Exception $e) {
        $updateMessage = $e->getMessage();
        $updateStatus = 'error';
    }
}

// Merr konfigurimet aktuale
try {
    $stmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vlerat e paracaktuara nëse nuk ekzistojnë konfigurime
    if (!$settings) {
        $settings = [
            'subscription_price' => 150.00,
            'payment_day' => 1,
            'subscription_frequency' => 'monthly',
            'email_notification' => 1,
            'grace_period' => 3
        ];
    }
} catch (PDOException $e) {
    // Kontrollo nëse tabela nuk ekziston dhe krijo nëse nevojitet
    if ($e->getCode() == '42S02') { // Kodi për "tabelë jo e gjetur"
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    subscription_price DECIMAL(10, 2) NOT NULL DEFAULT 150.00,
                    payment_day INT NOT NULL DEFAULT 1,
                    subscription_frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
                    email_notification TINYINT(1) NOT NULL DEFAULT 1,
                    grace_period INT NOT NULL DEFAULT 3,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                )
            ");
            
            // Vendos vlerat e paracaktuara
            $settings = [
                'subscription_price' => 150.00,
                'payment_day' => 1,
                'subscription_frequency' => 'monthly',
                'email_notification' => 1,
                'grace_period' => 3
            ];
            
            $updateMessage = "Tabela e konfigurimeve u krijua. Ju lutemi vendosni konfigurimet tuaja.";
            $updateStatus = 'info';
        } catch (PDOException $innerEx) {
            $updateMessage = "Gabim në krijimin e tabelës së konfigurimeve: " . $innerEx->getMessage();
            $updateStatus = 'error';
            $settings = [
                'subscription_price' => 150.00,
                'payment_day' => 1,
                'subscription_frequency' => 'monthly',
                'email_notification' => 1,
                'grace_period' => 3
            ];
        }
    } else {
        $updateMessage = "Gabim në bazën e të dhënave: " . $e->getMessage();
        $updateStatus = 'error';
        $settings = [
            'subscription_price' => 25.00,
            'payment_day' => 1,
            'subscription_frequency' => 'monthly',
            'email_notification' => 1,
            'grace_period' => 3
        ];
    }
}

// Kontrollo nëse tabela e pagesave të abonimit ekziston dhe krijoje nëse jo
try {
    $pdo->query("SELECT 1 FROM subscription_payments LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') { // Kodi për "tabelë jo e gjetur"
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS subscription_payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    noter_id INT NOT NULL,
                    amount DECIMAL(10, 2) NOT NULL,
                    payment_date DATETIME NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    reference VARCHAR(50),
                    transaction_id VARCHAR(100),
                    payment_method VARCHAR(50) NOT NULL,
                    description TEXT,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (noter_id) REFERENCES noteri(id)
                )
            ");
            
            if ($updateStatus != 'error') {
                $updateMessage .= " Tabela e pagesave të abonimit u krijua me sukses.";
                $updateStatus = 'info';
            }
        } catch (PDOException $innerEx) {
            if ($updateStatus != 'error') {
                $updateMessage .= " Gabim në krijimin e tabelës së pagesave: " . $innerEx->getMessage();
                $updateStatus = 'error';
            }
        }
    }
}

// Kontrollo nëse tabela e regjistrimeve të veprimeve ekziston dhe krijoje nëse jo
try {
    $pdo->query("SELECT 1 FROM activity_logs LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') { // Kodi për "tabelë jo e gjetur"
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    log_type VARCHAR(50) NOT NULL,
                    user_id INT,
                    status VARCHAR(20) NOT NULL,
                    message TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            if ($updateStatus != 'error') {
                $updateMessage .= " Tabela e regjistrimeve të veprimeve u krijua me sukses.";
                $updateStatus = 'info';
            }
        } catch (PDOException $innerEx) {
            if ($updateStatus != 'error') {
                $updateMessage .= " Gabim në krijimin e tabelës së regjistrimeve: " . $innerEx->getMessage();
                $updateStatus = 'error';
            }
        }
    }
}

// Kontrollo nëse kolona custom_price ekziston në tabelën noteri dhe shtoje nëse jo
try {
    $pdo->query("SELECT custom_price FROM noteri LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Unknown column") !== false) {
        try {
            $pdo->exec("ALTER TABLE noteri ADD COLUMN custom_price DECIMAL(10, 2) NULL");
            $pdo->exec("ALTER TABLE noteri ADD COLUMN subscription_status VARCHAR(20) DEFAULT 'active'");
            
            if ($updateStatus != 'error') {
                $updateMessage .= " Kolona custom_price dhe subscription_status u shtua në tabelën e noterëve.";
                $updateStatus = 'info';
            }
        } catch (PDOException $innerEx) {
            if ($updateStatus != 'error') {
                $updateMessage .= " Gabim në shtimin e kolonave në tabelën e noterëve: " . $innerEx->getMessage();
                $updateStatus = 'error';
            }
        }
    }
}

// Kontrollo nëse kolona account_number dhe bank_name ekzistojnë në tabelën noteri dhe shtoji nëse jo
try {
    $pdo->query("SELECT account_number, bank_name FROM noteri LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Unknown column") !== false) {
        try {
            $pdo->exec("ALTER TABLE noteri ADD COLUMN account_number VARCHAR(50) NULL");
            $pdo->exec("ALTER TABLE noteri ADD COLUMN bank_name VARCHAR(100) NULL");
            
            if ($updateStatus != 'error') {
                $updateMessage .= " Kolonat account_number dhe bank_name u shtuan në tabelën e noterëve.";
                $updateStatus = 'info';
            }
        } catch (PDOException $innerEx) {
            if ($updateStatus != 'error') {
                $updateMessage .= " Gabim në shtimin e kolonave të llogarisë bankare në tabelën e noterëve: " . $innerEx->getMessage();
                $updateStatus = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurimet e Abonimeve | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a56db;
            --primary-hover: #1e40af;
            --secondary-color: #6b7280;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-color: #374151;
            --heading-color: #1e293b;
            --success-color: #16a34a;
            --warning-color: #f59e0b;
            --danger-color: #dc2626;
        }
        
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-bg);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .panel {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        h1 {
            color: var(--primary-color);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        h1 i {
            margin-right: 12px;
        }
        
        h2 {
            color: var(--heading-color);
            margin-top: 30px;
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 5px solid #16a34a;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 5px solid #dc2626;
        }
        
        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 5px solid #2563eb;
        }
        
        form {
            margin: 20px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--heading-color);
        }
        
        input[type="text"],
        input[type="number"],
        input[type="email"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: white;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="email"]:focus,
        select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(26, 86, 219, 0.2);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 5px;
        }
        
        input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        
        .help-text {
            color: var(--secondary-color);
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        .button {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            font-family: inherit;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            margin-right: 10px;
            transition: background-color 0.2s;
        }
        
        .button:hover {
            background-color: var(--primary-hover);
        }
        
        .button i {
            margin-right: 6px;
        }
        
        .button-secondary {
            background-color: var(--secondary-color);
        }
        
        .button-secondary:hover {
            background-color: #4b5563;
        }
        
        .button-success {
            background-color: var(--success-color);
        }
        
        .button-success:hover {
            background-color: #15803d;
        }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--heading-color);
            margin-bottom: 10px;
        }
        
        .card-text {
            color: var(--secondary-color);
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 30px 0;
        }
        
        .info-box {
            background-color: #f1f5f9;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        
        .info-box p:last-child {
            margin-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .toolbar div {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <h1><i class="fas fa-cog"></i> Konfigurimet e Abonimeve</h1>
            
            <div>
                <a href="subscription_processor.php?test=true" class="button button-success">
                    <i class="fas fa-flask"></i> Simulimi i pagesave
                </a>
                
                <a href="dashboard.php" class="button button-secondary">
                    <i class="fas fa-arrow-left"></i> Kthehu
                </a>
            </div>
        </div>
        
        <?php if (!empty($updateMessage)): ?>
            <div class="alert alert-<?php echo $updateStatus; ?>">
                <?php echo $updateMessage; ?>
            </div>
        <?php endif; ?>
        
        <div class="panel">
            <h2>Parametrat e abonimeve</h2>
            <p>Këtu mund të konfiguroni parametrat për procesin e pagesave automatike të abonimeve për noterët.</p>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="subscription_price">Çmimi i abonimit mujor (EUR)</label>
                    <input type="number" id="subscription_price" name="subscription_price" value="<?php echo htmlspecialchars($settings['subscription_price']); ?>" step="0.01" min="0" required>
                    <div class="help-text">Ky është çmimi i paracaktuar për abonimet. Mund të vendosni çmime të personalizuara për noterë të veçantë.</div>
                </div>
                
                <div class="form-group">
                    <label for="payment_day">Dita e muajit për pagesë</label>
                    <input type="number" id="payment_day" name="payment_day" value="<?php echo htmlspecialchars($settings['payment_day']); ?>" min="1" max="28" required>
                    <div class="help-text">Zgjidhni një ditë nga 1 deri në 28. Pagesat do të procesohen automatikisht në këtë ditë të çdo muaji.</div>
                </div>
                
                <div class="form-group">
                    <label for="subscription_frequency">Frekuenca e abonimit</label>
                    <select id="subscription_frequency" name="subscription_frequency" required>
                        <option value="monthly" <?php echo $settings['subscription_frequency'] === 'monthly' ? 'selected' : ''; ?>>Mujore</option>
                        <option value="quarterly" <?php echo $settings['subscription_frequency'] === 'quarterly' ? 'selected' : ''; ?>>Çdo tre muaj</option>
                        <option value="annually" <?php echo $settings['subscription_frequency'] === 'annually' ? 'selected' : ''; ?>>Vjetore</option>
                    </select>
                    <div class="help-text">Zgjidhni sa shpesh do të procesohen pagesat e abonimit.</div>
                </div>
                
                <div class="form-group">
                    <label for="grace_period">Periudha e faljes (ditë)</label>
                    <input type="number" id="grace_period" name="grace_period" value="<?php echo htmlspecialchars($settings['grace_period']); ?>" min="0" required>
                    <div class="help-text">Numri i ditëve pas dështimit të pagesës përpara se abonimit t'i pezullohet.</div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="email_notification" name="email_notification" <?php echo $settings['email_notification'] ? 'checked' : ''; ?>>
                        <label for="email_notification">Dërgo njoftime me email</label>
                    </div>
                    <div class="help-text">Nëse është e aktivizuar, noterët do të marrin njoftime me email për pagesat e suksesshme dhe të dështuara.</div>
                </div>
                
                <div class="form-group" style="margin-top: 30px;">
                    <button type="submit" class="button">
                        <i class="fas fa-save"></i> Ruaj konfigurimet
                    </button>
                </div>
            </form>
            
            <div class="divider"></div>
            
            <h2>Lidhjet e shpejta</h2>
            <div class="card-grid">
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="card-title">Pagesa të personalizuara</div>
                    <div class="card-text">Menaxhoni çmimet e personalizuara për noterë të veçantë dhe vendosni statusin e abonimeve.</div>
                    <a href="subscription_custom_prices.php" class="button">Menaxho</a>
                </div>
                
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="card-title">Historiku i pagesave</div>
                    <div class="card-text">Shiko të gjitha pagesat e përpunuara, statusin e tyre dhe detajet e transaksioneve.</div>
                    <a href="subscription_payments.php" class="button">Shiko historikun</a>
                </div>
                
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-title">Raportet e abonimeve</div>
                    <div class="card-text">Gjeneroni raporte dhe statistika për abonimet dhe pagesat e noterëve.</div>
                    <a href="subscription_reports.php" class="button">Shiko raportet</a>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <div class="info-box">
                <h3>Konfigurimi i automatizimit</h3>
                <p>Për të aktivizuar proceset automatike të pagesave, duhet të konfigurohet një CRON job që të ekzekutohet çdo ditë:</p>
                <pre style="background-color: #f1f5f9; padding: 10px; border-radius: 4px; overflow-x: auto;">0 8 * * * php <?php echo realpath(__DIR__); ?>/subscription_processor.php token=<?php echo htmlspecialchars("YXV0b21hdGljX3N1YnNjcmlwdGlvbl90b2tlbg=="); ?></pre>
                <p>Ky komandë do të ekzekutohet në orën 8:00 të çdo dite, por pagesat do të procesohen vetëm në ditën e konfiguruar (<?php echo htmlspecialchars($settings['payment_day']); ?> të çdo muaji).</p>
            </div>
        </div>
    </div>
    
    <script>
        // Shtoni validim në anën e klientit nëse nevojitet
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const subscriptionPrice = document.getElementById('subscription_price').value;
                const paymentDay = document.getElementById('payment_day').value;
                
                if (parseFloat(subscriptionPrice) <= 0) {
                    e.preventDefault();
                    alert('Çmimi i abonimit duhet të jetë një numër pozitiv.');
                }
                
                if (parseInt(paymentDay) < 1 || parseInt(paymentDay) > 28) {
                    e.preventDefault();
                    alert('Dita e pagesës duhet të jetë një numër ndërmjet 1 dhe 28.');
                }
            });
        });
    </script>
</body>
</html>