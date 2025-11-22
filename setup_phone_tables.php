<?php
require_once 'config.php';

$required_tables = [
    'phone_verification_codes',
    'phone_verification_logs', 
    'sms_provider_config'
];

echo "=== CHECKING PHONE VERIFICATION TABLES ===\n";

foreach($required_tables as $table) {
    try {
        $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "✅ $table - EXISTS\n";
    } catch(Exception $e) {
        echo "❌ $table - MISSING\n";
    }
}

echo "\n=== CREATING MISSING TABLES ===\n";
try {
    // Create phone_verification_codes table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS phone_verification_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(20) NOT NULL,
        verification_code VARCHAR(10) NOT NULL,
        transaction_id VARCHAR(100) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        attempts INT DEFAULT 0,
        is_used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_phone (phone_number),
        INDEX idx_transaction (transaction_id),
        INDEX idx_expires (expires_at)
    )");
    echo "✅ Created phone_verification_codes table\n";
    
    // Create phone_verification_logs table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS phone_verification_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(20) NOT NULL,
        transaction_id VARCHAR(100) NOT NULL,
        action_type ENUM('send', 'verify', 'resend') NOT NULL,
        provider VARCHAR(50) NOT NULL,
        status ENUM('success', 'failed') NOT NULL,
        response_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_phone (phone_number),
        INDEX idx_transaction (transaction_id),
        INDEX idx_created (created_at)
    )");
    echo "✅ Created phone_verification_logs table\n";
    
    // Create sms_provider_config table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS sms_provider_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_name VARCHAR(50) NOT NULL UNIQUE,
        is_active TINYINT(1) DEFAULT 1,
        priority INT DEFAULT 1,
        api_key VARCHAR(255),
        api_secret VARCHAR(255),
        sender_name VARCHAR(20),
        base_url VARCHAR(255),
        daily_limit INT DEFAULT 1000,
        monthly_limit INT DEFAULT 30000,
        success_rate DECIMAL(5,2) DEFAULT 95.00,
        last_used_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "✅ Created sms_provider_config table\n";
    
    // Insert default provider configurations
    $pdo->exec("
    INSERT IGNORE INTO sms_provider_config (provider_name, is_active, priority, sender_name) VALUES
    ('IPKO', 1, 1, 'NOTERIA'),
    ('Infobip', 1, 2, 'NOTERIA'),
    ('Twilio', 1, 3, 'NOTERIA')
    ");
    echo "✅ Inserted default SMS provider configurations\n";
    
    echo "\n🎉 ALL PHONE VERIFICATION TABLES CREATED SUCCESSFULLY!\n";
    
} catch(Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>