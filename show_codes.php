<?php
require_once 'config.php';

echo "=== RECENT VERIFICATION CODES ===\n";

try {
    $stmt = $pdo->query("
        SELECT phone_number, verification_code, transaction_id, expires_at, is_used, created_at 
        FROM phone_verification_codes 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($codes)) {
        echo "No verification codes found.\n";
    } else {
        foreach($codes as $code) {
            echo "📱 {$code['phone_number']}\n";
            echo "   Code: {$code['verification_code']}\n";
            echo "   Transaction: {$code['transaction_id']}\n";
            echo "   Used: " . ($code['is_used'] ? 'YES' : 'NO') . "\n";
            echo "   Expires: {$code['expires_at']}\n";
            echo "   Created: {$code['created_at']}\n";
            echo "---\n";
        }
    }
    
    echo "\n💡 FOR TESTING:\n";
    if (!empty($codes)) {
        $latest = $codes[0];
        echo "Use code: {$latest['verification_code']}\n";
        echo "For phone: {$latest['phone_number']}\n";
        echo "Transaction: {$latest['transaction_id']}\n";
    }
    
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>