<?php
// Siguro që tabela zyrat ka të gjitha kolonat e nevojshme
require 'config.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Lista e kolonave të nevojshme
    $required_columns = ['id', 'emri', 'qyteti', 'shteti', 'telefoni', 'email', 'adresa'];
    
    // Merr të gjithë kolonat aktuale
    $stmt = $pdo->query("SHOW COLUMNS FROM zyrat");
    $existing_columns = [];
    while ($row = $stmt->fetch()) {
        $existing_columns[] = $row['Field'];
    }
    
    // Shto kolonat që mungojnë
    $added_columns = [];
    
    if (!in_array('emri', $existing_columns)) {
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN emri VARCHAR(255) NOT NULL DEFAULT 'Zyra pa emër'");
        $added_columns[] = 'emri';
    }
    
    if (!in_array('qyteti', $existing_columns)) {
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN qyteti VARCHAR(100)");
        $added_columns[] = 'qyteti';
    }
    
    if (!in_array('shteti', $existing_columns)) {
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN shteti VARCHAR(100) NOT NULL DEFAULT 'Kosovë'");
        $added_columns[] = 'shteti';
    }
    
    if (!in_array('telefoni', $existing_columns)) {
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN telefoni VARCHAR(20)");
        $added_columns[] = 'telefoni';
    }
    
    if (!in_array('email', $existing_columns)) {
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN email VARCHAR(255)");
        $added_columns[] = 'email';
    }
    
    if (!in_array('adresa', $existing_columns)) {
        $pdo->exec("ALTER TABLE zyrat ADD COLUMN adresa VARCHAR(255)");
        $added_columns[] = 'adresa';
    }
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 16px; color: #155724; margin: 20px;'>";
    echo "<strong>✅ Zyrat Table Update</strong><br>";
    if (!empty($added_columns)) {
        echo "Kolonat e shtuara: " . implode(", ", $added_columns);
    } else {
        echo "Të gjithë kolonat janë të pranishëm.";
    }
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 16px; color: #721c24; margin: 20px;'>";
    echo "<strong>❌ Gabim:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
