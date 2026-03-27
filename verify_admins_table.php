<?php
require_once 'confidb.php';

try {
    // Check if admins table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admins'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✓ admins table exists\n";
        
        // Count records
        $stmt2 = $pdo->query("SELECT COUNT(*) as count FROM admins");
        $count = $stmt2->fetch();
        echo "Records in admins table: " . $count['count'] . "\n";
        
        // List admin emails
        $stmt3 = $pdo->query("SELECT id, email, roli, status FROM admins");
        echo "\nAdmin accounts:\n";
        while ($admin = $stmt3->fetch()) {
            echo "  - {$admin['email']} (Role: {$admin['roli']}, Status: {$admin['status']})\n";
        }
    } else {
        echo "✗ admins table not found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
