<?php
// initialize_subscription_system.php - Një skedar për inicializimin e sistemit të abonimeve

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

// Funksioni për të kontrolluar nëse tabela ekziston
function tableExists($pdo, $tableName) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Funksioni për të krijuar tabelat e sistemit të abonimeve
function createTables($pdo) {
    $results = [];
    
    // Krijo tabelën system_settings nëse nuk ekziston
    if (!tableExists($pdo, 'system_settings')) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    subscription_price DECIMAL(10, 2) NOT NULL DEFAULT 25.00,
                    payment_day INT NOT NULL DEFAULT 1,
                    subscription_frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
                    email_notification TINYINT(1) NOT NULL DEFAULT 1,
                    grace_period INT NOT NULL DEFAULT 3,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $results['system_settings'] = "Tabela 'system_settings' u krijua me sukses.";
        } catch (PDOException $e) {
            $results['system_settings'] = "Gabim në krijimin e tabelës 'system_settings': " . $e->getMessage();
        }
    } else {
        $results['system_settings'] = "Tabela 'system_settings' ekziston tashmë.";
    }
    
    // Krijo tabelën noteri nëse nuk ekziston
    if (!tableExists($pdo, 'noteri')) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS noteri (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    emri VARCHAR(100) NOT NULL,
                    mbiemri VARCHAR(100) NOT NULL,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    telefoni VARCHAR(20),
                    adresa TEXT,
                    qyteti VARCHAR(100),
                    shteti VARCHAR(100),
                    statusi VARCHAR(20) DEFAULT 'active',
                    custom_price DECIMAL(10, 2) NULL,
                    subscription_status VARCHAR(20) DEFAULT 'active',
                    account_number VARCHAR(50) NULL,
                    bank_name VARCHAR(100) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $results['noteri'] = "Tabela 'noteri' u krijua me sukses.";
        } catch (PDOException $e) {
            $results['noteri'] = "Gabim në krijimin e tabelës 'noteri': " . $e->getMessage();
        }
    } else {
        // Nëse tabela ekziston, kontrollo nëse ka kolonat e nevojshme
        try {
            // Shto kolonën custom_price nëse nuk ekziston
            $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'custom_price'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE noteri ADD COLUMN custom_price DECIMAL(10, 2) NULL");
                $results['noteri_custom_price'] = "Kolona 'custom_price' u shtua në tabelën 'noteri'.";
            } else {
                $results['noteri_custom_price'] = "Kolona 'custom_price' ekziston tashmë në tabelën 'noteri'.";
            }
            
            // Shto kolonën subscription_status nëse nuk ekziston
            $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'subscription_status'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE noteri ADD COLUMN subscription_status VARCHAR(20) DEFAULT 'active'");
                $results['noteri_subscription_status'] = "Kolona 'subscription_status' u shtua në tabelën 'noteri'.";
            } else {
                $results['noteri_subscription_status'] = "Kolona 'subscription_status' ekziston tashmë në tabelën 'noteri'.";
            }
            
            // Shto kolonat account_number dhe bank_name nëse nuk ekzistojnë
            $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'account_number'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE noteri ADD COLUMN account_number VARCHAR(50) NULL");
                $results['noteri_account_number'] = "Kolona 'account_number' u shtua në tabelën 'noteri'.";
            } else {
                $results['noteri_account_number'] = "Kolona 'account_number' ekziston tashmë në tabelën 'noteri'.";
            }
            
            $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'bank_name'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE noteri ADD COLUMN bank_name VARCHAR(100) NULL");
                $results['noteri_bank_name'] = "Kolona 'bank_name' u shtua në tabelën 'noteri'.";
            } else {
                $results['noteri_bank_name'] = "Kolona 'bank_name' ekziston tashmë në tabelën 'noteri'.";
            }
        } catch (PDOException $e) {
            $results['noteri_columns'] = "Gabim në shtimin e kolonave në tabelën 'noteri': " . $e->getMessage();
        }
    }
    
    // Krijo tabelën subscription_payments nëse nuk ekziston
    if (!tableExists($pdo, 'subscription_payments')) {
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
                    INDEX (noter_id),
                    INDEX (payment_date),
                    INDEX (status),
                    FOREIGN KEY (noter_id) REFERENCES noteri(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $results['subscription_payments'] = "Tabela 'subscription_payments' u krijua me sukses.";
        } catch (PDOException $e) {
            $results['subscription_payments'] = "Gabim në krijimin e tabelës 'subscription_payments': " . $e->getMessage();
        }
    } else {
        $results['subscription_payments'] = "Tabela 'subscription_payments' ekziston tashmë.";
    }
    
    // Krijo tabelën activity_logs nëse nuk ekziston
    if (!tableExists($pdo, 'activity_logs')) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    log_type VARCHAR(50) NOT NULL,
                    user_id INT,
                    status VARCHAR(20) NOT NULL,
                    message TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (log_type),
                    INDEX (user_id),
                    INDEX (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $results['activity_logs'] = "Tabela 'activity_logs' u krijua me sukses.";
        } catch (PDOException $e) {
            $results['activity_logs'] = "Gabim në krijimin e tabelës 'activity_logs': " . $e->getMessage();
        }
    } else {
        $results['activity_logs'] = "Tabela 'activity_logs' ekziston tashmë.";
    }
    
    return $results;
}

