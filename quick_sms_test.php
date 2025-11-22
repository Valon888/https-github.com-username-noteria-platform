<?php
require_once 'config.php';
require_once 'PhoneVerificationAdvanced.php';

echo "=== QUICK SMS TEST ===\n";

try {
    $phoneVerifier = new PhoneVerificationAdvanced($pdo);
    
    // Test me numër telefoni dhe transaction ID
    echo "Testing SMS with phone +38344123456...\n";
    
    $result = $phoneVerifier->generateVerificationCode('+38344123456', 'TEST_' . time());
    
    if ($result['success']) {
        echo "✅ SUCCESS!\n";
        echo "   Message: {$result['message']}\n";
        echo "   Provider: {$result['provider_used']}\n";
        echo "   Expires in: {$result['expires_in_minutes']} minutes\n";
        
        if (isset($result['demo_mode'])) {
            echo "   🎭 DEMO MODE: SMS would be sent in production\n";
            echo "   📱 For testing, use any 6-digit code (123456 will work)\n";
        }
    } else {
        echo "❌ FAILED: {$result['error']}\n";
    }
    
    echo "\n📋 QUICK VERIFICATION TEST:\n";
    echo "Testing code verification with '123456'...\n";
    
    $verify_result = $phoneVerifier->verifyCode('+38344123456', '123456', 'TEST_' . time());
    
    if ($verify_result['success']) {
        echo "✅ Code verification: SUCCESS\n";
    } else {
        echo "❌ Code verification: {$verify_result['error']}\n";
    }
    
} catch(Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>