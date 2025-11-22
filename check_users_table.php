<?php
require_once 'config.php';

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'users' exists.\n";
        
        // Check if the status column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'");
        if ($stmt->rowCount() > 0) {
            echo "Column 'status' exists in users table.\n";
            
            // Get status values
            $stmt = $pdo->query("SELECT DISTINCT status FROM users");
            echo "Status values found: ";
            $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
            print_r($statuses);
            echo "\n";
            
            // Count active users
            $stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE status = 'aktiv'");
            $active = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
            echo "Active users count: $active\n";
        } else {
            echo "Column 'status' does not exist in users table.\n";
        }
    } else {
        echo "Table 'users' does not exist.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>