<?php
require 'confidb.php';

echo "=== Test Database Connection ===\n";
echo "Status: OK - Database connected\n\n";

echo "=== Users in Database ===\n";
try {
    $stmt = $pdo->query("SELECT id, emri, mbiemri, email, roli FROM users LIMIT 10");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "No users found. Creating test users...\n";
        // Create test users
        $test_users = [
            ['emri' => 'Admin', 'mbiemri' => 'Test', 'email' => 'admin@noteria.al', 'roli' => 'admin', 'password' => password_hash('Admin@2025', PASSWORD_BCRYPT)],
            ['emri' => 'Notary', 'mbiemri' => 'Test', 'email' => 'notary@noteria.al', 'roli' => 'notary', 'password' => password_hash('Notary@2025', PASSWORD_BCRYPT)],
            ['emri' => 'User', 'mbiemri' => 'Test', 'email' => 'user@noteria.al', 'roli' => 'user', 'password' => password_hash('User@2025', PASSWORD_BCRYPT)],
        ];
        
        $stmt = $pdo->prepare("INSERT INTO users (emri, mbiemri, email, roli, password, status) VALUES (?, ?, ?, ?, ?, 'active')");
        
        foreach ($test_users as $user) {
            $stmt->execute([
                $user['emri'],
                $user['mbiemri'],
                $user['email'],
                $user['roli'],
                $user['password']
            ]);
            echo "Created: {$user['email']} ({$user['roli']})\n";
        }
        echo "\n";
        
        // Re-fetch users
        $stmt = $pdo->query("SELECT id, emri, mbiemri, email, roli FROM users");
        $users = $stmt->fetchAll();
    }
    
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Name: {$user['emri']} {$user['mbiemri']}, Email: {$user['email']}, Role: {$user['roli']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Users Credentials ===\n";
echo "Admin: admin@noteria.al / Admin@2025\n";
echo "Notary: notary@noteria.al / Notary@2025\n";
echo "User: user@noteria.al / User@2025\n";
