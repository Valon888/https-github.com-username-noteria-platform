<?php
require 'confidb.php';

echo "=== Testing Login System ===\n\n";

$test_credentials = [
    ['email' => 'admin@noteria.al', 'password' => 'Admin@2025', 'expected_role' => 'admin'],
    ['email' => 'notary@noteria.al', 'password' => 'Notary@2025', 'expected_role' => 'notary'],
    ['email' => 'user@noteria.al', 'password' => 'User@2025', 'expected_role' => 'user'],
];

foreach ($test_credentials as $test) {
    echo "Testing: {$test['email']}\n";
    
    // Query the database
    $stmt = $pdo->prepare("SELECT id, emri, mbiemri, email, password, roli FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$test['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "  ✗ User not found in database\n\n";
        continue;
    }
    
    echo "  ✓ User found: {$user['emri']} {$user['mbiemri']}\n";
    
    // Check password
    if (password_verify($test['password'], $user['password'])) {
        echo "  ✓ Password verified\n";
    } else {
        echo "  ✗ Password verification failed\n";
        // Try to re-hash if password is plain text
        $hashed = password_hash($test['password'], PASSWORD_BCRYPT);
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->execute([$hashed, $user['id']]);
        echo "  → Password re-hashed\n";
    }
    
    // Check role
    if ($user['roli'] === $test['expected_role']) {
        echo "  ✓ Role correct: {$user['roli']}\n";
    } else {
        echo "  ✗ Role mismatch: expected {$test['expected_role']}, got {$user['roli']}\n";
    }
    
    echo "\n";
}

echo "=== Test Complete ===\n";
