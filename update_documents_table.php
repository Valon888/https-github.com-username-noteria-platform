<?php
require 'config.php';

try {
    // Shto kolonën file_size
    $pdo->exec("ALTER TABLE documents ADD COLUMN file_size BIGINT DEFAULT 0");
    echo "Kolona 'file_size' u shtua me sukses.<br>";
} catch (PDOException $e) {
    // Injoro nëse kolona ekziston tashmë (Error 1060)
    if (strpos($e->getMessage(), '1060') !== false) {
        echo "Kolona 'file_size' ekziston tashmë.<br>";
    } else {
        echo "Gabim gjatë shtimit të 'file_size': " . $e->getMessage() . "<br>";
    }
}

try {
    // Shto kolonën file_type
    $pdo->exec("ALTER TABLE documents ADD COLUMN file_type VARCHAR(100) DEFAULT NULL");
    echo "Kolona 'file_type' u shtua me sukses.<br>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), '1060') !== false) {
        echo "Kolona 'file_type' ekziston tashmë.<br>";
    } else {
        echo "Gabim gjatë shtimit të 'file_type': " . $e->getMessage() . "<br>";
    }
}

echo "Përditësimi i tabelës përfundoi.";
?>