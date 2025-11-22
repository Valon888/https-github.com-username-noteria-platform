<?php
require_once 'config.php';

try {
    echo "=== ADDING MISSING PHONE COLUMNS TO PAYMENT_LOGS ===\n";
    
    // Shto kolonën phone_number
    $pdo->exec("ALTER TABLE payment_logs ADD COLUMN phone_number VARCHAR(20) NULL AFTER office_name");
    echo "✅ Added phone_number column\n";
    
    // Shto kolonën phone_verified
    $pdo->exec("ALTER TABLE payment_logs ADD COLUMN phone_verified TINYINT(1) DEFAULT 0 AFTER verification_status");
    echo "✅ Added phone_verified column\n";
    
    // Shto kolonën phone_verified_at
    $pdo->exec("ALTER TABLE payment_logs ADD COLUMN phone_verified_at TIMESTAMP NULL AFTER phone_verified");
    echo "✅ Added phone_verified_at column\n";
    
    echo "\n=== VERIFYING NEW STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE payment_logs');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $phone_columns = ['phone_number', 'phone_verified', 'phone_verified_at'];
    foreach($columns as $col) {
        if (in_array($col['Field'], $phone_columns)) {
            echo "✅ {$col['Field']} - {$col['Type']} - {$col['Null']}\n";
        }
    }
    
    echo "\n🎉 ALL PHONE COLUMNS ADDED SUCCESSFULLY!\n";
    
} catch(Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>