<?php
// check_structure.php - Kontrollon strukturën e tabelës 'noteri'

require_once 'config.php';

try {
    // Kontrollo strukturën e tabelës noteri
    $stmt = $pdo->query("DESCRIBE noteri");
    echo "<h2>Struktura e tabelës 'noteri':</h2>";
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    // Merr një rresht për shembull
    $stmt = $pdo->query("SELECT * FROM noteri LIMIT 1");
    echo "<h2>Shembull i një rreshti nga tabela 'noteri':</h2>";
    echo "<pre>";
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Gabim: " . $e->getMessage();
}