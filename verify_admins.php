<?php
/**
 * Verify admins table
 */

require_once 'config.php';

try {
    echo "Checking admins table...\n\n";
    
    // Get all admins
    $admins = $pdo->query('SELECT id, email, emri, role, status, created_at FROM admins')->fetchAll();
    
    echo "Total administrators: " . count($admins) . "\n";
    echo "=====================================\n\n";
    
    foreach ($admins as $admin) {
        echo "ID: {$admin['id']}\n";
        echo "Email: {$admin['email']}\n";
        echo "Name: {$admin['emri']}\n";
        echo "Role: {$admin['role']}\n";
        echo "Status: {$admin['status']}\n";
        echo "Created: {$admin['created_at']}\n";
        echo "-----\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
