<?php
// Database setup script for Noteria platform

// Database connection parameters
$db_host = 'localhost';
$db_name = 'noteria';
$db_username = 'root';
$db_password = '';

try {
    // Connect to MySQL (without database)
    $pdo = new PDO("mysql:host=$db_host", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "Database '$db_name' created or already exists.<br>";
    
    // Select the database
    $pdo->exec("USE `$db_name`");
    
    // Load and execute SQL files
    $sqlFiles = [
        'sql/subscription_tables.sql',
        'sql/invoice_tables.sql'
    ];
    
    foreach ($sqlFiles as $sqlFile) {
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            
            // Split the SQL file into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)), function($val) {
                return !empty($val);
            });
            
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }
            
            echo "SQL file '$sqlFile' executed successfully.<br>";
        } else {
            echo "SQL file '$sqlFile' not found!<br>";
        }
    }
    
    // Create zyrat table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `zyrat` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `emri` varchar(255) NOT NULL,
      `adresa` varchar(255) DEFAULT NULL,
      `telefon` varchar(50) DEFAULT NULL,
      `email` varchar(255) DEFAULT NULL,
      `nipt` varchar(50) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Table 'zyrat' created or already exists.<br>";
    
    // Create users table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `emri` varchar(100) NOT NULL,
      `mbiemri` varchar(100) NOT NULL,
      `email` varchar(255) NOT NULL,
      `password` varchar(255) NOT NULL,
      `roli` enum('admin','zyra','klient') NOT NULL DEFAULT 'klient',
      `zyra_id` int(11) DEFAULT NULL,
      `status` enum('active','inactive') NOT NULL DEFAULT 'active',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`),
      KEY `zyra_id` (`zyra_id`),
      CONSTRAINT `users_ibfk_1` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Table 'users' created or already exists.<br>";
    
    // Create reservations table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `reservations` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `zyra_id` int(11) NOT NULL,
      `service` varchar(255) NOT NULL,
      `date` date NOT NULL,
      `time` time NOT NULL,
      `status` enum('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `zyra_id` (`zyra_id`),
      CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`zyra_id`) REFERENCES `zyrat` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Table 'reservations' created or already exists.<br>";
    
    // Insert sample data for testing if the tables are empty
    
    // Check if zyrat table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM zyrat");
    $zyratCount = $stmt->fetchColumn();
    
    if ($zyratCount == 0) {
        $pdo->exec("INSERT INTO `zyrat` (`emri`, `adresa`, `telefon`, `email`, `nipt`) VALUES
            ('Zyra Noteriale Tiranë', 'Rruga e Barrikadave, Nr. 118, Tiranë', '+355 69 123 4567', 'info@noteri-tirane.al', 'AL123456789'),
            ('Zyra Noteriale Durrës', 'Bulevardi Kryesor, Nr. 45, Durrës', '+355 69 876 5432', 'info@noteri-durres.al', 'AL987654321')");
        echo "Sample data inserted into 'zyrat' table.<br>";
    }
    
    // Check if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $usersCount = $stmt->fetchColumn();
    
    if ($usersCount == 0) {
        // Create admin and zyra users (password: 'password123' hashed)
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        
        $pdo->exec("INSERT INTO `users` (`emri`, `mbiemri`, `email`, `password`, `roli`, `zyra_id`) VALUES
            ('Admin', 'User', 'admin@noteria.al', '$hashedPassword', 'admin', NULL),
            ('Noter', 'Tirane', 'noter@noteri-tirane.al', '$hashedPassword', 'zyra', 1),
            ('Noter', 'Durres', 'noter@noteri-durres.al', '$hashedPassword', 'zyra', 2),
            ('Klient', 'Demo', 'klient@example.com', '$hashedPassword', 'klient', NULL)");
        echo "Sample users inserted into 'users' table.<br>";
    }
    
    // Insert subscription for testing if the subscription table is empty
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM subscription");
        $subscriptionCount = $stmt->fetchColumn();
        
        if ($subscriptionCount == 0) {
            $startDate = date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime('+30 days'));
            
            $pdo->exec("INSERT INTO `subscription` (`zyra_id`, `start_date`, `expiry_date`, `status`, `payment_status`, `payment_date`) VALUES
                (1, '$startDate', '$expiryDate', 'active', 'paid', NOW()),
                (2, '$startDate', '" . date('Y-m-d', strtotime('+5 days')) . "', 'active', 'paid', NOW())");
            echo "Sample subscriptions inserted into 'subscription' table.<br>";
        }
    } catch (PDOException $e) {
        echo "Table 'subscription' may not exist yet. Skipping sample data.<br>";
    }
    
    echo "<br>Database setup completed successfully!";
    
} catch (PDOException $e) {
    die("Database setup error: " . $e->getMessage());
}