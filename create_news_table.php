<?php
require_once 'confidb.php';

$sql = "CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title_sq VARCHAR(255) NOT NULL,
    title_sr VARCHAR(255) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    content_sq TEXT,
    content_sr TEXT,
    content_en TEXT,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    published TINYINT(1) DEFAULT 1
)";

try {
    $pdo->exec($sql);
    echo "Tabela 'news' u krijua me sukses.";
} catch (Exception $e) {
    echo "Gabim gjatë krijimit të tabelës: " . $e->getMessage();
}
?>