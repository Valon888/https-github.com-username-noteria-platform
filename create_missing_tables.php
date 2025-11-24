<?php
require 'confidb.php';

// Lista e tabelave që duhen krijuara
$tables = [
    'lajme' => "
        CREATE TABLE IF NOT EXISTS lajme (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            titull VARCHAR(255) NOT NULL,
            permbajtje TEXT NOT NULL,
            autori INT(11),
            data_publikimit TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_perditesimit TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status VARCHAR(50) DEFAULT 'aktive',
            INDEX (autori),
            INDEX (data_publikimit)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'noteret' => "
        CREATE TABLE IF NOT EXISTS noteret (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            emri VARCHAR(255) NOT NULL,
            mbiemri VARCHAR(255) NOT NULL,
            specialiteti VARCHAR(100),
            zona VARCHAR(100),
            email VARCHAR(255) UNIQUE,
            telefoni VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (emri),
            INDEX (zona)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'abonimet' => "
        CREATE TABLE IF NOT EXISTS abonimet (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            tipi VARCHAR(100) NOT NULL,
            cmimi DECIMAL(10, 2),
            statusi VARCHAR(50) DEFAULT 'aktive',
            data_fillimit TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_skadimit DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (statusi)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

try {
    foreach ($tables as $table_name => $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela '$table_name' u krijua me sukses!<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "✅ Tabela '$table_name' ekziston tashmë!<br>";
            } else {
                throw $e;
            }
        }
    }
    
    echo "<br><strong>✅ Të gjitha tabelat janë gati!</strong><br>";
    echo "Tani mund të kyçeni dhe të shihni dashboard-in!";
    
} catch (PDOException $e) {
    echo "❌ Gabim: " . $e->getMessage();
}
?>
