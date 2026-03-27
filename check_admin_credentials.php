<?php
require_once 'confidb.php';

try {
    // Get all admins and their password hashes
    $stmt = $pdo->query("SELECT id, email, password, roli, status FROM admins");
    echo "Current admin accounts in database:\n";
    echo str_repeat("=", 80) . "\n";
    
    while ($admin = $stmt->fetch()) {
        echo "\nEmail: {$admin['email']}\n";
        echo "Role: {$admin['roli']}\n";
        echo "Status: {$admin['status']}\n";
        echo "Password Hash: {$admin['password']}\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "\nDefault passwords set during migration:\n";
    echo "- admin@noteria.com: Noteria@Admin#2025\n";
    echo "- developer@noteria.com: Dev@Noteria#2025\n";
    echo "- support@noteria.com: Support@Noteria#2025\n";
    
    // Verify password hash
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "\nVerifying password hashes:\n";
    
    $test_password = "Noteria@Admin#2025";
    $hash = '$2y$10$ZC1sYGG2zAV1VFCu4iGKQuGXo/RhARRXVHvA8jG7XvHJh3W4Ox0Jm';
    
    $result = password_verify($test_password, $hash);
    echo "Testing password '$test_password' against admin hash: " . ($result ? "✓ VALID" : "✗ INVALID") . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
