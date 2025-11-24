<?php
require 'confidb.php';

try {
    // Krijo tabelën audit_log
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11),
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(50),
            user_agent VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (created_at),
            INDEX (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Tabela 'audit_log' u krijua me sukses!<br>";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "✅ Tabela 'audit_log' ekziston tashmë!<br>";
    } else {
        echo "❌ Gabim: " . $e->getMessage();
    }
}

echo "Tani mund të kyçeni në login.php!";
?>
