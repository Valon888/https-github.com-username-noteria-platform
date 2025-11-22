<?php
/**
 * Setup Database Tables for Automatic Billing System
 * Krijimi i tabelave për sistemin e faturimit automatik
 */

require_once 'confidb.php';

try {
    // Tabela për pagesat e abonimeve
    $createPaymentsTable = "
    CREATE TABLE IF NOT EXISTS `subscription_payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `noter_id` int(11) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `currency` varchar(3) DEFAULT 'EUR',
        `payment_method` varchar(50) DEFAULT 'auto_charge',
        `transaction_id` varchar(100) UNIQUE NOT NULL,
        `payment_date` datetime NOT NULL,
        `due_date` date NULL,
        `status` enum('pending','completed','failed','cancelled','refunded') DEFAULT 'pending',
        `billing_period_start` date NOT NULL,
        `billing_period_end` date NOT NULL,
        `payment_type` enum('automatic','manual') DEFAULT 'automatic',
        `processed_at` datetime NULL,
        `notes` text NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `noter_id` (`noter_id`),
        KEY `payment_date` (`payment_date`),
        KEY `status` (`status`),
        KEY `billing_period` (`billing_period_start`, `billing_period_end`),
        CONSTRAINT `fk_payments_noter` FOREIGN KEY (`noter_id`) REFERENCES `noteri` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createPaymentsTable);
    echo "✓ Tabela 'subscription_payments' u krijua me sukses.\n";
    
    // Tabela për statistikat e faturimit
    $createStatsTable = "
    CREATE TABLE IF NOT EXISTS `billing_statistics` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `billing_date` date NOT NULL,
        `total_noters_processed` int(11) DEFAULT 0,
        `successful_charges` int(11) DEFAULT 0,
        `failed_charges` int(11) DEFAULT 0,
        `total_amount_charged` decimal(12,2) DEFAULT 0.00,
        `processing_time_seconds` int(11) NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_billing_date` (`billing_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createStatsTable);
    echo "✓ Tabela 'billing_statistics' u krijua me sukses.\n";
    
    // Kontrollo dhe përditëso tabelën e noterëve nëse nevojitet
    $checkNoterColumns = $pdo->query("DESCRIBE noteri");
    $columns = $checkNoterColumns->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('subscription_type', $columns)) {
        $alterNoteri1 = "ALTER TABLE `noteri` ADD COLUMN `subscription_type` enum('standard','custom') DEFAULT 'standard'";
        $pdo->exec($alterNoteri1);
        echo "✓ Kolona 'subscription_type' u shtua në tabelën 'noteri'.\n";
    }
    
    if (!in_array('custom_price', $columns)) {
        $alterNoteri2 = "ALTER TABLE `noteri` ADD COLUMN `custom_price` decimal(8,2) NULL";
        $pdo->exec($alterNoteri2);
        echo "✓ Kolona 'custom_price' u shtua në tabelën 'noteri'.\n";
    }
    
    if (!in_array('status', $columns)) {
        $alterNoteri3 = "ALTER TABLE `noteri` ADD COLUMN `status` enum('active','inactive') DEFAULT 'active'";
        $pdo->exec($alterNoteri3);
        echo "✓ Kolona 'status' u shtua në tabelën 'noteri'.\n";
    }
    
    if (!in_array('data_regjistrimit', $columns)) {
        $alterNoteri4 = "ALTER TABLE `noteri` ADD COLUMN `data_regjistrimit` datetime DEFAULT CURRENT_TIMESTAMP";
        $pdo->exec($alterNoteri4);
        echo "✓ Kolona 'data_regjistrimit' u shtua në tabelën 'noteri'.\n";
    }
    
    // Tabela për konfigurimet e sistemit të faturimit
    $createConfigTable = "
    CREATE TABLE IF NOT EXISTS `billing_config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `config_key` varchar(100) NOT NULL UNIQUE,
        `config_value` text NOT NULL,
        `description` varchar(255) NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_config_key` (`config_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createConfigTable);
    echo "✓ Tabela 'billing_config' u krijua me sukses.\n";
    
    // Shto konfigurimet fillestare
    $defaultConfigs = [
        ['billing_time', '07:00:00', 'Ora kur ekzekutohet faturimi automatik'],
        ['billing_day', '1', 'Dita e muajit kur ekzekutohet faturimi (1-28)'],
        ['standard_price', '150.00', 'Çmimi mujor në EUR'],
        ['due_days', '7', 'Numri i ditëve për të paguar pas faturimit'],
        ['email_notifications', '1', 'A të dërgohen njoftimet email (1=po, 0=jo)'],
        ['auto_billing_enabled', '1', 'A është i aktivizuar faturimi automatik (1=po, 0=jo)']
    ];
    
    $insertConfig = $pdo->prepare("
        INSERT IGNORE INTO billing_config (config_key, config_value, description) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($defaultConfigs as $config) {
        $insertConfig->execute($config);
    }
    echo "✓ Konfigurimet fillestare u shtuan.\n";
    
    // Krijo indekse për performance të mirë
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_payments_noter_date ON subscription_payments (noter_id, payment_date)",
        "CREATE INDEX IF NOT EXISTS idx_payments_status_date ON subscription_payments (status, payment_date)",
        "CREATE INDEX IF NOT EXISTS idx_noteri_status ON noteri (status)",
        "CREATE INDEX IF NOT EXISTS idx_noteri_subscription ON noteri (subscription_type)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $pdo->exec($index);
        } catch (PDOException $e) {
            // Indeksi mund të ekzistojë tashmë
        }
    }
    echo "✓ Indekset u krijuan.\n";
    
    echo "\n=== SETUP I KOMPLETUAR ME SUKSES! ===\n";
    echo "Sistemi i faturimit automatik është gati për përdorim.\n\n";
    
    echo "HAPAT E ARDHSHËM:\n";
    echo "1. Konfiguro cron job për të ekzekutuar auto_billing_system.php në ora 07:00 çdo ditë\n";
    echo "   Shembull: 0 7 * * * /usr/bin/php " . __DIR__ . "/auto_billing_system.php\n\n";
    echo "2. Testo sistemin duke ekzekutuar manualisht:\n";
    echo "   php auto_billing_system.php\n\n";
    echo "3. Monitoroni log files:\n";
    echo "   - billing_log.txt (aktiviteti normal)\n";
    echo "   - billing_error.log (gabimet)\n\n";
    
} catch (PDOException $e) {
    echo "GABIM: " . $e->getMessage() . "\n";
    exit(1);
}
?>