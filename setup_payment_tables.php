<?php
// Script për krijimin e tabelave të sistemit të pagesave
// filepath: d:\xampp\htdocs\noteria\setup_payment_tables.php

require_once 'config.php';

try {
    echo "Fillimi i krijimit të tabelave...\n\n";
    
    // 1. Krijo tabelën payment_logs
    echo "1. Duke krijuar tabelën payment_logs...\n";
    $sql_payment_logs = "
    CREATE TABLE IF NOT EXISTS payment_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        office_email VARCHAR(255) NOT NULL,
        transaction_id VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('bank_transfer', 'paypal', 'card') DEFAULT 'bank_transfer',
        status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
        verification_attempts INT DEFAULT 0,
        api_response TEXT,
        payment_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        verified_at TIMESTAMP NULL,
        
        INDEX idx_email (office_email),
        INDEX idx_transaction (transaction_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_payment_logs);
    echo "✓ Tabela payment_logs u krijua me sukses!\n\n";
    
    // 2. Kontrollo dhe përditëso tabelën zyrat
    echo "2. Duke kontrolluar tabelën zyrat...\n";
    
    // Kontrollo nëse kolonat ekzistojnë
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'transaction_id'");
    if ($stmt->rowCount() == 0) {
        echo "   Shtimi i kolonës transaction_id...\n";
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN transaction_id VARCHAR(100) NULL");
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'payment_method'");
    if ($stmt->rowCount() == 0) {
        echo "   Shtimi i kolonës payment_method...\n";
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN payment_method ENUM('bank_transfer', 'paypal', 'card') DEFAULT 'bank_transfer'");
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'payment_verified'");
    if ($stmt->rowCount() == 0) {
        echo "   Shtimi i kolonës payment_verified...\n";
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN payment_verified BOOLEAN DEFAULT FALSE");
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'payment_proof_path'");
    if ($stmt->rowCount() == 0) {
        echo "   Shtimi i kolonës payment_proof_path...\n";
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN payment_proof_path VARCHAR(500) NULL");
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'created_at'");
    if ($stmt->rowCount() == 0) {
        echo "   Shtimi i kolonës created_at...\n";
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat LIKE 'updated_at'");
    if ($stmt->rowCount() == 0) {
        echo "   Shtimi i kolonës updated_at...\n";
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    
    echo "✓ Tabela zyrat u përditësua me sukses!\n\n";
    
    // 3. Shto indekse të nevojshme
    echo "3. Duke shtuar indekse...\n";
    try {
        $pdo->exec("ALTER TABLE zyrat ADD INDEX idx_transaction_id (transaction_id)");
        echo "   ✓ Indeksi idx_transaction_id u shtua\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   - Indeksi idx_transaction_id tashmë ekziston\n";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE zyrat ADD INDEX idx_payment_verified (payment_verified)");
        echo "   ✓ Indeksi idx_payment_verified u shtua\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   - Indeksi idx_payment_verified tashmë ekziston\n";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE zyrat ADD INDEX idx_email (email)");
        echo "   ✓ Indeksi idx_email u shtua\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   - Indeksi idx_email tashmë ekziston\n";
        }
    }
    
    // 4. Krijo tabelën e auditimit (opsionale)
    echo "\n4. Duke krijuar tabelën e auditimit...\n";
    $sql_audit = "
    CREATE TABLE IF NOT EXISTS payment_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(100) NOT NULL,
        action ENUM('created', 'verified', 'failed', 'cancelled', 'refunded') NOT NULL,
        user_ip VARCHAR(45),
        user_agent TEXT,
        additional_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_transaction (transaction_id),
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_audit);
    echo "✓ Tabela payment_audit_log u krijua me sukses!\n\n";
    
    // 5. Krijo tabelën e konfigurimit të sigurisë
    echo "5. Duke krijuar tabelën e konfigurimit...\n";
    $sql_settings = "
    CREATE TABLE IF NOT EXISTS security_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_name VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        description TEXT,
        is_encrypted BOOLEAN DEFAULT FALSE,
        updated_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_settings);
    echo "✓ Tabela security_settings u krijua me sukses!\n\n";
    
    // 6. Fut të dhënat fillestare të konfigurimit
    echo "6. Duke futur konfigurimin fillestar...\n";
    $settings = [
        ['max_daily_transactions_per_email', '5', 'Numri maksimal i transaksioneve për email në ditë'],
        ['min_payment_amount', '10', 'Shuma minimale e pagesës në Euro'],
        ['max_payment_amount', '10000', 'Shuma maksimale e pagesës në Euro'],
        ['payment_verification_timeout', '300', 'Koha e timeout për verifikim në sekonda'],
        ['max_file_upload_size', '5242880', 'Madhësia maksimale e file në bytes (5MB)'],
        ['allowed_file_types', 'pdf,jpg,jpeg,png', 'Tipet e lejuara të file-ave'],
        ['require_payment_proof', 'true', 'A është e detyrueshme dëshmi e pagesës'],
        ['enable_duplicate_check', 'true', 'A kontrollohen pagesat duplikate'],
        ['duplicate_check_hours', '24', 'Orët për kontroll të duplikateve']
    ];
    
    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO security_settings (setting_name, setting_value, description) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute($setting);
    }
    echo "✓ Konfigurimi fillestar u fut me sukses!\n\n";
    
    // 7. Testo lidhjen
    echo "7. Duke testuar sistemin...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payment_logs");
    $result = $stmt->fetch();
    echo "   Tabela payment_logs: " . $result['count'] . " regjistra\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM zyrat");
    $result = $stmt->fetch();
    echo "   Tabela zyrat: " . $result['count'] . " regjistra\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM security_settings");
    $result = $stmt->fetch();
    echo "   Tabela security_settings: " . $result['count'] . " regjistra\n";
    
    echo "\n🎉 SUKSES! Të gjitha tabelat u krijuan me sukses!\n";
    echo "Sistemi i verifikimit të pagesave është gati për përdorim.\n\n";
    
    // Shfaq informacion shtesë
    echo "ℹ️ Informacion:\n";
    echo "- Tabelat e krijuara: payment_logs, payment_audit_log, security_settings\n";
    echo "- Tabela zyrat u përditësua me kolona të reja\n";
    echo "- Indekset u shtuan për performancë të mirë\n";
    echo "- Konfigurimi fillestar u ngarkua\n\n";
    
    echo "🔧 Hapat e ardhshëm:\n";
    echo "1. Konfiguroni API keys në payment_config.php\n";
    echo "2. Testoni sistemin me zyrat_register.php\n";
    echo "3. Kontrolloni log-et në direktorinë logs/\n";
    
} catch (PDOException $e) {
    echo "❌ GABIM: " . $e->getMessage() . "\n";
    echo "Kodi i gabimit: " . $e->getCode() . "\n\n";
    
    if ($e->getCode() == 1049) {
        echo "💡 Sugjerim: Baza e të dhënave nuk ekziston. Sigurohuni që:\n";
        echo "1. MySQL/MariaDB është duke punuar\n";
        echo "2. Baza e të dhënave 'noteria' është krijuar\n";
        echo "3. Kredencialet në config.php janë të sakta\n";
    } elseif ($e->getCode() == 1045) {
        echo "💡 Sugjerim: Gabim në kredenciale. Kontrolloni:\n";
        echo "1. Username dhe password në config.php\n";
        echo "2. Lejet e përdoruesit të bazës së të dhënave\n";
    } elseif ($e->getCode() == 2002) {
        echo "💡 Sugjerim: Nuk mund të lidhet me serverin MySQL:\n";
        echo "1. Sigurohuni që XAMPP/WAMP është duke punuar\n";
        echo "2. Kontrolloni nëse MySQL service është aktiv\n";
    }
} catch (Exception $e) {
    echo "❌ GABIM I PËRGJITHSHËM: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Setup përfundoi në " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n";
?>