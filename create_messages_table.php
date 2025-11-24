<?php
require 'confidb.php';

try {
    // Krijo tabelën messages
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            sender_id INT(11) NOT NULL,
            receiver_id INT(11) NOT NULL,
            subject VARCHAR(255),
            message_text TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (sender_id),
            INDEX (receiver_id),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Tabela 'messages' u krijua me sukses!<br>";
    
    // Krijo edhe tabelën notifications nëse nuk ekziston
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            message VARCHAR(255) NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Tabela 'notifications' u krijua me sukses!<br>";
    
    echo "<br><strong>✅ Të gjitha tabelat janë gati!</strong><br>";
    echo "Tani mund të kyçeni në dashboard!";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "✅ Tabelat ekzistojnë tashmë!<br>";
    } else {
        echo "❌ Gabim: " . $e->getMessage();
    }
}
?>
