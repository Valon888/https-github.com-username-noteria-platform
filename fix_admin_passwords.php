<?php
require_once 'confidb.php';

try {
    // Define the passwords and their hashes
    $admins = [
        [
            'email' => 'admin@noteria.com',
            'password' => 'Noteria@Admin#2025',
            'name' => 'Admin',
            'role' => 'super_admin'
        ],
        [
            'email' => 'developer@noteria.com',
            'password' => 'Dev@Noteria#2025',
            'name' => 'Developer',
            'role' => 'developer'
        ],
        [
            'email' => 'support@noteria.com',
            'password' => 'Support@Noteria#2025',
            'name' => 'Support',
            'role' => 'admin'
        ]
    ];
    
    echo "Updating admin passwords...\n";
    echo str_repeat("=", 80) . "\n\n";
    
    foreach ($admins as $admin) {
        // Hash the password
        $hash = password_hash($admin['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        
        // Update in database
        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
        $stmt->execute([$hash, $admin['email']]);
        
        echo "✓ Updated {$admin['email']}\n";
        echo "  Password: {$admin['password']}\n";
        echo "  Hash: $hash\n";
        
        // Verify the hash
        if (password_verify($admin['password'], $hash)) {
            echo "  Verification: ✓ VALID\n\n";
        } else {
            echo "  Verification: ✗ FAILED\n\n";
        }
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "✨ All admin passwords have been updated successfully!\n";
    echo "\nYou can now login with:\n";
    foreach ($admins as $admin) {
        echo "  Email: {$admin['email']}\n  Password: {$admin['password']}\n\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
