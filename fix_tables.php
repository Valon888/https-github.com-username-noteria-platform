<?php
require 'confidb.php';

try {
    // Kontrollo strukturën e tabelës lajme
    $stmt = $pdo->query("SHOW COLUMNS FROM lajme");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Kolonat ekzistuese në tabelën 'lajme':<br>";
    foreach ($columns as $col) {
        echo "- " . $col . "<br>";
    }
    echo "<br>";
    
    // Shto kolonat që mungojnë
    if (!in_array('data_publikimit', $columns) && !in_array('created_at', $columns)) {
        $pdo->exec("ALTER TABLE lajme ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✅ Kolona 'created_at' u shtua!<br>";
    }
    
    // Kontrollo për 'titulli' vs 'titull'
    if (!in_array('titulli', $columns) && in_array('titull', $columns)) {
        // Kolona 'titull' ekziston, ndaj nuk ka nevojë për ndryshim
        echo "✅ Kolona 'titull' ekziston!<br>";
    }
    
    // Kontrollo për 'permbajtja' vs 'permbajtje'
    if (!in_array('permbajtja', $columns) && in_array('permbajtje', $columns)) {
        // Kolona 'permbajtje' ekziston, ndaj nuk ka nevojë për ndryshim
        echo "✅ Kolona 'permbajtje' ekziston!<br>";
    }
    
    echo "<br><strong>✅ Tabelat janë rregulluar!</strong>";
    
} catch (PDOException $e) {
    echo "❌ Gabim: " . $e->getMessage();
}
?>
