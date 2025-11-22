<?php
require_once 'config.php';
require_once 'PhoneVerificationAdvanced.php';

echo "=== SMS PROVIDER DIAGNOSTICS ===\n";

try {
    // Kontrolloj provider-ët në databazë
    $stmt = $pdo->query("SELECT * FROM sms_provider_config ORDER BY priority");
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 PROVIDERS IN DATABASE:\n";
    foreach($providers as $provider) {
        echo "- {$provider['provider_name']}: ";
        echo "Active=" . ($provider['is_active'] ? 'YES' : 'NO');
        echo ", Priority={$provider['priority']}";
        echo ", API Key=" . ($provider['api_key'] ? 'SET' : 'MISSING');
        echo "\n";
    }
    
    echo "\n🧪 TESTING SMS SYSTEM:\n";
    
    // Test me numër të thjeshtë
    $phoneVerifier = new PhoneVerificationAdvanced($pdo);
    
    // Test me demo mode (pa dërgim real SMS)
    echo "Testing generateVerificationCode for +38344123456...\n";
    
    $result = $phoneVerifier->generateVerificationCode('+38344123456', 'TEST_TXN_123');
    
    if ($result['success']) {
        echo "✅ SMS Generation: SUCCESS\n";
        echo "   Message: {$result['message']}\n";
        if (isset($result['provider_used'])) {
            echo "   Provider: {$result['provider_used']}\n";
        }
    } else {
        echo "❌ SMS Generation: FAILED\n";
        echo "   Error: {$result['error']}\n";
    }
    
    echo "\n💡 RECOMMENDATIONS:\n";
    
    if (empty($providers) || !array_filter($providers, fn($p) => $p['is_active'])) {
        echo "- Configure at least one active SMS provider\n";
    }
    
    $has_credentials = false;
    foreach($providers as $provider) {
        if ($provider['is_active'] && $provider['api_key']) {
            $has_credentials = true;
            break;
        }
    }
    
    if (!$has_credentials) {
        echo "- Add API credentials for at least one provider\n";
        echo "- For testing, you can enable demo mode\n";
    }
    
} catch(Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>