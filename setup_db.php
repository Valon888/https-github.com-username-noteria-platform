<?php
require 'confidb.php';

echo "=== Verifikimi i të gjitha tabelave ===\n\n";

$tables_needed = [
    'users' => "SELECT COUNT(*) FROM users",
    'audit_log' => "SELECT COUNT(*) FROM audit_log",
    'lajme' => "SELECT COUNT(*) FROM lajme",
    'messages' => "SELECT COUNT(*) FROM messages",
    'notifications' => "SELECT COUNT(*) FROM notifications",
    'noteret' => "SELECT COUNT(*) FROM noteret",
    'abonimet' => "SELECT COUNT(*) FROM abonimet",
];

$missing_tables = [];

foreach ($tables_needed as $table => $query) {
    try {
        $result = $pdo->query($query);
        $count = $result->fetchColumn();
        echo "✓ $table: $count rows\n";
    } catch (Exception $e) {
        echo "✗ $table: MISSING\n";
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    echo "\n=== Creating Missing Tables ===\n";
    
    // Create audit_log
    if (in_array('audit_log', $missing_tables)) {
        echo "Creating audit_log...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11),
            action VARCHAR(100),
            details TEXT,
            ip_address VARCHAR(50),
            user_agent VARCHAR(255),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX (user_id),
            INDEX (action),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    // Create lajme
    if (in_array('lajme', $missing_tables)) {
        echo "Creating lajme...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS lajme (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            titull VARCHAR(255) NOT NULL,
            permbajtje LONGTEXT NOT NULL,
            autori VARCHAR(255),
            data_publikimit DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            data_perditesimit DATETIME NULL,
            status ENUM('published', 'draft', 'archived') DEFAULT 'draft',
            INDEX (data_publikimit),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    // Create messages
    if (in_array('messages', $missing_tables)) {
        echo "Creating messages...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            sender_id INT(11) NOT NULL,
            receiver_id INT(11) NOT NULL,
            subject VARCHAR(255),
            message_text LONGTEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (sender_id),
            INDEX (receiver_id),
            INDEX (is_read),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    // Create notifications
    if (in_array('notifications', $missing_tables)) {
        echo "Creating notifications...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (is_read),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    // Create noteret
    if (in_array('noteret', $missing_tables)) {
        echo "Creating noteret...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS noteret (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            emri VARCHAR(100) NOT NULL,
            mbiemri VARCHAR(100) NOT NULL,
            specialiteti VARCHAR(100),
            zona VARCHAR(100),
            email VARCHAR(255) UNIQUE,
            telefoni VARCHAR(20),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (zona),
            INDEX (specialiteti)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    // Create abonimet
    if (in_array('abonimet', $missing_tables)) {
        echo "Creating abonimet...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS abonimet (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            tipi VARCHAR(100),
            cmimi DECIMAL(10,2),
            statusi ENUM('active', 'inactive', 'suspended', 'expired') DEFAULT 'active',
            data_fillimit DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            data_skadimit DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (statusi),
            INDEX (data_skadimit)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    echo "\n✓ All missing tables created!\n";
}

echo "\n=== Final Verification ===\n";
$stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'noteria' ORDER BY table_name");
$db_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Tables in database: " . count($db_tables) . "\n";
foreach ($db_tables as $table) {
    echo "  - $table\n";
}
