<?php
require_once 'config.php';
require_once 'PhoneVerificationAdvanced.php';

echo "=== COMPLETE SMS TEST ===\n";

try {
    $phoneVerifier = new PhoneVerificationAdvanced($pdo);
    $phone = '+38344999888';
    $transaction_id = 'TEST_FINAL_' . time();
    
    echo "Step 1: Generating SMS code...\n";
    $generate_result = $phoneVerifier->generateVerificationCode($phone, $transaction_id);
    
    if ($generate_result['success']) {
        echo "✅ SMS Generated successfully!\n";
        echo "   Provider: {$generate_result['provider_used']}\n";
        echo "   Expires in: {$generate_result['expires_in_minutes']} minutes\n";
        
        // Get the generated code from database
        $stmt = $pdo->prepare("
            SELECT verification_code 
            FROM phone_verification_codes 
            WHERE phone_number = ? AND transaction_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$phone, $transaction_id]);
        $code = $stmt->fetchColumn();
        
        echo "\nStep 2: Testing verification with code: $code\n";
        $verify_result = $phoneVerifier->verifyCode($phone, $code, $transaction_id);
        
        if ($verify_result['success']) {
            echo "🎉 COMPLETE SUCCESS!\n";
            echo "   Message: {$verify_result['message']}\n";
            if (isset($verify_result['verified_at'])) {
                echo "   Verified at: {$verify_result['verified_at']}\n";
            }
        } else {
            echo "❌ Verification failed: {$verify_result['error']}\n";
        }
        
    } else {
        echo "❌ SMS Generation failed: {$generate_result['error']}\n";
    }
    
} catch(Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>