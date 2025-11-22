<?php
// Include database configuration
require_once 'config.php';

try {
    // Connection is already established in config.php as $pdo
    
    // Check if table already exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'login_attempts'");
    if ($checkTable->rowCount() > 0) {
        echo "Table 'login_attempts' already exists.";
        exit;
    }
    
    // SQL to create table
    $sql = "CREATE TABLE `login_attempts` ( 
        `id` INT(11) NOT NULL AUTO_INCREMENT, 
        `user_id` INT(11) DEFAULT NULL, 
        `ip_address` VARCHAR(45) NOT NULL, 
        `user_agent` TEXT, 
        `attempt_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
        `successful` TINYINT(1) NOT NULL DEFAULT 0, 
        `username_attempt` VARCHAR(255) DEFAULT NULL, 
        PRIMARY KEY (`id`), 
        KEY `user_id` (`user_id`) 
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Execute SQL
    $pdo->exec($sql);
    
    echo "Table 'login_attempts' created successfully!";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>