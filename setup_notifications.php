<?php
require_once 'config.php';
require_once 'confidb.php';

echo "=== Krijimi i tabelës së njoftimeve ===\n\n";

try {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'info', -- info, success, warning, error
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (is_read)
    )";

    $pdo->exec($sql);
    echo "Tabela 'notifications' u krijua ose ekziston tashmë.\n";

} catch (PDOException $e) {
    echo "Gabim: " . $e->getMessage() . "\n";
}
?>
