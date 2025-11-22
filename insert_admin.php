<?php
/**
 * Insert test administrator
 * This script helps insert a new administrator to the admins table
 */

require_once 'config.php';

try {
    // Test admin credentials
    $email = 'admin@noteria.al';
    $password = 'Admin@2025'; // Strong password
    $emri = 'Admin Noteria'; // Full name
    
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        echo "[INFO] Admin with email '$email' already exists!\n";
        exit(0);
    }
    
    // Hash password using bcrypt
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insert new admin
    $stmt = $pdo->prepare("
        INSERT INTO admins (email, password, emri, status, role) 
        VALUES (?, ?, ?, 'active', 'super_admin')
    ");
    
    $stmt->execute([$email, $hashed_password, $emri]);
    $admin_id = $pdo->lastInsertId();
    
    echo "[SUCCESS] Administrator created successfully!\n\n";
    echo "Admin Details:\n";
    echo "  ID: $admin_id\n";
    echo "  Email: $email\n";
    echo "  Name: $emri\n";
    echo "  Role: super_admin\n";
    echo "  Status: active\n";
    echo "  Password: " . substr($password, 0, 3) . "****** (Keep it safe!)\n";
    
} catch (PDOException $e) {
    echo "[ERROR] Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "[ERROR] Error: " . $e->getMessage() . "\n";
    exit(1);
}
