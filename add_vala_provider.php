<?php
require_once 'config.php';

try {
    echo "=== ADDING VALA TO SMS PROVIDER CONFIG ===\n";
    
    // Përditëso prioritetet për të bërë vend për Vala
    $pdo->exec("UPDATE sms_provider_config SET priority = priority + 1 WHERE priority >= 2");
    echo "✅ Updated existing provider priorities\n";
    
    // Shto Vala si provider i dytë (pas IPKO)
    $pdo->exec("
        INSERT IGNORE INTO sms_provider_config 
        (provider_name, is_active, priority, sender_name, daily_limit, monthly_limit, success_rate) 
        VALUES ('Vala', 1, 2, 'NOTERIA', 2000, 50000, 96.50)
    ");
    echo "✅ Added Vala SMS provider to database\n";
    
    echo "\n=== CURRENT SMS PROVIDERS ===\n";
    $stmt = $pdo->query("SELECT provider_name, is_active, priority, sender_name FROM sms_provider_config ORDER BY priority");
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($providers as $provider) {
        $status = $provider['is_active'] ? '🟢' : '🔴';
        echo "$status Priority {$provider['priority']}: {$provider['provider_name']} ({$provider['sender_name']})\n";
    }
    
    echo "\n🎉 Vala SMS provider successfully configured!\n";
    
} catch(Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>