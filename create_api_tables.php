<?php
// create_api_tables.php - Krijon tabelat e nevojshme për API
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once 'config.php';

try {
    // Kontrollo lidhjen me databazën
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Lidhja me databazën u realizua me sukses!</p>";
    
    // Krijo tabelën api_tokens
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expired_at TIMESTAMP NULL,
            description VARCHAR(255),
            UNIQUE(token)
        )
    ");
    echo "<p style='color: green;'>✅ Tabela api_tokens u krijua ose ekzistonte tashmë.</p>";
    
    // Krijo tabelën payment_logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_email VARCHAR(255) NOT NULL,
            office_name VARCHAR(255) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            operator VARCHAR(50),
            payment_method VARCHAR(50) NOT NULL,
            payment_amount DECIMAL(10,2) NOT NULL,
            payment_details TEXT,
            transaction_id VARCHAR(100) NOT NULL,
            verification_status VARCHAR(20) DEFAULT 'pending',
            file_path VARCHAR(255),
            numri_fiskal VARCHAR(20),
            numri_biznesit VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verified_at TIMESTAMP NULL,
            verified_by VARCHAR(100),
            UNIQUE(transaction_id)
        )
    ");
    echo "<p style='color: green;'>✅ Tabela payment_logs u krijua ose ekzistonte tashmë.</p>";
    
    // Kontrollo nëse tabelat janë krijuar me sukses duke listuar tabelat
    $stmt = $pdo->query("SHOW TABLES LIKE 'api_tokens'");
    $hasApiTokens = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'payment_logs'");
    $hasPaymentLogs = $stmt->rowCount() > 0;
    
    if ($hasApiTokens && $hasPaymentLogs) {
        echo "<p style='color: green;'>✅ Të dyja tabelat ekzistojnë në databazë!</p>";
        
        // Shto një token fillestar për testim nëse nuk ka asnjë token
        $stmt = $pdo->query("SELECT COUNT(*) FROM api_tokens");
        $tokenCount = $stmt->fetchColumn();
        
        if ($tokenCount === 0) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 year'));
            $description = 'Token fillestar për testim';
            
            $stmt = $pdo->prepare("INSERT INTO api_tokens (token, expired_at, description) VALUES (?, ?, ?)");
            $stmt->execute([$token, $expiry, $description]);
            
            echo "<p style='color: blue;'>ℹ️ U krijua një token fillestar për testim:</p>";
            echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>{$token}</pre>";
            echo "<p>Kopjoni këtë token dhe përdoreni për thirrjet API.</p>";
        } else {
            // Shfaq token-in më të fundit
            $stmt = $pdo->query("SELECT token, expired_at FROM api_tokens ORDER BY created_at DESC LIMIT 1");
            $latestToken = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<p style='color: blue;'>ℹ️ Token ekzistues në sistem:</p>";
            echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>{$latestToken['token']}</pre>";
            echo "<p>Skadon më: {$latestToken['expired_at']}</p>";
        }
        
        echo "<p>Për të menaxhuar token-at, shkoni te <a href='token_generator.php'>Gjeneruesi i Token-ave</a>.</p>";
        echo "<p>Për të testuar API-in, shkoni te <a href='api_client_test.php'>API Test Client</a>.</p>";
    } else {
        echo "<p style='color: red;'>⚠️ Problem me krijimin e tabelave. Kontrolloni nëse përdoruesi i databazës ka privilegjet e duhura.</p>";
        
        if (!$hasApiTokens) {
            echo "<p style='color: red;'>❌ Tabela api_tokens nuk ekziston.</p>";
        }
        
        if (!$hasPaymentLogs) {
            echo "<p style='color: red;'>❌ Tabela payment_logs nuk ekziston.</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Gabim në lidhjen me databazën:</p>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    
    // Kontrollo nëse është problem me lidhjen
    if (strpos($e->getMessage(), "Unknown database") !== false) {
        echo "<p>Databaza 'noteria' nuk ekziston. Ju duhet ta krijoni atë manualisht ose të modifikoni parametrat e lidhjes në config.php.</p>";
    }
    
    // Kontrollo nëse është problem me kredencialet
    if (strpos($e->getMessage(), "Access denied") !== false) {
        echo "<p>Kredencialet e përdoruesit nuk janë të sakta ose përdoruesi nuk ka privilegjet e duhura.</p>";
    }
}
?>