// Funksioni për të krijuar të dhëna fillestare në system_settings
function insertInitialSettings($pdo) {
    if (tableExists($pdo, 'system_settings')) {
        try {
            // Kontrollo nëse ka të dhëna në tabelë
            $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                // Shto të dhëna fillestare
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings 
                    (subscription_price, payment_day, subscription_frequency, email_notification, grace_period, created_at, updated_at) 
                    VALUES 
                    (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $stmt->execute([
                    25.00,          // subscription_price (25 EUR)
                    1,              // payment_day (dita 1 e çdo muaji)
                    'monthly',      // subscription_frequency
                    1,              // email_notification (aktivizuar)
                    3               // grace_period (3 ditë)
                ]);
                
                return "Të dhënat fillestare u shtuan me sukses në tabelën 'system_settings'.";
            } else {
                return "Tabela 'system_settings' ka tashmë të dhëna, nuk u bë asnjë ndryshim.";
            }
        } catch (PDOException $e) {
            return "Gabim në shtimin e të dhënave fillestare në 'system_settings': " . $e->getMessage();
        }
    } else {
        return "Tabela 'system_settings' nuk ekziston, nuk mund të shtohen të dhëna fillestare.";
    }
}

// Funksioni për të shtuar noterë për testim
function insertTestNoters($pdo) {
    if (tableExists($pdo, 'noteri')) {
        try {
            // Kontrollo nëse ka noterë në tabelë
            $stmt = $pdo->query("SELECT COUNT(*) FROM noteri");
            $count = $stmt->fetchColumn();
            
            // Nëse nuk ka noterë, shto disa për testim
            if ($count == 0) {
                // Përgatit të dhënat e noterëve të testimit
                $testNoters = [
                    [
                        'emri' => 'Arben',
                        'mbiemri' => 'Krasniqi',
                        'email' => 'arben.krasniqi@noteria.al',
                        'telefoni' => '044123456',
                        'adresa' => 'Rr. Adem Jashari, nr. 15',
                        'qyteti' => 'Prishtinë',
                        'shteti' => 'Kosovë',
                        'account_number' => 'AL12345678901234567890',
                        'bank_name' => 'BKT'
                    ],
                    [
                        'emri' => 'Lumnije',
                        'mbiemri' => 'Berisha',
                        'email' => 'lumnije.berisha@noteria.al',
                        'telefoni' => '045789012',
                        'adresa' => 'Rr. Nëna Terezë, nr. 28',
                        'qyteti' => 'Prizren',
                        'shteti' => 'Kosovë',
                        'account_number' => 'AL09876543210987654321',
                        'bank_name' => 'Raiffeisen Bank'
                    ],
                    [
                        'emri' => 'Blerim',
                        'mbiemri' => 'Hoxha',
                        'email' => 'blerim.hoxha@noteria.al',
                        'telefoni' => '049567890',
                        'adresa' => 'Rr. Fan Noli, nr. 7',
                        'qyteti' => 'Gjakovë',
                        'shteti' => 'Kosovë',
                        'account_number' => 'AL54321678901234509876',
                        'bank_name' => 'ProCredit Bank'
                    ]
                ];
                
                // Shto secilin noter në databazë
                $stmt = $pdo->prepare("
                    INSERT INTO noteri 
                    (emri, mbiemri, email, telefoni, adresa, qyteti, shteti, statusi, 
                     custom_price, subscription_status, account_number, bank_name, created_at, updated_at) 
                    VALUES 
                    (?, ?, ?, ?, ?, ?, ?, 'active', NULL, 'active', ?, ?, NOW(), NOW())
                ");
                
                foreach ($testNoters as $noter) {
                    $stmt->execute([
                        $noter['emri'],
                        $noter['mbiemri'],
                        $noter['email'],
                        $noter['telefoni'],
                        $noter['adresa'],
                        $noter['qyteti'],
                        $noter['shteti'],
                        $noter['account_number'],
                        $noter['bank_name']
                    ]);
                }
                
                return "U shtuan " . count($testNoters) . " noterë për testim.";
            } else {
                return "Tabela 'noteri' ka tashmë " . $count . " noterë, nuk u shtuan noterë të rinj për testim.";
            }
        } catch (PDOException $e) {
            return "Gabim në shtimin e noterëve për testim: " . $e->getMessage();
        }
    } else {
        return "Tabela 'noteri' nuk ekziston, nuk mund të shtohen noterë për testim.";
    }
}

// Ekzekuto inicializimin dhe raporto rezultatet
$tablesResults = createTables($pdo);
$settingsResult = insertInitialSettings($pdo);
$notersResult = insertTestNoters($pdo);

// Shfaq rezultatet në format HTML
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicializimi i sistemit të abonimeve | Noteria</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1, h2 {
            color: #1a56db;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        .success {
            color: #16a34a;
        }
        
        .error {
            color: #dc2626;
        }
        
        .info {
            color: #2563eb;
        }
        
        .action {
            margin-top: 20px;
        }
        
        .action a {
            display: inline-block;
            padding: 10px 15px;
            background-color: #1a56db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <h1>Inicializimi i sistemit të abonimeve</h1>
    
    <h2>Rezultati i krijimit të tabelave</h2>
    <table>
        <tr>
            <th>Tabela</th>
            <th>Rezultati</th>
        </tr>
        <?php foreach ($tablesResults as $table => $result): ?>
            <tr>
                <td><?php echo $table; ?></td>
                <td class="<?php echo strpos($result, 'Gabim') !== false ? 'error' : 'success'; ?>">
                    <?php echo $result; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    
    <h2>Rezultati i shtimit të konfigurimit fillestar</h2>
    <div class="<?php echo strpos($settingsResult, 'Gabim') !== false ? 'error' : 'success'; ?>">
        <?php echo $settingsResult; ?>
    </div>
    
    <h2>Rezultati i shtimit të noterëve për testim</h2>
    <div class="<?php echo strpos($notersResult, 'Gabim') !== false ? 'error' : 'success'; ?>">
        <?php echo $notersResult; ?>
    </div>
    
    <div class="action">
        <a href="check_subscription_tables.php">Kontrollo tabelat e krijuara</a>
        <a href="subscription_settings.php">Shko tek konfigurimet e abonimeve</a>
    </div>
</body>
</html>