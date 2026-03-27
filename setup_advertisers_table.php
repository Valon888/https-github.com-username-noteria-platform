<?php
/**
 * Setup Script - Krijon tabelën advertisers nëse nuk ekziston
 * Aksesohet direkt në browser: http://localhost/noteria/setup_advertisers_table.php
 */

require_once 'confidb.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    echo "✅ Lidhja me databazën u arrit me sukses!\n\n";
    
    // Kontrolloni nëse tabela advertisers ekziston
    $tables = $pdo->query("SHOW TABLES LIKE 'advertisers'")->fetchAll();
    
    if (empty($tables)) {
        echo "📋 Tabela 'advertisers' nuk ekziston. Po krijohet...\n\n";
        
        $createTable = "CREATE TABLE IF NOT EXISTS advertisers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            phone VARCHAR(20),
            website VARCHAR(255),
            category VARCHAR(100),
            description LONGTEXT,
            business_registration VARCHAR(100),
            subscription_status VARCHAR(50) DEFAULT 'pending',
            subscription_plan VARCHAR(50),
            payment_status VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_status (subscription_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createTable);
        echo "✅ Tabela 'advertisers' u krijua me sukses!\n\n";
        
        // Shfaq strukturën
        $columns = $pdo->query("DESC advertisers")->fetchAll(PDO::FETCH_ASSOC);
        echo "📊 Struktura e tabelës:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-20s %-15s %-10s %-15s %-20s\n", "FIELD", "TYPE", "NULL", "KEY", "EXTRA");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($columns as $col) {
            printf("%-20s %-15s %-10s %-15s %-20s\n", 
                $col['Field'], 
                $col['Type'], 
                $col['Null'], 
                $col['Key'], 
                $col['Extra']
            );
        }
        echo str_repeat("-", 80) . "\n\n";
        
    } else {
        echo "✅ Tabela 'advertisers' ekziston!\n\n";
        
        // Shfaq strukturën
        $columns = $pdo->query("DESC advertisers")->fetchAll(PDO::FETCH_ASSOC);
        echo "📊 Struktura e tabelës:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-20s %-15s %-10s %-15s %-20s\n", "FIELD", "TYPE", "NULL", "KEY", "EXTRA");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($columns as $col) {
            printf("%-20s %-15s %-10s %-15s %-20s\n", 
                $col['Field'], 
                $col['Type'], 
                $col['Null'], 
                $col['Key'], 
                $col['Extra']
            );
        }
        echo str_repeat("-", 80) . "\n\n";
        
        // Shfaq numrin e rekordeve
        $count = $pdo->query("SELECT COUNT(*) FROM advertisers")->fetchColumn();
        echo "📈 Numri i reklamuesve të regjistruar: " . $count . "\n\n";
    }
    
    echo "✅ Setimi u kompletua me sukses!\n";
    echo "🔗 Mund të hiqni këtë file pas setupit ose ta mbani për referim.\n";
    
} catch (PDOException $e) {
    echo "❌ Gabim në lidhjen me databazën:\n";
    echo "Mesazhi: " . $e->getMessage() . "\n";
    echo "Kodi: " . $e->getCode() . "\n";
}
?>
