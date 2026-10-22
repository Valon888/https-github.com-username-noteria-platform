<?php
require 'config.php';
require 'confidb.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_size BIGINT DEFAULT 0,
        file_type VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Tabela 'documents' u krijua ose ekziston tashmë.";

} catch (PDOException $e) {
    echo "Gabim gjatë krijimit të tabelës: " . $e->getMessage();
}
?>